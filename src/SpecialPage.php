<?php

namespace PageAssessments;

use Html;
use HTMLForm;
use HTMLTextField;
use IDatabase;
use OutputPage;
use QueryPage;
use ResultWrapper;
use Skin;
use Status;
use Title;

/**
 * A special page for searching Page Assessments. Can also be transcluded (in which case the
 * search results' sorting links will be disabled).
 */
class SpecialPage extends QueryPage {

	/**
	 * Create this special page, giving it a name and making it transcludable.
	 */
	public function __construct() {
		parent::__construct();
		$this->mName = 'PageAssessments';
		$this->mIncludable = true;
	}

	/**
	 * List the page under "Page tools" at Special:SpecialPages
	 * @return string
	 */
	protected function getGroupName() {
		return 'pagetools';
	}

	/**
	 * Returns the name that goes in the \<h1\> in the special page itself, and
	 * also the name that will be listed in Special:Specialpages.
	 *
	 * Overridden here because we want proper sentence casing, rather than 'PageAssessments'.
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'pageassessments-special' )->text();
	}

	/**
	 * The information for the database query. Don't include an ORDER or LIMIT clause, they will
	 * be added.
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
		$project = $this->getRequest()->getVal( 'project' );
		if ( strlen( $project ) > 0 ) {
			$info['conds']['pap_project_title'] = $project;
		}
		// Page title.
		$pageTitle = $this->getRequest()->getVal( 'page_title' );
		if ( strlen( $pageTitle ) > 0 ) {
			$title = Title::newFromText( $pageTitle )->getDBkey();
			$info['conds']['page_title'] = $title;
		}
		// Namespace (if it's set, it's either an integer >= 0, 'all', or the empty string).
		$namespace = $this->getRequest()->getVal( 'namespace' );
		if ( strlen( $namespace ) > 0 && $namespace !== 'all' ) {
			$info['conds']['page_namespace'] = $namespace;
		}
		return $info;
	}

	/**
	 * Format and output report results.
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param IDatabase $dbr Database (read) connection to use
	 * @param ResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 * @return bool False if no results are displayed, true otherwise.
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		// Don't display anything if there are no results.
		if ( $num < 1 ) {
			return false;
		}
		$out->addModuleStyles( 'mediawiki.pager.tablePager' );
		$tableClasses = 'mw-datatable page-assessments TablePager';
		$html = Html::openElement( 'table', [ 'class' => $tableClasses ] )
			. Html::openElement( 'thead' )
			. Html::openElement( 'tr' )
			. $this->getTableHeader( 'project', 'project' )
			. $this->getTableHeader( 'page_title', 'page-title' )
			. Html::rawElement( 'th', [], wfMessage( 'pageassessments-importance' )->text() )
			. Html::rawElement( 'th', [], wfMessage( 'pageassessments-class' )->text() )
			. $this->getTableHeader( 'timestamp', 'timestamp' )
			. Html::closeElement( 'tr' )
			. Html::closeElement( 'thead' )
			. Html::openElement( 'tbody' );
		for ( $i = 0; $i < $num; $i++ ) {
			$row = $res->fetchObject();
			if ( $row ) {
				$html .= $this->formatResult( $skin, $row );
			} else {
				break;
			}
		}
		$html .= HTML::closeElement( 'tbody' )
			. HTML::closeElement( 'table' );
		$out->addHTML( $html );
		return true;
	}

	/**
	 * Get a HTML TH element, with a link to sort the column.
	 * @param string $field The field that this column contains.
	 * @param string $messageKey The i18n message (suffix) for the column header.
	 * @return string HTML table header.
	 */
	protected function getTableHeader( $field, $messageKey ) {
		$text = wfMessage( 'pageassessments-' . $messageKey )->text();

		// If this special page is being included, don't enable header sorting.
		if ( $this->including() ) {
			return Html::rawElement( 'th', [], $text );
		}

		// Otherwise, set up the correct link.
		$query = $this->getRequest()->getValues();
		$cellAttrs = [];
		if ( $this->getOrderFields() == [ $field ] ) {
			// Currently sorted by this field.
			$query['dir'] = $this->sortDescending() ? 'asc' : 'desc';
			$currentDir = $this->sortDescending() ? 'descending' : 'ascending';
			$cellAttrs['class'] = 'TablePager_sort-' . $currentDir;
		} else {
			$query['dir'] = 'asc';
			$query['sort'] = $field;
		}
		$link = $this->getLinkRenderer()->makeLink( $this->getPageTitle(), $text, [], $query );
		return Html::rawElement( 'th', $cellAttrs, $link );
	}

