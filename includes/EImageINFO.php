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
	 * @param string
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
	 * Try get from the database value of the ei_file field, by ei_eid value
	 *
	 * @param string $hash is sha1 checksum of the JSON parameters used to the clip create
	 * @return string sha1 checksum content of the clip or false
	 */
	public static function dbGetHashByHash( $hash ) {
		$dbw = wfGetDB( DB_PRIMARY );
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
	 * @param integer
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
	 * @return bool
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

	function getType() {
		return $this->type;
	}

}
