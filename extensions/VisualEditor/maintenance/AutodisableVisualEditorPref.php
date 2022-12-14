<?php
/**
 * Sets the VisualEditor autodisable preference on appropriate users.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 * @author Alex Monk <amonk@wikimedia.org>
 * @file
 * @ingroup Extensions
 * @ingroup Maintenance
 */

use MediaWiki\MediaWikiServices;

$maintenancePath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../../maintenance/Maintenance.php';

require_once $maintenancePath;

class AutodisableVisualEditorPref extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'VisualEditor' );
		$this->addDescription( "Sets the VisualEditor autodisable preference on appropriate users." );
		$this->setBatchSize( 500 );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$dbr = wfGetDB( DB_REPLICA );
		$services = MediaWikiServices::getInstance();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$userOptionsManager = $services->getUserOptionsManager();

		$lastUserId = -1;
		do {
			$results = $dbr->newSelectQueryBuilder()
				->from( 'user' )
				->leftJoin( 'user_properties', null, [
					'user_id = up_user',
					'up_property' => "visualeditor-enable"
				] )
				->field( 'user_id' )
				->where( [
					'user_id > ' . $dbr->addQuotes( $lastUserId ),
					// only select users with no entry in user_properties
					'up_value IS NULL',
					'user_editcount > 0'
				] )
				->limit( $this->mBatchSize )
				->orderBy( 'user_id' )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $results as $userRow ) {
				$user = User::newFromId( $userRow->user_id );
				$user->load( User::READ_LATEST );
				$userOptionsManager->setOption( $user, 'visualeditor-autodisable', true );
				$user->saveSettings();
				$lastUserId = $userRow->user_id;
			}
			$this->output( "Added preference for " . $results->numRows() . " users.\n" );
			$lbFactory->waitForReplication();
		} while ( $results->numRows() == $this->mBatchSize );
		$this->output( "done.\n" );
	}
}

$maintClass = AutodisableVisualEditorPref::class;
require_once RUN_MAINTENANCE_IF_MAIN;
