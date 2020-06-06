<?php
/**
 * This script will remove any unused projects from the page_assessments_projects
 * table. This may include WikiProjects that have been renamed or retired, or
 * projects that were used in an assessment by mistake or for testing.
 */

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeUnusedProjects extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageAssessments' );
		$this->addDescription( "Purge unused projects from the page_assessments_projects table" );
		$this->addOption( 'dry-run',
			"Show how many projects would be deleted, but don't actually purge them." );
	}

	public function execute() {
		$dbw = $this->getDB( DB_MASTER );
		$dbr = $this->getDB( DB_REPLICA );
		// Count all the projects
		$initialCount = $dbr->selectField( 'page_assessments_projects', 'COUNT(*)', [], __METHOD__ );
		$this->output( "Projects before purge: $initialCount\n" );

		// Build a list of all the projects that are parents of other projects
		$projectIds1 = [];
		$res = $dbr->select( 'page_assessments_projects', 'DISTINCT( pap_parent_id )', [], __METHOD__ );
		foreach ( $res as $row ) {
			if ( $row->pap_parent_id ) {
				$projectIds1[] = $row->pap_parent_id;
			}
		}

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		// Build a list of all the projects that are used in assessments
		$projectIds2 = [];
		$res = $dbr->select( 'page_assessments', 'DISTINCT( pa_project_id )', [], __METHOD__ );
		foreach ( $res as $row ) {
			if ( $row->pa_project_id ) {
				$projectIds2[] = $row->pa_project_id;
			}
		}

		// Combine the two lists
		$usedProjectIds = array_unique( array_merge( $projectIds1, $projectIds2 ) );

		// Protect against lack of projects in some environments - T219935
		if ( !count( $usedProjectIds ) ) {
			$this->output( "No projects found.\nDone.\n" );
			return;
		}

		if ( $this->hasOption( 'dry-run' ) ) {
			$finalCount = count( $usedProjectIds );
		} else {
			$this->output( "Purging unused projects from page_assessments_projects...\n" );
			// Delete all the projects that aren't used in any current assessments
			// and aren't parents of other projects.
			$conds = [ 'pap_project_id NOT IN (' . $dbr->makeList( $usedProjectIds ) . ')' ];
			$dbw->delete( 'page_assessments_projects', $conds, __METHOD__ );
			$this->output( "Done.\n" );
			$lbFactory->waitForReplication();
			// Recount all the projects
			$finalCount = $dbr->selectField( 'page_assessments_projects', 'COUNT(*)', [], __METHOD__ );
		}
		$this->output( "Projects after purge: $finalCount\n" );
	}

}

$maintClass = PurgeUnusedProjects::class;
require_once RUN_MAINTENANCE_IF_MAIN;
