<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\PageAssessments;

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
		$excludedNsIds = array_values( array_filter( $nsIds, static function ( $ns ) {
			return MWNamespace::isTalk( $ns );
		} ) );
		$widget = new NamespaceInputWidget( [
			'value' => $value,
			'name' => $this->mName,
			'id' => $this->mID,
			'exclude' => $excludedNsIds,
		] );
		return $widget;
	}

}
