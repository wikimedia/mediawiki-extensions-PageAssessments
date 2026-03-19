<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments\HookHandler;

use CirrusSearch\Hooks\CirrusSearchAddQueryFeaturesHook;
use CirrusSearch\SearchConfig;
use MediaWiki\Extension\PageAssessments\CirrusSearchInProjectFeature;

class CirrusSearchHooks implements CirrusSearchAddQueryFeaturesHook {

	/** @inheritDoc */
	public function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ): void {
		$extraFeatures[] = new CirrusSearchInProjectFeature();
	}
}
