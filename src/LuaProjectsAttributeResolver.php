<?php

namespace MediaWiki\Extension\PageAssessments;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\TitleAttributeResolver;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;

class LuaProjectsAttributeResolver extends TitleAttributeResolver {

	private bool $assessmentsOnTalkPage;

	public function __construct( Config $config ) {
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

		$projects = PageAssessmentsDAO::getAllAssessments( $title->getId() );
		// Sort to ensure output is stable
		sort( $projects );
		return self::makeArrayOneBased( $projects );
	}

	/**
	 * Renumber an array for return to Lua. Duplicates Scribunto TitleLibrary::makeArrayOneBased.
	 * TODO: unify them.
	 *
	 * @param array $arr
	 * @return array
	 */
	private static function makeArrayOneBased( array $arr ) {
		if ( !$arr ) {
			return $arr;
		}
		return array_combine( range( 1, count( $arr ) ), array_values( $arr ) );
	}
}
