<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

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
	 * Try get item from the database by the md5 hash of file
	 *
	 * @return mixed Array or false
	 */
	public static function dbGetClipInfoByHash( $image ) {
		$dbw = wfGetDB( DB_PRIMARY );
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
	 * Try get item from the database by the curid string
	 *
	 * @return mixed Array or false
	 */
	public static function dbGetPage( $curid ) {
		$dbw = wfGetDB( DB_PRIMARY );
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
	 * Try get item from the database by the eid string
	 *
	 * @return boolean
	 */
	function dbGetByEid() {
		$dbw = wfGetDB( DB_PRIMARY );
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
	 * STATIC METHODS
	 *
	 * Function to get the width & height of the image.
	 * Formated as string WxH for using as parameter
	 * of the 'eimage'
	 *
	 * @param Parser $parser Parser object passed a reference
	 * @param string $name Name of the image being parsed in
	 * @return string or NULL
	 */
	public static function eInfo( $parser, $name = '', $meta = '' ) {
		$value = strlen( $name );
		if ( $value == 39 ) {
			// eid or image hash
			switch ( $meta ) {
//			return $file->getWidth() . 'x' . $file->getHeight();
			default: // file -> výsledkem bude seznam stránek, které s ním pracují
				// z tabulky 'imagelinks', vytáhne 'il_from' a 'il_from_namespace'
				break;
			}
		} elseif ( $value > 0 ) {
			// curid
			$this->id = $name;
			if ( $this->dbGetPage() ) {
				switch ( $meta ) {
				case 'pagename':
					break;
				default:
					break;
				}
			}
		}
		return self::ERR_UNKNOWN_VALUE;
	}


	function getType() {
		return $this->type;
	}

}
