<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageAssessments\HookHandler\OutputPageHooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageAssessments\HookHandler\OutputPageHooks
 * @group PageAssessments
 * @group Database
 */
class OutputPageHooksTest extends MediaWikiIntegrationTestCase {

	public function testOutputPageParserOutput(): void {
		$this->overrideConfigValue( 'PageAssessmentsOnTalkPages', true );
		/** @var Title $subjectTitle */
		$subjectTitle = $this->insertPage( 'PageAssessmentsTestPage', 'mainspace content' )['title'];
		/** @var Title $talkTitle */
		$talkTitle = $this->insertPage(
			'Talk:PageAssessmentsTestPage',
			'{{#assessment:Medicine|B|Low}} {{#assessment:Biology|C|Mid}}'
		)['title'];

		foreach ( [ $subjectTitle, $talkTitle ] as $title ) {
			$wikiPage = $this->getExistingTestPage( $title );

			$context = new RequestContext();
			$context->setTitle( $title );
			$outputPage = new OutputPage( $context );
			$outputPage->addParserOutputMetadata( $wikiPage->getParserOutput() );

			$actual = $outputPage->getJsConfigVars()[OutputPageHooks::JS_CONFIG_VAR] ?? [];
			$this->assertArrayEquals( [
				'Biology' => [ 'class' => 'C', 'importance' => 'Mid' ],
				'Medicine' => [ 'class' => 'B', 'importance' => 'Low' ],
			], $actual, true, true );
		}
	}
}
