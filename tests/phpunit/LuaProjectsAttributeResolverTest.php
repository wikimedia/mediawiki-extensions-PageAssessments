<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Extension\PageAssessments\LuaProjectsAttributeResolver;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestHelper;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

if ( !ExtensionRegistry::getInstance()->isLoaded( 'Scribunto' ) ) {
	// Skip all tests in this class if Scribunto is not loaded, since they depend on it.
	return;
}

/**
 * @covers \MediaWiki\Extension\PageAssessments\LuaProjectsAttributeResolver
 * @group Database
 * @group PageAssessments
 */
class LuaProjectsAttributeResolverTest extends MediaWikiIntegrationTestCase {
	use LuaEngineTestHelper;

	private ?LuaEngine $engine = null;

	protected function getEngineName(): string {
		return 'LuaStandalone';
	}

	public function testResolve(): void {
		$this->overrideConfigValue( 'PageAssessmentsOnTalkPages', false );
		$title = $this->insertPage(
			'PageAssessmentsLuaTestPage',
			'{{#assessment:Medicine|B|Low}} {{#assessment:Biology|C|Mid}}'
		)['title'];

		$resolver = new LuaProjectsAttributeResolver(
			$this->getServiceContainer()->get( 'PageAssessments.Store' ),
			$this->getServiceContainer()->getMainConfig(),
		);
		$resolver->setEngine( $this->getEngine() );

		$this->assertSame( [
			'Biology' => [
				'class' => 'C',
				'importance' => 'Mid'
			],
			'Medicine' => [
				'class' => 'B',
				'importance' => 'Low'
			],
		], $resolver->resolve( $title ) );
	}
}
