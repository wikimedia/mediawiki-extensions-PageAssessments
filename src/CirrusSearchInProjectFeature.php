<?php

namespace MediaWiki\Extension\PageAssessments;

use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use CirrusSearch\Wikimedia\WeightedTagsHooks;
use Elastica\Query\DisMax;
use Elastica\Query\Term;

class CirrusSearchInProjectFeature extends SimpleKeywordFeature {
	public const MAX_CONDITIONS = 32;
	public const TAG_PREFIX = 'ext.pageassessments.project';

	/** @inheritDoc */
	protected function getKeywords() {
		return [ 'inproject' ];
	}

	/** @inheritDoc */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix,
		WarningCollector $warningCollector
	) {
		$values = explode( '|', $value, self::MAX_CONDITIONS + 1 );
		if ( count( $values ) > self::MAX_CONDITIONS ) {
			$warningCollector->addWarning(
				'cirrussearch-feature-too-many-conditions',
				$key,
				self::MAX_CONDITIONS
			);
			$values = array_slice(
				$values,
				0,
				self::MAX_CONDITIONS
			);
		}
		return [ 'projects' => $values ];
	}

	/** @inheritDoc */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsed = $this->parseValue( $key, $value, $quotedValue, '', '', $context );
		'@phan-var array $parsed';
		$projects = $parsed['projects'];
		if ( $projects === [] ) {
			$context->setResultsPossible( false );
			return [ null, true ];
		}
		$query = new DisMax();
		foreach ( $projects as $project ) {
			$projectQuery = new Term();
			$projectQuery->setTerm( WeightedTagsHooks::FIELD_NAME, self::TAG_PREFIX . '/' . $project );
			$query->addQuery( $projectQuery );
		}

		if ( !$negated ) {
			$context->addNonTextQuery( $query );
			return [ null, false ];
		} else {
			return [ $query, false ];
		}
	}
}
