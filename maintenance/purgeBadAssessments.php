<?php
/**
 * This script will remove any assessments from the page_assessments table that
 * have page ID 0. This was possible before Iec0e5b3 was merged.
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PurgeBadAssessments extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'PageAssessments' );
		$this->addDescription( "Purge bad assessments from the page_assessments table" );
	}

	public function execute() {
		$dbw = $this->getDB( DB_MASTER );
		$this->output( "Purging bad assessments from page_assessments...\n" );
		// Delete all assessments with page ID 0
		$dbw->delete( 'page_assessments', 'pa_page_id = 0' );
		$this->output( "Done.\n" );
		$this->output( "Assessments deleted: " . $dbw->affectedRows() . "\n" );
	}

}

$maintClass = "PurgeBadAssessments";
require_once RUN_MAINTENANCE_IF_MAIN;
