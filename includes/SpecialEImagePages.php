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
		$this->outputHeader();
		$this->addHelpLink( 'Extension:EImage' );
//		$optionsConfig = $config = $this->getConfig()->get( 'EImage' );

		$output = $this->getOutput();
		$seznam = $this->getCacheList();
		if ( !$this->mIncluding ) {
			$this->setHeaders();
			$output->addWikiMsg( $this->msg( 'eimagepages-nocache' ) );
			$output->addWikiMsg( $this->msg( 'eimagepages-expire' ) );
			// nefungují šablony
			$vysledek = $this->getCacheList();
			$output->addHtml( $vysledek );
//			$output->addWikiMsg( 'Vložení speciální stránky {{Special:EImagePages}}' );
		} else {
//		$output->wrapWikiMsg( "<div class=\"error\">$1</div>", 'multiboilerplate-special-no-boilerplates' );
			// fungují šablony!!!
			$output->wrapWikiMsg( "<div class=\"error\">$1</div>", 'Výstup při {{big|vložení}} do stránky přes {-{Special:EImagePages}-}' );
		}
		return true;
//		return $output;
	}

	/**
	 * Pole klipů
	 */
	function getCacheList() {
		global $wgScript;
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$items = $dbw->select(
			'ei_cache',
			[ 'ei_clip', 'ei_file' ],
			'ei_expire > 0',
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
		$string = '<table class="wikitable sortable jquery-tablesorter"><thead>';
		$string .= '<th class="headerSort" tabindex="0" role="columnheader button" title="Sort ascending">';
		$string .= $this->msg( 'eimagepages-column-clip' )->text();
		$string .= '</th>';
		$string .= '<th class="headerSort" tabindex="0" role="columnheader button" title="Sort ascending">';
		$string .= $this->msg( 'eimagepages-column-desc' )->text();
		$string .= '</th>';
		$string .= '<th class="headerSort" tabindex="0" role="columnheader button" title="Sort ascending">';
		$string .= $this->msg( 'eimagepages-column-pages' )->text();
		$string .= '</th>';
		$string .= '</thead>';
		foreach( $items as $row ) {
			$string .= '<tr>';
			$string .= '<td style="vertical-align:top;">';
			$string .= '<img src="/wiki/images/eimage/thumbs/'. $row->ei_file .'.png" alt="' . $row->ei_file . '" /></td>' ;
			$string .= '<td style="vertical-align:top;">';
			$clip = FormatJson::decode( $row->ei_clip, true );
			if ( substr( $clip['source'], 0, 4 ) === 'http' ) {
				$string .= '<a href="' . $clip['source'] . '">' . $clip['source'] . '</a>';
			} else {
				if ( $clip['page'] != 0 ) {
					$string .= '<a href="' . $wgScript . '?title=File%3A' . $clip['source'] . '&page=' . $clip['page'] . '">' . $clip['source'] . '</a>';
				} else {
					$string .= '<a href="' . $wgScript . '?title=File%3A' . $clip['source'] . '">' . $clip['source'] . '</a>';
				}
			}
			$string .= '<br />';
			if ( $clip['page'] != 0 ) $string .= $this->cellItem( 'page', $clip['page'] );
			if ( $clip['dpi'] != 300 ) $string .= $this->cellItem( 'dpi', $clip['dpi'] );
			if ( $clip['iw'] != 0 ) $string .= $this->cellItem( 'width', $clip['iw'] );
			if ( $clip['ih'] != 0 ) $string .= $this->cellItem( 'height', $clip['ih'] );
			if ( $clip['cw'] != 0 ) $string .= $this->cellItem( 'crop', $clip['cx'] . '&nbsp;' . $clip['cy'] .'&nbsp;' . $clip['cw'] . '&nbsp;' . $clip['ch'] . '&nbsp;' . $clip['resize'] );
			$string .= '</td>';
			$dbw->startAtomic( __METHOD__ );
			$pages = $dbw->select(
				'ei_pages',
				[
					'ei_page',
				],
				[ 'ei_image' => $row->ei_file ],
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
			$string .= '<td style="vertical-align:top;">';
			foreach ( $pages as $page ) {
				$info = EImageINFO::dbGetPage( $page->ei_page );
				// print_r( $info );
				switch ( $info['namespaceid'] ) {
				case 250:
					$string .= '<a href="' . $wgScript . '/Page:' . $info['title'] . '">' . 'Page:' . $info['title'] . '</a><br />';
					break;
				default:
					$string .= '<a href="' . $wgScript . '/' . $info['title'] . '">' . $info['title'] . '</a><br />';
					break;
				}
			}
			$string .= '</td></tr>';
		}
		$string .= '</table>';
		return $string;
	}

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
