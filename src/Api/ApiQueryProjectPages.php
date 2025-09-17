<?php

namespace MediaWiki\Extension\PageAssessments\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiPageSet;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryGeneratorBase;
use MediaWiki\Extension\PageAssessments\PageAssessmentsDAO;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API module for retrieving all the pages associated with a project, for example,
 * WikiProject Medicine. (T119997)
 */
class ApiQueryProjectPages extends ApiQueryGeneratorBase {

	/**
	 * Array of project IDs for the projects listed in the API query
	 * @var array
	 */
	private $projectIds = [];

	public function __construct( ApiQuery $query, string $moduleName ) {
		// The prefix pp is already used by the pageprops module, so using wpp instead.
		parent::__construct( $query, $moduleName, 'wpp' );
	}

	/**
	 * Evaluate the parameters, perform the requested query, and set up the result
	 */
	public function execute() {
		$this->run();
	}

	/**
	 * Evaluate the parameters, perform the requested query, and set up the result for generator mode
	 * @param ApiPageSet $resultPageSet
	 */
	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 */
	private function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();

		if ( $params['assessments'] && $resultPageSet !== null ) {
			$this->addWarning( 'apiwarn-pageassessments-nogeneratorassessments' );
		}

		$this->buildDbQuery( $params, $resultPageSet );

		// If matching projects were found, run the query.
		if ( $this->projectIds ) {
			$db_res = $this->select( __METHOD__ );
		// Otherwise, just set the result to an empty array (still works with foreach).
		} else {
			$db_res = [];
		}

		if ( $resultPageSet === null ) {
			$result = $this->getResult();
			$count = 0;
			foreach ( $db_res as $row ) {
				if ( ++$count > $params['limit'] ) {
					$this->setContinueEnumParameter( 'continue', "$row->pa_project_id|$row->pa_page_id" );
					break;
				}

				// Change project id back into its corresponding project title
				$projectTitle = $row->pap_project_title;
				if ( !$projectTitle ) {
					continue;
				}

				// Add information to result
				$vals = $this->generateResultVals( $row );
				$fit = $result->addValue(
					[ 'query', 'projects', $projectTitle ], $row->pa_page_id, $vals
				);

				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', "$row->pa_project_id|$row->pa_page_id" );
					break;
				}

				// Add metadata to make XML results for pages parse better
				$result->addIndexedTagName( [ 'query', 'projects', $projectTitle ], 'page' );
				$result->addArrayType( [ 'query', 'projects', $projectTitle ], 'array' );
			}
			// Add metadata to make XML results for projects parse better
			$result->addIndexedTagName( [ 'query', 'projects' ], 'project' );
			$result->addArrayType( [ 'query', 'projects' ], 'kvp', 'name' );
		} else {
			$count = 0;
			foreach ( $db_res as $row ) {
				if ( ++$count > $params['limit'] ) {
					$this->setContinueEnumParameter( 'continue', "$row->pa_project_id|$row->pa_page_id" );
					break;
				}

				$resultPageSet->processDbRow( $row );
			}
		}
	}

	/**
	 * @param array $params
	 * @param ApiPageSet|null $resultPageSet
	 */
	private function buildDbQuery( array $params, $resultPageSet ) {
		$this->addTables( [ 'page', 'page_assessments' ] );
		$this->addFields( [ 'pa_page_id', 'pa_project_id' ] );
		$this->addJoinConds( [
			'page' => [
				'JOIN',
				[ 'page_id = pa_page_id' ],
			]
		] );

		if ( $resultPageSet === null ) {
			$this->addTables( 'page_assessments_projects' );
			$this->addFields( [ 'page_title', 'page_namespace', 'pap_project_title' ] );
			$this->addJoinConds( [
				'page_assessments_projects' => [
					'JOIN',
					[ 'pa_project_id = pap_project_id' ],
				]
			] );
			if ( $params['assessments'] ) {
				$this->addFields( [ 'pa_class', 'pa_importance' ] );
			}
		} else {
			$this->addFields( $resultPageSet->getPageTableFields() );
		}

		if ( isset( $params['projects'] ) ) {
			// Convert the project names into corresponding IDs
			foreach ( $params['projects'] as $project ) {
				$id = PageAssessmentsDAO::getProjectId( $project );
				if ( $id ) {
					$this->projectIds[] = $id;
				} else {
					$this->addWarning( [ 'apiwarn-pageassessments-badproject',
						wfEscapeWikiText( $project ) ] );
				}
			}
		}

		// DB stores project IDs, so that's what goes into the where field
		$this->addWhereFld( 'pa_project_id', $this->projectIds );
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		if ( $params['continue'] !== null ) {
			$this->handleQueryContinuation( $params['continue'] );
		}

		// If more than 1 project is requested, order by project first.
		if ( count( $this->projectIds ) > 1 ) {
			$this->addOption( 'ORDER BY', [ 'pa_project_id', 'pa_page_id' ] );
		} else {
			$this->addOption( 'ORDER BY', 'pa_page_id' );
		}
	}

	/**
	 * @param string $continueParam
	 */
	private function handleQueryContinuation( $continueParam ) {
		$continues = $this->parseContinueParamOrDie( $continueParam, [ 'int', 'int' ] );
		$this->addWhere( $this->getDB()->buildComparison( '>=', [
			'pa_project_id' => $continues[0],
			'pa_page_id' => $continues[1],
		] ) );
	}

	/**
	 * @param \stdClass $row
	 * @return array
	 */
	private function generateResultVals( $row ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );

		$vals = [
			'pageid' => (int)$row->pa_page_id,
			'ns' => (int)$row->page_namespace,
			'title' => $title->getPrefixedText(),
		];

		if ( isset( $row->pa_class ) && isset( $row->pa_importance ) ) {
			$vals['assessment'] = [
				'class' => $row->pa_class,
				'importance' => $row->pa_importance,
			];
		}

		return $vals;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'assessments' => [
				ParamValidator::PARAM_DEFAULT => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'projects' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/** @inheritDoc */
	public function getExamplesMessages() {
		return [
			'action=query&list=projectpages&wppprojects=Medicine|Anatomy'
				=> 'apihelp-query+projectpages-example-simple-1',
			'action=query&list=projectpages&wppprojects=Medicine&wppassessments=true'
				=> 'apihelp-query+projectpages-example-simple-2',
			'action=query&generator=projectpages&prop=info&gwppprojects=Textile%20Arts'
				=> 'apihelp-query+projectpages-example-generator',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:PageAssessments';
	}
}
