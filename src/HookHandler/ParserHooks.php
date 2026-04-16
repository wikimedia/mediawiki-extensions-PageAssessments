<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Deferred\Hook\LinksUpdateCompleteHook;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class ParserHooks implements ParserFirstCallInitHook, LinksUpdateCompleteHook {

	public const EXT_DATA_KEY = 'ext-pageassessment-assessmentdata';

	public function __construct(
		private readonly PageAssessmentsStore $store,
		private readonly NamespaceInfo $namespaceInfo,
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
		$values = [ $project, $class, $importance ];
		if ( $parserData == null ) {
			$parserData = [];
		}
		$parserData[] = $values;
		$parser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $parserData );
	}

	/**
	 * Update assessment records after talk page is saved
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ): void {
		$assessmentsOnTalkPages = $this->config->get( 'PageAssessmentsOnTalkPages' );
		$title = $linksUpdate->getTitle();
		// Only check for assessment data where assessments are actually made.
		if ( ( $assessmentsOnTalkPages && $title->isTalkPage() ) ||
			( !$assessmentsOnTalkPages && !$title->isTalkPage() )
		) {
			$pOut = $linksUpdate->getParserOutput();
			if ( $pOut->getExtensionData( self::EXT_DATA_KEY ) !== null ) {
				$assessmentData = $pOut->getExtensionData( self::EXT_DATA_KEY );
			} else {
				// Even if there is no assessment data, we still need to run doUpdates
				// in case any assessment data was deleted from the page.
				$assessmentData = [];
			}
			// Assessment data should only be associated with subject pages regardless
			// of whether it is recorded on talk pages or subject pages.
			if ( $title->isTalkPage() ) {
				$title = Title::newFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
			}
			$this->store->doUpdates( $title, $assessmentData, $ticket );
		}
	}
}
