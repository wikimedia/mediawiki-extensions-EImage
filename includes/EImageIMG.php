<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

class EImageIMG extends EImageBOX {

	private $type = 'eimg';
	private $imgSource; // originální zdroj obrázku
	private $eid; // identifikátor lokálního souboru - nastaví se až po zpracování parametrů obrázku
	// hodnoty atributů pro crop
	// jsou v pixelech
	private $cx; // horizontální posun výřezu vůči originálu
	private $cy; // vertikální posun výřezu vůči originálu
	private $cw; // šířka zobrazené plochy – tomu bude odpovídat hodnota atributu pro CSS width;
	private $ch; // výška zobrazené plochy - té bude odpovídat hodnota atributu pro CSS height;

	// konstruktor této funkce
	function __construct() {
		$this->type;
		$this->setProperty( 'class', $this->type );
		return true;
	}

	// vrací typ tohoto objektu
	function getType() {
		return $this->type;
	}

	// vrací výchozí zdroj obrázku
	function getSource() {
		return $this->imgSource;
	}

	// testuje pouze to, jestli nějaký zdroj obrázku vůbec je
	function testImg() {
		if ( $this->content ) {
			// Přerazí obsah atributu title, nastavený přes alt=
			$this->setProperty( 'title', $this->content );
		}
		if ( empty( $this->imgSource ) ) {
			if ( isset( $this->attribute['index'][0] ) ) {
				$this->imgSource = $this->attribute['index'][0];
				if ( $this->imgSource == 'none' ) {
					return false;
				} else {
					// nějaký zdroj obrázku existuje
					return true;
				}
			} else {
				return false;
			}
		}
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		return Html::rawElement( 'div', $this->property, '' );
	}

}
