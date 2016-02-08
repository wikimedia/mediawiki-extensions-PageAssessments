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
	 * @return bool
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'assessment', 'PageAssessmentsBody::cacheAssessment' );
	}

	/**
	 * Obtain parser data after parsing is complete
	 * @param OutputPage $out OutputPage object
	 * @param ParserOutput $pOut ParserOutput object
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $pOut ) {
		if ( $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' ) != null ) {
			$assessmentData = $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' );
			$title = $pOut->getDisplayTitle();
			if ( $title !== null ) {
				// Get Title class object for the subject page for the talk page
				$titleObj = Title::newFromText( $title )->getSubjectPage();
				PageAssessmentsBody::execute( $titleObj, $assessmentData );
			}
		}
	}

	/**
	 * Run database updates
	 * @param DatabaseUpdater $updater DatabaseUpdater object
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$dbDir = __DIR__ . '/db';
		$updater->addExtensionUpdate( array( 'addTable', 'page_assessments', "$dbDir/addReviewsTable.sql", true ) );
		return true;
	}

	/**
	 * Run unit tests
	 */
	public static function onUnitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/tests/phpunit/*Test.php' ) );
		return true;
	}

	/**
	 * Delete assessment records when page is deleted
	 */
	public static function onArticleDeleteComplete( &$article, &$user, $reason, $id, $content = null, $logEntry ) {
		PageAssessmentsBody::deleteRecordsForPage( $id );
		return true;
	}

}
