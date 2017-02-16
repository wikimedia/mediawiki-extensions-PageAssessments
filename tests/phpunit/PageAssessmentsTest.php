<?php

/**
 * Test the database access and core functionality of PageAssessmentsBody.
 *
 * @group Database
 * @group PageAssessments
 */
class PageAssessmentTest extends MediaWikiTestCase {

	/**
	 * Setup for tests
	 */
	protected function setUp() {
		parent::setUp();
		$this->tablesUsed = array( 'page_assessments' );
	}


	/**
	 * Test the insertRecord() function in PageAssessmentsBody class
	 */
	public function testInsert() {
		$pageBody = new PageAssessmentsBody;
		$values = array(
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'A',
			'pa_importance' => 'High',
			'pa_page_revision' => '20'
		);
		$pageBody->insertRecord( $values );
		$this->assertSelect(
			'page_assessments', // Table
			array( 'pa_page_id', 'pa_class', 'pa_importance' ), // Fields to select
			array(), // Conditions
			array( array( '10', 'A', 'High' ) ) // Expected values
		);
	}


	/**
	 * Test the updateRecord() function in PageAssessmentsBody class
	 */
	public function testUpdate() {
		$this->testInsert();
		$pageBody = new PageAssessmentsBody;
		$values = array(
			'pa_page_id' => '10',
			'pa_project_id' => '3',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		);
		$pageBody->updateRecord( $values );
		$this->assertSelect(
			'page_assessments',
			array( 'pa_page_id', 'pa_class', 'pa_importance' ),
			array(),
			array( array( '10', 'B', 'Low' ) )
		);
	}


	/**
	 * Test the deleteRecord() function in PageAssessmentsBody class
	 */
	public function testDelete() {
		$this->testInsert();
		$pageBody = new PageAssessmentsBody;
		$values = array(
			'pa_page_id' => '10',
			'pa_project_id' => '3'
		);
		$pageBody->deleteRecord( $values );
		$res = $this->db->select( 'page_assessments', '*' );
		$row = $res->fetchRow();
		$this->assertEmpty( $row );
	}


	/**
	 * Test the getAllProjects() function in PageAssessmentsBody class
	 */
	public function testGetAllProjects() {
		$pageBody = new PageAssessmentsBody;
		// Insert a record
		$this->testInsert();
		$values = array(
			'pa_page_id' => '10',
			'pa_project_id' => '4',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		);
		// Insert another record
		$pageBody->insertRecord( $values );
		$res = $pageBody->getAllProjects( '10' );
		$expected = array( 3, 4 );
		// Since the projects may be returned in any order, we can't do a simple
		// assertEquals() on the arrays. Instead we compare the arrays using array_diff()
		// in both directions and make sure that the results are empty.
		$this->assertEmpty( array_merge( array_diff( $expected, $res ), array_diff( $res, $expected ) ) );
	}


	/**
	 * Test the cleanProjectTitle() function in PageAssessmentsBody class
	 */
	public function testCleanProjectTitle() {
		$pageBody = new PageAssessmentsBody;
		$projectTitle = "Drinks/the '''Coffee task force'''";
		$cleanedProjectTitle = $pageBody->cleanProjectTitle( $projectTitle );
		$this->assertEquals( "Drinks/Coffee task force", $cleanedProjectTitle );
	}


	/**
	 * Tear down - called at the end
	 */
	protected function tearDown() {
		parent::tearDown();
	}

}
