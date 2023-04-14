<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

class EImageBOX {
	public $width; // šířka boxu
	public $height; // výška boxu
	public $css = []; // parametry stylu jako pole
	public $style = [];
	public $onclick;
	public $title;
	public $bgSource = 'none'; // pozadí boxu
	public $attribute = [ 'index' => [] , 'name' => [] ];
	public $content;
	public $property = [];
	public $params = []; // pole s vlastnostmi boxu
	private $type = 'eibox';
	private $name = 'none';
	private $eid;
	private $id;

	/**
	 * Strings for identify content encoded by base64
	 *
	 * Example using:
	 *
	 *    $this->base64id[ substr( $string, 0, 7 ) ]
	 *
	 * @var array
	 */
	public $base64id = [
		'Qk3qewc' => 0, // bmp
		'/9j/4AA' => 1, // jpg
		'R0lGODl' => 2, // gif
		'iVBORw0' => 3, // png
		'PD94bWw' => 4, // svg
		'JVBERi0' => 5, // pdf
		'0M8R4KG' => 6, // doc
		'QVQmVEZ' => 7, // djvu
		'ewogICJ' => 8, // json
		'UklGRiR' => 9, // wav
		'SUQzBAB' => 10, // mp3
		'TVRoZAA' => 11, // midi
		'ZkxhQwA' => 12, // flac
		'UEsDBBQ' => 13, // zip (odt, docx, aj )
		'AH9qqG1' => 14, // mov (raw)
		'AAAAFGZ' => 15, // mov
		'UklGRoI' => 16, // avi
		'GkXfowE' => 17, // mkv
		'AAAAIGZ' => 18, // mp4
		'PG1lZGl' => 19, // xml (mediawiki)
		'PD94bWw' => 20, // xml (api)
		'PGV4cG9' => 21, // xml (database)
		'PD9waHA' => 22, // script (php)
		'IyEvYml' => 23, // script (/bin/bash)
		'IyEgL2J' => 24, // script (/bin/sh)
		'IyEvdXN' => 25, // script (/usr/bin/env - perl, bash, etc.)
		'/9j/2wB' => 26, // jpg
		'UklGRlw' => 27, // webp VP8 encoding
		'UklGRl4' => 28, // webp
		'UklGRij' => 29, // webp
		'AAAAIGZ' => 30 // avif
		];

	/**
	 * Suffix for file by content id (identify by base64id from strings)
	 *
	 * Example using:
	 *
	 *    $this->suffix[ $this->dbmimetype ]
	 *
	 * @var array
	 */
	public $suffix = [
		0 => '.bmp',
		1 => '.jpg',
		2 => '.gif',
		3 => '.png',
		4 => '.svg',
		5 => '.pdf',
		6 => '.doc',
		7 => '.djvu',
		8 => '.json',
		9 => '.wav',
		10 => '.mp3',
		11 => '.midi',
		12 => '.flac',
		13 => '.zip',
		14 => '.mov',
		15 => '.mov',
		16 => '.avi',
		17 => '.mkv',
		18 => '.mp4',
		19 => '.xml',
		20 => '.xml',
		21 => '.xml',
		22 => '.php',
		23 => '.sh',
		24 => '.sh',
		25 => '.sh',
		26 => '.jpg',
		27 => 'webp',
		28 => 'webp',
		29 => 'webp',
		30 => '.avif'
		];

	/**
	 * Mimetype string of the file (identify by base64id from strings)
	 *
	 * Example using:
	 *
	 *    $this->suffix[ $this->dbmimetype ]
	 *
	 * @var array
	 */
	public $mimetype = [
		0 => 'image/bmp',
		1 => 'image/jpeg',
		2 => 'image/gif',
		3 => 'image/png',
		4 => 'image/svg+xml',
		5 => 'application/pdf',
		6 => 'application/msword',
		7 => 'image/vnd.djvu+multipage',
		8 => 'application/json',
		9 => 'audio/x-wav',
		10 => 'audio/mpeg',
		11 => 'audio/midi',
		12 => 'audio/flac',
		13 => 'application/zip',
		14 => 'video/quicktime',
		15 => 'video/quicktime',
		16 => 'video/x-msvideo',
		17 => 'video/x-matroska',
		18 => 'vide/mp4',
		19 => 'application/xml',
		20 => 'application/xml',
		21 => 'application/xml',
		22 => 'application/x-php',
		23 => 'application/x-shellscript',
		24 => 'application/x-shellscript',
		25 => 'application/x-shellscript',
		26 => 'image/jpeg',
		27 => 'image/webp',
		28 => 'image/webp',
		29 => 'image/webp',
		30 => 'image/avif'
		];

