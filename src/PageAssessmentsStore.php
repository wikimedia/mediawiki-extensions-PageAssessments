<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments;

use CirrusSearch\WeightedTagsUpdater;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;

class PageAssessmentsStore {

	private bool $cirrusSearchLoaded;
	private int $updateRowsPerQuery;
	private bool $subprojectsEnabled;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		readonly ExtensionRegistry $extensionRegistry,
		readonly Config $config,
		private readonly ?WeightedTagsUpdater $weightedTagsUpdater,
	) {
		$this->cirrusSearchLoaded = $extensionRegistry->isLoaded( 'CirrusSearch' );
		$this->updateRowsPerQuery = $this->config->get( MainConfigNames::UpdateRowsPerQuery );
		$this->subprojectsEnabled = $this->config->get( 'PageAssessmentsSubprojects' );
	}

	private function getReplicaDBConnection(): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase();
	}

	private function getPrimaryDBConnection(): IDatabase {
		return $this->connectionProvider->getPrimaryDatabase();
	}

	/**
	 * Driver function that handles updating assessment data in database
	 * @param Title $titleObj Title object of the subject page
	 * @param array $assessmentData Data for all assessments compiled
	 * @param mixed|null $ticket Transaction ticket
	 */
	public function doUpdates( Title $titleObj, array $assessmentData, mixed $ticket = null ): void {
		$ticket = $ticket ?: $this->connectionProvider->getEmptyTransactionTicket( __METHOD__ );

		$changed = false;
		$pageId = $titleObj->getArticleID();
		$revisionId = $titleObj->getLatestRevID();
		// Compile a list of projects found in the parserData to find out which
		// assessment records need to be inserted, deleted, or updated.
		$projects = [];
		foreach ( $assessmentData as $key => $parserData ) {
			// If the name of the project is set...
			if ( isset( $parserData[0] ) && $parserData[0] !== '' ) {
				// Clean the project name.
				$projectName = $this->cleanProjectTitle( $parserData[0] );
				// Replace the original project name with the cleaned project
				// name in the assessment data, since we'll need it to match later.
				$assessmentData[$key][0] = $projectName;
				// Get the corresponding ID from page_assessments_projects table.
				$projectId = $this->getProjectId( $projectName );
				// If there is no existing project by that name, add it to the table.
				if ( $projectId === null ) {
					if ( $this->subprojectsEnabled ) {
						// Extract possible parent from the project name.
						$parentId = $this->extractParentProjectId( $projectName );
						// Insert project data into the database table.
						$projectId = $this->insertProject( $projectName, $parentId );
					} else {
						$projectId = $this->insertProject( $projectName );
					}
				}
				// Add the project's ID to the array.
				$projects[$projectName] = $projectId;
			}
		}
		// Get a list of all the projects previously assigned to the page.
		$projectsInDb = $this->getAllProjects( $pageId, IDBAccessObject::READ_LATEST );

		$toInsert = array_diff( $projects, $projectsInDb );
		$toDelete = array_diff( $projectsInDb, $projects );
		$toUpdate = array_intersect( $projects, $projectsInDb );

		$i = 0;

		// Add and update assessment records to the database
		foreach ( $assessmentData as $parserData ) {
			// Make sure the name of the project is set.
			if ( !isset( $parserData[0] ) || $parserData[0] == '' ) {
				continue;
			}
			$projectId = $projects[$parserData[0]];
			if ( $projectId && $pageId ) {
				$class = $parserData[1];
				$importance = $parserData[2];
				$values = [
					'pa_page_id' => $pageId,
					'pa_project_id' => $projectId,
					'pa_class' => $class,
					'pa_importance' => $importance,
					'pa_page_revision' => $revisionId
				];
				if ( in_array( $projectId, $toInsert ) ) {
					$this->insertRecord( $values );
					$changed = true;
				} elseif ( in_array( $projectId, $toUpdate ) ) {
					if ( $this->updateRecord( $values ) ) {
						$changed = true;
					}
				}
				// Check for database lag if there's a huge number of assessments
				if ( $i > 0 && $i % $this->updateRowsPerQuery === 0 ) {
					$this->connectionProvider->commitAndWaitForReplication( __METHOD__, $ticket );
				}
				$i++;
			}
		}

		// Delete records from the database
		foreach ( $toDelete as $project ) {
			$values = [
				'pa_page_id' => $pageId,
				'pa_project_id' => $project
			];
			$this->deleteRecord( $values );
			$changed = true;
			// Check for database lag if there's a huge number of deleted assessments
			if ( $i > 0 && $i % $this->updateRowsPerQuery === 0 ) {
				$this->connectionProvider->commitAndWaitForReplication( __METHOD__, $ticket );
			}
			$i++;
		}

		if ( $changed ) {
			$this->updateSearchIndex( $titleObj, $assessmentData );
		}
	}

	/**
	 * Update projects in the CirrusSearch index.
	 *
	 * @param Title $titleObj
	 * @param array $assessmentData
	 */
	public function updateSearchIndex( Title $titleObj, array $assessmentData ): void {
		if ( !$this->cirrusSearchLoaded ) {
			return;
		}
		$tags = [];
		foreach ( $assessmentData as $parserData ) {
			if ( !isset( $parserData[0] ) || $parserData[0] == '' || str_contains( $parserData[0], '|' ) ) {
				// Ignore empty or invalid project names. Pipe character is not allowed in weighted_tags.
				continue;
			}
			// Name already cleaned above in doUpdates()
			$name = $parserData[0];
			$weight = $this->importanceToWeight( $parserData[ 2 ] );
			$tags[ $name ] = $weight;
		}

		if ( $tags === [] ) {
			$this->weightedTagsUpdater?->resetWeightedTags(
				$titleObj->toPageIdentity(),
				[ 'ext.pageassessments.project' ]
			);
		} else {
			$this->weightedTagsUpdater?->updateWeightedTags(
				$titleObj->toPageIdentity(),
				'ext.pageassessments.project',
				$tags
			);
		}
	}

	private function importanceToWeight( string $importance ): int {
		// TODO: Read from local JSON page in MediaWiki namespace?
		$importanceMap = [
			'top' => 100,
			'high' => 80,
			'mid' => 60,
			'low' => 40,
			// Consider unknown as low-importance
			'unknown' => 40,
			'na' => 10
		];
		return $importanceMap[ strtolower( $importance ) ] ?? 10;
	}

	/**
	 * Extract parent from a project name and return the ID. For example, if the
	 * project name is "Novels/Crime task force", the parent will be "Novels",
	 * i.e. WikiProject Novels.
	 *
	 * @param string $projectName Project title
	 * @return ?int project ID or false if not found
	 */
	protected function extractParentProjectId( string $projectName ): ?int {
		$projectNameParts = explode( '/', $projectName );
		if ( count( $projectNameParts ) > 1 && $projectNameParts[0] !== '' ) {
			return $this->getProjectId( $projectNameParts[0] );
		}
		return null;
	}

	/**
	 * Get project ID for a given wikiproject title
	 * @param string $project Project title
	 * @return ?int project ID or null if not found
	 */
	public function getProjectId( string $project ): ?int {
		$ret = $this->getReplicaDBConnection()
			->newSelectQueryBuilder()
			->select( 'pap_project_id' )
			->from( 'page_assessments_projects' )
			->where( [ 'pap_project_title' => $project ] )
			->caller( __METHOD__ )
			->fetchField();
		return is_numeric( $ret ) ? (int)$ret : null;
	}

	/**
	 * Insert a new wikiproject into the projects table
	 * @param string $project Wikiproject title
	 * @param ?int $parentId ID of the parent project (for subprojects) (optional)
	 * @return int Insert Id for new project
	 */
	public function insertProject( string $project, ?int $parentId = null ): int {
		$dbw = $this->getPrimaryDBConnection();
		$values = [ 'pap_project_title' => $project ];
		if ( $parentId ) {
			$values[ 'pap_parent_id' ] = $parentId;
		}
		$dbw->newInsertQueryBuilder()
			->insertInto( 'page_assessments_projects' )
			// Use ignore() in case two projects with the same name are added at once.
			// This normally shouldn't happen, but is possible perhaps from clicking
			// 'Publish changes' twice in very quick succession. (See T286671)
			->ignore()
			->row( $values )
			->caller( __METHOD__ )
			->execute();
		return $dbw->insertId();
	}

	/**
	 * Clean up the title of the project (or subproject)
	 *
	 * Since the project title comes from a template parameter, it can basically
	 * be anything. This function accounts for common cases where editors put
	 * extra stuff into the parameter besides just the name of the project.
	 * @param string $project WikiProject title
	 * @return string Cleaned-up WikiProject title
	 */
	public function cleanProjectTitle( string $project ): string {
		// Remove any bold formatting.
		$project = str_replace( "'''", "", $project );
		// Remove "the" prefix for subprojects (common on English Wikipedia).
		// This is case-sensitive on purpose, as there are some legitimate
		// subproject titles starting with "The", e.g. "The Canterbury Tales".
		$project = str_replace( "/the ", "/", $project );
		// Truncate to 255 characters to avoid DB warnings.
		return substr( $project, 0, 255 );
	}

	/**
	 * Update record in DB if there are new values
	 * @param array $values New values to be entered into the DB
	 * @return bool true if an update was performed false otherwise
	 */
	public function updateRecord( array $values ): bool {
		$conds = [
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		];
		// Check if there are no updates to be done
		$record = $this->getReplicaDBConnection()
			->newSelectQueryBuilder()
			->select( [ 'pa_class', 'pa_importance', 'pa_project_id', 'pa_page_id' ] )
			->from( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $record as $row ) {
			if ( $row->pa_importance == $values['pa_importance'] &&
				$row->pa_class == $values['pa_class']
			) {
				// Return if no update is needed
				return false;
			}
		}
		// Make updates if there are changes
		$this->getPrimaryDBConnection()
			->newUpdateQueryBuilder()
			->update( 'page_assessments' )
			->set( $values )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Insert a new record in DB
	 * @param array $values New values to be entered into the DB
	 * @return bool true
	 */
	public function insertRecord( array $values ): bool {
		// Use IGNORE in case 2 records for the same project are added at once.
		// This normally shouldn't happen, but is possible. (See T152080)
		$this->getPrimaryDBConnection()
			->newInsertQueryBuilder()
			->insertInto( 'page_assessments' )
			->ignore()
			->row( $values )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Get all projects associated with a given page (as project IDs)
	 * @param int $pageId Page ID
	 * @param int $flags IDBAccessObject::READ_* constant. This can be used to
	 *     force reading from the primary database. See docs at IDBAccessObject.php.
	 * @return array $results All projects associated with given page
	 */
	public function getAllProjects( int $pageId, int $flags = IDBAccessObject::READ_NORMAL ): array {
		if ( ( $flags & IDBAccessObject::READ_LATEST ) == IDBAccessObject::READ_LATEST ) {
			$db = $this->getPrimaryDBConnection();
		} else {
			$db = $this->getReplicaDBConnection();
		}
		$res = $db->newSelectQueryBuilder()
			->select( 'pa_project_id' )
			->from( 'page_assessments' )
			->where( [ 'pa_page_id' => $pageId ] )
			->recency( $flags )
			->caller( __METHOD__ )->fetchResultSet();
		$results = [];
		foreach ( $res as $row ) {
			$results[] = $row->pa_project_id;
		}
		return $results;
	}

	/**
	 * Get all assessment data associated with the given page
	 *
	 * @param int $pageId Page ID
	 * @return array $results All projects names and assessments associated with the given page
	 */
	public function getAllAssessments( int $pageId ): array {
		$res = $this->getReplicaDBConnection()
			->newSelectQueryBuilder()
			->select( [ 'pap_project_title', 'pa_class', 'pa_importance' ] )
			->from( 'page_assessments' )
			->join( 'page_assessments_projects', null, [ 'pap_project_id = pa_project_id' ] )
			->where( [ 'pa_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$results = [];
		foreach ( $res as $row ) {
			$results[] = [
				'name' => $row->pap_project_title,
				'class' => $row->pa_class,
				'importance' => $row->pa_importance
			];
		}
		return $results;
	}

	/**
	 * Delete a record from DB
	 * @param array $values Conditions for looking up records to delete
	 * @return bool true
	 */
	public function deleteRecord( array $values ): bool {
		$conds = [
			'pa_page_id' => $values['pa_page_id'],
			'pa_project_id' => $values['pa_project_id']
		];
		$this->getPrimaryDBConnection()
			->newDeleteQueryBuilder()
			->deleteFrom( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	/**
	 * Delete all records for a given page when page is deleted
	 * Note: We don't take care of undeletions explicitly, the records are restored
	 * when the page is parsed again.
	 * @param int $id Page ID of deleted page
	 */
	public function deleteRecordsForPage( int $id ): void {
		$conds = [
			'pa_page_id' => $id,
		];
		$this->getPrimaryDBConnection()
			->newDeleteQueryBuilder()
			->deleteFrom( 'page_assessments' )
			->where( $conds )
			->caller( __METHOD__ )
			->execute();
	}
}
