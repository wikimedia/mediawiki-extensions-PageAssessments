<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * Schema Hooks for PageAssessments extension
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\PageAssessments;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * Run database updates
	 * @param DatabaseUpdater $updater DatabaseUpdater object
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbDir = __DIR__ . '/../db';
		$type = $updater->getDB()->getType();
		$updater->addExtensionTable( 'page_assessments_projects',
			"$dbDir/$type/tables-generated.sql" );
		if ( $type === 'mysql' ) {
			$updater->addExtensionField( 'page_assessments_projects',
				'pap_parent_id', "$dbDir/mysql/patch-subprojects.sql" );
		}
	}

}