	/**
	 * Add the query and sort parameters to the paging links (prev/next/lengths).
	 * @return string[]
	 */
	public function linkParameters() {
		$params = [];
		foreach ( [ 'project', 'namespace', 'page_title', 'sort', 'dir' ] as $key ) {
			$val = $this->getRequest()->getVal( $key );
			if ( $val ) {
				$params[$key] = $val;
			}
		}
		return $params;
	}

	/**
	 * Formats the results of the query for display. The skin is the current
	 * skin; you can use it for making links. The result is a single row of
	 * result data. You should be able to grab SQL results off of it.
	 * If the function returns false, the line output will be skipped.
	 * @param Skin $skin The current skin
	 * @param object $result Result row
	 * @return string|bool String or false to skip
	 */
	public function formatResult( $skin, $result ) {
		$renderer = $this->getLinkRenderer();
		$pageTitle = Title::newFromText(
			$result->page_title,
			$result->page_namespace
		);

		// Link to the page.
		$pageLink = $renderer->makeKnownLink( $pageTitle );

		// Timestamp of assessed revision.
		$lang = $this->getLanguage();
		$ts = $lang->userTimeAndDate( $result->timestamp, $this->getUser() );
		$linkQuery = [ 'oldid' => $result->page_revision ];
		$timestampLink = $renderer->makeKnownLink( $pageTitle, $ts, [], $linkQuery );

		// HTML table row.
		return Html::rawElement( 'tr', [],
			Html::rawElement( 'td', [], $result->project ).
			Html::rawElement( 'td', [], $pageLink ).
			Html::rawElement( 'td', [], $result->importance ).
			Html::rawElement( 'td', [], $result->class ).
			Html::rawElement( 'td', [], $timestampLink )
		);
	}

	/**
	 * Output the special page.
	 * @param string $parameters The parameters to the special page.
	 */
	public function execute( $parameters ) {
		// Set up.
		$out = $this->getOutput();
		$out->setPageTitle( $this->getDescription() );
		$this->addHelpLink( 'Extension:PageAssessments' );

		// Output form.
		if ( !$this->including() ) {
			$form = $this->getForm();
			$form->show();
		}

		// Leave the output of the search results to QueryPage.
		parent::execute( $parameters );
	}

	/**
	 * Get the fields that the results are currently ordered by.
	 * @return string[]
	 */
	function getOrderFields() {
		$permitted = [ 'project', 'page_title', 'timestamp' ];
		$requested = $this->getRequest()->getVal( 'sort' );
		if ( in_array( $requested, $permitted ) ) {
			return [ $requested ];
		}
		return [];
	}

	/**
	 * Whether we're currently sorting descending, or ascending. Based on the request 'dir'
	 * value; anything starting with 'desc' is considered 'desecending'.
	 * @return bool
	 */
	function sortDescending() {
		return stripos( $this->getRequest()->getVal( 'dir' ), 'desc' ) === 0;
	}

	/**
	 * Get the search form. This also loads the required Javascript module and global JS variable.
	 * @return HTMLForm
	 */
	protected function getForm() {
		$this->getOutput()->addModules( 'ext.pageassessments.special' );

		// Add a list of all projects to the page's JS.
		$projects = wfGetDB( DB_REPLICA )->selectFieldValues(
			[ 'page_assessments_projects' ],
			'pap_project_title',
			'',
			__METHOD__,
			[ 'ORDER BY' => 'pap_project_title' ]
		);
		$this->getOutput()->addJsConfigVars( 'wgPageAssessmentProjects', $projects );

		// Define the form fields.
		$formDescriptor = [
			'project' => [
				'id' => 'pageassessments-project',
				'class' => HTMLTextField::class,
				'name' => 'project',
				'label-message' => 'pageassessments-project',
			],
			'namespace' => [
				'id' => 'pageassessments-namespace',
				'class' => NamespaceSelect::class,
				'name' => 'namespace',
				'label-message' => 'pageassessments-page-namespace',
			],
			'page_title' => [
				'id' => 'pageassessments-page-title',
				'class' => HTMLTextField::class,
				'name' => 'page_title',
				'label-message' => 'pageassessments-page-title',
			],
		];

		// Construct and return the form.
		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setMethod( 'get' );
		$form->setSubmitTextMsg( 'pageassessments-search' );
		$form->setSubmitCallback( function ( array $data, HTMLForm $form ) {
			// Filtering only by namespace can be slow, disallow it:
			// https://phabricator.wikimedia.org/T168599
			if ( $data['namespace'] !== null
				&& $data['namespace'] !== 'all'
				// strlen( null ) produces 0
				&& !strlen( $data['project'] )
				&& !strlen( $data['page_title'] )
			) {
				return Status::newFatal( 'pageassessments-error-namespace-filter' );
			}
		} );
		return $form;
	}
}
