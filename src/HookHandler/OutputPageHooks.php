<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Extension\PageAssessments\PageAssessmentsProcessor;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;

readonly class OutputPageHooks implements OutputPageParserOutputHook {

	public const string JS_CONFIG_VAR = 'wgPageAssessments';

	public function __construct(
		private PageAssessmentsProcessor $assessmentsProcessor,
	) {
	}

	/**
	 * Set self::JS_CONFIG_VAR to any assessments data found in the ParserOutput
	 *
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$assessmentData = $this->assessmentsProcessor->extractAssessmentDataFromParserOutput(
			$parserOutput
		);
		if ( $assessmentData !== [] ) {
			$outputPage->addJsConfigVars( self::JS_CONFIG_VAR, $assessmentData );
		}
	}
}
