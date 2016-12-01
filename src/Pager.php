<?php
namespace PageAssessments;

use LikeMatch;
use MediaWiki\MediaWikiServices;
use TablePager;
use Title;

class Pager extends TablePager {

	/** @var boolean Should field sorting be enabled? */
	protected $sortable;

	/**
	 * All parameters for the main paged query.
	 * @return string[]
	 */
	public function getQueryInfo() {
		$info = [
			'tables' => [ 'page_assessments', 'page_assessments_projects', 'page', 'revision' ],
			'fields' => [
				'project' => 'pap_project_title',
				'class' => 'pa_class',
				'importance' => 'pa_importance',
				'timestamp' => 'rev_timestamp',
				'page_title' => 'page_title',
				'page_revision' => 'pa_page_revision',
				'page_namespace' => 'page_namespace',
			],
			'conds' => [],
			'options' => [],
			'join_conds' => [
				'page_assessments_projects' => [ 'JOIN', 'pa_project_id = pap_project_id' ],
				'page' => [ 'JOIN', 'pa_page_id = page_id' ],
				'revision' => [ 'JOIN', 'page_id = rev_page AND pa_page_revision = rev_id' ],
			],
		];
		// Project.
		$project = $this->getRequest()->getVal( 'project', false );
		if ( !empty( $project ) ) {
			$info['conds']['pap_project_title'] = $project;
		}
		// Namespace (if its set, it's either an integer >= 0, 'all', or the empty string).
		$namespace = $this->getRequest()->getVal( 'namespace', false );
		if ( $namespace !== 'all' && $namespace !== '' ) {
			$info['conds']['page_namespace'] = $namespace;
		}
		$pageTitle = $this->getRequest()->getVal( 'page_title', false );
		if ( !empty( $pageTitle ) ) {
			$title = Title::newFromText( $pageTitle )->getDBkey();
			$info['conds']['page_title'] = $title;
		}
		return $info;
	}

	/**
	 * Should the table be sortable? It's not when transcluded.
	 * @param boolean $sortable Whether to sort or not.
	 */
	public function setSortable( $sortable ) {
		$this->sortable = (bool)$sortable;
	}

	/**
	 * Return true if the named field should be sortable by the UI, false otherwise.
	 * @param string $field The field in question; matches one returned by self::getFieldNames().
	 * @return boolean
	 */
	public function isFieldSortable( $field ) {
		// Done enable sorting for transcluded pagers, because the sorting links will not be to
		// the current page.
		if ( $this->sortable === false ) {
			// Strict check, to avoid false negative when this method is used in parent::__construct
			return false;
		}
		$sortable = [
			'project',
			'page',
			'timestamp',
		];
		return in_array( $field, $sortable );
	}

	/**
	 * Format a table cell. The return value should be HTML, but use an empty
	 * string not &#160; for empty cells. Do not include the <td> and </td>.
	 *
	 * The current result row is available as $this->mCurrentRow, in case you
	 * need more context.
	 *
	 * @param string $name The database field name
	 * @param string $value The value retrieved from the database
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$renderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$pageTitle = Title::newFromText(
			$this->mCurrentRow->page_title,
			$this->mCurrentRow->page_namespace
		);

		// Page title.
		if ( $name === 'page' ) {
			return $renderer->makeKnownLink( $pageTitle );
		}

		// Timestamp of assessed revision.
		if ( $name === 'timestamp' ) {
			$lang = $this->getLanguage();
			$ts = $lang->userTimeAndDate( $this->mCurrentRow->timestamp, $this->getUser() );
			$linkQuery = [ 'oldid' => $this->mCurrentRow->page_revision ];
			return $renderer->makeKnownLink( $pageTitle, $ts, [], $linkQuery );
		}

		// All field names from self::getFieldNames() have been taken care of above,
		// so this shouldn't be used.
		return $value;
	}

	/**
	 * An array mapping database field names to a textual description of the
	 * field name, for use in the table header. The description should be plain
	 * text, it will be HTML-escaped later.
	 *
	 * @return array
	 */
	public function getFieldNames() {
		return [
			'project' => wfMessage( 'pageassessments-project' )->text(),
			'page' => wfMessage( 'pageassessments-page-title' )->text(),
			'importance' => wfMessage( 'pageassessments-importance' )->text(),
			'class' => wfMessage( 'pageassessments-class' )->text(),
			'timestamp' => wfMessage( 'pageassessments-timestamp' )->text(),
		];
	}

	/**
	 * The database field name used as a default sort order.
	 *
	 * @protected
	 *
	 * @return string
	 */
	public function getDefaultSort() {
		return 'project';
	}

}
