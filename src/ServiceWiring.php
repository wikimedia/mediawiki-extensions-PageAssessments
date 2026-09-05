<?php
declare( strict_types = 1 );

use CirrusSearch\WeightedTagsUpdater;
use MediaWiki\Extension\PageAssessments\PageAssessmentsProcessor;
use MediaWiki\Extension\PageAssessments\PageAssessmentsStore;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'PageAssessments.AssessmentsProcessor' => static function (
		MediaWikiServices $services
	): PageAssessmentsProcessor {
		return new PageAssessmentsProcessor(
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