	function __construct() {
		$this->type;
		$this->setProperty( 'class', $this->type );
		return true;
	}

	function getType() {
		return $this->type;
	}

	// Properties jsou vlastnosti předané jako parametry
	function printProperties() {
		return serialize( $this->property );
	}

	function getProperty( $attr ) {
		if ( isset( $this->property[$attr] ) ) {
			return $this->property[$attr];
		}
	}

	function setProperty( $attr, $value ) {
		if ( !empty( $value ) ) {
			if ( !empty( $attr ) ) {
				$this->property[$attr] = $value;
				return;
			}
		}
	}

	// Nastavení parametrů stylů
	// nastavení obrázku na pozadí (1 pozice)
	function setBackground( $img ) {
		if ( !empty( $img ) ) {
			$this->bgSource = $img;
			return true;
		} else {
			return false;
		}
	}

	// Šířka boxu v procentech (2 pozice)
	function setPercentualWidth() {
		if ( is_numeric( $this->attribute['index'][1] ) ) {
			$value = $this->attribute['index'][1];
			$this->style[] = "width:{$value}%;";
		}
	}

	// Barva pozadí 'color='
	function setColor() {
		// test syntaxe barvy
		if ( isset( $this->attribute['name']['color'] ) ) {
			$this->style[] = "background:{$this->attribute['name']['color']};";
		}
	}

	// Vnitřní odsazení 'padding='
	function setPadding() {
		if ( isset( $this->attribute['name']['padding'] ) ) {
			$this->style[] = "padding:{$this->attribute['name']['padding']};";
		}
	}

	// Natočení boxu 'rotate='
	function setRotate() {
		if ( isset( $this->attribute['name']['rotate'] ) ) {
			$value = $this->attribute['name']['rotate'];
			$this->style[] = "-webkit-transform:rotate({$value}deg);";
			$this->style[] = "-moz-transform: rotate({$value}deg);";
			$this->style[] = "-o-transform: rotate({$value}deg);";
			$this->style[] = "-ms-transform: rotate({$value}deg);";
			$this->style[] = "transform: rotate({$value}deg);";
		}
	}

	// Pozicování boxu 'absolute=' resp. 'relative='
	function setPosition() {
		if ( isset( $this->attribute['name']['position'] ) ) {
			switch ( $this->attribute['name']['align'] ) {
				case 'relative':
					$css = $this->getProperty( 'style' );
					$this->style[] = "position:relative;";
					break;
				case 'absolute':
					$css = $this->getProperty( 'style' );
					$this->style[] = "position:absolute;";
					break;
			}
		}
	}

	// Nastavení okrajů boxu, v závislosti na zarovnání boxu 'border='
	function setBorder() {
		if ( isset( $this->attribute['name']['border'] ) ) {
			$border = $this->attribute['name']['border'];
			switch ( $this->attribute['name']['align'] ) {
				case 'left':
					$this->style[] = "margin: {$border}px {$border}px {$border}px 0;";
					break;
				case 'underleft':
					$this->style[] = "margin: {$border}px {$border}px {$border}px 0;";
					break;
				case 'right':
					$this->style[] = "margin: {$border}px 0 {$border}px {$border}px;";
					break;
				case 'underright':
					$this->style[] = "margin: {$border}px 0 {$border}px {$border}px;";
					break;
				case 'center':
					$this->style[] = "margin: {$border}px auto {$border}px auto;";
					break;
				case 'undercenter':
					$this->style[] = "margin: {$border}px auto {$border}px auto;";
					break;
			}
		}
	}

