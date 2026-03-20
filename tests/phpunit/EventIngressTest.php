<?php

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageAssessments\EventIngress
 * @group Database
 * @group PageAssessments
 */
class EventIngressTest extends MediaWikiIntegrationTestCase {

	public function testDataIsDeleted(): void {
		$this->overrideConfigValue( 'PageAssessmentsOnTalkPages', true );
		/** @var PageAssessmentsStore $store */
		$store = $this->getServiceContainer()->get( 'PageAssessments.Store' );
		/** @var Title $subjectTitle */
		$subjectTitle = $this->insertPage( 'PageAssessmentsTestPage', 'Mainspace content' )['title'];
		$this->insertPage(
			'Talk:PageAssessmentsTestPage',
			'{{#assessment:Medicine|B|Low}}'
		);
		$articleId = $subjectTitle->getArticleID();
		// Sanity check.
		$records = $store->getAllAssessments( $articleId );
		$this->assertCount( 1, $records );
		// Now delete the *subject* page, and check the record is gone.
		$this->deletePage( $subjectTitle );
		$this->runDeferredUpdates();
		$records = $store->getAllAssessments( $articleId );
		$this->assertCount( 0, $records );
	}
}
