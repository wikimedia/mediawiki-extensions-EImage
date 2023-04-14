<?php
/**
 * Implements Special:EImagePages SpecialPage for EImage extension
 * This page lists all clips created by the EImageIMG class
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\EImage;

//use MediaWiki\MediaWikiServices;
use EImageINFO;
use EImageBOX;
use FormatJson;
use FormSpecialPage;
use Html;
use HTMLForm;
use MediaWiki\Revision\RevisionLookup;
use Parser;
use ParserFactory;
use ParserOptions;
use SpecialPage;
use SearchEngineFactory;
use Title;

#class SpecialEImagePages extends \IncludableSpecialPage {
class SpecialEImagePages extends SpecialPage {

	/** @var SearchEngineFactory */
	private $searchEngineFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var ParserFactory */
	private $parserFactory;

	/**
	 * @param string $par
	 */
/*
	public function execute( $par ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:EImage' );
		parent::execute( $par );
//		if ( $this->title instanceof Title ) {
//			$id = $this->getRequest()->getInt( 'id' );
//			$this->showCitations( $this->title, $id );
//		}
		return true;
	}
*/


//print_r('hjhjhjhj');
	/**
	 * @param RepoGroup $repoGroup
	 * @param ILoadBalancer $loadBalancer
	 * @param CommentStore $commentStore
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserCache $userCache
	 */

	public function __construct() {
		parent::__construct( 'EImagePages' );
		$this->mIncludable = true;
	}


	/**
	 * Hlavní výkonná funkce
	 * $par je uživatelské jméno. By default prázdné
	 */

	public function execute( $par ) {
		global $wgScript;
		$this->outputHeader();
		$this->addHelpLink( 'Extension:EImage' );

		$output = $this->getOutput();
		if ( !$this->mIncluding ) {
			$this->setHeaders();
			$output->addWikiMsg( $this->msg( 'eimagepages-nocache' ) );
			$output->addWikiMsg( $this->msg( 'eimagepages-expire' ) );
			// nefungují šablony
			// $output->addHtml( $vysledek );
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->startAtomic( __METHOD__ );
			$items = $dbw->select(
				'ei_cache',
				[ 'ei_eid', 'ei_clip', 'ei_file', 'ei_counter', 'ei_ctime', 'ei_expire' ],
				'ei_expire > 0',
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
			foreach ( $items as $row ) {
				$eid = $row->ei_eid;
				$image = $row->ei_file;
				$clip = FormatJson::decode( $row->ei_clip, true );
				$string = "&emsp;<b>[0]: </b>";
				if ( substr( $clip['source'], 0, 4 ) === 'http' ) {
					$string .= EImageBox::poach( '<a href="' . $clip['source'] . '">' . $clip['source'] . '</a>' );
				} else {
					if ( $clip['page'] != 0 ) {
						$string .= EImageBOX::poach( '<a href="' . $wgScript . '?title=File%3A' . $clip['source'] . '&page=' . $clip['page'] . '">' . $clip['source'] . '</a>' );
					} else {
						$string .= EImageBox::poach( '<a href="' . $wgScript . '?title=File%3A' . $clip['source'] . '">' . $clip['source'] . '</a>' );
					}
				}
				$string .= '<br />';
				if ( $clip['page'] != 0 ) $string .= $this->cellItem( 'page', $clip['page'] );
				if ( $clip['dpi'] != 300 ) $string .= $this->cellItem( 'dpi', $clip['dpi'] );
				if ( $clip['iw'] != 0 ) $string .= $this->cellItem( 'width', $clip['iw'] );
				if ( $clip['ih'] != 0 ) $string .= $this->cellItem( 'height', $clip['ih'] );
				if ( $clip['cw'] != 0 ) $string .= $this->cellItem( 'crop', $clip['cx'] . '&nbsp;' . $clip['cy'] .'&nbsp;' . $clip['cw'] . '&nbsp;' . $clip['ch'] . '&nbsp;' . $clip['resize'] );
				$dbw->startAtomic( __METHOD__ );
				$pages = $dbw->select(
					'ei_pages',
					[ 'ei_page' ],
					[ 'ei_image' => $image ],
					__METHOD__
					);
				$dbw->endAtomic( __METHOD__ );
				foreach ( $pages as $page ) {
					$info = EImageINFO::dbGetPage( $page->ei_page );
					// print_r( $info );
					switch ( $info['namespaceid'] ) {
					case 250:
						$string .= EImageBOX::poach( '<a href="' . $wgScript . '/Page:' . $info['title'] . '">' . 'Page:' . $info['title'] . '</a><br />' );
						break;
					default:
						$string .= EImageBOX::poach( '<a href="' . $wgScript . '/' . $info['title'] . '">' . $info['title'] . '</a><br />' );
						break;
					}
				}
				$output->wrapWikiMsg(
					"{{#eibox:" . $image .
					"|85|padding=0 0 0 180px|" .
					"<span style='font-size:0.7rem;'>" .
					"&emsp;<b>eid: </b>" . $eid . "<br />" .
					"&nbsp;<b>clip: </b>" . $image . "<br />" .
					$string .
					"</span>" .
					"}}"
					);
				$output->wrapWikiMsg( "{{#eibox:/d/d7/dot.png|100|color=red|}}" );
			}
		} else {
			// fungují šablony!!!
			$output->wrapWikiMsg( "<div class=\"error\">$1</div>", 'Výstup při {{big|vložení}} do stránky přes {-{Special:EImagePages}-}' );
		}
		return true;
	}
//		return 'ENCODED_EIMAGE_CONTENT ' . base64_encode( $block->getHtml() ) . ' END_ENCODED_EIMAGE_CONTENT';

	function cellItem( $param, $value = '' ) {
		$string = '<b>';
		$string .= $param;
		$string .= ':</b>&nbsp;';
		$string .= $value;
		$string .= '<br />';
		return $string;
	}

	/**
	 * Group SpecialPages
	 */
	protected function getGroupName() {
		return 'media';
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	public function requiresUnblock() {
		return false;
	}

	/** @inheritDoc */
	public function requiresWrite() {
		return false;
	}

}
