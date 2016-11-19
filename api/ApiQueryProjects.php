<?php

/*
 * API module for retrieving all the projects on a wiki
 */
class ApiQueryProjects extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'pj' );
	}

	/**
	 * Evaluate the parameters, perform the requested query, and set up the result
	 */
	public function execute() {
		// Set the caching parameters
		$this->getMain()->setCacheMode( 'public' );
		$this->getMain()->setCacheMaxAge( 3600 ); // 1 hour

		// Set the database query parameters
		$this->addTables( [ 'page_assessments_projects' ] );
		$this->addFields( [ 'project_title' => 'pap_project_title' ] );
		$this->addOption( 'ORDER BY', 'pap_project_title' );

		// Execute the query and put the results in an array
		$db_res = $this->select( __METHOD__ );
		$projects = [];
		foreach ( $db_res as $row ) {
			$projects[] = $row->project_title;
		}

		// Build the API output
		$result = $this->getResult();
		$result->addValue( 'query', 'projects', $projects );
	}

	/**
	 * Return usage examples for this module
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=query&prop=projects' => 'apihelp-query+projects-example',
		];
	}
}
