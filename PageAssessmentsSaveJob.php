<?php
/**
 * Database writes for the PageAssessments extension using the job queue
 *
 * @file
 * @ingroup Extensions
 */

class PageAssessmentsSaveJob extends Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'AssessmentSaveJob', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		$jobType = $this->params['job_type'];
		// Perform updates
		if ( $jobType == 'insert' ) {
			// Compile the array to be inserted to the DB
			$values = array(
				'pa_page_id' => $this->params['pa_page_id'],
				'pa_project' => $this->params['pa_project'],
				'pa_class' => $this->params['pa_class'],
				'pa_importance' => $this->params['pa_importance'],
				'pa_page_revision' => $this->params['pa_page_revision']
			);
			PageAssessmentsBody::insertRecord( $values );
		} elseif ( $jobType == 'update' ) {
			// Compile the array to be inserted to the DB
			$values = array(
				'pa_page_id' => $this->params['pa_page_id'],
				'pa_project' => $this->params['pa_project'],
				'pa_class' => $this->params['pa_class'],
				'pa_importance' => $this->params['pa_importance'],
				'pa_page_revision' => $this->params['pa_page_revision']
			);
			PageAssessmentsBody::updateRecord( $values );
		} elseif ( $jobType == 'delete' ) {
			$values = array(
				'pa_page_id' => $this->params['pa_page_id'],
				'pa_project' => $this->params['pa_project']
			);
			PageAssessmentsBody::deleteRecord( $values );
		}
		return true;
	}

}
