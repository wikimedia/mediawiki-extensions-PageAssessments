<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\StripState;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class ParserHooks implements ParserAfterParseHook, ParserFirstCallInitHook, RevisionDataUpdatesHook {

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
		AssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parser->getOutput(), $project, $class, $importance
		);
	}

	/**
	 * @param Parser $parser
	 * @param string &$text
	 * @param StripState $stripState
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ): void {
		$parserOptions = $parser->getOptions();

		if ( $parserOptions && (
			// Skip for parses of messages (T374761#12134375).
			$parserOptions->isMessage() ||
			// T435143: Don't run this for Parsoid because this hook
			// is called for nested pipelines which is not what we want.
			// For Parsoid, ParsoidAsssessmentsProcessor runs as a global
			// pass on the final DOM to compute page assessments.
			$parserOptions->getUseParsoid()
		) ) {
			return;
		}

		$title = Title::newFromPageReference( $parser->getPage() );
		AssessmentsProcessor::copyPageAssessmentsToParserOutput(
			$title, $parser->getOutput(), $this->config, $this->store
		);
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
			$assessmentData = AssessmentsProcessor::extractAssessmentDataFromParserOutput(
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
