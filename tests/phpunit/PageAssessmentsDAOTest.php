<?php

use MediaWiki\Extension\PageAssessments\PageAssessmentsDAO;

/**
 * Test the database access and core functionality of PageAssessmentsDAO.
 *
 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO
 * @group Database
 * @group PageAssessments
 */
class PageAssessmentsDAOTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO::insertRecord()
	 */
	public function testInsert() {
		$pageBody = new PageAssessmentsDAO;
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'A',
			'pa_importance' => 'High',
			'pa_page_revision' => '20'
		];
		$pageBody->insertRecord( $values );
		$this->newSelectQueryBuilder()
			->select( [ 'pa_page_id', 'pa_class', 'pa_importance' ] )
			->from( 'page_assessments' )
			->assertRowValue( [ '10', 'A', 'High' ] );
	}

	/**
	 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO::updateRecord()
	 */
	public function testUpdate() {
		$this->testInsert();
		$pageBody = new PageAssessmentsDAO;
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		];
		$changed = $pageBody->updateRecord( $values );
		$this->assertTrue( $changed );
		$this->newSelectQueryBuilder()
			->select( [ 'pa_page_id', 'pa_class', 'pa_importance' ] )
			->from( 'page_assessments' )
			->assertRowValue( [ '10', 'B', 'Low' ] );
		// Perform the same update twice and check that it's a noop
		$changed = $pageBody->updateRecord( $values );
		$this->assertFalse( $changed );
	}

	/**
	 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO::deleteRecord()
	 */
	public function testDelete() {
		$this->testInsert();
		$pageBody = new PageAssessmentsDAO;
		$values = [
			'pa_page_id' => '10',
			'pa_project_id' => '3'
		];
		$pageBody->deleteRecord( $values );
		$row = $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( 'page_assessments' )
			->caller( __METHOD__ )
			->fetchRow();
		$this->assertFalse( $row );
	}

	/**
	 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO::getAllProjects()
	 */
	public function testGetAllProjects() {
		$pageBody = new PageAssessmentsDAO;
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
		$pageBody->insertRecord( $values );
		$res = $pageBody->getAllProjects( '10' );
		$expected = [ 3, 4 ];
		// Since the projects may be returned in any order, we can't do a simple
		// assertEquals() on the arrays. Instead we compare the arrays using array_diff()
		// in both directions and make sure that the results are empty.
		$this->assertSame( [], array_merge( array_diff( $expected, $res ), array_diff( $res, $expected ) ) );
	}

	/**
	 * @covers MediaWiki\Extension\PageAssessments\PageAssessmentsDAO::cleanProjectTitle()
	 */
	public function testCleanProjectTitle() {
		$pageBody = new PageAssessmentsDAO;
		$projectTitle = "Drinks/the '''Coffee task force'''";
		$cleanedProjectTitle = $pageBody->cleanProjectTitle( $projectTitle );
		$this->assertEquals( "Drinks/Coffee task force", $cleanedProjectTitle );
	}
}
