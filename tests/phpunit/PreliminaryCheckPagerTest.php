<?php

use MediaWiki\CheckUser\PreliminaryCheckPager;
use MediaWiki\CheckUser\PreliminaryCheckService;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILBFactory;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\PreliminaryCheckPager
 */
class PreliminaryCheckPagerTest extends MediaWikiTestCase {

	/**
	 * @return MockObject|ExtensionRegistry
	 */
	private function getMockExtensionRegistry() {
		return $this->getMockBuilder( ExtensionRegistry::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|TokenManager
	 */
	private function getMockTokenManager() {
		return $this->getMockBuilder( TokenManager::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|PreliminaryCheckService
	 */
	private function getMockPreliminaryCheckService() {
		return $this->getMockBuilder( PreliminaryCheckService::class )
			->disableOriginalConstructor()->getMock();
	}

	public function testGetQueryInfoFiltersIPsFromTargets() {
		$registry = $this->getMockExtensionRegistry();
		$registry->method( 'isLoaded' )->willReturn( true );

		$tokenManager = $this->getMockTokenManager();
		$tokenManager->method( 'getDataFromRequest' )->willReturn( [
			'targets' => [ 'UserA', 'UserB', '1.2.3.4' ]
		] );

		$lbf = $this->getMockBuilder( ILBFactory::class )
			->disableOriginalConstructor()->getMock();

		$preliminaryCheckService = new PreliminaryCheckService( $lbf, $registry, 'testwiki' );

		$services = MediaWikiServices::getInstance();
		$pager = new PreliminaryCheckPager( RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$tokenManager,
			$registry,
			$preliminaryCheckService
		);

		$result = $pager->getQueryInfo();

		$expected = [
			'tables' => 'localuser',
			'fields' => [
				'lu_name',
				'lu_wiki',
			],
			'conds' => [ 'lu_name' => [ 'UserA', 'UserB' ] ]
		];
		$this->assertSame( $expected, $result );
	}

	public function testGetIndexFieldLocal() {
		$services = MediaWikiServices::getInstance();
		$pager = new PreliminaryCheckPager(
			RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$services->get( 'CheckUserTokenManager' ),
			$this->getMockExtensionRegistry(),
			$this->getMockPreliminaryCheckService()
		);
		$this->assertEquals( 'user_name', $pager->getIndexfield() );
	}

	public function testGetIndexFieldGlobal() {
		$services = MediaWikiServices::getInstance();
		$registry = $this->getMockExtensionRegistry();
		$preliminaryCheckService = $this->getMockPreliminaryCheckService();
		$pager = $this->getMockBuilder( PreliminaryCheckPager::class )
			->setConstructorArgs( [ RequestContext::getMain(),
				$services->getLinkRenderer(),
				$services->getNamespaceInfo(),
				$services->get( 'CheckUserTokenManager' ),
				$registry,
				$preliminaryCheckService
			 ] )
			->setMethods( [ 'isGlobalCheck' ] )
			->getMock();

		$pager->method( 'isGlobalCheck' )->willReturn( true );
		$this->assertEquals( [ [ 'lu_name', 'lu_wiki' ] ], $pager->getIndexfield() );
	}
}