	// Zarovnání boxu
	function setAlign() {
		if ( isset( $this->attribute['name']['align'] ) ) {
			switch ( $this->attribute['name']['align'] ) {
				case 'left':
					$this->style[] = "float:left;";
					break;
				case 'underleft':
					$this->style[] = "float:left;";
					$this->style[] = "clear:both;";
					break;
				case 'right':
					$this->style[] = "float:right;";
					break;
				case 'underright':
					$this->style[] = "float:right;";
					$this->style[] = "clear:both;";
					break;
				case 'center':
					$this->style[] = "display:block;";
					$this->style[] = "margin:15px auto 15px auto;";
					break;
				case 'undercenter':
					$this->style[] = "display:block;";
					$this->style[] = "clear:both;";
					break;
			}
		}
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		global $wgLocalFileRepo, $wgEImageCache;
		if ( $this->property['class'] ) {
			$params['class'] = $this->property['class'];
		}
		if ( isset( $this->attribute['name']['id'] ) ) {
			$params['id'] = $this->attribute['name']['id'];
		}
		if ( isset( $this->attribute['name']['title'] ) ) {
			$params['title'] = $this->attribute['name']['title'];
		}
		if ( isset( $this->attribute['index'][0] ) ) {
			// Na pozadí může být obrázek adresovaný přes plné URL, cestu, název, eid hash nebo náhled identifikovaný přes sha1 checksum
			if ( ( $this->attribute['index'][0] !== 'none' ) && ( !empty( $this->attribute['index'][0] ) ) ) {
				$i = substr( $this->attribute['index'][0], 0, 1 );
				switch ( $i ) {
				case 'h': // URL začíná vždy h
					$content = file_get_contents( $this->attribute['index'][0] );
					$bgsize = getimagesizefromstring( $content );
					$this->style[] = self::poach( "min-height: ". $bgsize[1] . "px;background-image:url(data:" . $bgsize['mime'] . ";base64," . base64_encode( $content ) . ");background-repeat: no-repeat; background-position: top left;" );
					break;
				case '/':
					$content = file_get_contents( $wgLocalFileRepo['directory'] . $this->attribute['index'][0] );
					$bgsize = getimagesizefromstring( $content );
					$this->style[] = self::poach( "min-height: ". $bgsize[1] . "px;background-image:url(data:" . $bgsize['mime'] . ";base64," . base64_encode( $content ) . ");background-repeat: no-repeat; background-position: top left;" );
					break;
				default: // hash clipu nikdy nezačíná písmenem h
					$hash = EImageINFO::dbGetHashByHash( $this->attribute['index'][0] );
					if ( $hash ) {
						// ei_eid
						$clip = new EImageIMG;
						$clip->setEid( $this->attribute['index'][0] );
						$clip->dbGetClip();
						$content = file_get_contents( $clip->imgStorage );
						$bgsize = getimagesizefromstring( $content );
						// clip on background
						$this->style[] = self::poach( "min-height: ". $bgsize[1] . "px;background-image:url(data:" . $bgsize['mime'] . ";base64," . base64_encode( $content ) . ");background-repeat: no-repeat; background-position: top left;" );
					} else {
						// ei_file
						$content = file_get_contents( $wgLocalFileRepo['directory'] . DIRECTORY_SEPARATOR . $wgEImageCache['path'] . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $this->attribute['index'][0] . '.png' );
						$bgsize = getimagesizefromstring( $content );
						// thumbnail on background - it use special page
						$this->style[] = self::poach( "min-height: ". $bgsize[1] . "px;background-image:url(data:" . $bgsize['mime'] . ";base64," . base64_encode( $content ) . ");background-repeat: no-repeat; background-position: top left;" );
					}
					break;
				}
			}
		}
		$params['style'] = $this->getStyle();
		return Html::rawElement( 'div', $params, $this->content );
	}

	function getStyle() {
		return implode( $this->style );
	}

	/**
	 * Ochranný obal před sanitizací MW
	 *
	 * @param string $string
	 * @return string
	 */
	public static function poach( $string ) {
		return 'ENCODED_EIMAGE_CONTENT ' . base64_encode( $string ) . ' END_ENCODED_EIMAGE_CONTENT';
	}

}
