<?php

namespace MediaWiki\Extension\PageAssessments;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\TitleAttributeResolver;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;

class LuaProjectsAttributeResolver extends TitleAttributeResolver {

	private bool $assessmentsOnTalkPage;

	public function __construct(
		private readonly PageAssessmentsStore $store,
		readonly Config $config
	) {
		$this->assessmentsOnTalkPage = $config->get( 'PageAssessmentsOnTalkPages' );
	}

	/**
	 * @param LinkTarget $target
	 * @return array
	 */
	public function resolve( LinkTarget $target ) {
		$title = Title::newFromLinkTarget( $target );
		$talkTitle = $title->getTalkPageIfDefined();

		$assessmentPageTitle = $this->assessmentsOnTalkPage ? $talkTitle : $title;

		if ( !$assessmentPageTitle || !$assessmentPageTitle->canExist() ) {
			return [];
		}
		$this->addTemplateLink( $assessmentPageTitle );
		$this->incrementExpensiveFunctionCount();

		return $this->store->getAllAssessments( $title->getId() );
	}
}
