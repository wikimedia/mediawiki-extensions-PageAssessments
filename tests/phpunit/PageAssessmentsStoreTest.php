<?php

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWikiIntegrationTestCase;

/**
 * Test the database access and core functionality of PageAssessmentsDAO.
 *
 * @covers \MediaWiki\Extension\PageAssessments\PageAssessmentsStore
 * @group Database
 * @group PageAssessments
 */
class PageAssessmentsStoreTest extends MediaWikiIntegrationTestCase {

	protected PageAssessmentsStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->store = $this->getServiceContainer()->get( 'PageAssessments.Store' );
	}

	public function testInsert() {
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'A',
			'pa_importance' => 'High',
			'pa_page_revision' => '20'
		];
		$this->store->insertRecord( $values );
		$this->newSelectQueryBuilder()
			->select( [ 'pa_page_id', 'pa_class', 'pa_importance' ] )
			->from( 'page_assessments' )
			->assertRowValue( [ '10', 'A', 'High' ] );
	}

	public function testUpdate() {
		$this->testInsert();
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		];
		$changed = $this->store->updateRecord( $values );
		$this->assertTrue( $changed );
		$this->newSelectQueryBuilder()
			->select( [ 'pa_page_id', 'pa_class', 'pa_importance' ] )
			->from( 'page_assessments' )
			->assertRowValue( [ '10', 'B', 'Low' ] );
		// Perform the same update twice and check that it's a noop
		$changed = $this->store->updateRecord( $values );
		$this->assertFalse( $changed );
	}

	public function testDelete() {
		$this->testInsert();
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3'
		];
		$this->store->deleteRecord( $values );
		$row = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'page_assessments' )
			->caller( __METHOD__ )
			->fetchRow();
		$this->assertFalse( $row );
	}

	public function testGetAllProjects() {
		// Insert a record
		$this->testInsert();
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '4',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		];
		// Insert another record
		$this->store->insertRecord( $values );
		$res = $this->store->getAllProjects( '10' );
		$expected = [ 3, 4 ];
		// Since the projects may be returned in any order, we can't do a simple
		// assertEquals() on the arrays. Instead we compare the arrays using array_diff()
		// in both directions and make sure that the results are empty.
		$this->assertSame( [], array_merge( array_diff( $expected, $res ), array_diff( $res, $expected ) ) );
	}

	public function testCleanProjectTitle() {
		$projectTitle = "Drinks/the '''Coffee task force'''";
		$cleanedProjectTitle = $this->store->cleanProjectTitle( $projectTitle );
		$this->assertEquals( "Drinks/Coffee task force", $cleanedProjectTitle );
	}
}
