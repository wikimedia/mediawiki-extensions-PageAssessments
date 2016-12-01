<?php

namespace PageAssessments;

use HTMLForm;
use HTMLTextField;
use SpecialPage as MediaWikiSpecialPage;

/**
 * A special page for searching Page Assessments. Can also be transcluded (in which case the
 * search results' sorting links will be disabled).
 */
class SpecialPage extends MediaWikiSpecialPage {

	/**
	 * Create this special page, giving it a name and making it transcludable.
	 */
	public function __construct() {
		parent::__construct();
		$this->mName = 'PageAssessments';
		$this->mIncludable = true;
	}

	/**
	 * Do not include this one in the list of special pages at Special:SpecialPages, at least until
	 * it's more functional that it currently is.
	 * @return bool False.
	 */
	public function isListed() {
		return false;
	}

	/**
	 * Returns the name that goes in the \<h1\> in the special page itself, and
	 * also the name that will be listed in Special:Specialpages.
	 *
	 * Overridden here because we want proper sentence casing, rather than 'PageAssessments'.
	 *
	 * @return string
	 */
	function getDescription() {
		return $this->msg( 'pageassessments-special' )->text();
	}

	/**
	 * Output the special page.
	 * @param string $parameters The parameters to the special page.
	 */
	public function execute( $parameters ) {
		// Set up.
		$out = $this->getOutput();
		$out->setPageTitle( $this->getDescription() );
		$this->addHelpLink( 'Help:Extension:PageAssessments' );

		// Output form.
		if ( !$this->including() ) {
			$form = $this->getForm();
			$form->show();
		}

		// Output results, if a search has been performed.
		$queryValues = $this->getRequest()->getQueryValues();
		// The request has 'title' when this special page is not transcluded.
		unset( $queryValues['title'] );
		if ( count( $queryValues ) > 0 ) {
			$pager = new Pager();
			$pager->setSortable( !$this->including() );
			// Summary of search resutls.
			$total = wfMessage( 'pageassessments-total-results', $pager->getNumRows() );
			$out->addElement( 'p', ['class'=>'total-results'], $total );
			// Table pager.
			$out->addParserOutput( $pager->getFullOutput() );
		}
	}

	/**
	 * Get the search form.
	 * @return HTMLForm
	 */
	protected function getForm() {
		$formDescriptor = [
			'project' => [
				'class' => HTMLTextField::class,
				'name' => 'project',
				'label-message' => 'pageassessments-project',
			],
			'namespace' => [
				'class' => NamespaceSelect::class,
				'name' => 'namespace',
				'label-message' => 'pageassessments-page-namespace',
			],
			'page_title' => [
				'class' => HTMLTextField::class,
				'name' => 'page_title',
				'label-message' => 'pageassessments-page-title',
			],
		];
		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setMethod( 'get' );
		$form->setSubmitTextMsg( 'pageassessments-search' );
		$form->setSubmitCallback( function() {
			// No callback required, but HTMLForm says we have to set one.
		} );
		return $form;
	}

}
