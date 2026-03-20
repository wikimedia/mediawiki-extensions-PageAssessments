<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageAssessments\HookHandler\ParserHooks
 * @group Database
 * @group PageAssessments
 */
class ParserHooksTest extends MediaWikiIntegrationTestCase {

	protected PageAssessmentsStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->store = $this->getServiceContainer()->get( 'PageAssessments.Store' );
	}

	public function testDataIsSaved(): void {
		$this->overrideConfigValue( 'PageAssessmentsOnTalkPages', false );
		$ret = $this->insertPage(
			'PageAssessmentsTestPage',
			'{{#assessment:Medicine|B|Low}}' .
				'{{#assessment:Biology|C|Mid}}' .
				'{{#assessment:Philosophy|Start|High}}'
		);
		$records = $this->store->getAllAssessments( $ret['title']->getArticleId() );
		$this->assertCount( 3, $records );
		$this->assertSame( 'Medicine', $records[0]['name'] );
		$this->assertSame( 'B', $records[0]['class'] );
		$this->assertSame( 'Low', $records[0]['importance'] );
		$this->assertSame( 'Biology', $records[1]['name'] );
		$this->assertSame( 'C', $records[1]['class'] );
		$this->assertSame( 'Mid', $records[1]['importance'] );
		$this->assertSame( 'Philosophy', $records[2]['name'] );
		$this->assertSame( 'Start', $records[2]['class'] );
		$this->assertSame( 'High', $records[2]['importance'] );
	}

	public function testDataIsSavedFromTalk(): void {
		$this->overrideConfigValue( 'PageAssessmentsOnTalkPages', true );
		$subjectTitle = Title::newFromText( 'PageAssessmentsTestPage' );
		$talkTitle = Title::newFromText( 'Talk:PageAssessmentsTestPage' );
		$this->insertPage( $talkTitle, '{{#assessment:Medicine|B|Low}}' );
		$records = $this->store->getAllAssessments( $subjectTitle->getArticleID() );
		// Should be empty since the subject page doesn't exist.
		$this->assertCount( 0, $records );
		// Now create the subject page.
		$this->insertPage( $subjectTitle, 'Test' );
		// Perform null edit to re-trigger the assessment parsing and saving.
		$this->editPage( $talkTitle, '{{#assessment:Medicine|B|Low}}' );
		$records = $this->store->getAllAssessments( $subjectTitle->getArticleID() );
		$this->assertCount( 1, $records );
		$this->assertSame( 'Medicine', $records[0]['name'] );
		$this->assertSame( 'B', $records[0]['class'] );
		$this->assertSame( 'Low', $records[0]['importance'] );
	}
}
