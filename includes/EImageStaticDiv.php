<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

use MediaWiki\MediaWikiServices;

class EImageQR extends EImageBOX {
    public $width;
    public $heigth;
    public $content;
    private $type = 'eqrcode';
    private $name = 'none';
    private $eid; // identifikátor lokálního souboru

//    function __construct() {
//	$this->type;
//	return true;
//    }

    function getType() {
	return $this->type;
    }

    function getBgName() {
	$this->eid = md5($this->name);
	$this->eid .= md5($this->content);
	return $this->eid;
    }
}

class EImageIMG extends EImageBOX {

	private $type = 'eimg';
	private $imgSource; // originální zdroj obrázku
	private $eid; //identifikátor lokálního souboru - nastaví se až po zpracování parametrů obrázku
	// hodnoty atributů pro crop
	// jsou v pixelech
	private $cx; // horizontální posun výřezu vůči originálu
	private $cy; // vertikální posun výřezu vůči originálu
	private $cw; //šířka zobrazené plochy – tomu bude odpovídat hodnota atributu pro CSS width;
	private $ch; //výška zobrazené plochy - té bude odpovídat hodnota atributu pro CSS height;

	// konstruktor této funkce
	function __construct() {
		$this->type;
		$this->setProperty('class', $this->type);
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
		if ($this->content) {
			// Přerazí obsah atributu title, nastavený přes alt=
			$this->setProperty( 'title', $this->content );
		}
		if (empty($this->imgSource)) {
			if (isset($this->attribute['index'][0])) {
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

class EImageBOX {
    public $width; // šířka boxu
    public $height; // výška boxu
    public $css = []; //parametry stylu jako pole
    public $bgSource = 'none'; // pozadí boxu
    public $attribute = Array ( 'index' => [] , 'name' => [] );
    public $content;
    public $property = [];
    public $params = []; // pole s vlastnostmi boxu
    private $type = 'eibox';
    private $name = 'none';
    private $eid;

	function __construct() {
		$this->type;
		$this->setProperty('class', $this->type);
		return true;
	}

	function getType() {
		return $this->type;
	}

	// Properties jsou vlastnosti předané jako parametry
	function printProperties() {
		return serialize($this->property);
	}
	function getProperty( $attr ) {
		if (isset($this->property[$attr])) {
			return $this->property[$attr];
		}
	}
	function setProperty( $attr, $value ) {
		if (!empty($value)) {
			if (!empty($attr)) {
				$this->property[$attr] = $value;
				return;
			}
		}
	}

	// Nastavení parametrů stylů
	// nastavení obrázku na pozadí (1 pozice)
	function setBackground( $img ) {
		if (!empty($img)) {
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
			switch ($this->attribute['name']['align']) {
				case 'relative' : $css = $this->getProperty('style');
					$this->addCssString( "position:relative" );
					break;
				case 'absolute' : $css = $this->getProperty('style');
					// nastavit i ostatní parametry
					$this->addCssString( "position:absolute" );
					break;
			}
		}
	}

	// Nastavení okrajů boxu, v závislosti na zarovnání boxu 'border='
	function setBorder() {
		if ( isset($this->attribute['name']['border']) ) {
			$border = $this->attribute['name']['border'];
			switch ($this->attribute['name']['align']) {
				case 'left' :
					$this->addCssString( "margin: {$border}px {$border}px {$border}px 0" );
					break;
				case 'underleft' :
					$this->addCssString( "margin: {$border}px {$border}px {$border}px 0" );
					break;
				case 'right' :
					$this->addCssString( "margin: {$border}px 0 {$border}px {$border}px" );
					break;
				case 'underright' :
					$this->addCssString( "margin: {$border}px 0 {$border}px {$border}px" );
					break;
				case 'center' :
					$this->addCssString( "margin: {$border}px auto {$border}px auto" );
					break;
				case 'undercenter' :
					$this->addCssString( "margin: {$border}px auto {$border}px auto" );
					break;
			}
		}
	}

	// Zarovnání boxu
	function setAlign() {
		if ( isset($this->attribute['name']['align']) ) {
			switch ($this->attribute['name']['align']) {
				case 'left' :
					$this->addCssString( "float:left" );
					break;
				case 'underleft' :
					$this->addCssString( "float:left" );
					$this->addCssString( "clear:both" );
					break;
				case 'right' :
					$this->addCssString( "float:right" );
					break;
				case 'underright' :
					$this->addCssString( "float:right" );
					$this->addCssString( "clear:both" );
					break;
				case 'center' :
					$this->addCssString( "display:block" );
					break;
				case 'undercenter' :
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
		list ($attr, $value) = explode( ':', $string);
		$this->css[trim($attr)] = trim($value);
		$this->setCss();
	}

	function getCss() {
		$css = '';
		foreach ( array_keys($this->css) as $key ) {
//print(gettype($key);	
			if ($key != '') {
				$css .= $key;
				$css .= ':';
				$css .= $this->css[$key];
				$css .= ';';
			}
		}
		$this->property['style'] = $css ;
		return $this->property['style'];
	}

	// obrázek na pozadí
	function getBackground() {
		if (empty($this->bgSource)) {
			if (isset($this->attribute['index'][0])) {
				$this->bgSource = $this->attribute['index'][0];
				if ( $this->bgSource == 'none' ) {
					// nastavit pozadí bez obrázku
				} else {
					// Otestovat zdroj obrázku true pokud existuje
				}
			} else {
				$this->bgSource = 'none' ;
			}
		} else {
			$this->bgSource = 'none' ;
		}
		return $this->bgSource;
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		$this->setCss();
		return Html::element( 'div', $this->property, $this->content );
	}

}

class EImageStaticDiv {

	/**
	 * Error message constants
	 */
	const ERR_INVALID_TITLE = 'eimage-invalid-title';
	// element #eimg musí mít jako parametr zdroj obrazových dat
	const ERR_NONE_IMG = 'eimg-need-img';
	const ERR_NOT_EXIST = NULL;
	const ERR_UNKNOWN_VALUE = 0;


    // čas vytvoření
    private $eiTimeOg;
//    private integer $eiTimeOg;
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

//    function __construct() {
//	$this->eiTimeOg = new DateTimeImmutable();
//    }

    /*
     * Dotaz do databáze, jestli už náhodou objekt neexistuje
     * https://www.mediawiki.org/wiki/Manual:Database_access
     *
     * @param Parser $parser: MediaWiki parser object.
     * @param PPFrame $frame: The frame to use for expanding any template variables.
     * @param Array $args
     * @return Array or false
     */
	private static function eimageRequest( $parameters ) {
		global $wgEImageNoCache;

		if ( $wgEImageNoCache ) {
			return false;
		}
		$loadbalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbrequest = $loadbalancer->getConnectionRef( DB_PRIMARY );

		$result = $dbrequest->newSelectQueryBuilder()
				->select( [ 'ei_image', 'ei_width' ] )
				->from( 'eimage_metadata_cache' )
				->where( "ei_width > 0" )
				->caller( __METHOD__ )
				->fetchResultSet();

		foreach ( $result as $row ) {
//			print 'Category ' . $row->cat_title . ' contains ' . $row->cat_pages . " entries.\n";
//			print '<-- ' . print_r($row) . ' -->';
		}
//		// zpracování dotazu
//		foreach ( $result as $row ) {
//			print $row->pole;
//			}
		return true;
	}


	/*
	 * Čas po kterém bude potřeba udělat kontrolu existence
	 *
	 * @param integer  : Timestamp of the creation
	 * @return integer : Timestamp of the expiration
	 */
	private static function expirationTime( $time ) {
		global $wgEImageStaleMinutes;

		if ( is_int($wgEImageStaleMinutes) ) {
			$expire = $time + ( $wgEImageStaleMinutes * 60 );
		} else {
			$expire = $expire + 3600 ;
		}
		return $expire;
	}

	/* Zapíše info o vygenerovaném obrázku do tabulky
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

	/* Vrací cestu do keše vygenerovaných obrázků
	*/
	private static function getPath() {
		return true;
	}

	/* Zakládá repozitář obrázků
	*/
	private static function eimageStorage() {
		global $wgEImageFSRepos;

		$repository = self::getFileRepo( $wgEImageFSRepos['eimagecache'] );
		$path = $repository->getZonePath( 'public' ) . '/' . $this->getPath();
	}

	/* Vygeneruje do repozitáře obrázek dle zadaných parametrů
	*/
	private static function eimageCreate( $parameters ) {
		global $wgEImageNoCache;
		// tady bude kód, který řešil php skript crop.php
		// …
		// po vygenerování souboru zapíše info o souboru do tabulky
		if ( self::eimageInsertItem( $parameters ) ) {
			return true;
		}
		return false;
	}

	/* Vygeneruje do repozitáře obrázek dle zadaných parametrů
	*/
	private static function eimageFileExist( $parameters = NULL ) {
		// pokud soubor existuje, zaktualizuje v tabulce čas expirace
		return false;
	}

	/* zjistí, je-li v repozitáři obrázek dle zadaných parametrů
	* @param String $serialpole: serializované pole
	* @return Boolean
	*/
	private static function eimageTest( $parameters = NULL ) {
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
		// false. Pokud je, nebo ho nechá vytvořit, vrátí Array
		// Vrátí-li pouze true, požadavek na vytvoření se zatím zpracovává
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
		$lockManagerGroup = MediaWikiServices::getInstance()->getLockManagerGroupFactory()
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



	/*
	* Add input parameters into array $object->attribute
	*
	* @param PPFrame $frame: The frame to use for expanding any template variables.
	* @param Array $args
	* @param Object  $object
	* @return Object
	*/
	private static function parameterParser ( Parser $parser, PPFrame $frame, $args, $object ) {
		// Read the rest of the user input.
		$i = 0;
		foreach ( $args as $arg ) {
			$arg = trim( $frame->expand( $arg ) );
			switch (trim(strstr( $arg, '=', true ))) {
				case 'class'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					// Přiřazení dalších CSS tříd, mimo výchozí
					$class = $object->getProperty( 'class' );
					$object->attribute['name']['class'] = $string;
					$object->setProperty( 'class', $class . ' ' . $string );
					continue 2;
				case 'id'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					// Nastavení identifikátoru bloku (id=)
					$object->attribute['name']['id'] = $string;
					$object->setProperty( 'id', $string );
					continue 2;
				case 'alt'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['alt'] = $string;
					$object->setProperty( 'title', $string );
					continue 2;
				case 'absolute'	:
				case 'relative'	: $array = preg_split("/[\s,=]+/", $arg);
					// nastavení parametrů pro absolutní umístění
					$object->attribute['name'][$array[0]] = trim( substr( strstr( $arg, '=' ), 1) );;
					$object->addCssString( "position:{$array[0]}" );
					// nastavení parametrů pro absolutní umístění
					// top, left, width, height, z-index
					// 0    0     0 (dynamická šířka) 0 (dynamická výška) 0 (neřešit)
					if (isset($array[1])) $object->addCssString( "left:{$array[1]}" );
					if (isset($array[2])) $object->addCssString( "top:{$array[2]}" );
					if (isset($array[3])) {
						if ( $array[3] != '0' ) $object->addCssString( "width:{$array[3]}" );
					}
					if (isset($array[4])) {
						if ( $array[4] != '0' ) $object->addCssString( "height:{$array[4]}" );
					}
					if (isset($array[5])) {
						if ( $array[5] != '0' ) $object->addCssString( "right:${array[5]}" );
					}
					if (isset($array[6])) {
						if ( $array[6] != '0' ) $object->addCssString( "bottom:{$array[6]}" );
					}
					if (isset($array[7])) {
						if ( $array[7] != '0' ) $object->addCssString( "z-index:{$array[7]}" );
					}
					if (isset($array[8])) {
						if ( $array[8] != '0' ) $object->addCssString( "scale:{$array[8]}" );
					}
					continue 2;
				case 'page'	: $string = (int) trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['page'] = $string;
					continue 2;
				case 'width'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					// pokud je objektem obrázek, půjde o výchozí šířku obrázku, ze které se bude dělat crop
					$object->attribute['name']['width'] = $string ;
					continue 2;
				case 'border'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['border'] = $string;
					continue 2;
				case 'crop'	: $object->attribute['name']['crop']	= trim( substr( strstr( $arg, '=' ), 1) );
					continue 2;
				case 'color'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['color'] = $string;
					continue 2;
				case 'rotate'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['rotate'] = $string;
					continue 2;
				case 'resize'	: $string = trim( substr( strstr( $arg, '=' ), 1) );
					$object->attribute['name']['resize'] = $string;
					continue 2;
				}
			if ( in_array( $arg, array ( 'absolute', 'relative') ) ) {
				$object->attribute['name']['position'] = $arg;
				$object->addCssString( "position:{$arg}" );
				continue;
				}
			if ( in_array( $arg, array ( 'left', 'right', 'center', 'justify', 'underleft', 'underright', 'undercenter', 'inherit') ) ) {
				$object->attribute['name']['align'] = $arg;
				continue;
				}
			$object->attribute['index'][$i] = $arg;
			$i += 1;
//			$text = $parser->recursiveTagParseFully( $arg, $frame );
			}
		$object->setPercentualWidth();
		$object->setBorder();
		$object->setColor();
		$object->setAlign();
		$object->setRotate();
		$object->content = $arg ;
		return $object;
	}


	/*
	* Read user input and return <div /> with "position:relative;" in its style,
	* which may be usable as container for wiki code.
	*
	* @param Parser $parser: MediaWiki parser object.
	* @param PPFrame $frame: The frame to use for expanding any template variables.
	* @param Array $args
	* @return string
	*/
	public static function block( Parser $parser, PPFrame $frame, $args ) {
		$object = new EImageBOX();
		$block = self::parameterParser( $parser, $frame, $args, $object );
//		$div = new EImageQR();
//		$div->content = $parameters['content'];
		if ( $block->getBackground() == 'none' ) {
			// Funkce #eibox s parametrem none, nebo bez obrázku se interpretuje bez obrázku na pozadí
//			return Html::noticeBox( "Není co kešovat, blok je bez obrázku na pozadí" , $block );
		} else {
			// Zpracuje se obrázek na pozadí
			return Html::noticeBox( "Nejprve je potřeba zpracovat obrázek" . $block->getBackground() , $block );
		}
//		return Html::noticeBox( "Aktuální styl: " . $block->getCss() , $block );
		return $block->getHtml();
	}

	/*
	* Read user input and return <div /> with "position:relative;" with image as
	* background, but content (last parameter) is viewed as alternative text.
	* It's a replacement for deprecated tag <img />
	*
	* @param Parser $parser: MediaWiki parser object.
	* @param PPFrame $frame: The frame to use for expanding any template variables.
	* @param Array $args
	* @return string
	*/
	public static function image( Parser $parser, PPFrame $frame, $args ) {
		$object = new EImageIMG;
		$image = self::parameterParser( $parser, $frame, $args, $object );
		$image->setCssString( "position:relative" );
		if (!$image->testImg()) {
			// Funkce #eimg s parametrem none, nebo bez obrázku nemůže být prezentována jako obrázek
			return self::ERR_NONE_IMG;
		}
		return $image->getHtml();
	}

	/*
	* Read user input and return <div /> with "position:relative;" with image as
	* background, but content (last parameter) is viewed as alternative text.
	* It's a replacement for deprecated tag <img />
	*
	* @param Parser $parser: MediaWiki parser object.
	* @param PPFrame $frame: The frame to use for expanding any template variables.
	* @param Array $args
	* @return string
	*/
	public static function qrcode( Parser $parser, PPFrame $frame, $args ) {
		$function = Array ( 'type' => 'qrcode' );
		$parameters = self::parameterParser( $parser, $frame, $args, $function );
		// Funkce specifické pro generování obrázku s QR kódem
		self::eimageTest( $parameters );


		// aby se zobrazoval alternativní text, musí být vložen do atributu title=
		// <div title="I AM HELLO WORLD">HELLO WORLD</div>
//	echo '<!-- ' . print_r($properties) . ' -->';
		return $parameters['type'];
	}

    /*
     * Get thumbnail by width, what is input for action crop
     *
     * @param url
     * @return file (DBKey)
     */
    private static function imageCrop( $url ) {
        return true;
    }

}
