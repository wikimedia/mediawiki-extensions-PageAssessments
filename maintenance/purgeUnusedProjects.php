<?php
/**
 * This script will remove any unused projects from the page_assessments_projects
 * table. This may include WikiProjects that have been renamed or retired, or
 * projects that were used in an assessment by mistake or for testing.
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PurgeUnusedProjects extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageAssessments' );
		$this->addDescription( "Purge unused projects from the page_assessments_projects table" );
		$this->addOption( 'dry-run', "Show how many projects would be deleted, but don't actually purge them." );
	}

	public function execute() {
		$dbw = $this->getDB( DB_MASTER );
		$dbr = $this->getDB( DB_REPLICA );
		// Count all the projects
		$initialCount = $dbr->selectField( 'page_assessments_projects', 'COUNT(*)' );
		$this->output( "Projects before purge: $initialCount\n" );
		if ( $this->hasOption( 'dry-run' ) ) {
			// Count all the projects used in current assessments
			$finalCount = $dbr->selectField( 'page_assessments', 'COUNT( DISTINCT pa_project_id )' );
		} else {
			$this->output( "Purging unused projects from page_assessments_projects...\n" );
			// Delete all the projects that aren't used in any current assessments
			$cond = 'pap_project_id NOT IN ( SELECT DISTINCT( pa_project_id ) FROM page_assessments )';
			$dbw->delete( 'page_assessments_projects', [ $cond ], __METHOD__ );
			$this->output( "Done.\n" );
			wfWaitForSlaves();
			// Recount all the projects
			$finalCount = $dbr->selectField( 'page_assessments_projects', 'COUNT(*)' );
		}
		$this->output( "Projects after purge: $finalCount\n" );
	}

}

$maintClass = "PurgeUnusedProjects";
require_once RUN_MAINTENANCE_IF_MAIN;
