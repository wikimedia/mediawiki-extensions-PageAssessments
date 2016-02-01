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
			'pa_page_name' => 'Test1',
			'pa_page_namespace' => '0',
			'pa_project' => 'Medicine',
			'pa_class' => 'A',
			'pa_importance' => 'High',
			'pa_page_revision' => '20'
		);
		$pageBody->insertRecord( $values );
		$this->assertSelect(
			'page_assessments', // Table
			array( 'pa_page_name', 'pa_class', 'pa_importance' ), // Fields to select
			array(), // Conditions
			array( array( 'Test1', 'A', 'High' ) ) // Expected values
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
			'pa_page_name' => 'Test1',
			'pa_page_namespace' => '0',
			'pa_project' => 'Medicine',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		);
		$pageBody->updateRecord( $values );
		$this->assertSelect(
			'page_assessments',
			array( 'pa_page_name', 'pa_class', 'pa_importance' ),
			array(),
			array( array( 'Test1', 'B', 'Low' ) )
		);
	}


	/**
	 * Test the deleteRecord() function in PageAssessmentsBody class
	 */
	public function testDelete() {
		$this->testInsert();
		$pageBody = new PageAssessmentsBody;
		$values = array(
			'pa_page_name' => 'Test1',
			'pa_project' => 'Medicine'
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
			'pa_page_name' => 'Test1',
			'pa_page_namespace' => '0',
			'pa_project' => 'History',
			'pa_class' => 'B',
			'pa_importance' => 'Low',
			'pa_page_revision' => '21'
		);
		// Insert another record
		$pageBody->insertRecord( $values );
		$res = $pageBody->getAllProjects( 'Test1' );
		$this->assertEquals( $res, array( 'History', 'Medicine' ) );
	}


	/**
	 * Tear down - called at the end
	 */
	protected function tearDown() {
		parent::tearDown();
	}

}
