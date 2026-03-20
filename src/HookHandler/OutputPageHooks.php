<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

readonly class OutputPageHooks implements OutputPageParserOutputHook {

	public const string JS_CONFIG_VAR = 'wgPageAssessments';

	private bool $assessmentsOnTalkPages;

	public function __construct(
		private NamespaceInfo $namespaceInfo,
		Config $config,
	) {
		$this->assessmentsOnTalkPages = $config->get( 'PageAssessmentsOnTalkPages' );
	}

	/**
	 * Sets the self::JS_CONFIG_VAR when assessments exist either on the
	 * current page, or its talk or subject page counterpart.
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $paTitle = $outputPage->getTitle();
		// If the title is non-special, and assessments are stored on talk pages (such as on enwiki),
		// then check the talk page for assessments instead of the subject page.
		if ( $title->getNamespace() >= 0 &&
			$this->assessmentsOnTalkPages &&
			!$title->isTalkPage()
		) {
			$paTitle = Title::newFromLinkTarget(
				$this->namespaceInfo->getTalkPage( $title )
			);
		}
		// Return early if the title containing assessments doesn't exist or isn't wikitext.
		if ( !$paTitle->exists() || $paTitle->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$extData = $parserOutput->getExtensionData( ParserHooks::EXT_DATA_KEY );
		if ( $extData ) {
			$outputPage->addJsConfigVars( self::JS_CONFIG_VAR, $extData );
		}
	}
}
