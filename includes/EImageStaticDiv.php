<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

use MediaWiki\MediaWikiServices;

class EImageStaticDiv {

	/**
	 * Error message constants
	 */
	const ERR_INVALID_TITLE = 'eimage-invalid-title';
	// element #eimg musí mít jako parametr zdroj obrazových dat
	const ERR_NONE_IMG = 'eimg-need-img';
	const ERR_NOT_EXIST = null;
	const ERR_UNKNOWN_VALUE = 0;

	// čas vytvoření
	private $eiTimeOg;
	// private integer $eiTimeOg;
	// čas kontroly
	private integer $eiTimeLu;
	//
	private $eiImage;
	// hodnota ei_articles obsahuje serializované pole všech old_id,
	// kde se vyskytne stejná hodnota #eimg:obrazek.jpg,
	// a zároveň řetězec pro akci crop
	private $eiArticles;
	// východí šířka - není-li uvedena, načítá se z obrázku (exif)
	private $eiWidth;
	// hash?
	private $eiFilename;
	//
	private $eiTitle;
	// serializované pole exif tagů
	private $eiComment;
	//
	private $eiImgurl;
	// název souboru v úložišti
	private $eiImgurlfs;
	//
	private $eiImgurlpage;
	//
	private $eiErrormsg;

	// function __construct() {
	//	$this->eiTimeOg = new DateTimeImmutable();
	//    }

	/**
	 * Dotaz do databáze, jestli už náhodou objekt neexistuje
	 * https://www.mediawiki.org/wiki/Manual:Database_access
	 *
	 * @param array $parameters
	 * @return bool
	 */
	private static function eimageRequest( $parameters ) {
		$loadbalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbrequest = $loadbalancer->getConnectionRef( DB_PRIMARY );

		$result = $dbrequest->newSelectQueryBuilder()
				->select( [ 'ei_image', 'ei_width' ] )
				->from( 'eimage_metadata_cache' )
				->where( "ei_width > 0" )
				->caller( __METHOD__ )
				->fetchResultSet();

		foreach ( $result as $row ) {
			// print 'Category ' . $row->cat_title . ' contains ' . $row->cat_pages . " entries.\n";
			// print '<-- ' . print_r($row) . ' -->';
		}
		// // zpracování dotazu
		//		foreach ( $result as $row ) {
		//			print $row->pole;
		//			}
		return true;
	}

	/**
	 * Čas po kterém bude potřeba udělat kontrolu existence
	 *
	 * @param int $time Timestamp of the creation
	 * @return int Timestamp of the expiration
	 */
	private static function expirationTime( $time ) {
		global $wgEImageStaleMinutes;

		if ( is_int( $wgEImageStaleMinutes ) ) {
			$expire = $time + ( $wgEImageStaleMinutes * 60 );
		} else {
			$expire = $expire + 3600;
		}
		return $expire;
	}

	/**
	 * Zapíše info o vygenerovaném obrázku do tabulky
	 *
	 * @param array $parameters
	 * @return bool
	 */
	private static function eimageInsertItem( $parameters ) {
		// čas vytvoření
		$eiTimeOg = date_timestamp_get( date_create() );
		$eiTimeLu = self::expirationTime( $eiTimeOg );
		// jméno - md5sum kombinace + md5sum šířky + md5sum crop
		print '<!-- ' . $eiTimeOg . ' – ' . $eiTimeLu . ' -->';
		print '<!-- ' . print_r( $parameters ) . ' -->';
		// lokální url (pokud existuje)
		// exif ? načíst jen pokud se změní čas vytvoření souboru
		// parametry
		return true;
	}

	/**
	 * Vrací cestu do keše vygenerovaných obrázků
	 *
	 * @return bool
	 */
	private static function getPath() {
		return true;
	}

	/**
	 * Zakládá repozitář obrázků
	 */
	private static function eimageStorage() {
		global $wgEImageFSRepos;

		$repository = self::getFileRepo( $wgEImageFSRepos['eimagecache'] );
		$path = $repository->getZonePath( 'public' ) . '/' . self::getPath();
	}

