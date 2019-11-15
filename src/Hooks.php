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
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\PageAssessments;

use Content;
use DatabaseUpdater;
use LinksUpdate;
use LogEntry;
use Parser;
use RequestContext;
use User;
use WikiPage;

class Hooks {

	/**
	 * Register the parser function hook
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$callback = PageAssessmentsDAO::class . '::cacheAssessment';
		$parser->setFunctionHook( 'assessment', $callback );
	}

	/**
	 * Update assessment records after talk page is saved
	 * @param LinksUpdate $linksUpdate
	 * @param mixed|null $ticket
	 */
	public static function onLinksUpdateComplete( LinksUpdate $linksUpdate, $ticket = null ) {
		$assessmentsOnTalkPages = RequestContext::getMain()->getConfig()->get(
			'PageAssessmentsOnTalkPages'
		);
		$title = $linksUpdate->getTitle();
		// Only check for assessment data where assessments are actually made.
		if ( ( $assessmentsOnTalkPages && $title->isTalkPage() ) ||
			( !$assessmentsOnTalkPages && !$title->isTalkPage() )
		) {
			$pOut = $linksUpdate->getParserOutput();
			if ( $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' ) !== null ) {
				$assessmentData = $pOut->getExtensionData( 'ext-pageassessment-assessmentdata' );
			} else {
				// Even if there is no assessment data, we still need to run doUpdates
				// in case any assessment data was deleted from the page.
				$assessmentData = [];
			}
			// Assessment data should only be associated with subject pages regardless
			// of whether it is recorded on talk pages or subject pages.
			if ( $title->isTalkPage() ) {
				$title = $title->getSubjectPage();
			}
			PageAssessmentsDAO::doUpdates( $title, $assessmentData, $ticket );
		}
	}

	/**
	 * Run database updates
	 * @param DatabaseUpdater|null $updater DatabaseUpdater object
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$dbDir = __DIR__ . '/../db';
		$updater->addExtensionTable( 'page_assessments_projects',
			"$dbDir/addProjectsTable.sql" );
		$updater->addExtensionTable( 'page_assessments',
			"$dbDir/addReviewsTable.sql" );
		$updater->addExtensionField( 'page_assessments_projects',
			'pap_parent_id', "$dbDir/patch-subprojects.sql" );
	}

	/**
	 * Delete assessment records when page is deleted
	 * @param WikiPage $article
	 * @param User|null $user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param LogEntry|null $logEntry
	 * @param int|null $archivedRevisionCount
	 */
	public static function onArticleDeleteComplete(
		$article,
		$user,
		$reason,
		$id,
		$content = null,
		$logEntry = null,
		$archivedRevisionCount = null
	) {
		PageAssessmentsDAO::deleteRecordsForPage( $id );
	}

}
