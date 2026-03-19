<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * Run database updates
	 * @param DatabaseUpdater $updater DatabaseUpdater object
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$dbDir = __DIR__ . '/../../db';
		$type = $updater->getDB()->getType();
		$updater->addExtensionTable( 'page_assessments_projects',
			"$dbDir/$type/tables-generated.sql" );
		if ( $type === 'mysql' ) {
			$updater->addExtensionField( 'page_assessments_projects',
				'pap_parent_id', "$dbDir/mysql/patch-subprojects.sql" );
		}
	}

}
