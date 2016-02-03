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

class PageAssessmentsBody {

	/**
	 * Driver function
	 */
	public static function execute( $titleObj, $assessmentData ) {
		$pageId = $titleObj->getArticleID();
		$revisionId = $titleObj->getLatestRevID();
		// Compile a list of projects to find out which ones to be deleted afterwards
		$projects = array();
		foreach ( $assessmentData as $parserData ) {
			$projects[] = $parserData[0];
		}
		$projectsInDb = PageAssessmentsBody::getAllProjects( $pageId );
		$toInsert = array_diff( $projects, $projectsInDb );
		$toDelete = array_diff( $projectsInDb, $projects );
		$toUpdate = array_intersect( $projects, $projectsInDb );
		$jobs = array();

		foreach ( $assessmentData as $parserData ) {
			$project = $parserData[0];
			$class = $parserData[1];
			$importance = $parserData[2];
			$values = array(
				'pa_page_id' => $pageId,
				'pa_project' => $project,
				'pa_class' => $class,
				'pa_importance' => $importance,
				'pa_page_revision' => $revisionId
			);
			if ( in_array( $project, $toInsert ) ) {
				$values['job_type'] = 'insert';
			} elseif ( in_array( $project, $toUpdate ) ) {
				$values['job_type'] = 'update';
			}
			$jobs[] = new PageAssessmentsSaveJob( $titleObj, $values );
		}
		// Add deletion jobs to job array
		foreach ( $toDelete as $project ) {
			$values = array(
				'pa_page_id' => $pageId,
				'pa_project' => $project,
				'job_type' => 'delete'
			);
			$jobs[] = new PageAssessmentsSaveJob( $titleObj, $values );
		}

		JobQueueGroup::singleton()->push( $jobs );
		return;
	}


	/**
	 * Update record in DB if there are new values
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public static function updateRecord( $values ) {
		$dbr = wfGetDB( DB_SLAVE );
		$conds =  array(
			'pa_page_id' => $values['pa_page_id'],
			'pa_project'  => $values['pa_project']
		);
		// Check if there are no updates to be done
		$record = $dbr->select(
			'page_assessments',
			array( 'pa_class', 'pa_importance', 'pa_project', 'pa_page_id' ),
			$conds
		);
		foreach ( $record as $row ) {
			if ( $row->pa_importance == $values['pa_importance'] &&
				$row->pa_class == $values['pa_class'] ) {
				// Return if no updates
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
	 * Get all records for give page
	 * @param int $id Page ID
	 * @param string $project Project
	 * @return array $results All projects associated with given page title
	 */
	public static function getAllProjects( $pageId ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'page_assessments',
			'pa_project',
			array( 'pa_page_id' => $pageId )
		);
		$results = array();
		if ( $res ) {
			foreach ( $res as $row ) {
				$results[] = $row->pa_project;
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
			'pa_project' => $values['pa_project']
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
