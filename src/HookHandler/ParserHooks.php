<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\StripState;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class ParserHooks implements ParserAfterParseHook, ParserFirstCallInitHook, RevisionDataUpdatesHook {

	public const string EXT_DATA_KEY = 'ext-pageassessment-assessmentdata';

	public function __construct(
		private readonly PageAssessmentsStore $store,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Config $config,
	) {
	}

	/**
	 * Register the parser function hook
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'assessment', $this->cacheAssessment( ... ) );
	}

	/**
	 * Function called on parser init
	 * @param Parser $parser Parser object
	 * @param string $project Wikiproject name
	 * @param string $class Class of article
	 * @param string $importance Importance of article
	 */
	private function cacheAssessment(
		Parser $parser,
		string $project = '',
		string $class = '',
		string $importance = ''
	): void {
		self::storeAssessmentDataInParserOutput(
			$parser->getOutput(), $project, $class, $importance
		);
	}

	public static function storeAssessmentDataInParserOutput(
		ParserOutput $parserOutput,
		string $project, string $class, string $importance,
	) {
		// Keep the page assessment data as three unioned sets, so that it
		// is compatible with Parsoid Selective Update.  We will reconstruct
		// this into an array indexed by $project before emitting it in the
		// JS vars
		$parserOutput->appendExtensionData(
			self::EXT_DATA_KEY . "|projects", $project
		);
		$parserOutput->appendExtensionData(
			self::EXT_DATA_KEY . "|class|{$project}", $class
		);
		$parserOutput->appendExtensionData(
			self::EXT_DATA_KEY . "|importance|{$project}|{$class}", $importance
		);
	}

	public static function extractAssessmentDataFromParserOutput(
		ParserOutput $parserOutput
	): array {
		$assessmentData =
			// check the bare key name for backward compatibility (MW < 1.47)
			$parserOutput->getExtensionData( self::EXT_DATA_KEY ) ?? [];
		$projects = $parserOutput->getExtensionData(
			self::EXT_DATA_KEY . "|projects"
		) ?? [];
		foreach ( $projects as $project => $unused1 ) {
			$classes = $parserOutput->getExtensionData(
				self::EXT_DATA_KEY . "|class|{$project}"
			) ?? [];
			foreach ( $classes as $class => $unused2 ) {
				$importances = $parserOutput->getExtensionData(
					self::EXT_DATA_KEY . "|importance|{$project}|{$class}"
				) ?? [];
				foreach ( $importances as $importance => $unused3 ) {
					if ( isset( $assessmentData[$project] ) ) {
						// There's already an assessment for this project
						// on the page.  We could keep all of them, or
						// flag an error, or choose one deterministically.
						// We'll chose the lexicographically "first"
						$prev = $assessmentData[$project]['class'] . '|' .
							  $assessmentData[$project]['importance'];
						$curr = "{$class}|{$importance}";
						if ( $prev <= $curr ) {
							continue;
						}
					}
					$assessmentData[$project] = [
						'class' => $class,
						'importance' => $importance,
					];
				}
			}
		}
		return $assessmentData;
	}

	/**
	 * If we are on the subject page and assessments are on talk,
	 * duplicate the assessment data in the subject page's parser cache.
	 * This is later fetched by OutputPageHooks::onOutputPageParserOutput().
	 *
	 * @param Parser $parser
	 * @param string &$text
	 * @param StripState $stripState
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ): void {
		// Skip for parses of messages (T374761#12134375).
		if ( $parser->getOptions()?->isMessage() ) {
			return;
		}

		$title = Title::newFromPageReference( $parser->getPage() );
		if (
			$title->canHaveTalkPage() &&
			!$title->isTalkPage() &&
			$this->config->get( 'PageAssessmentsOnTalkPages' )
		) {
			$assessmentData = $this->store->getAllAssessments( $title->getArticleID() );
			foreach ( $assessmentData as $project => [
				'class' => $class, 'importance' => $importance
			] ) {
				self::storeAssessmentDataInParserOutput(
					$parser->getOutput(), $project, $class, $importance
				);
			}
		}
	}

	/**
	 * Update assessment records after talk page is saved
	 *
	 * @param Title $title
	 * @param RenderedRevision $renderedRevision
	 * @param DeferrableUpdate[] &$updates
	 */
	public function onRevisionDataUpdates( $title, $renderedRevision, &$updates ) {
		$isTalkPage = $title->isTalkPage();
		$assessmentsOnTalkPages = $this->config->get( 'PageAssessmentsOnTalkPages' );

		// Only check for assessment data where assessments are actually made.
		if ( ( $assessmentsOnTalkPages && $isTalkPage ) ||
			( !$assessmentsOnTalkPages && !$isTalkPage )
		) {
			$parserOutput = $renderedRevision->getRevisionParserOutput();
			$assessmentData = self::extractAssessmentDataFromParserOutput(
				$parserOutput
			);
			// Even if there is no assessment data (it's []), we still
			// need to run doUpdates in case any assessment data was
			// deleted from the page.

			// Assessment data should only be associated with subject pages regardless
			// of whether it is recorded on talk pages or subject pages.
			if ( $isTalkPage ) {
				$title = Title::newFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
			}

			$changed = $this->store->doUpdates( $title, $assessmentData );

			// Refresh cache of subject page if applicable, so that $wgPageAssessments stays up to date.
			if ( $changed && $isTalkPage ) {
				$this->wikiPageFactory->newFromTitle( $title )->updateParserCache();
			}
		}
	}
}
