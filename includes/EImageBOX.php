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
	public $bgSource = 'none'; // pozadí boxu
	public $attribute = [ 'index' => [] , 'name' => [] ];
	public $content;
	public $property = [];
	public $params = []; // pole s vlastnostmi boxu
	private $type = 'eibox';
	private $name = 'none';
	private $eid;

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
			$this->addCssString( "width:{$value}%" );
		}
	}

	// Barva pozadí 'color='
	function setColor() {
		// test syntaxe barvy
		if ( isset( $this->attribute['name']['color'] ) ) {
			$this->addCssString( "background:{$this->attribute['name']['color']}" );
		}
	}

	// Natočení boxu 'rotate='
	function setRotate() {
		if ( isset( $this->attribute['name']['rotate'] ) ) {
			$value = $this->attribute['name']['rotate'];
			$this->addCssString( "-webkit-transform:rotate({$value}deg)" );
			$this->addCssString( "-moz-transform: rotate({$value}deg)" );
			$this->addCssString( "-o-transform: rotate({$value}deg)" );
			$this->addCssString( "-ms-transform: rotate({$value}deg)" );
			$this->addCssString( "transform: rotate({$value}deg)" );
		}
	}

	// Pozicování boxu 'absolute=' resp. 'relative='
	function setPosition() {
		if ( isset( $this->attribute['name']['position'] ) ) {
			switch ( $this->attribute['name']['align'] ) {
				case 'relative':
					$css = $this->getProperty( 'style' );
					$this->addCssString( "position:relative" );
					break;
				case 'absolute':
					$css = $this->getProperty( 'style' );
					// nastavit i ostatní parametry
					$this->addCssString( "position:absolute" );
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
					$this->addCssString( "margin: {$border}px {$border}px {$border}px 0" );
					break;
				case 'underleft':
					$this->addCssString( "margin: {$border}px {$border}px {$border}px 0" );
					break;
				case 'right':
					$this->addCssString( "margin: {$border}px 0 {$border}px {$border}px" );
					break;
				case 'underright':
					$this->addCssString( "margin: {$border}px 0 {$border}px {$border}px" );
					break;
				case 'center':
					$this->addCssString( "margin: {$border}px auto {$border}px auto" );
					break;
				case 'undercenter':
					$this->addCssString( "margin: {$border}px auto {$border}px auto" );
					break;
			}
		}
	}

	// Zarovnání boxu
	function setAlign() {
		if ( isset( $this->attribute['name']['align'] ) ) {
			switch ( $this->attribute['name']['align'] ) {
				case 'left':
					$this->addCssString( "float:left" );
					break;
				case 'underleft':
					$this->addCssString( "float:left" );
					$this->addCssString( "clear:both" );
					break;
				case 'right':
					$this->addCssString( "float:right" );
					break;
				case 'underright':
					$this->addCssString( "float:right" );
					$this->addCssString( "clear:both" );
					break;
				case 'center':
					$this->addCssString( "display:block" );
					break;
				case 'undercenter':
					$this->addCssString( "display:block" );
					$this->addCssString( "clear:both" );
					break;
			}
		}
	}

	// Nastavení parametrů stylu podle obsahu buňky s klíčem 'style' v poli $this->property
	function setCss() {
		// Hodnota -1 je zde kvůli tomu, že za každou hodnotou stylu by měl být středník
		// do parametru $this->property se přidávají styly jako textové řetězce!!!
		$this->property['style'] = $this->getCss();
	}

	function getCssValue( $attr ) {
		return $this->css[$attr];
	}

	function addCssString( $string ) {
		// nejprve přidat do pole $this->css (pokud není)
		// Z řetězce 'vlastnost: hodnota1 hodnota2 atd'
		// $this-css['vlastnost'] = "hodnota1 hodnota2 atd;"
		list( $attr, $value ) = explode( ':', $string );
		$this->css[trim( $attr )] = trim( $value );
		$this->setCss();
	}

	function getCss() {
		$css = '';
		foreach ( array_keys( $this->css ) as $key ) {
			// print(gettype($key);
			if ( $key != '' ) {
				$css .= $key;
				$css .= ':';
				$css .= $this->css[$key];
				$css .= ';';
			}
		}
		$this->property['style'] = $css;
		return $this->property['style'];
	}

	// obrázek na pozadí
	function getBackground() {
		if ( empty( $this->bgSource ) ) {
			if ( isset( $this->attribute['index'][0] ) ) {
				$this->bgSource = $this->attribute['index'][0];
				if ( $this->bgSource == 'none' ) {
					// nastavit pozadí bez obrázku
				} else {
					// Otestovat zdroj obrázku true pokud existuje
				}
			} else {
				$this->bgSource = 'none';
			}
		} else {
			$this->bgSource = 'none';
		}
		return $this->bgSource;
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		$this->setCss();
		return Html::element( 'div', $this->property, $this->content );
	}

}
