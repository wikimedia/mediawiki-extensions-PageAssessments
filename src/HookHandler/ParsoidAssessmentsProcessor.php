<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Extension\PageAssessments\PageAssessmentsProcessor;
use MediaWiki\Title\Title;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMProcessor as ParsoidExtDOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class ParsoidAssessmentsProcessor extends ParsoidExtDOMProcessor {
	public function __construct(
		private readonly PageAssessmentsProcessor $assessmentsProcessor,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi, Node $node, array $opts
	): void {
		$title = Title::newFromLinkTarget( $extApi->getPageConfig()->getLinkTarget() );
		$this->assessmentsProcessor->copyPageAssessmentsToParserOutput(
			$title, $extApi->getMetadata()
		);
	}
}
