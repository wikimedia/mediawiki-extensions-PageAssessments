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
	public static function execute ( $titleObj, $assessmentData ) {
		$pageTitle = $titleObj->getText();
		$pageNamespace = $titleObj->getNamespace();
		$pageId = $titleObj->getArticleID();
		$revisionId = $titleObj->getLatestRevID();
		$userId = Revision::newFromId( $revisionId )->getUser();
		// Compile a list of projects to find out which ones to be deleted afterwards
		$projects = array();
		foreach ( $assessmentData as $parserData ) {
			$projects[] = $parserData[0];
		}
		$projectsInDb = PageAssessmentsBody::getAllProjects( $pageTitle );
		$toInsert = array_diff( $projects, $projectsInDb );
		$toDelete = array_diff( $projectsInDb, $projects );
		$toUpdate = array_intersect( $projects, $projectsInDb );

		foreach ( $assessmentData as $parserData ) {
			$project = $parserData[0];
			$class = $parserData[1];
			$importance = $parserData[2];
			$values = array(
				'pa_page_id' => $pageId,
				'pa_page_name' => $pageTitle,
				'pa_page_namespace' => $pageNamespace,
				'pa_project' => $project,
				'pa_class' => $class,
				'pa_importance' => $importance,
				'pa_page_revision' => $revisionId
			);
			if ( in_array( $project, $toInsert ) ) {
				PageAssessmentsBody::insertRecord( $values );
			} elseif ( in_array( $project, $toUpdate ) ) {
				PageAssessmentsBody::updateRecord( $values );
			}
		}
		foreach ( $toDelete as $project ) {
			PageAssessmentsBody::deleteRecord( $pageTitle, $project );
		}
		return;
	}


	/**
	 * Update record in DB with new values
	 * @param array $values New values to be entered into the DB
	 * @return bool True/False on query success/fail
	 */
	public static function updateRecord ( $values ) {
		$dbw = wfGetDB( DB_MASTER );
		$conds =  array(
			'pa_page_name' => $values['pa_page_name'],
			'pa_project'  => $values['pa_project']
		);
		$dbw->update( 'page_assessments', $values, $conds, __METHOD__ );
		return true;
	}


	/**
	 * Insert a new record in DB
	 * @param array $values New values to be entered into the DB
	 * @return bool True/False on query success/fail
	 */
	public static function insertRecord ( $values ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'page_assessments', $values, __METHOD__ );
		return true;
	}


	/**
	 * Get all records for give page
	 * @param string $title Page title
	 * @param string $project Project
	 * @return array $results All projects associated with given page title
	 */
	public static function getAllProjects ( $title ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'page_assessments',
			'pa_project',
			array( 'pa_page_name' => $title )
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
	 * @param string $title Page title
	 * @param string $project Project
	 * @return bool True/False on query success/fail
	 */
	public static function deleteRecord ( $title, $project ) {
		$dbw = wfGetDB( DB_MASTER );
		$conds = array(
			'pa_page_name' => $title,
			'pa_project' => $project
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
	public static function cacheAssessment ( &$parser, $project = '', $class = '', $importance = '' ) {
		$parserData = $parser->getOutput()->getExtensionData( 'ext-pageassessment-assessmentdata' );
		$values = array( $project, $class, $importance );
		if ( $parserData == null ) {
			$parserData = array();
		}
		$parserData[] = $values;
		$parser->getOutput()->setExtensionData( 'ext-pageassessment-assessmentdata', $parserData );
	}

}
