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
	 * @fixme Not Parsoid-compatible due to re-setting extension data, should use appendExtensionData instead.
	 */
	private function cacheAssessment(
		Parser $parser,
		string $project = '',
		string $class = '',
		string $importance = ''
	): void {
		$parserData = $parser->getOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( $parserData == null ) {
			$parserData = [];
		}

		// This gets used in the $wgPageAssessments JS var. Key by project to make it easier to
		// see if a specific project is present, and match the format of ApiQueryPageAssessments.
		$parserData[ $project ] = [
			'class' => $class,
			'importance' => $importance
		];
		$parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $parserData );
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
		$title = Title::newFromPageReference( $parser->getPage() );
		if ( !$title->isTalkPage() && $this->config->get( 'PageAssessmentsOnTalkPages' ) ) {
			$assessmentData = $this->store->getAllAssessments( $title->getArticleID() );
			$parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $assessmentData );
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
			if ( $parserOutput->getExtensionData( self::EXT_DATA_KEY ) !== null ) {
				$assessmentData = $parserOutput->getExtensionData( self::EXT_DATA_KEY );
			} else {
				// Even if there is no assessment data, we still need to run doUpdates
				// in case any assessment data was deleted from the page.
				$assessmentData = [];
			}
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
