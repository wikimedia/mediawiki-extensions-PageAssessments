<?php

namespace MediaWiki\Extension\PageAssessments\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/*
 * API query module that returns associated projects and assessment data for a given set
 * of pages. (T119997)
 */
class ApiQueryPageAssessments extends ApiQueryBase {

	/** @inheritDoc */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'pa' );
	}

	/**
	 * Evaluate the parameters, perform the requested query, and set up the result
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$pages = $this->getPageSet()->getGoodTitles();
		// are there pages to get project/assessment info for?
		if ( !count( $pages ) ) {
			return;  // If not, return so other prop modules can run
		}

		$this->buildDbQuery( $pages, $params );
		$db_res = $this->select( __METHOD__ );

		// API result
		$result = $this->getResult();

		$count = 0;
		foreach ( $db_res as $row ) {
			// One more than limit, so there are additional projects in the list
			if ( ++$count > $params['limit'] ) {
				$this->setContinueEnumParameter( 'continue', "$row->page_id|$row->project_id" );
				break;
			}

			$projectValues = [
				'class' => $row->class,
				'importance' => $row->importance,
			];

			$projectName = $row->project_name;
			// If the project name can't be found, skip adding it to the results.
			if ( !$projectName ) {
				continue;
			}

			$fit = $result->addValue(
				[ 'query', 'pages', $row->page_id, $this->getModuleName() ],
				$projectName,
				$projectValues
			);

			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', "$row->page_id|$row->project_id" );
				break;
			}

			// Make it easier to parse XML-formatted results
			$result->addArrayType(
				[ 'query', 'pages', $row->page_id, $this->getModuleName() ], 'kvp', 'project'
			);
			$result->addIndexedTagName(
				[ 'query', 'pages', $row->page_id, $this->getModuleName() ], 'p'
			);
		}
	}

	/**
	 * @param array $pages
	 * @param array $params
	 */
	private function buildDbQuery( array $pages, array $params ) {
		global $wgPageAssessmentsSubprojects;

		// build basic DB query
		$this->addTables( [ 'page_assessments', 'page_assessments_projects' ] );
		$this->addFields( [
			'project_id' => 'pa_project_id',
			'class' => 'pa_class',
			'importance' => 'pa_importance',
			'page_id' => 'pa_page_id',
			'project_name' => 'pap_project_title'
		] );
		$this->addJoinConds( [
			'page_assessments_projects' => [
				'JOIN',
				[ 'pa_project_id = pap_project_id' ],
			]
		] );
		$this->addWhereFld( 'pa_page_id', array_keys( $pages ) );
		// If this wiki distinguishes between projects and subprojects, exclude
		// subprojects (i.e. projects with parents) unless explicitly asked for.
		if ( $wgPageAssessmentsSubprojects && !$params['subprojects'] ) {
			$this->addWhere( [ 'pap_parent_id' => null ] );
		}
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		// handle continuation if present
		if ( $params['continue'] !== null ) {
			$this->handleQueryContinuation( $params['continue'] );
		}

		// assure strict ordering, but mysql gets cranky if you order by a field
		// when there's only one to sort
		if ( count( $pages ) > 1 ) {
			$this->addOption( 'ORDER BY', [ 'pa_page_id', 'pa_project_id' ] );
		} else {
			$this->addOption( 'ORDER BY', 'pa_project_id' );
		}
	}

	/**
	 * @param string $continueParam
	 */
	private function handleQueryContinuation( $continueParam ) {
		$continues = $this->parseContinueParamOrDie( $continueParam, [ 'int', 'int' ] );
		$this->addWhere( $this->getDB()->buildComparison( '>=', [
			'pa_page_id' => $continues[0],
			'pa_project_id' => $continues[1],
		] ) );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		global $wgPageAssessmentsSubprojects;

		$allowedParams = [
			'continue' => [ ApiBase::PARAM_HELP_MSG => 'api-help-param-continue' ],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
		];
		if ( $wgPageAssessmentsSubprojects ) {
			$allowedParams[ 'subprojects' ] = [
				ParamValidator::PARAM_DEFAULT => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			];
		}
		return $allowedParams;
	}

	/** @inheritDoc */
	public function getExamplesMessages() {
		global $wgPageAssessmentsSubprojects;

		$exampleMessages = [
			'action=query&prop=pageassessments&titles=Apple|Pear&formatversion=2'
				=> 'apihelp-query+pageassessments-example-formatversion',
			'action=query&prop=pageassessments&titles=Apple'
				=> 'apihelp-query+pageassessments-example-simple',
		];
		if ( $wgPageAssessmentsSubprojects ) {
			$exampleMessages['action=query&prop=pageassessments&titles=Apple&pasubprojects=true'] =
				'apihelp-query+pageassessments-example-subprojects';
		}
		return $exampleMessages;
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:PageAssessments';
	}
}
