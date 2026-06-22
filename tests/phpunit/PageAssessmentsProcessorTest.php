<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\Tests;

use MediaWiki\Extension\PageAssessments\PageAssessmentsProcessor;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\ParserOutput;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageAssessments\PageAssessmentsProcessor
 * @group Database
 * @group PageAssessments
 */
class PageAssessmentsProcessorTest extends MediaWikiIntegrationTestCase {

	private const NS_WIKIPROJECT = 102;

	protected function setUp(): void {
		parent::setUp();
		// T328012: on zhwiki, WikiProject pages can be tagged using either
		// Traditional or Simplified Chinese project names, and these
		// should be treated as the same project.
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'zh',
			MainConfigNames::ExtraNamespaces => [
				self::NS_WIKIPROJECT => 'WikiProject',
				self::NS_WIKIPROJECT + 1 => 'WikiProject_talk',
			],
			'PageAssessmentsNamespace' => self::NS_WIKIPROJECT,
		] );
	}

	private function getProcessor(): PageAssessmentsProcessor {
		return $this->getServiceContainer()->get( 'PageAssessments.AssessmentsProcessor' );
	}

	public function testExtractAssessmentDataNormalizesLanguageVariant(): void {
		// Only the Simplified Chinese project page exists.
		$this->insertPage( '铁道', 'WikiProject Railways', self::NS_WIKIPROJECT );

		$parserOutput = new ParserOutput();
		PageAssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parserOutput, '鐵道', 'B', 'Low'
		);

		$assessmentData = $this->getProcessor()->extractAssessmentDataFromParserOutput( $parserOutput );

		$this->assertArrayHasKey( '铁道', $assessmentData );
		$this->assertArrayNotHasKey( '鐵道', $assessmentData );
		$this->assertSame(
			[ 'class' => 'B', 'importance' => 'Low' ],
			$assessmentData['铁道']
		);
	}

	public function testExtractAssessmentDataMergesLanguageVariantsOfSameProject(): void {
		// This is the motivating example from T328012: a page assessed
		// under both the Traditional and Simplified Chinese spelling of
		// the same WikiProject must be recognized as a single project,
		// not two separate ones.
		$this->insertPage( '铁道', 'WikiProject Railways', self::NS_WIKIPROJECT );

		$parserOutput = new ParserOutput();
		PageAssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parserOutput, '鐵道', 'B', 'Low'
		);
		PageAssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parserOutput, '铁道', 'C', 'Mid'
		);

		$assessmentData = $this->getProcessor()->extractAssessmentDataFromParserOutput( $parserOutput );

		$this->assertCount( 1, $assessmentData );
		$this->assertArrayHasKey( '铁道', $assessmentData );
		// Of the two conflicting assessments, the lexicographically
		// "first" one is kept (see PageAssessmentsProcessor's tie-break rule).
		$this->assertSame(
			[ 'class' => 'B', 'importance' => 'Low' ],
			$assessmentData['铁道']
		);
	}

	public function testExtractAssessmentDataLeavesProjectUnchangedWhenNoVariantPageExists(): void {
		// Neither the Traditional nor Simplified WikiProject page exists,
		// so there's nothing to normalize against.
		$parserOutput = new ParserOutput();
		PageAssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parserOutput, '鐵道', 'B', 'Low'
		);

		$assessmentData = $this->getProcessor()->extractAssessmentDataFromParserOutput( $parserOutput );

		$this->assertArrayHasKey( '鐵道', $assessmentData );
		$this->assertSame(
			[ 'class' => 'B', 'importance' => 'Low' ],
			$assessmentData['鐵道']
		);
	}

	public function testExtractAssessmentDataHandlesInvalidProjectTitle(): void {
		// A project name that cannot be resolved to a Title (e.g. a
		// fragment-only string) must be passed through unchanged instead
		// of being dropped or causing an error.
		$invalidProject = '#Fragment';

		$parserOutput = new ParserOutput();
		PageAssessmentsProcessor::storeAssessmentDataInParserOutput(
			$parserOutput, $invalidProject, 'B', 'Low'
		);

		$assessmentData = $this->getProcessor()->extractAssessmentDataFromParserOutput( $parserOutput );

		$this->assertArrayHasKey( $invalidProject, $assessmentData );
		$this->assertSame(
			[ 'class' => 'B', 'importance' => 'Low' ],
			$assessmentData[$invalidProject]
		);
	}
}
