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
 * PageAssessments extension body
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class PageAssessmentsBody implements IDBAccessObject {

	/** @var array Instance cache associating project IDs with project names */
	protected static $projectNames = [];

	/**
	 * Driver function that handles updating assessment data in database
	 * @param Title $titleObj Title object of the subject page
	 * @param array $assessmentData Data for all assessments compiled
	 * @param mixed $ticket Transaction ticket
	 */
	public static function doUpdates( $titleObj, $assessmentData, $ticket = null ) {
		global $wgUpdateRowsPerQuery;

		$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$ticket = $ticket ?: $factory->getEmptyTransactionTicket( __METHOD__ );

		$pageId = $titleObj->getArticleID();
		$revisionId = $titleObj->getLatestRevID();
		// Compile a list of projects to find out which ones to be deleted afterwards
		$projects = array();
		foreach ( $assessmentData as $parserData ) {
			// For each project, get the corresponding ID from page_assessments_projects table
			$projectId = self::getProjectId( $parserData[0] );
			if ( $projectId === false ) {
				$projectId = self::insertProject( $parserData[0] );
			}
			$projects[$parserData[0]] = $projectId;
		}
		$projectsInDb = self::getAllProjects( $pageId, self::READ_LATEST );
		$toInsert = array_diff( $projects, $projectsInDb );
		$toDelete = array_diff( $projectsInDb, $projects );
		$toUpdate = array_intersect( $projects, $projectsInDb );

		$i = 0;

		// Add and update records to the database
		foreach ( $assessmentData as $parserData ) {
			$projectId = $projects[$parserData[0]];
			if ( $projectId ) {
				$class = $parserData[1];
				$importance = $parserData[2];
				$values = array(
					'pa_page_id' => $pageId,
					'pa_project_id' => $projectId,
					'pa_class' => $class,
					'pa_importance' => $importance,
					'pa_page_revision' => $revisionId
				);
				if ( in_array( $projectId, $toInsert ) ) {
					self::insertRecord( $values );
				} elseif ( in_array( $projectId, $toUpdate ) ) {
					self::updateRecord( $values );
				}
				// Check for database lag if there's a huge number of assessments
				if ( $i > 0 && $i % $wgUpdateRowsPerQuery == 0 ) {
					$factory->commitAndWaitForReplication( __METHOD__, $ticket );
				}
				$i++;
			}
		}

		// Delete records from the database
		foreach ( $toDelete as $project ) {
			$values = array(
				'pa_page_id' => $pageId,
				'pa_project_id' => $project
			);
			self::deleteRecord( $values );
			// Check for database lag if there's a huge number of deleted assessments
			if ( $i > 0 && $i % $wgUpdateRowsPerQuery == 0 ) {
				$factory->commitAndWaitForReplication( __METHOD__, $ticket );
			}
			$i++;
		}

		return;
	}

	/**
	 * Get name for the given wikiproject
	 * @param integer $projectId The ID of the project
	 * @return string|false The name of the project or false if not found
	 */
	public static function getProjectName( $projectId ) {
		// Check for a valid project ID
		if ( $projectId > 0 ) {
			// See if the project name is already in the instance cache
			if ( isset( self::$projectNames[$projectId] ) ) {
				return self::$projectNames[$projectId];
			} else {
				$dbr = wfGetDB( DB_SLAVE );
				$projectName = $dbr->selectField(
					'page_assessments_projects',
					'pap_project_title',
					[ 'pap_project_id' => $projectId ]
				);
				// Store the project name in instance cache
				self::$projectNames[$projectId] = $projectName;
				return $projectName;
			}
		}
		return false;
	}


	/**
	 * Get project ID for a give wikiproject title
	 * @param string $project Project title
	 * @return int|false project ID or false if not found
	 */
	public static function getProjectId( $project ) {
		$dbr = wfGetDB( DB_SLAVE );
		return $dbr->selectField(
			'page_assessments_projects',
			'pap_project_id',
			[ 'pap_project_title' => $project ]
		);
	}


	/**
	 * Insert a new wikiproject into the projects table
	 * @param string $project Wikiproject title
	 * @return int Insert Id for new project
	 */
	public static function insertProject( $project ) {
		$dbw = wfGetDB( DB_MASTER );
		$values = array(
			'pap_project_title' => $project,
			'pap_project_id' => $dbw->nextSequenceValue( 'pap_project_id_seq' )
		);
		$dbw->insert( 'page_assessments_projects', $values, __METHOD__ );
		$id = $dbw->insertId();
		return $id;
	}


	/**
	 * Update record in DB if there are new values
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public static function updateRecord( $values ) {
		$dbr = wfGetDB( DB_SLAVE );
		$conds = array(
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		);
		// Check if there are no updates to be done
		$record = $dbr->select(
			'page_assessments',
			array( 'pa_class', 'pa_importance', 'pa_project_id', 'pa_page_id' ),
			$conds
		);
		foreach ( $record as $row ) {
			if ( $row->pa_importance == $values['pa_importance'] &&
				$row->pa_class == $values['pa_class']
			) {
				// Return if no update is needed
				return true;
			}
		}
		// Make updates if there are changes
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'page_assessments', $values, $conds, __METHOD__ );
		return true;
	}


	/**
	 * Insert a new record in DB
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public static function insertRecord( $values ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'page_assessments', $values, __METHOD__ );
		return true;
	}


	/**
	 * Get all projects associated with a given page (as project IDs)
	 * @param int $pageId Page ID
	 * @param int $flags IDBAccessObject::READ_* constant. This can be used to
	 *     force reading from the master database. See docs at IDBAccessObject.php.
	 * @return array $results All projects associated with given page
	 */
	public static function getAllProjects( $pageId, $flags = self::READ_NORMAL ) {
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = wfGetDB( $index );
		$res = $db->select(
			'page_assessments',
			'pa_project_id',
			array( 'pa_page_id' => $pageId ),
			__METHOD__,
			$options
		);
		$results = array();
		if ( $res ) {
			foreach ( $res as $row ) {
				$results[] = $row->pa_project_id;
			}
		}
		return $results;
	}


	/**
	 * Delete a record from DB
	 * @param array $values Conditions for looking up records to delete
	 * @return bool true
	 */
	public static function deleteRecord( $values ) {
		$dbw = wfGetDB( DB_MASTER );
		$conds = array(
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		);
		$dbw->delete( 'page_assessments', $conds, __METHOD__ );
		return true;
	}


	/**
	 * Delete all records for a given page when page is deleted
	 * Note: We don't take care of undeletions explicitly, the records are restored
	 * when the page is parsed again.
	 * @param int $id Page ID of deleted page
	 * @return bool true
	 */
	public static function deleteRecordsForPage( $id ) {
		$dbw = wfGetDB( DB_MASTER );
		$conds = array(
			'pa_page_id' => $id,
		);
		$dbw->delete( 'page_assessments', $conds, __METHOD__ );
		return true;
	}


	/**
	 * Function called on parser init
	 * @param Parser $parser Parser object
	 * @param string $project Wikiproject name
	 * @param string $class Class of article
	 * @param string $importance Importance of article
	 */
	public static function cacheAssessment( &$parser, $project = '', $class = '', $importance = '' ) {
		$parserData = $parser->getOutput()->getExtensionData( 'ext-pageassessment-assessmentdata' );
		$values = array( $project, $class, $importance );
		if ( $parserData == null ) {
			$parserData = array();
		}
		$parserData[] = $values;
		$parser->getOutput()->setExtensionData( 'ext-pageassessment-assessmentdata', $parserData );
	}

}