	/**
	 * Vygeneruje do repozitáře obrázek dle zadaných parametrů
	 *
	 * @param array $parameters
	 * @return bool
	 */
	private static function eimageCreate( $parameters ) {
		// tady bude kód, který řešil php skript crop.php
		// …
		// po vygenerování souboru zapíše info o souboru do tabulky
		if ( self::eimageInsertItem( $parameters ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Vygeneruje do repozitáře obrázek dle zadaných parametrů
	 *
	 * @param array|null $parameters
	 * @return bool
	 */
	private static function eimageFileExist( $parameters = null ) {
		// pokud soubor existuje, zaktualizuje v tabulce čas expirace
		return false;
	}

	/**
	 * zjistí, je-li v repozitáři obrázek dle zadaných parametrů
	 *
	 * @param array|null $parameters
	 * @return bool
	 */
	private static function eimageTest( $parameters = null ) {
		// udělá md5sum
		// 1, zkontroluje je-li soubor v databázi
		if ( self::eimageRequest( $parameters ) ) {
			// 1a, pokud je, ověří jeho existenci
			if ( self::eimageFileExist( $parameters ) ) {
				return true;
			} else {
				// 1b, pokud není, tak ho vytvoří
				// znovu, pokud od času vytvoření
				// uplynula určitá doba
				self::eimageCreate( $parameters );
			}
		} else {
			// 2, pokud je v databázi, ale na disku není, vrátí
			//// false. Pokud je, nebo ho nechá vytvořit, vrátí Array
			/// // Vrátí-li pouze true, požadavek na vytvoření se zatím zpracovává
			self::eimageCreate( $parameters );
		}
		return true;
	}

	/**
	 * @param array $info
	 * @return FileRepo
	 */
	public static function getFileRepo( $info ) {
		$repoName = $info['name'];
		$directory = $info['directory'];
		// MediaWiki 1.34+
		$lockManagerGroup = MediaWikiServices::getInstance()
			->getLockManagerGroupFactory()
			->getLockManagerGroup( WikiMap::getCurrentWikiId() );
		$info['backend'] = new FSFileBackend( [
			'name' => $repoName . '-backend',
			'wikiId' => WikiMap::getCurrentWikiId(),
			'lockManager' => $lockManagerGroup->get( 'fsLockManager' ),
			'containerPaths' => [
				"{$repoName}-public" => "{$directory}",
				"{$repoName}-temp" => "{$directory}/temp",
				"{$repoName}-thumb" => "{$directory}/thumb",
			],
			'fileMode' => 0644,
			'tmpDirectory' => wfTempDir()
			] );
		return new FileRepo( $info );
	}

/*
	Argumenty jsou předávané jako Array, kde
	- [0] je objekt funkce (cesta k obrázku)
	- [1] a další.. jsou objekty třídy PPNode_Hash_Tree, které obsahují parametry
	tyto objekty vrací pole s polema. kde title vypadá takto:
	[0] title [1] #eimg:obrázek.jpg
	V případě, že je vstup zpracováván nějakou šablonou, navalí ho tak, jak ho vrací ta šablona.
	Během dalšího zpracování přidává další argumenty. Takže lze provést zpracování pozičních i pojmenovaných atributů
	https://doc.wikimedia.org/mediawiki-core/master/php/classPPNode__Hash__Tree.html

	Test existence položky v lokální databázi však lze provést až v okamžiku, kdy proběhne detekce na crop

	1, do databáze se uloží položka teprve po zpracování obrázku – nemá to vliv na další dění!
	2, obrázek se pojmenuje jako md5sum serializovaného pole, kde je index []řetězce #eimg:obrázek.jpg volitelně rozšiřitelný o parametry pro crop
		2a, parametr title nikdy nebude delší než těch 255 znaků
		2b, parametr crop také nebude delší

	ei_imgulrfs hash souboru v úložišti

	3, při kontrole existence se zkontroluje čas expirace, na základě md5sumu se vytáhne serializované pole kde [0] bude  enkodovaný přes base64 tabulce text, vyhledá

*/
/*
	// dotaz na obrázek
	if ( self::eimageRequest() ) {
		return 'záznam existuje';
	} else {
		if ( self::eimageCreate() ) {
		return 'obrázek byl vytvořen';
		} else {
		return 'obrázek se nepodařilo vytvořit';
		}
	}
//	return false;
*/

/* Pole $properties funguje podobně jako fungovalo u šablon Image a block
	Poziční parametry
		0 – zdroj obrázku
		1 - šířka bloku v procentech
		2 - zbylý obsah – ten ale může obsahovat další kód,

	Pozn.:
		* Je-li zdroj obrázku none, zpracuje se jako volný blok a do keše se nic nezapisuje
		* Je-li zdroj obrázek, zapíše se do keše a obrázek se použije jao pozadí

	Pojmenované parametry
		crop - výřez
		width - výchozí šířka bloku (nepovinný)
		align - zarovnání
*/

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
					$object->attribute['name']['class'] = $string;
					$object->setProperty( 'class', $class . ' ' . $string );
					continue 2;
				case 'id':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					// Nastavení identifikátoru bloku (id=)
					$object->attribute['name']['id'] = $string;
					$object->setProperty( 'id', $string );
					continue 2;
				case 'alt':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['alt'] = $string;
					$object->setProperty( 'title', $string );
					continue 2;
				case 'absolute':
				case 'relative':
					$array = preg_split( "/[\s,=]+/", $arg );
					// nastavení parametrů pro absolutní umístění
					$object->attribute['name'][$array[0]] = trim( substr( strstr( $arg, '=' ), 1 ) );

					$object->addCssString( "position:{$array[0]}" );
					// nastavení parametrů pro absolutní umístění
					// top, left, width, height, z-index
					// 0    0     0 (dynamická šířka) 0 (dynamická výška) 0 (neřešit)
					if ( isset( $array[1] ) ) {
						$object->addCssString( "left:{$array[1]}" );
					}
					if ( isset( $array[2] ) ) {
						$object->addCssString( "top:{$array[2]}" );
					}
					if ( isset( $array[3] ) ) {
						if ( $array[3] != '0' ) {
							$object->addCssString( "width:{$array[3]}" );
						}
					}
					if ( isset( $array[4] ) ) {
						if ( $array[4] != '0' ) {
							$object->addCssString( "height:{$array[4]}" );
						}
					}
					if ( isset( $array[5] ) ) {
						if ( $array[5] != '0' ) {
							$object->addCssString( "right:${array[5]}" );
						}
					}
					if ( isset( $array[6] ) ) {
						if ( $array[6] != '0' ) {
							$object->addCssString( "bottom:{$array[6]}" );
						}
					}
					if ( isset( $array[7] ) ) {
						if ( $array[7] != '0' ) {
							$object->addCssString( "z-index:{$array[7]}" );
						}
					}
					if ( isset( $array[8] ) ) {
						if ( $array[8] != '0' ) {
							$object->addCssString( "scale:{$array[8]}" );
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
				case 'rotate':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['rotate'] = $string;
					continue 2;
				case 'resize':
					$string = trim( substr( strstr( $arg, '=' ), 1 ) );
					$object->attribute['name']['resize'] = $string;
					continue 2;
			}
			if ( in_array( $arg, [ 'absolute', 'relative' ] ) ) {
				$object->attribute['name']['position'] = $arg;
				$object->addCssString( "position:{$arg}" );
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
		$object = new EImageBOX();
		$block = self::parameterParser( $parser, $frame, $args, $object );
		// $div = new EImageQR();
		// $div->content = $parameters['content'];
		if ( $block->getBackground() == 'none' ) {
			// Funkce #eibox s parametrem none, nebo bez obrázku se interpretuje bez obrázku na pozadí
			// return Html::noticeBox( "Není co kešovat, blok je bez obrázku na pozadí" , $block );
		} else {
			// Zpracuje se obrázek na pozadí
			return Html::noticeBox( "Nejprve je potřeba zpracovat obrázek" . $block->getBackground(), $block );
		}
		// return Html::noticeBox( "Aktuální styl: " . $block->getCss() , $block );
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
		$image->setCssString( "position:relative" );
		if ( !$image->testImg() ) {
			// Funkce #eimg s parametrem none, nebo bez obrázku nemůže být prezentována jako obrázek
			return self::ERR_NONE_IMG;
		}
		return $image->getHtml();
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

	/**
	 * Get thumbnail by width, what is input for action crop
	 *
	 * @param string $url
	 * @return bool file (DBKey)
	 */
	private static function imageCrop( $url ) {
		return true;
	}

}
