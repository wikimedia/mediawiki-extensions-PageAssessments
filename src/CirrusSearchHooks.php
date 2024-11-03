<?php

namespace MediaWiki\Extension\PageAssessments;

use CirrusSearch\Hooks\CirrusSearchAddQueryFeaturesHook;
use CirrusSearch\SearchConfig;

class CirrusSearchHooks implements CirrusSearchAddQueryFeaturesHook {

	/** @inheritDoc */
	public function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ): void {
		$extraFeatures[] = new CirrusSearchInProjectFeature();
	}
}
