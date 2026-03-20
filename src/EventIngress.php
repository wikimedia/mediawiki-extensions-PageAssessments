<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\PageAssessments;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\Event\PageDeletedListener;

class EventIngress extends DomainEventIngress implements PageDeletedListener {

	public function __construct(
		private readonly PageAssessmentsStore $store
	) {
	}

	/** @inheritDoc */
	public function handlePageDeletedEvent( PageDeletedEvent $event ): void {
		// TODO: Should probably delete assessments where the parser function is removed, too?
		$this->store->deleteRecordsForPage( $event->getPageId() );
	}
}
