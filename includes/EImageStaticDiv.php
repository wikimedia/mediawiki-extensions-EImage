<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

class EImageStaticDiv {

	/**
	 * Error message constants
	 */
	const ERR_INVALID_TITLE = 'eimage-invalid-title';
	// element #eimg musí mít jako parametr zdroj obrazových dat
	const ERR_NONE_IMG = 'eimg-need-img';
	const ERR_NOT_EXIST = null;
	const ERR_UNKNOWN_VALUE = 0;

	/**
	 * Add input parameters into array $object->attribute
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame The frame to use for expanding any template variables.
	 * @param array $args
	 * @param object $object
	 * @return object
	 */
	private static function parameterParser( Parser $parser, PPFrame $frame, $args, $object ) {
		// Read the rest of the user input.
		$i = 0;
		foreach ( $args as $arg ) {
			$arg = trim( $frame->expand( $arg ) );
			switch ( trim( strstr( $arg, '=', true ) ) ) {
				case 'class':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					// Přiřazení dalších CSS tříd, mimo výchozí
					$class = $object->getProperty( 'class' );
					$object->setProperty( 'class', $class . ' ' . $string );
					continue 2;
				case 'id':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					// Nastavení identifikátoru bloku (id=)
					$object->attribute['name']['id'] = $string;
					continue 2;
				case 'alt':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['title'] = $string;
					continue 2;
				case 'absolute':
				case 'relative':
					$array = preg_split( "/[\s,=]+/", $arg );
					// nastavení parametrů pro absolutní umístění
					$object->attribute['name'][$array[0]] = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->style[] = "position:{$array[0]};";
					/*
					 nastavení parametrů pro absolutní umístění
					 top, left, width, height, z-index
					 0    0     0 (dynamická šířka) 0 (dynamická výška) 0 (neřešit)
					*/
					if ( isset( $array[1] ) ) {
						$object->style[] = "left:{$array[1]};";
					}
					if ( isset( $array[2] ) ) {
						$object->style[] = "top:{$array[2]};";
					}
					if ( isset( $array[3] ) ) {
						if ( $array[3] != '0' ) {
							$object->style[] = "width:{$array[3]};";
						}
					}
					if ( isset( $array[4] ) ) {
						if ( $array[4] != '0' ) {
							$object->style[] = "height:{$array[4]};";
						}
					}
					if ( isset( $array[5] ) ) {
						if ( $array[5] != '0' ) {
							$object->style[] = "right:{$array[5]};";
						}
					}
					if ( isset( $array[6] ) ) {
						if ( $array[6] != '0' ) {
							$object->style[] = "bottom:{$array[6]};";
						}
					}
					if ( isset( $array[7] ) ) {
						if ( $array[7] != '0' ) {
							$object->style[] = "z-index:{$array[7]};";
						}
					}
					if ( isset( $array[8] ) ) {
						if ( $array[8] != '0' ) {
							$object->style[] = "scale:{$array[8]};";
						}
					}
					continue 2;
				case 'page':
					$string = (int)trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['page'] = $string;
					continue 2;
				case 'width':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					// pokud je objektem obrázek, půjde o výchozí šířku obrázku, ze které se bude dělat crop
					$object->attribute['name']['width'] = $string;
					continue 2;
				case 'border':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['border'] = $string;
					continue 2;
				case 'crop':
					$object->attribute['name']['crop'] = trim( substr( strstr( $arg, '=' ), 1 ) );
					continue 2;
				case 'color':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['color'] = $string;
					continue 2;
				case 'padding':
					$object->attribute['name']['padding'] = trim( substr( strstr( $arg, '=' ), 1 ) );
					continue 2;
				case 'rotate':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['rotate'] = $string;
					continue 2;
				case 'resize':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['resize'] = $string;
					continue 2;
				case 'link':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['location'] = $string;
					continue 2;
			}
			if ( in_array( $arg, [ 'absolute', 'relative' ] ) ) {
				$object->attribute['name']['position'] = $arg;
				$object->style[] = "position:{$arg};";
				continue;
			}
			if ( in_array( $arg, [ 'nocache' ] ) ) {
				$object->attribute['name']['cache'] = false;
				continue;
			}
			if ( in_array( $arg, [ 'left', 'right', 'center', 'justify', 'underleft', 'underright', 'undercenter', 'inherit' ] ) ) {
				$object->attribute['name']['align'] = $arg;
				continue;
			}
			$object->attribute['index'][$i] = $arg;
			$i += 1;
			// $text = $parser->recursiveTagParseFully( $arg, $frame );
		}
		$object->setPercentualWidth();
		$object->setBorder();
		$object->setColor();
		$object->setPadding();
		$object->setAlign();
		$object->setRotate();
		$object->content = $arg;
		return $object;
	}

	/**
	 * Read user input and return <div /> with "position:relative;" in its style,
	 * which may be usable as container for wiki code.
	 *
	 * @param Parser $parser MediaWiki parser object.
	 * @param PPFrame $frame The frame to use for expanding any template variables.
	 * @param array $args
	 * @return string
	 */
	public static function block( Parser $parser, PPFrame $frame, $args ) {
		$object = new EImageBOX;
		$block = self::parameterParser( $parser, $frame, $args, $object );
		return $block->getHtml();
	}

	/**
	 * Read user input and return <div /> with "position:relative;" with image as
	 * background, but content (last parameter) is viewed as alternative text.
	 * It's a replacement for deprecated tag <img />
	 *
	 * @param Parser $parser MediaWiki parser object.
	 * @param PPFrame $frame The frame to use for expanding any template variables.
	 * @param array $args
	 * @return string
	 */
	public static function image( Parser $parser, PPFrame $frame, $args ) {
		$object = new EImageIMG;
		$image = self::parameterParser( $parser, $frame, $args, $object );
		$image->getImage();
		return EImageIMG::poach( $image->getHtml() );
	}

	/**
	 * Read user input and return <div /> with "position:relative;" with image as
	 * background, but content (last parameter) is viewed as alternative text.
	 * It's a replacement for deprecated tag <img />
	 *
	 * @param Parser $parser MediaWiki parser object.
	 * @param PPFrame $frame The frame to use for expanding any template variables.
	 * @param array $args
	 * @return string
	 */
	public static function qrcode( Parser $parser, PPFrame $frame, $args ) {
		$function = [ 'type' => 'qrcode' ];
		$parameters = self::parameterParser( $parser, $frame, $args, $function );
		// Funkce specifické pro generování obrázku s QR kódem
		self::eimageTest( $parameters );

		// aby se zobrazoval alternativní text, musí být vložen do atributu title=
		// <div title="I AM HELLO WORLD">HELLO WORLD</div>
		//	echo '<!-- ' . print_r($properties) . ' -->';
		return $parameters['type'];
	}

}
