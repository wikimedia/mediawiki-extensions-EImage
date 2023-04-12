<?php
/**
 * Maintainer script to clean duplicate items from 'ei_pages' table and
 * remove orphaned items from cache
 *
 * @ingroup Maintenance
 */
use MediaWiki\MediaWikiServices;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

class cleanUnusedClips extends Maintenance {
	private $clips;
	private $thumbnails;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'store', 'Remove orphaned clips from the local cache', false, false );
		$this->addOption( 'verbose', 'By default is this silent script. More info print only in verbose mode', false, false );
		$this->addDescription( 'This maitenance script clear unused or expired items from cache of the EImage extension' );
		// $this->setBatchSize( 100 );
		$this->requireExtension( 'EImage' );
	}

	public function execute() {
		global $wgServer, $wgScript;
		$soubor = new EImageIMG;
		$this->clips = $soubor->getCacheLocal() . DIRECTORY_SEPARATOR;
		$dbw = $this->getDB( DB_PRIMARY );
		if ( $this->getOption( 'store' ) ) {
			// List pages where is any EImageIMG clip
			$dbw->startAtomic( __METHOD__ );
			$pages = $dbw->select(
				'ei_pages',
				'ei_page',
				'ei_page > 0' ,
				__METHOD__
				);
			$dbw->endAtomic( __METHOD__ );
			$tocheck = [];
			foreach ( $pages as $page ) {
				$tocheck[] = $page->ei_page;
			}
			foreach ( array_unique( $tocheck, SORT_NUMERIC ) as $page ) {
				// Downloading page content will increase the expiration time only for active objects
				// https://www.example.org/wiki/index.php?curid=38428
				if ( $this->getOption( 'verbose' ) ) {
					$this->output( "Download curid {$page}\n" );
				}
				$content = file_get_contents( $wgServer . $wgScript . '?curid=' . strval( $page ) );
				unset( $content );
			}
			$date = new DateTimeImmutable();
			$d = glob( $this->clips . '[a-z0-9]*.*', GLOB_BRACE );
			foreach ( $d as $item ) {
				$position = strripos( $item, '/' );
				$clip = str_split( substr( $item, $position + 1 ), 40 );
				$dbw->startAtomic( __METHOD__ );
				$expiration = $dbw->selectField(
					'ei_cache',
					'ei_expire',
					[ 'ei_file' => $clip[0] ],
					__METHOD__
				);
				$dbw->endAtomic( __METHOD__ );
				if ( $expiration) {
					$limit = abs( $expiration ) - abs( $date->getTimestamp() );
					if ( $limit < 0 ) {
						if ( $soubor->clipThumbnail( $clip[0], false ) ) {
							if ( $this->getOption( 'verbose' ) ) {
								$this->output( "Expired before: " . strval( $limit ) ." - " . $clip[0] ."\n" );
							}
						}
					}
				} else {
					if ( $soubor->clipThumbnail( $clip[0], false ) ) {
						if ( $this->getOption( 'verbose' ) ) {
							$this->output( "Obsolete clip: " . $clip[0] . "\n" );
						}
					}
				}
			}
		} else {
			$dbw->startAtomic( __METHOD__ );
			$items = $dbw->select(
				'ei_cache',
				[ 'ei_file' ],
				'ei_expire > 0',
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
			foreach ( $items as $clip ) {
				if ( $soubor->clipThumbnail( $clip->ei_file ) ) {
					$used = $soubor->dbClipArrayGet( $clip->ei_file );
					if ( $this->getOption( 'verbose' ) ) {
						$this->output( $clip->ei_file . " used " . count( $used ) . "x\n"  );
					}
				} else {
					if ( $soubor->dbDeleteId( $clip->ei_file ) ) {
						if ( $this->getOption( 'verbose' ) ) {
							$this->output( "Remove: " . $clip->ei_file . "\n" );
						}
					}
				}
			}
			if ( $this->getOption( 'verbose' ) ) {
				echo "Done\n";
			}
		}
		return true;
	}

}

$maintClass = CleanUnusedClips::class;
require_once RUN_MAINTENANCE_IF_MAIN;

