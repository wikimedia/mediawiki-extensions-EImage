<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class EImageIMG extends EImageBOX {

	private $type = 'eimg';
	public $imgSource; // originální zdroj obrázku
	public $imagecontent;
	public $imgStorage = '.'; // cesta do úložiště obrázků
	private $location; // pokud má div fungovat jako aktivní odkaz
	private $title; // pokud se má objevit bublina
	private $style = [];
	public $name; // jméno vytažené ze $imgSource
	private $eid; // identifikátor lokálního souboru - nastaví se až po zpracování parametrů obrázku
	// hodnoty atributů pro akci crop jsou v pixelech
	private $cx; // horizontální posun výřezu vůči originálu
	private $cy; // vertikální posun výřezu vůči originálu
	public $cw; // šířka zobrazené plochy – tomu bude odpovídat hodnota atributu pro CSS width;
	private $ch; // výška zobrazené plochy - té bude odpovídat hodnota atributu pro CSS height;
	private $resize; // přeškálování obrázku
	private $id; // číslo řádku v databázi
	private $iw; // Šířka obrázku, se kterou se bude kalkulovat pro výřez
	private $ih; // Výška obrázku, se kterou se bude kalkulovat pro výřez
	private $dbmimetype;
	private $suffix = [
			1 => '.jpg',
			2 => '.gif',
			3 => '.png'
		];
	private $local = 'none';
	private $tempdir;
	private $original;
	public $exif = [];
	public $newexif = [];

	// konstruktor této funkce
	function __construct() {
		$this->type;
		$this->setProperty( 'class', $this->type );
		return true;
	}

	/**
	 * Error message constants
	 */
	const ERR_INVALID_TITLE = 'eimage-invalid-title';
	const ERR_NOT_EXIST = null;
	const ERR_UNKNOWN_VALUE = 0;
	const E_WARNING = 'eimage-invalid-source';

	/**
	 * Version for cache invalidation.
	 */
	private const CACHE_VERSION = 1;

	/**
	 * Cache expiry time for the LilyPond version
	 */
	private const VERSION_TTL = 3600;

	/**
	 * FileBackend instance cache
	 */
	private static $backend;

	function addCount() {
		$this->counter =+ 1;
	}

	function getStyle() {
		return implode( $this->style );
	}

	/**
	 * Return image as base64 string for CSS style
	 * @return array
	 */
	public function cssImage() {
		if ( $this->imagecontent ) {
			$params = getimagesizefromstring( base64_decode( $this->imagecontent ) );
			$mime = [ NULL, 'jpeg', 'gif', 'png' ];
			$this->style[] = "background-image:url(data:image/" . $mime[ $this->dbmimetype ] . ";base64," . $this->imagecontent . ");";
		}
	}

	// Obtain a BoxedCommand
	private static function BoxedCommand() {
		// www.mediawiki.org/wiki/Manual:BoxedCommand
		return MediaWikiServices::getInstance()->getShellCommandFactory()
			// Route to $wgShellboxUrls['eimage']
			->createBoxed( 'eimage' )
			// Disable network access
			->disableNetwork()
			// Use firejail's default seccomp filter
			->firejailDefaultSeccomp();
	}

	/* DATABASE METHODS */
	/**
	 * Insert new item into the database
	 * @ return int
	 */
	function dbAddItem() {
		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->insert( 'ei_cache',
			[
			'ei_eid' => strval( $this->eid ),
			'ei_file' => strval( $this->image ),
			'ei_ctime' => $dbw->timestamp( date( DATE_ATOM, mktime() ) ),
			'ei_origin_exif' => FormatJson::encode( $this->exif, true ),
			'ei_width' => intval( $this->cw ),
			'ei_height' => intval( $this->ch ),
			'ei_type' => $this->dbmimetype
			],
			__METHOD__
		);
		$this->id = $this->id ?: $dbw->insertId(); // set for accessors
		return $this->id;
	}
	/**
	 * Vytáhne všechny expirované záznamy, u kterých nedosáhl count prahové hodnoty
	 * a ty odstraní z databáze
	 */
	function dbCleanExpiredItems() {
		// TODO
		return;
	}
	/**
	 * Projede seznam uložených souborů a ty, pro které nenajde žádný záznam v databázi
	 * odstraní
	 */
	function dbCleanOrphanedItems() {
		// TODO
		return;
	}
	/**
	 * Delete item form the database by id
	 * @ return bool
	 */
	function dbDeleteItem( $id = NULL ) {
		if ( is_null( $id ) ) return false;
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete( 'ei_cache',
			[ 'ei_id' => $id ],
			__METHOD__
			);
		return true;
	}
	/**
	 * Try get item from the database by the eid string
	 *
	 * @ return int
	 */
	function dbGetItem() {
		$dbw = wfGetDB( DB_PRIMARY );
		$result = $dbw->select( 'ei_cache',
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
			[ 'ei_eid' => $this->getEid() ],
			__METHOD__
			);
		foreach ( $result as $row ) {
			$this->id = $row->ei_id;
			$this->image = $row->ei_file;
			$this->exif = $row->ei_origin_exif;
			$this->counter = $row->ei_counter;
			$this->width = $row->ei_width;
			$this->height = $row->ei_height;
			$this->expire = $row->ei_expire;
			switch ($row->ei_type) {
				case 1 : $this->mime = 'image/jpeg';
					$this->dbmimetype = 1;
					$this->imgStorage = $this->getCacheLocal ( $this->image . $this->suffix[1] );
					break;
				case 2 : $this->mime = 'image/gif';
					$this->dbmimetype = 2;
					$this->imgStorage = $this->getCacheLocal ( $this->image . $this->suffix[2] );
					break;
				case 3 : $this->mime = 'image/png';
					$this->dbmimetype = 3;
					$this->imgStorage = $this->getCacheLocal ( $this->image . $this->suffix[3] );
					break;
				default : $this->mime = NULL;
					$this->dbmimetype = NULL;
					break;
			}
		}
		return true;
	}
	/**
	 * Insert new item into the database
	 * @ return bool
	 */
	function dbSetExpirationTime( $count, $eid ) {
		global $wgEImageCache;
		$date = new DateTimeImmutable();
		$timestamp = (int)$date->getTimestamp() + $wgEImageCache['expire'];
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->update( 'ei_cache',
			[
				'ei_counter' => $count,
				'ei_expire' => $timestamp
			],
			[ 'ei_eid' => $eid ],
			__METHOD__
			);
	}

	/* STATIC METHODS */
	/**
	 * Function to get the width & height of the image.
	 * Formated as string WxH for using as parameter
	 * of the 'eimage'
	 *
	 * @param Parser $parser Parser object passed a reference
	 * @param string $name Name of the image being parsed in
	 * @return string or NULL
	 */
	public static function eimageArea( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return $file->getWidth() . 'x' . $file->getHeight();
		}
		return self::ERR_UNKNOWN_VALUE;
	}
	/**
	 * Get EXIF metadata from file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @param string $meta Metadata name
	 * @return string
	 */
	public static function eimageExif( $parser, $name = '', $meta = '' ) {
		global $wgEImageExif;
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			$parser->getOutput()->addImage( $file->getTitle()->getDBkey() );
			switch ( $meta ) {
				case 'meta':
					break;
				default:
/*
					if ( self::testPath( $wgEImageExif['app'] ) ) {
						$exiftagy = self::readExif( $file->getLocalRefPath() );
						switch ( $meta ) {
							case 'array':
								return serialize( $exiftagy[0] );
								break;
							case 'list':
								return implode( ', ', array_keys( $exiftagy[0] ) );
								break;
							case 'template':
								break;
							default:
								return $exiftagy[0][$meta];
						}
					} else {
						$fp = fopen( $file->getLocalRefPath(), 'rb' );
						if ( !$fp ) {
							return self::ERR_NOT_EXIST;
						}
						try {
							$headers = exif_read_data( $fp );
						} catch ( Exception $e ) {
							return wfMessage( 'error_unknown_filetype' )->text();
						}
						if ( $headers ) {
							switch ( $meta ) {
								case 'serialize':
									return serialize( $headers );
								case 'json':
									return "<!-- " . print_r( $headers ) . " -->";
								default:
									break;
							}
						}
					}
*/
					break;
				}
			return $meta;
		}
		return self::ERR_NOT_EXIST;
	}
	/**
	 * Function to get the height of the image.
	 *
	 * @param Parser $parser Parser object passed a reference
	 * @param string $name Name of the image being parsed in
	 * @return mixed integer of the height or error message.
	 */
	public static function eimageHeight( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return $file->getHeight();
		}
		return self::ERR_UNKNOWN_VALUE;
	}
	/**
	 * Function to get the path of the image.
	 *
	 * @param Parser $parser Parser object passed a reference
	 * @param string $name Name of the image being parsed in
	 * @return string or NULL
	 */
	public static function eimageLocalPath( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return parse_url( $file->getURL(), PHP_URL_PATH );
		}
		return self::ERR_NOT_EXIST;
	}
	/**
	 * Get the MIME type of a file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @return string or NULL
	 */
	public static function eimageMime( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return $file->getMimeType();
		}
		return self::ERR_NOT_EXIST;
	}
	/**
	 * Get the number of pages of a file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @return int or NULL
	 */
	public static function eimagePages( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			$nrpages = $file->pageCount( $file );
			if ( $nrpages == false ) {
				return 0;
			}
			return $nrpages;
		}
		return self::ERR_NOT_EXIST;
	}
	/**
	 * Get the size of a file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @return string or NULL
	 */
	public static function eimageSize( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return htmlspecialchars( $parser->getTargetLanguage()->formatSize( $file->getSize() ) );
		}
		return self::ERR_NOT_EXIST;
	}
	/**
	 * Convert a string title into a File, returning an appropriate
	 * error message string if this is not possible
	 *
	 * The string can be with or without namespace, and might
	 * include an interwiki prefix, etc. if interwiki use
	 *
	 * @param string $text Title string
	 * @return mixed File, string or NULL
	 */
	private static function eimageTitleResolve( $text ) {
		global $wgEImageOnlyLocalSource;
		if ( $text ) {
			$title = Title::newFromText( $text );
			if ( $title instanceof Title ) {
				if ( $title->getNamespace() != NS_FILE ) {
					$title = Title::makeTitle( NS_FILE, $title->getText() );
				}
				if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
					// MediaWiki 1.34+
					if ( $wgEImageOnlyLocalSource ) {
						// Search file only local repo
						$file = MediaWikiServices::getInstance()->getRepoGroup()
							->getLocalRepo()->findFile( $title );
					} else {
						$file = MediaWikiServices::getInstance()->getRepoGroup()
							->findFile( $title );
					}
				} else {
					$file = wfFindFile( $title );
				}
				return $file instanceof File
					? $file
					: self::ERR_NOT_EXIST;
			}
		}
		return self::ERR_INVALID_TITLE;
	}
	/**
	 * Function to get the width of the image.
	 *
	 * @param Parser $parser Parser object passed a reference
	 * @param string $name Name of the image being parsed in
	 * @return mixed integer of the width or error message.
	 */
	public static function eimageWidth( $parser, $name = '' ) {
		$file = self::eimageTitleResolve( $name );
		if ( $file instanceof File ) {
			return $file->getWidth();
		}
		return self::ERR_UNKNOWN_VALUE;
	}

	// odstraní soubory co nejsou v databázi
	function cleanCacheLocal() {
		$seznam = scandir( $this->getCacheLocal() );
		// Tohle je rychlá operace, určená k odstranění expirovaných souborů
		// Je potřeba vybrat položky, co mají podprahový counter kterým ještě nevypršela expirace,
		// protože ty by měly být v RAM
		// Pokud položka v poli $seznam nebude, může být přesunutá do lokální keše
		// je třeba udělat kontrolu. Pokud v lokální keši soubor není,
		// je potřeba odstranit položku z databáze
		// ...a obráceně – pokud položka pole $seznam nebude v tom co vrátila databáze
		// je potřeba aplikovat ulink($item)
		return true;
	}

	/**
	 * PHP script pro výřez a přeškálování náhledu obrázku.
	 * @return string base64 encode image
	 */
	function crop() {
		$cx = $this->cx; // posun obrazu v pixelech; kladná hodnota doprava záporná doleva (default 0)
		$cy = $this->cy; // posun obrazu v pixelech; kladná hodnota dolů záporná nahoru (default 0)
		$cw = $this->cw; // šířka zobrazovaného výřezu v pixelech
		$ch = $this->ch; // výška zobrazovaného výřezu v pixelech
		$iw = $this->iw;
		$resize = floatval( $this->resize ); // přeškálování výřezu; 0.01 < (default 1) < 3
		$original = base64_decode( $this->originalsource );
		$params = getimagesizefromstring( $original );
		$width = intval( $params[0] );
		$height = intval( $params[1] );
		$draft = imagecreatefromstring($original);
		// Resize podle parametru width=
		if ( $iw > 0 ) {
			//echo "Scale before crop by default width {$iw}";
			$resampled = imagescale( imagecreatefromstring( $original ), $iw, -1, IMG_BICUBIC_FIXED );
		}
		/* $this->original is over */
		unset($original);
		//echo "{$this->getName()} :  {$params['mime']}; width: {$width}px; height: {$height}px;\n";
		if ( $cw == 0 ) {
			/* Original without resize */
			if ( isset($resampled) ) {
				return $this->exportNewImage( $params['mime'], $resampled );
			} else {
				return $this->exportNewImage( $params['mime'], $draft );
			}
		} else {
			// CROP
			if ( isset($resampled) ) {
				$image_c = imagecrop( $resampled, [ 'x' => abs($cx), 'y' => abs($cy), 'width' => $cw, 'height' => $ch ] );
			} else {
				// Rozměry nového obrázku s posunem
				$image_p = imagecreatetruecolor( $width, $height );
				// Naplácnutí posunutého obsahu na obrázek o původní velikosti
				imagecopyresampled( $image_p, $draft, abs($cx), abs($cy), 0, 0, $width, $height, $width, $height );
				$image_c = imagecrop( $image_p, [ 'x' => 0, 'y' => 0, 'width' => $cw, 'height' => $ch ] );
				// Výřez z posunutého obrázku
				imagedestroy( $image_p );
			}
		}
		// …a přeškálování
		if ( isset( $resize ) ) {
			// Změna velikost
			if ( $resize > 3 &&  $resize < 0.01 ) {
				die('Minimal value of the resize is 0.01 and max 3') ;
			}
			// Vypočítanou hodnotu je třeba zaokrouhlit na celé pixely
			$neww = round( $cw * $resize );
			$newh = round( $ch * $resize );
			// echo "Rescale after crop to {$neww}x{$newh} by resize ${resize}\n";
			// Zmenšení obrázku podle nastaveného poměru
			$image_r = imagecreatetruecolor( $neww , $newh );
			imagecopyresampled( $image_r, $image_c, 0, 0, 0, 0, $neww, $newh, $cw, $ch );
			imagedestroy( $image_c );
			$draft = $image_r;
		} else {
			// Odeslání výřezu bez přeškálování – image_c se zruší ve funkci exportNewImage()
			$draft = $image_c;
		}
		return $this->exportNewImage( $params['mime'], $draft );
	}

	/**
	 * Write resource as image by mimetype
	 *
	 * @param string $mimetype
	 * @param resource $image
	 * @param string $path to file
	 * @return mixed
	 */
	function exportNewImage( $mime, $image ) {
		global $wgEImageExif;
		$name = $this->getName();
		$path = tempnam( sys_get_temp_dir(), "FOO");
		switch ($mime) {
			case 'image/jpeg' : imagejpeg( $image, $path );
				$this->dbmimetype = 1;
				break;
			case 'image/gif' : imagegif( $image, $path );
				$this->dbmimetype = 2;
				break;
			case 'image/png' : imagepng( $image, $path );
				$this->dbmimetype = 3;
				break;
			}
		imagedestroy($image);
		if ( $wgEImageExif ) {
			/* Tahle část nastaví nové parametry pro exif tagy */
			$this->setNewExif();
			$data = [ array_merge( $this->exif[0], $this->newexif[0] ) ];
			$remove = [
				'RedTRC',
				'GreenTRC',
				'BlueTRC',
				'ThumbnailImage'
				];
			foreach ( $remove as $key ) {
				unset( $data[0][$key] );
			}
			$data[0]['SourceFile'] = $this->getName();
			$data[0]['HistoryWhen'] = date( DATE_ATOM, mktime() );
			/* Upravené pole exif tagů zapisuji do souboru $exifpath */
			$exifpath = "{$path}_json";
			file_put_contents( $exifpath, FormatJson::encode( $data, true ) );
			/* A tento soubor použiji jako zdroj pro exiftools */
			$command = self::BoxedCommand()
				->routeName( 'eimage-exif' );
			$result = $command
				->params(
					$wgEImageExif['app'],
					'-j' . '+=' . $exifpath,
					'-o', '-',
					$this->getName()
				)
				// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
				->inputFileFromFile( $this->getName(), $path )
				->execute();
			if ( $result->getStderr() ) {
				print_r( $result->getExitCode() );
				print_r( $result->getStderr() );
				return false;
			}
			$content = $result->getStdout();
			$this->image = sha1( $content );
			$this->imagecontent = base64_encode( $content );
			unlink( $exifpath );
			unlink( $path );
			return true;
		} else {
			/* Vypnuto použití exiftools */
			$this->image = sha1( $path );
			$this->imagecontent = base64_encode( file_get_contents( $path ) );
			unlink( $path );
			return true;
		}
	}

	/**
	 * Read content from path
	 * @return bool
	 */
	function getContent() {
		$content = file_get_contents( $this->imgStorage );
		if ( $content ) {
			$this->imagecontent = base64_encode( $content );
			return true;
		}
		return false;
	}
	/**
	 * Push content into path
	 * @return bool
	 */
	function pushContent() {
		return file_put_contents( $this->imgStorage, base64_decode ( $this->imagecontent ) );
	}


	function getCropInfo() {
		$crop = '';
		if ( $this->cx ) $crop .= strval( $this->cx );
		if ( $this->cy ) $crop .= ' ' . strval( $this->cy );
		if ( $this->cw ) $crop .= ' ' . strval( $this->cw );
		if ( $this->ch ) $crop .= ' ' . strval( $this->ch );
		if ( $this->resize ) $crop .= ' ' . strval( $this->resize );
		return $crop;
	}

	// vrátí aktuální hodnotu eid – mění se podle parametrů
	function getEid() {
		return $this->eid;
	}

	/**
	 * Soubor z názvem který odpovídá jeho kontrolnímu součtu, by už měl být
	 * v lokálním úložišti. Pokud tam není, je třeba zjistit, zda už byl přesunut
	 * z dočasného úložiště v rámci RAM.
	 * Bude-li tam, je třeba ho z RAM přesunou do lokálního úložiště
	 * Nebude-li tam, je potřeba pokračovat v dalším zpracování a vygenerovat jej
	 * na základě parametrů znovu.
	 */
	function getFileFromStorageBySum() {
		return;
	}

	/**
	 * Na základě dotazu do databáze je znám kontrolní součet datového souboru,
	 * pod kterým by měl být uložen v adresáři eimage v rámci dočasného adresáře
	 * v RAM, protože počet přístupů ještě nedosáhl prahové hodnoty.
	 * Je potřeba ho použít a navýšit počet přístupů
	 * Pokud tam není, je nutné zkontrolovat lokální úložiště
	 */
	function getFileFromTempBySum() {
		return;
	}

	// vrací název souboru vykuchaný z $this->imgSource
	function getName() {
		$position = strrpos( $this->imgSource, '/' );
		if ( $position ) {
			$this->name = substr( $this->imgSource, $position + 1 );
		} else {
			$this->name = $this->imgSource;
		}
		return $this->name;
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		return Html::rawElement( 'div',
			[
				'class' => 'target',
				'title' => $this->content,
				'onclick' => $this->onclick,
				'style' => 'position:relative;' . $this->getStyle()
			],
				''
			);
	}

	/**
	 * Get content from source and put into temporary file 'original',
	 * set path into 'originalsource' property of the object EImageIMG
	 * and return to next reworking.
	 * It's first function for call, if you want rework content image
	 */
	function getOriginalContent() {
		// 1. načteme obsah ze vzdáleného zdroje
		if ( $this->local == 'none' ) {
			$content = file_get_contents( $this->imgSource );
		} else {
			$content = file_get_contents( $this->local );
		}
		if ( $content === false ) return false;
		// 2. protože nedošlo k selhání, můžu nastavit pracovní adresář,
		//    nastavit cestu a uložit data do souboru original
		$this->originalsource = base64_encode( $content );
		// ...a vrátím k dalšímu zpracování
		return $content;
	}

	/**
	 * Get all exif tags from the original file in JSON and covert into array
	 * in the 'exif' property of the object EImageIMG. And return this array
	 * to rework.
	 * Call it only after getOriginalContent()
	 */
	function getOriginalExif() {
		global $wgEImageExif;
		// Zpracování exif tagů může být vypnuto..
		if ( ! $wgEImageExif ) return;
		$command = self::BoxedCommand()
			->routeName( 'eimage-exif' );
		$result = $command
			->params(
				$wgEImageExif['app'],
				'-j', '-b',
				'original'
				)
			// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
			->inputFileFromString( 'original', base64_decode( $this->originalsource ) )
			->execute();
		$this->exif = FormatJson::decode( $result->getStdout(), true );
		if ( $this->exif ) {
			return true;
		} else {
			echo $result->getStderr();
			return false;
		}
	}

	/**
	 * Get path from MW DB
	 *
	 * @return string
	 */
	function getOrigin() {
		$file = self::eimageTitleResolve( $this->imgSource );
		if ( $file instanceof File ) {
			$this->local = $file->getLocalRefPath();
			$this->setEid();
		}
	}

	/**
	 * Tato funkce vrací cestu do místa, kde jsou uloženy data obrázku
	 * Nejprve se, po sestavení eid řetězce zeptá databáze, jestli už
	 * takové eid něco nemá.
	 * Pokud ano, zjistí na základě vrácených dat, z jakého souboru data
	 * načíst a kde ho hledat – při nízkém prahu bude v dočasném úložišti
	 *
	 * Pokud se žádný záznam nevrátí, je to signál že se data teprve budou
	 * generovat a vrátí cestu do adresáře dočasného úložiště
	 *
	 * @return string
	 */
	function getPath() {
		global $wgEImageCache, $wgLocalFileRepo;
		$this->setEid();
		if ( $wgEImageCache ) {
			$this->dbGetItem();
			if ( $this->id ) {
				if ( $this->getContent() ) {
					//if ( $this->counter < $wgEImageCache['threshold'] ) {
					//}
					$this->dbSetExpirationTime( $this->counter + 1, $this->eid );
					$this->cssImage();
					$this->style[] = "width: {$this->cw}px;";
					$this->style[] = "height: {$this->ch}px;";
					$this->style[] = "scale: {$this->resize};";
					return true;
				} else {
					echo "Soubor {$this->image} v úložišti není.\
Bude se muset se muset generovat znovu a tím pádem se změní i jeho jméno ID této položky se může rovnou odstranit";
					return false;
				}
			}
		}
		//echo "Stáhnu originál\n";
		$this->getOriginalContent();
		//echo "Načtu z něj původné exif tagy\n";
		$this->getOriginalExif();
		//echo "A jdu na ořez\n";
		$this->crop();
		if ( $wgEImageCache ) {
			// Přidávám položku do databáze
			$this->dbAddItem();
			$this->getCacheLocal( $this->image . $this->suffix[ $this->dbmimetype ] );
			$this->pushContent();
		}
		$this->cssImage();
		$this->style[] = "width: {$this->cw}px;";
		$this->style[] = "height: {$this->ch}px;";
		$this->style[] = "scale: {$this->resize};";
		return true;
	}

	/**
	 * Return mimetype
	 * @return string
	 */
	function getMimetype() {
		return $this->mime;
	}

	/**
	 * Return path to the original source of the image file
	 * @return string
	 */
	function getSource() {
		return $this->imgSource;
	}

	/* STORAGES */
	/**
	 * Return path to the local storage of files
	 * @return string
	 */
	function getCacheLocal( $string = '' ) {
		global $wgLocalFileRepo, $wgEImageCache;
		$cache = $wgLocalFileRepo['directory'] . DIRECTORY_SEPARATOR . $wgEImageCache['path'];
		// mode 755 is need for www access
		if ( ! is_dir( $cache ) ) mkdir( $cache, 0755, true );
		if ( $string ) {
			$this->imgStorage = $cache . DIRECTORY_SEPARATOR . $string ;
			return $this->imgStorage;
		} else {
			return $cache;
		}
	}

	/**
	 * Vrací typ tohoto objektu
	 */
	function getType() {
		return $this->type;
	}

	/**
	 * Zpracuje nová, uživatelsky nastavená exif data do pole, které sloučí s polem
	 * původních exif tagů $this->exif
	 */
	function setNewExif() {
		global $wgMetaNamespace;
		$exifdata = ExtensionRegistry::getInstance()->getAllThings()['EImage'];
		$data = [];
		$data[0]['Producer'] = '@ ' . date( "Y", mktime() ) . " ${wgMetaNamespace}";
		$data[0]['HistoryParameters'] = "Zpracováno rozšířením {$exifdata['name']} verze {$exifdata['version']}, které naprgal Aleš Kapica - Want";
		if ( isset( $data[0]['HistorySoftwareAgent'] ) ) {
			$history = explode( $exifdata['name'], $data[0]['HistoryParameters'] );
			if ( isset( $history[1] ) ) {
				// Zdroj už jednou tímto skriptem prošel
				$agent = $history[0] . $exifdata['name'] . ' ' . $exifdata['version'];
			} else {
				// Zdroj nějakou historii už za sebou má, ale zde je poprvé
				$agent = $history[0] . ' & ' . $exifdata['name'] . ' ' . $exifdata['version'];
			}
		} else {
			$agent = $exifdata['name'] . ' ' . $exifdata['version'];
		}
		$data[0]['HistorySoftwareAgent'] = $agent;
		$this->newexif = $data;
	}

	/**
	 * Set width and height of the crop area (in pixels).
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 * @param string
	 */
	function setArea( $string = '' ) {
		$area = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $area[0] ) ) $this->cw = $area[0];
		if ( isset( $area[1] ) ) $this->ch = $area[1];
	}

	/**
	 * Left-top corner is the zero point for shift of the crop area.
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 * @param string
	 */
	function setAxes( $string = '' ) {
		$axes = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $axes[0] ) ) $this->cx = $axes[0];
		if ( isset( $axes[1] ) ) $this->cy = $axes[1];
	}

	/**
	 * Settings for crop can be set by one string.
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 * @param string
	 */
	function setCrop( $string = '' ) {
		$crop = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $crop[0] ) ) $this->cx = "{$crop[0]}";
		if ( isset( $crop[1] ) ) $this->cy = "{$crop[1]}";
		if ( isset( $crop[2] ) ) $this->cw = "{$crop[2]}";
		if ( isset( $crop[3] ) ) $this->ch = "{$crop[3]}";
		if ( isset( $crop[4] ) ) {
			$this->resize = "{$crop[4]}";
		} else {
			$this->resize = "1";
		}
	}

	/**
	 * EImage ID is based on source of the image, crop and resize parameters
	 */
	function setEid() {
		$this->eid = strtoupper( bin2hex( "{$this->imgSource}!{$this->cx} {$this->cy} {$this->cw} {$this->ch}!{$this->resize}!{$this->width}" ) );
	}

	/**
	 * EImage image as active link
	 */
	function setLocation( $string = '' ) {
		global $wgScript;
		$this->location = $string;
		$this->style[] = "cursor:pointer;";
		if ( substr( $string, 0, 4 ) == 'http' ) {
			$this->onclick = "window.location.href='{$string}'";
		} elseif ( substr( $string, 0, 1 ) == '#' )  {
			$this->onclick = "window.location.href='{$string}'";
		} else {
			$this->onclick = "window.location.href='{$wgScript}/{$string}'";
		}
	}

	/**
	 * Resize value of the cropped area
	 * Parameter is part of the EImageIMG object ID.
	 *
	 * @param string
	 */
	function setResize( $string = '' ) {
		$this->resize = trim( $string );
	}

	/**
	 * URL, path or name of the image
	 * Parameter is part of the EImageIMG object ID.
	 *
	 * @param string
	 */
	function setSource( $string = '' ) {
		if ( empty($string) ) return false;
		if ( substr( $string, 0, 4 ) !== 'http' ) {
			$this->imgSource = str_replace( ' ', '_', $string );
		} else {
			$this->imgSource = $string;
		}
		$this->getOrigin();
	}

	/**
	 * EImage image as alternative text
	 */
	function setTitle( $string = '' ) {
		$this->title = $string;
	}

	/**
	 * Value of the width for default image
	 *
	 * @param string
	 */
	function setSourceWidth( $string = '' ) {
		$this->iw = trim( $string );
	}
	// Vrací aktuální hodnotu výchozí šířky
	function getSourceWidth() {
		return $this->iw;
	}
	// Vrací šířku výřezu
	function getWidthClip() {
		return $this->cw;
	}

	// Vrací šířku výřezu
	function getHeightClip() {
		return $this->ch;
	}

}
