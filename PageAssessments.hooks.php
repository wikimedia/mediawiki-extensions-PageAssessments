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
 * Hooks for PageAssessments extension
 *
 * @ingroup Extensions
 */

class PageAssessmentsHooks {

	/**
	 * Register the parser function hook
	 * @param $parser Parser
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'assessment', 'PageAssessmentsBody::cacheAssessment' );
	}

	/**
	 * Insert assessment records after page is saved
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdateComplete( &$linksUpdate ) {
		$pOut = $linksUpdate->getParserOutput();
		if ( $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' ) !== null ) {
			$assessmentData = $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' );
		} else {
			// Even if there is no assessment data, we still need to run doUpdates
			// in case any assessment data was deleted from the page.
			$assessmentData = [];
		}
		$title = $linksUpdate->getTitle();
		// In most cases $title will be a talk page, but we want to associate the
		// assessment data with the subject page.
		$subjectTitle = $title->getSubjectPage();
		PageAssessmentsBody::doUpdates( $subjectTitle, $assessmentData );
	}

	/**
	 * Run database updates
	 * @param DatabaseUpdater $updater DatabaseUpdater object
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$dbDir = __DIR__ . '/db';
		$updater->addExtensionUpdate( array( 'addTable', 'page_assessments_projects', "$dbDir/addProjectsTable.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'page_assessments', "$dbDir/addReviewsTable.sql", true ) );
	}

	/**
	 * Delete assessment records when page is deleted
	 */
	public static function onArticleDeleteComplete( &$article, &$user, $reason, $id, $content = null, $logEntry ) {
		PageAssessmentsBody::deleteRecordsForPage( $id );
	}

}
