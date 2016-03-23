<?php

/*
 * API module for retrieving all the pages associated with a project, for example,
 * WikiProject Medicine. (T119997)
 */
class ApiQueryProjectPages extends ApiQueryGeneratorBase {

	/**
	 * Array of project IDs for the projects listed in the API query
	 * @var array
	 */
	private $projectIds = array();

	public function __construct( ApiQuery $query, $moduleName ) {
		// The prefix pp is already used by the pageprops module, so using wpp instead.
		parent::__construct( $query, $moduleName, 'wpp' );
	}

	// for a generator module, you can either execute it on its own...
	public function execute() {
		$this->run();
	}

	// ... or from the results of another query
	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	// this is what actually does the (further) querying/generation of result set
	private function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();

		if ( $params['assessments'] && isset( $resultPageSet ) ) {
			$this->setWarning( 'It is not possible to retrieve page assessment results from generator=projectpages.' );
		}

		$this->buildDbQuery( $params, $resultPageSet );

		// If matching projects were found, or no specific projects were requested, run the query.
		if ( $this->projectIds || !$params['projects'] ) {
			$db_res = $this->select( __METHOD__ );
		// Otherwise, just set the result to an empty array (still works with foreach).
		} else {
			$db_res = array();
		}

		if ( $resultPageSet === null ) {
			$result = $this->getResult();
			$count = 0;
			foreach ( $db_res as $row ) {
				if ( ++$count > $params['limit'] ) {
					$this->setContinueEnumParameter( 'continue', "$row->project_id|$row->page_id" );
					break;
				}

				// Change project id back into its corresponding project title
				$projectTitle = $row->project_name;
				if ( !$projectTitle ) {
					continue;
				}

				// Add information to result
				$vals = $this->generateResultVals( $row );
				$fit = $result->addValue(
					array( 'query', 'projects', $projectTitle ), $row->page_id, $vals
				);

				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', "$row->project_id|$row->page_id" );
					break;
				}

				// Add metadata to make XML results for pages parse better
				$result->addIndexedTagName( array( 'query', 'projects', $projectTitle ), 'page' );
				$result->addArrayType( array( 'query', 'projects', $projectTitle ), 'array' );
			}
			// Add metadata to make XML results for projects parse better
			$result->addIndexedTagName( array( 'query', 'projects' ), 'project' );
			$result->addArrayType( array( 'query', 'projects' ), 'kvp', 'name' );
		} else {
			$count = 0;
			foreach ( $db_res as $row ) {
				if ( ++$count > $params['limit'] ) {
					$this->setContinueEnumParameter( 'continue', "$row->project_id|$row->page_id" );
					break;
				}

				$resultPageSet->processDbRow( $row );
			}
		}
	}

	private function buildDbQuery( array $params, $resultPageSet ) {
		$this->addTables( array( 'page', 'page_assessments' ) );
		$this->addFields( array(
			'page_id' => 'pa_page_id',
			'project_id' => 'pa_project_id',
		) );
		$this->addJoinConds( array(
			'page' => array(
				'JOIN',
				array( 'page_id = pa_page_id' ),
			)
		) );

		if ( $resultPageSet === null ) {
			$this->addTables( 'page_assessments_projects' );
			$this->addFields( array(
				'title' => 'page_title',
				'namespace' => 'page_namespace',
				'project_name' => 'pap_project_title'
			) );
			$this->addJoinConds( array(
				'page_assessments_projects' => array(
					'JOIN',
					array( 'pa_project_id = pap_project_id' ),
				)
			) );
			if ( $params['assessments'] ) {
				$this->addFields( array(
					'class' => 'pa_class',
					'importance' => 'pa_importance'
				) );
			}
		} else {
			$this->addFields( $resultPageSet->getPageTableFields() );
		}

		if ( isset( $params['projects'] ) ) {
			// Convert the project names into corresponding IDs
			foreach ( $params['projects'] as $project ) {
				$id = PageAssessmentsBody::getProjectId( $project );
				if ( $id ) {
					$this->projectIds[] = $id;
				} else {
					$this->setWarning( 'Project name not recognized: ' . $project );
				}
			}
		}

		// DB stores project IDs, so that's what goes into the where field
		$this->addWhereFld( 'pa_project_id', $this->projectIds );
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		if ( $params['continue'] !== null ) {
			$this->handleQueryContinuation( $params['continue'] );
		}

		// If more than 1 project is requested (or all projects), order by project first.
		if ( count( $this->projectIds ) !== 1 ) {
			$this->addOption( 'ORDER BY', 'pa_project_id, pa_page_id' );
		} else {
			$this->addOption( 'ORDER BY', 'pa_page_id' );
		}

	}

	private function handleQueryContinuation( $continueParam ) {
		$continues = explode( '|', $continueParam );
		$this->dieContinueUsageIf( count( $continues ) !== 2 );

		$continueProject = (int)$continues[0];
		$continuePage = (int)$continues[1];
		$this->dieContinueUsageIf( $continues[0] !== (string)$continueProject );  //die if PHP has made unhelpful falsy conversions
		$this->dieContinueUsageIf( $continues[1] !== (string)$continuePage );  //die if PHP has made unhelpful falsy conversions

		$this->addWhere( "pa_project_id > $continueProject OR (pa_project_id = $continueProject AND pa_page_id >= $continuePage)" );
	}

	private function generateResultVals( $row ) {
		$vals = array(
			'pageid' => (int)$row->page_id,
			'ns' => (int)$row->namespace,
			'title' => $row->title,
		);

		if ( isset( $row->class ) && isset( $row->importance ) ) {
			$vals['assessment'] = array(
				'class' => $row->class,
				'importance' => $row->importance,
			);
		}

		return $vals;
	}

	public function getAllowedParams() {
		return array(
			'assessments' => array(
				ApiBase::PARAM_DFLT => false,
				ApiBase::PARAM_TYPE => 'boolean',
			),
			'projects' => array(
				ApiBase::PARAM_ISMULTI => true
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => '10',
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			),
			'continue' => array(
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			),
		);
	}

	public function getExamplesMessages() {
		return array(
			'action=query&list=projectpages'
				=> 'apihelp-query+projectpages-example-simple-1',
			'action=query&list=projectpages&wppprojects=Medicine&wppassessments=true'
				=> 'apihelp-query+projectpages-example-simple-2',
			'action=query&generator=projectpages&prop=info&gwppprojects=Textile%20Arts'
				=> 'apihelp-query+projectpages-example-generator',
		);
	}
}
