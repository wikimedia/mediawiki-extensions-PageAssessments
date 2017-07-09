<?php

/*
 * API query module that returns associated projects and assessment data for a given set
 * of pages. (T119997)
 */
class ApiQueryPageAssessments extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'pa' );
	}

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
				'importance'=> $row->importance,
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
			$this->addWhere( 'pap_parent_id IS NULL' );
		}
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		// handle continuation if present
		if ( $params['continue'] !== null ) {
			$this->handleQueryContinuation( $params['continue'] );
		}

		// assure strict ordering, but mysql gets cranky if you order by a field
		// when there's only one to sort
		if ( count( $pages ) > 1 ) {
			$this->addOption( 'ORDER BY', 'pa_page_id, pa_project_id' );
		} else {
			$this->addOption( 'ORDER BY', 'pa_project_id' );
		}
	}

	private function handleQueryContinuation( $continueParam ) {
		$continues = explode( '|', $continueParam );
		$this->dieContinueUsageIf( count( $continues ) !== 2 );

		$continuePage = (int)$continues[0];
		$continueProject = (int)$continues[1];
		// die if PHP has made unhelpful falsy conversions
		$this->dieContinueUsageIf( $continues[0] !== (string)$continuePage );
		$this->dieContinueUsageIf( $continues[1] !== (string)$continueProject );

		$this->addWhere( "pa_page_id > $continuePage OR " .
			"(pa_page_id = $continuePage AND pa_project_id >= $continueProject)" );
	}

	public function getAllowedParams() {
		global $wgPageAssessmentsSubprojects;

		$allowedParams = [
			'continue' => [ ApiBase::PARAM_HELP_MSG => 'api-help-param-continue' ],
			'limit' => [
				ApiBase::PARAM_DFLT => '10',
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
		];
		if ( $wgPageAssessmentsSubprojects ) {
			$allowedParams[ 'subprojects' ] = [
				ApiBase::PARAM_DFLT => false,
				ApiBase::PARAM_TYPE => 'boolean',
			];
		}
		return $allowedParams;
	}

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

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:PageAssessments';
	}
}
