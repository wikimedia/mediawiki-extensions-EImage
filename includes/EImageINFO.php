<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class EImageINFO {
	private $id; // id stránky či souboru
	private $title; // Název stránky
	private $image; // md5 hash obrázku vygenerovaného přes EImageIMG
	private $namespaceid; // číselné id jmenného prostoru
	private $redirect; // přesměrování
	private $len; // velikost
	private $content; // model obsahu
	private $lang; // jazyk stránky
	private $type = 'einfo';
	private $eid; // identifikátor lokálního souboru

	/**
	 * Get 'ei_file' values of all clips used on the page, identified by curid
	 *
	 * @param int $curid DB id of the article
	 * @return mixed Array or false
	 */
	public static function dbGetClipsByCurid( $curid ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'ei_pages',
			'ei_image',
			[ 'ei_page' => $curid ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		if ( count( $result ) > 0 ) {
			$i = [];
			foreach ( $result as $row ) {
				$i[] = $row->ei_image;
			}
			return $i;
		}
		return false;
	}

	/**
	 * Try get item from the database by the md5 hash of file
	 *
	 * @param string $image sha1 checksum content of the clip
	 * @return mixed Array or false
	 */
	public static function dbGetClipInfoByHash( $image ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'ei_cache',
			[
				'ei_id',
				'ei_eid',
				'ei_clip',
				'ei_origin_exif',
				'ei_counter',
				'ei_width',
				'ei_height',
				'ei_expire',
				'ei_type'
			],
			[ 'ei_file' => $image ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		if ( count( $result ) > 0 ) {
			$i = [];
			foreach ( $result as $row ) {
				$i['id'] = $row->ei_id;
				$i['eid'] = $row->ei_eid;
				$i['clip'] = $row->ei_clip;
				$i['file'] = $image;
				$i['exif'] = $row->ei_origin_exif;
				$i['counter'] = $row->ei_counter;
				$i['width'] = $row->ei_width;
				$i['height'] = $row->ei_height;
				$i['expire'] = $row->ei_expire;
				$i['type'] = $row->ei_type;
			}
			return $i;
		}
		return false;
	}

	/**
	 * Try get from the database value of the ei_file field, by ei_eid value
	 *
	 * @param string $hash is sha1 checksum of the JSON parameters used to the clip create
	 * @return string sha1 checksum content of the clip or false
	 */
	public static function dbGetHashByHash( $hash ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->selectField(
			'ei_cache',
			'ei_file',
			[ 'ei_eid' => $hash ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		if ( $result ) {
			return $result;
		}
		return false;
	}

	/**
	 * Try get item from the database by the curid string
	 *
	 * @param int $curid page use clip
	 * @return mixed Array or false
	 */
	public static function dbGetPage( $curid ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'page',
			[
				'page_namespace',
				'page_title',
				'page_is_redirect',
				'page_len',
				'page_content_model',
				'page_lang'
			],
			[ 'page_id' => $curid ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		if ( count( $result ) > 0 ) {
			$i = [];
			foreach ( $result as $row ) {
				$i['title'] = $row->page_title;
				$i['namespaceid'] = $row->page_namespace;
				$i['redirect'] = $row->page_is_redirect;
				$i['size'] = $row->page_len;
				$i['content_model'] = $row->page_content_model;
				$i['lang'] = $row->page_lang;
			}
			return $i;
		}
		return false;
	}

	/**
	 * Try get item from the database by the title string.
	 * For same title can be founded more pages in more namespaces.
	 *
	 * @param string $title name of the article
	 * @param int|null $namespace if is null, list articles by identical title from all namespaces
	 * @return mixed Array or false
	 */
	public static function dbGetPageByTitle( $title, $namespace = null ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'page',
			[
				'page_id',
				'page_namespace',
				'page_title',
				'page_is_redirect',
				'page_len',
				'page_content_model',
				'page_lang'
			],
			[ 'page_title' => $title ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		if ( count( $result ) > 0 ) {
			$i = [];
			foreach ( $result as $row ) {
				if ( $namespace === null ) {
					$i['page_id']['title'] = $row->page_title;
					$i['page_id']['namespaceid'] = $row->page_namespace;
					$i['page_id']['redirect'] = $row->page_is_redirect;
					$i['page_id']['size'] = $row->page_len;
					$i['page_id']['content_model'] = $row->page_content_model;
					$i['page_id']['lang'] = $row->page_lang;
				} else {
					if ( $row->page_namespace == $namespace ) {
						$i['curid'] = $row->page_id;
						$i['title'] = $row->page_title;
						$i['namespaceid'] = $row->page_namespace;
						$i['redirect'] = $row->page_is_redirect;
						$i['size'] = $row->page_len;
						$i['content_model'] = $row->page_content_model;
						$i['lang'] = $row->page_lang;
					}
				}
			}
			return $i;
		}
		return false;
	}

	/**
	 * Try get item from the database by the eid string
	 *
	 * @return bool
	 */
	function dbGetByEid() {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'ei_cache',
			[
				'ei_id',
				'ei_file',
				'ei_origin_exif',
				'ei_counter',
				'ei_width',
				'ei_height',
				'ei_expire',
				'ei_type'
			],
			[ 'ei_eid' => $this->eid ],
			__METHOD__
			);
		if ( count( $result ) > 0 ) {
			foreach ( $result as $row ) {
				$this->id = $row->ei_id;
				$this->image = $row->ei_file;
				$this->exif = $row->ei_origin_exif;
				$this->counter = $row->ei_counter;
				$this->width = $row->ei_width;
				$this->height = $row->ei_height;
				$this->expire = $row->ei_expire;
				$this->dbmimetype = $row->ei_type;
			}
		}
		$dbw->endAtomic( __METHOD__ );
		if ( strlen( $this->image ) > 0 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This method is used by #einfo. Output depend not only $name (file or the clip identifier),
	 * but on meta value too.
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @param string $meta Metadata name
	 * @return string
	 */
	public static function getInfo( $parser, $name = '', $meta = '' ) {
		if ( empty( $name ) ) {
			// info se bude týkat stránky ze které se funkce volá
			$idpage = (int)RequestContext::getMain()->getWikiPage()->getId();
			return $idpage;
		} elseif ( is_numeric( $name ) ) {
			// curid
			$page = self::dbGetPage( $name );
			$string = $name;
			foreach ( $page as $key => $value ) {
				$string .= ";{$key}={$value}";
			}
			return $string;
		} else {
			// hash or article?
			$image = self::dbGetHashByHash( $name );
			if ( $image ) {
				// $name == 'ei_eid' identifikátor klipu
				return 'eid';
			} else {
				$clip = self::dbGetClipInfoByHash( $name );
				if ( is_array( $clip ) ) {
					// 'ei_file' identifikátor klipu
					switch ( $meta ) {
						case 'clip': // vrátí parametry klipu uložené v JSON jako řetězec, který lze předhodit ke zpracování šabloně, nebo parsovací funkci
							$string = $clip['eid'];
							foreach ( FormatJson::decode( $clip['clip'], true ) as $key => $value ) {
								$string .= ";{$key}={$value}";
							}
							return $string;
						case 'exif': // vrátí exif tagy, uložené v JSON jako řetězec, který lze předhodit ke zpracování šabloně, nebo parsovací funkci
							$string = $clip['eid'];
							foreach ( FormatJson::decode( $clip['exif'], true )[0] as $key => $value ) {
								$string .= ";{$key}={$value}";
							}
							return $string;
						default:
							return $clip['eid'];
						// return 'clip';
					}
				} else {
					// titul článku, nebo soubor?
					$stranka = Title::newFromText( $name );
					if ( $stranka instanceof Title ) {
						$page = self::dbGetPageByTitle( $stranka->mTextform, $stranka->mNamespace );
						if ( is_array( $page ) ) {
							return 'article title';
						}
					}
				}
			}
		}
	}

	function getType() {
		return $this->type;
	}

}
