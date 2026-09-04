<?php
declare( strict_types = 1 );

use CirrusSearch\WeightedTagsUpdater;
use MediaWiki\Extension\PageAssessments\HookHandler\AssessmentsProcessor;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'PageAssessments.AssessmentsProcessor' => static function (
		MediaWikiServices $services
	): AssessmentsProcessor {
		return new AssessmentsProcessor(
			$services->getMainConfig(),
			$services->get( 'PageAssessments.Store' )
		);
	},
	'PageAssessments.Store' => static function (
		MediaWikiServices $services
	): PageAssessmentsStore {
		return new PageAssessmentsStore(
			$services->getConnectionProvider(),
			$services->getExtensionRegistry(),
			$services->getMainConfig(),
			class_exists( WeightedTagsUpdater::class ) ?
				$services->get( WeightedTagsUpdater::SERVICE ) :
				null
		);
	},
];
