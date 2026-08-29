<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\Title\Title;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMProcessor as ParsoidExtDOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class ParsoidAssessmentsProcessor extends ParsoidExtDOMProcessor {
	public function __construct(
		private readonly PageAssessmentsStore $store,
		private readonly Config $config
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi, Node $node, array $opts
	): void {
		$title = Title::newFromLinkTarget( $extApi->getPageConfig()->getLinkTarget() );
		AssessmentsProcessor::copyPageAssessmentsToParserOutput(
			$title, $extApi->getMetadata(), $this->config, $this->store
		);
	}
}
