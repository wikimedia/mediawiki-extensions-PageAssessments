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

namespace MediaWiki\Extension\PageAssessments;

use IDBAccessObject;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class PageAssessmentsDAO {

	/** @var array Instance cache associating project IDs with project names */
	protected static $projectNames = [];

	private static function getReplicaDBConnection(): IReadableDatabase {
		return MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
	}

	private static function getPrimaryDBConnection(): IDatabase {
		return MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
	}

	/**
	 * Driver function that handles updating assessment data in database
	 * @param Title $titleObj Title object of the subject page
	 * @param array $assessmentData Data for all assessments compiled
	 * @param mixed|null $ticket Transaction ticket
	 */
	public static function doUpdates( $titleObj, $assessmentData, $ticket = null ) {
		global $wgUpdateRowsPerQuery, $wgPageAssessmentsSubprojects;

		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$ticket = $ticket ?: $dbProvider->getEmptyTransactionTicket( __METHOD__ );

		$pageId = $titleObj->getArticleID();
		$revisionId = $titleObj->getLatestRevID();
		// Compile a list of projects found in the parserData to find out which
		// assessment records need to be inserted, deleted, or updated.
		$projects = [];
		foreach ( $assessmentData as $key => $parserData ) {
			// If the name of the project is set...
			if ( isset( $parserData[0] ) && $parserData[0] !== '' ) {
				// Clean the project name.
				$projectName = self::cleanProjectTitle( $parserData[0] );
				// Replace the original project name with the cleaned project
				// name in the assessment data, since we'll need it to match later.
				$assessmentData[$key][0] = $projectName;
				// Get the corresponding ID from page_assessments_projects table.
				$projectId = self::getProjectId( $projectName );
				// If there is no existing project by that name, add it to the table.
				if ( $projectId === false ) {
					if ( $wgPageAssessmentsSubprojects ) {
						// Extract possible parent from the project name.
						$parentId = self::extractParentProjectId( $projectName );
						// Insert project data into the database table.
						$projectId = self::insertProject( $projectName, $parentId );
					} else {
						$projectId = self::insertProject( $projectName );
					}
				}
				// Add the project's ID to the array.
				$projects[$projectName] = $projectId;
			}
		}
		// Get a list of all the projects previously assigned to the page.
		$projectsInDb = self::getAllProjects( $pageId, IDBAccessObject::READ_LATEST );

		$toInsert = array_diff( $projects, $projectsInDb );
		$toDelete = array_diff( $projectsInDb, $projects );
		$toUpdate = array_intersect( $projects, $projectsInDb );

		$i = 0;

		// Add and update assessment records to the database
		foreach ( $assessmentData as $parserData ) {
			// Make sure the name of the project is set.
			if ( !isset( $parserData[0] ) || $parserData[0] == '' ) {
				continue;
			}
			$projectId = $projects[$parserData[0]];
			if ( $projectId && $pageId ) {
				$class = $parserData[1];
				$importance = $parserData[2];
				$values = [
					'pa_page_id' => $pageId,
					'pa_project_id' => $projectId,
					'pa_class' => $class,
					'pa_importance' => $importance,
					'pa_page_revision' => $revisionId
				];
				if ( in_array( $projectId, $toInsert ) ) {
					self::insertRecord( $values );
				} elseif ( in_array( $projectId, $toUpdate ) ) {
					self::updateRecord( $values );
				}
				// Check for database lag if there's a huge number of assessments
				if ( $i > 0 && $i % $wgUpdateRowsPerQuery == 0 ) {
					$dbProvider->commitAndWaitForReplication( __METHOD__, $ticket );
				}
				$i++;
			}
		}

		// Delete records from the database
		foreach ( $toDelete as $project ) {
			$values = [
				'pa_page_id' => $pageId,
				'pa_project_id' => $project
			];
			self::deleteRecord( $values );
			// Check for database lag if there's a huge number of deleted assessments
			if ( $i > 0 && $i % $wgUpdateRowsPerQuery == 0 ) {
				$dbProvider->commitAndWaitForReplication( __METHOD__, $ticket );
			}
			$i++;
		}
	}

	/**
	 * Get name for the given wikiproject
	 * @param int $projectId The ID of the project
	 * @return string|false The name of the project or false if not found
	 */
	public static function getProjectName( $projectId ) {
		// Check for a valid project ID
		if ( $projectId > 0 ) {
			// See if the project name is already in the instance cache
			if ( isset( self::$projectNames[$projectId] ) ) {
				return self::$projectNames[$projectId];
			} else {
				$dbr = self::getReplicaDBConnection();
				$projectName = $dbr->newSelectQueryBuilder()
					->select( 'pap_project_title' )
					->from( 'page_assessments_projects' )
					->where( [ 'pap_project_id' => $projectId ] )
					->caller( __METHOD__ )
					->fetchField();
				// Store the project name in instance cache
				self::$projectNames[$projectId] = $projectName;
				return $projectName;
			}
		}
		return false;
	}

	/**
	 * Extract parent from a project name and return the ID. For example, if the
	 * project name is "Novels/Crime task force", the parent will be "Novels",
	 * i.e. WikiProject Novels.
	 *
	 * @param string $projectName Project title
	 * @return int|false project ID or false if not found
	 */
	protected static function extractParentProjectId( $projectName ) {
		$projectNameParts = explode( '/', $projectName );
		if ( count( $projectNameParts ) > 1 && $projectNameParts[0] !== '' ) {
			return self::getProjectId( $projectNameParts[0] );
		}
		return false;
	}

	/**
	 * Get project ID for a given wikiproject title
	 * @param string $project Project title
	 * @return int|false project ID or false if not found
	 */
	public static function getProjectId( $project ) {
		$dbr = self::getReplicaDBConnection();
		return $dbr->newSelectQueryBuilder()
			->select( 'pap_project_id' )
			->from( 'page_assessments_projects' )
			->where( [ 'pap_project_title' => $project ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * Insert a new wikiproject into the projects table
	 * @param string $project Wikiproject title
	 * @param int|null $parentId ID of the parent project (for subprojects) (optional)
	 * @return int Insert Id for new project
	 */
	public static function insertProject( $project, $parentId = null ) {
		$dbw = self::getPrimaryDBConnection();
		$values = [ 'pap_project_title' => $project ];
		if ( $parentId ) {
			$values[ 'pap_parent_id' ] = (int)$parentId;
		}
		$dbw->newInsertQueryBuilder()
			->insertInto( 'page_assessments_projects' )
			// Use ignore() in case two projects with the same name are added at once.
			// This normally shouldn't happen, but is possible perhaps from clicking
			// 'Publish changes' twice in very quick succession. (See T286671)
			->ignore()
			->row( $values )
			->caller( __METHOD__ )
			->execute();
		$id = $dbw->insertId();
		return $id;
	}

	/**
	 * Clean up the title of the project (or subproject)
	 *
	 * Since the project title comes from a template parameter, it can basically
	 * be anything. This function accounts for common cases where editors put
	 * extra stuff into the parameter besides just the name of the project.
	 * @param string $project WikiProject title
	 * @return string Cleaned-up WikiProject title
	 */
	public static function cleanProjectTitle( $project ) {
		// Remove any bold formatting.
		$project = str_replace( "'''", "", $project );
		// Remove "the" prefix for subprojects (common on English Wikipedia).
		// This is case-sensitive on purpose, as there are some legitimate
		// subproject titles starting with "The", e.g. "The Canterbury Tales".
		$project = str_replace( "/the ", "/", $project );
		// Truncate to 255 characters to avoid DB warnings.
		return substr( $project, 0, 255 );
	}

	/**
	 * Update record in DB if there are new values
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public static function updateRecord( $values ) {
		$dbr = self::getReplicaDBConnection();
		$conds = [
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		];
		// Check if there are no updates to be done
		$record = $dbr->newSelectQueryBuilder()
			->select( [ 'pa_class', 'pa_importance', 'pa_project_id', 'pa_page_id' ] )
			->from( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $record as $row ) {
			if ( $row->pa_importance == $values['pa_importance'] &&
				$row->pa_class == $values['pa_class']
			) {
				// Return if no update is needed
				return true;
			}
		}
		// Make updates if there are changes
		$dbw = self::getPrimaryDBConnection();
		$dbw->newUpdateQueryBuilder()
			->update( 'page_assessments' )
			->set( $values )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Insert a new record in DB
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public static function insertRecord( $values ) {
		$dbw = self::getPrimaryDBConnection();
		// Use IGNORE in case 2 records for the same project are added at once.
		// This normally shouldn't happen, but is possible. (See T152080)
		$dbw->newInsertQueryBuilder()
			->insertInto( 'page_assessments' )
			->ignore()
			->row( $values )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Get all projects associated with a given page (as project IDs)
	 * @param int $pageId Page ID
	 * @param int $flags IDBAccessObject::READ_* constant. This can be used to
	 *     force reading from the primary database. See docs at IDBAccessObject.php.
	 * @return array $results All projects associated with given page
	 */
	public static function getAllProjects( $pageId, $flags = IDBAccessObject::READ_NORMAL ) {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = self::getPrimaryDBConnection();
		} else {
			$db = self::getReplicaDBConnection();
		}
		$res = $db->newSelectQueryBuilder()
			->select( 'pa_project_id' )
			->from( 'page_assessments' )
			->where( [ 'pa_page_id' => $pageId ] )
			->recency( $flags )
			->caller( __METHOD__ )->fetchResultSet();
		$results = [];
		foreach ( $res as $row ) {
			$results[] = $row->pa_project_id;
		}
		return $results;
	}

	/**
	 * Delete a record from DB
	 * @param array $values Conditions for looking up records to delete
	 * @return bool true
	 */
	public static function deleteRecord( $values ) {
		$dbw = self::getPrimaryDBConnection();
		$conds = [
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		];
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
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
		$dbw = self::getPrimaryDBConnection();
		$conds = [
			'pa_page_id' => $id,
		];
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Function called on parser init
	 * @param Parser $parser Parser object
	 * @param string $project Wikiproject name
	 * @param string $class Class of article
	 * @param string $importance Importance of article
	 */
	public static function cacheAssessment(
		Parser $parser,
		$project = '',
		$class = '',
		$importance = ''
	) {
		$parserData = $parser->getOutput()->getExtensionData( 'ext-pageassessment-assessmentdata' );
		$values = [ $project, $class, $importance ];
		if ( $parserData == null ) {
			$parserData = [];
		}
		$parserData[] = $values;
		$parser->getOutput()->setExtensionData( 'ext-pageassessment-assessmentdata', $parserData );
	}

}
