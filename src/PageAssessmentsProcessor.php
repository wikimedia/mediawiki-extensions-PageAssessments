<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments;

use MediaWiki\Config\Config;
use MediaWiki\Language\ILanguageConverter;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;

readonly class PageAssessmentsProcessor {
	public const string EXT_DATA_KEY = 'ext-pageassessment-assessmentdata';

	public function __construct(
		private Config $config,
		private PageAssessmentsStore $store,
		private ILanguageConverter $languageConverter,
	) {
	}

	/**
	 * If we are on the subject page and assessments are on talk,
	 * duplicate the assessment data in the subject page's parser cache.
	 * This is later fetched by OutputPageHooks::onOutputPageParserOutput().
	 */
	public function copyPageAssessmentsToParserOutput(
		Title $title, ContentMetadataCollector $parserOutput
	): void {
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
					$parserOutput, $project, $class, $importance
				);
			}
		}
	}

	public static function storeAssessmentDataInParserOutput(
		ContentMetadataCollector $parserOutput,
		string $project, string $class, string $importance,
	): void {
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

	/**
	 * Normalize a WikiProject name by resolving it to the canonical
	 * spelling of its LanguageConverter variant, if any. This is the
	 * same logic that is used for category variants in CategoryLinksTable
	 * in core.
	 */
	public function normalizeProjectName( string $project ): string {
		$projectNs = $this->config->get( 'PageAssessmentsNamespace' );
		if ( $projectNs < 0 ) {
			return $project;
		}
		$projectTitle = Title::newFromText( $project, $projectNs );
		if ( $projectTitle === null ) {
			return $project;
		}
		// This method passes $project and $projectTitle by reference
		$this->languageConverter->findVariantLink( $project, $projectTitle, true );
		return $projectTitle->getText();
	}

	public function extractAssessmentDataFromParserOutput(
		ParserOutput $parserOutput
	): array {
		$assessmentData =
			// check the bare key name for backward compatibility (MW < 1.47)
			$parserOutput->getExtensionData( self::EXT_DATA_KEY ) ?? [];
		$projects = $parserOutput->getExtensionData(
			self::EXT_DATA_KEY . "|projects"
		) ?? [];
		foreach ( $projects as $project => $unused1 ) {
			$projectKey = $this->normalizeProjectName( $project );
			$classes = $parserOutput->getExtensionData(
				self::EXT_DATA_KEY . "|class|{$project}"
			) ?? [];
			foreach ( $classes as $class => $unused2 ) {
				$importances = $parserOutput->getExtensionData(
					self::EXT_DATA_KEY . "|importance|{$project}|{$class}"
				) ?? [];
				foreach ( $importances as $importance => $unused3 ) {
					if ( isset( $assessmentData[$projectKey] ) ) {
						// There's already an assessment for this project
						// on the page.  We could keep all of them, or
						// flag an error, or choose one deterministically.
						// We'll chose the lexicographically "first"
						$prev = $assessmentData[$projectKey]['class'] . '|' .
							  $assessmentData[$projectKey]['importance'];
						$curr = "{$class}|{$importance}";
						if ( $prev <= $curr ) {
							continue;
						}
					}
					$assessmentData[$projectKey] = [
						'class' => $class,
						'importance' => $importance,
					];
				}
			}
		}
		return $assessmentData;
	}
}
