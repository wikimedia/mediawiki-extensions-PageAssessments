<?php

namespace PageAssessments;

use HTMLSelectNamespace;
use MediaWiki\Widget\NamespaceInputWidget;
use MWNamespace;

/**
 * This is an HTML form field for selecting non-talk namespaces. It excludes all namespaces with
 * an even-numbered ID.
 *
 * It only overrides the OOUI widget because that's all that the PageAssessments special page needs.
 */
class NamespaceSelect extends HTMLSelectNamespace {

	/**
	 * Get the widget for selecting one or all non-talkspace namespace(s).
	 * @param string $value The currently selected value.
	 * @return NamespaceInputWidget
	 */
	public function getInputOOUI( $value ) {
		$nsIds = array_keys( MWNamespace::getCanonicalNamespaces() );
		$excludedNsIds = array_filter( $nsIds, function( $ns ) {
			return MWNamespace::isTalk( $ns );
		} );
		$widget = new NamespaceInputWidget( [
			'value' => $value,
			'name' => $this->mName,
			'id' => $this->mID,
			'includeAllValue' => $this->mAllValue,
			'exclude' => $excludedNsIds,
		] );
		return $widget;
	}

}
