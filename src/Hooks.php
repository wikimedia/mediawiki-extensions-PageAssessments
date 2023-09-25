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
use LinksUpdate;
use LogEntry;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use Parser;
use RequestContext;
use User;
use WikiPage;

class Hooks implements
	ParserFirstCallInitHook,
	LinksUpdateCompleteHook,
	ArticleDeleteCompleteHook
{

	/**
	 * Register the parser function hook
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'assessment', [ PageAssessmentsDAO::class, 'cacheAssessment' ] );
	}

	/**
	 * Update assessment records after talk page is saved
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
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
	 * Delete assessment records when page is deleted
	 * @param WikiPage $article
	 * @param User|null $user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param LogEntry|null $logEntry
	 * @param int|null $archivedRevisionCount
	 */
	public function onArticleDeleteComplete(
		$article,
		$user,
		$reason,
		$id,
		$content,
		$logEntry,
		$archivedRevisionCount
	) {
		PageAssessmentsDAO::deleteRecordsForPage( $id );
	}

}
