<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

use MediaWiki\MediaWikiServices;

class EImageIMG extends EImageBOX {

	/**
	 * Nekešované parametry, důležité pro zpracování klipu, které se nikam neukládají
	 *
	 * @var bool
	 */
	private $cache = true;    // bool kešování obrázku

	/**
	 * Kešované parametry, důležité pro identifikaci klipu, ukládané do tabulky 'ei_cache' ve formátu JSON
	 *
	 * @var mixed
	 */
	private $dbmimetype;      // integer id mimetype, pole 'ei_type'
	private $eid;             // string identifikátor klipu v poli 'ei_eid', je to sha1 checksum obsahu pole 'ei_clip' který lze vytvořit bezprostředně po zpracování vstupních parametrů, před generováním klipu
	private $clip = [];       // array uložené do pole 'ei_clip' ve formátu JSON (mumerické hodnoty atributů pro akci crop jsou v pixelech)
	private $image;           // string identifikátor klipu v poli 'ei_file', resp. 'ei_image' je sha1 checksum obsahu vygenerovaného klipu
	// Jeho součástí jsou následující parametry (mumerické hodnoty atributů pro akci crop jsou v pixelech)
	private $imgSource;       // string originální zdroj obrázku
	private $page = 0;        // integer číslo stránky, se kterým se pracuje u souborů typu PDF a DjVu
	private $iw = 0;          // integer výchozí šířka v pixelech; hodnota 0 zastupuje originál
	private $ih = 0;          // integer výchozí šířka v pixelech; hodnota 0 zastupuje originál
	private $dpi = 300;       // integer hodnota DPI se zjišťuje ze vstupního dokumentu
	private $cx = 0;          // integer horizontální posun budoucího výřezu v pixelech od levého horního rohu; kladná hodnota doprava, záporná doleva (default 0)
	private $cy = 0;          // integer vertikální posun budoucího výřezu v pixelech; kladná hodnota dolů, záporná nahoru (default 0)
	private $cw = 0;          // integer šířka výřezu v pixelech – tomu bude odpovídat hodnota atributu pro CSS width;
	private $ch = 0;          // integer výška výřezu v pixelech - té bude odpovídat hodnota atributu pro CSS height;
	private $resize = 1;      // float přeškálování obrázku

	// Následující parametry se ukládájí do pole 'ei_clip' ve formátu JSON (hodnoty atributů pro akci crop jsou v pixelech)
	public $exif = [];        // array pole originálních exif tagů, zajímavých z hlediska historie souboru, které se ukládá do databáze jako JSON

	/**
	 * Path of the clip, is set for new clip by method $this->getCacheLocal()
	 *
	 * @var string
	 */
	public $imgStorage = '.';
	private $local = 'none';  // string u lokálního souboru vede na lokální soubor

	public $name;             // string jméno vytažené ze $imgSource
	public $imagecontent;     // string binary encoded to base64
	private $type = 'eimg';   // string
	private $class = [ 'eimg' ]; // array
	private $location;        // string pokud má div fungovat jako aktivní odkaz
	private $id;              // integer číslo řádku v databázi
	private $tempdir;         // string
	private $original;        //
	private $src;             // string pokud se používá element img, vkládá se obrázek přes atribut src=
	private $thumbwidth = 180;// výchozí šířka náhledu v pixelech
	public $newexif = [];     // array

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

	/**
	 * Metoda zpracovává bitmapové formáty. Kromě jiného také zajišťuje
	 * export výřezu z DjVu stránky, který uloží jako bitmapový soubor
	 *
	 * @return bool
	 */
	function cropDjVuPage() {
		global $wgEImageDjVu, $wgEImageExif;
		if ( $this->dbmimetype == 7 ) {
			/* Konverze vstupního DjVu do PNG nebo JPEG */
			if ( !isset( $this->page ) || ( $this->page == 0 ) ) {
				$page = 1;
			} else {
				$page = $this->page;
			}
			$djvusource = base64_decode( $this->originalsource );
			$command = self::BoxedCommand()
				->routeName( 'eimage-djvu' );
			// 1. step getsize to get real width & height of the selected page
			$getsize = $command->params(
				$wgEImageDjVu['editor'],
					'input.djvu',
					'-e', 'select ' . $page . '; size'
					)
				->inputFileFromString( 'input.djvu', $djvusource )
				->execute();
			if ( $getsize->getStderr() ) {
				print_r( $getsize->getExitCode() );
				print_r( $getsize->getStderr() );
				unset( $djvusource );
				return false;
			}
			// djvused print result to stdout
			$size = explode( ' ', $getsize->getStdout() );
			// 2. step calculate scale and set fragment to crop
			if ( count( $size ) == 2 ) {
				$temp = explode( '=', $size[0] );
				$origw = intval( $temp[1] );
				$temp = explode( '=', $size[1] );
				$origh = intval( $temp[1] );
				unset( $temp );
				// recount size of the crop (default 300 DPI)
				/* WARNING DjVu zero point is in left-bottom corner */
				if ( $this->iw > 0 ) {
					// crop is based on the new default size
					$scale = (int)round( $this->dpi / $origw * $this->iw );
					$width = $this->iw;
					$height = (int)round( $this->iw * $origh / $origw );
					if ( $this->cw > 0 ) {
						// crop based on the new size
						$w = $this->cw;
						$h = $this->ch;
						$x = abs( $this->cx );
						$y = $height - abs( $this->cy ) - $this->ch;
						if ( ( $width >= ( $w + $x ) ) && ( $y > 0 ) ) {
							$segment = $w . 'x' . $h . '+' . $x . '+' . $y;
							$this->iw = $w;
							$this->ih = $h;
						} else {
							// invalid crop values
							return false;
						}
					} else {
						// zero value $this->cw signalize export the fullsize page, coordinates $this->cx & $this->cy ignore
						$segment = $width . 'x' . $height . '+0+0';
						$this->iw = $width;
						$this->ih = $height;
					}
				} else {
					$scale = $this->dpi;
					if ( $this->cw > 0 ) {
						// crop is based on the original size
						$w = $this->cw;
						$h = $this->ch;
						$x = abs( $this->cx );
						$y = $origh - abs( $this->cy ) - $this->ch;
						if ( ( $origw >= ( $w + $x ) ) && ( $y > 0 ) ) {
							$segment = $this->cw . 'x' . $this->ch . '+' . abs( $this->cx ) . '+' . $this->cy;
							$this->iw = $this->cw;
							$this->ih = $this->ch;
						} else {
							// invalid crop values
							return false;
						}
					} else {
						// export fullsize page, $this->cx & $this->cy ignored
						$segment = $origw . 'x' . $origh . '+0+0';
						$this->iw = $origw;
						$this->ih = $origh;
					}
				}
			} else {
				// probably the problematic file
				return false;
			}
			// 3. step crop
			$command = self::BoxedCommand()
				->routeName( 'eimage-crop' );
			$crop = $command
				->params(
				$wgEImageDjVu['app'],
					'-format=pnm',
					'-page=' . $page,
					'-scale=' . $scale,
					'-segment=' . $segment,
					'input.djvu'
					)
				->inputFileFromString( 'input.djvu', $djvusource )
				->execute();
			if ( $crop->getStderr() ) {
				print_r( $crop->getExitCode() );
				print_r( $crop->getStderr() );
				unset( $djvusource );
				return false;
			}
			$pnmstring = $crop->getStdout();
			// 4. step convert from PNM to JPG
			$command = self::BoxedCommand()
				->routeName( 'eimage-getjpg' );
			$getjpg = $command
				->params(
				$wgEImageDjVu['netpbm'],
					'input.pnm'
				)
			->inputFileFromString( 'input.pnm', $pnmstring )
			->execute();
			if ( $getjpg->getStderr() ) {
				print_r( $getjpg->getExitCode() );
				print_r( $getjpg->getStderr() );
				unset( $pnmstring );
				return false;
			}
			// pnmtojpeg send output to stdout
			$content = $getjpg->getStdout();
			if ( empty( $content ) ) {
				unset( $pnmstring );
				return false;
			}
			// 5. step write new exif tags into the clip image
			$this->mime = 'image/jpeg';
			$this->dbmimetype = 1;
			if ( $wgEImageExif ) {
				/* Tahle část nastaví nové parametry pro exif tagy */
				$this->setNewExif();
				$djvupage = $this->getName() . '-' . $this->page . '.jpg';
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
				$data[0]['SourceFile'] = $djvupage;
				$data[0]['HistoryWhen'] = date( DATE_ATOM, mktime() );
				$command = self::BoxedCommand()
					->routeName( 'eimage-exif' );
				$result = $command
					->params(
						$wgEImageExif['app'],
						'-j+=exif.json',
						'-o', '-',
						$djvupage
					)
					->inputFileFromString( 'exif.json', FormatJson::encode( $data, true ) )
					->inputFileFromString( $djvupage, $content )
					->execute();
				if ( $result->getStderr() ) {
					print_r( $result->getExitCode() );
					print_r( $result->getStderr() );
					unset( $data );
					unset( $content );
					unset( $djvupage );
					return false;
				}
				$vysledek = $result->getStdout();
				$this->image = sha1( $vysledek );
				$this->imagecontent = base64_encode( $vysledek );
				unset( $data );
				unset( $content );
				unset( $djvupage );
				unset( $vysledek );
				return true;
			} else {
				/* Vypnuto použití exiftools */
				$this->image = sha1( $content );
				$this->imagecontent = base64_encode( $content );
				unset( $content );
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Metoda zpracovává SVG, které konvertuje na PDF ze kterého udělá
	 * výřez který bude opět v SVG formátu. Jako vstup lze použít i
	 * vícestránkový PDF soubor
	 *
	 * @return bool
	 */
	function cropPdfPage() {
		global $wgEImagePdf, $wgEImageSvg, $wgMetaNamespace;
		$exifdata = ExtensionRegistry::getInstance()->getAllThings()['EImage'];
		$tempFilePdf = tempnam( sys_get_temp_dir(), "PDF" );
		$tempFileSvg = tempnam( sys_get_temp_dir(), "SVG" );
		if ( $this->dbmimetype == 4 || $this->dbmimetype == 20 ) {
			/* Konverze vstupního SVG do PDF */
			$command = self::BoxedCommand()
				->routeName( 'eimage-svg' );
			$pdf = $command
				->params(
				$wgEImageSvg['app'],
					'-f', 'pdf',
					'--dpi', $this->dpi,
					'-W', $this->iw,
					'-H', $this->ih,
					'--output-width', $this->iw,
					'--output-height', $this->ih,
					'-o', 'output.pdf',
					'input.svg',
				)
			->inputFileFromString( 'input.svg', base64_decode( $this->originalsource ) )
			->outputFileToFile( 'output.pdf', $tempFilePdf )
			->execute();
			if ( $pdf->getStderr() ) {
				print_r( $pdf->getExitCode() );
				print_r( $pdf->getStderr() );
				unlink( $tempFilePdf );
				return false;
			}
		} elseif ( $this->dbmimetype == 5 ) {
			file_put_contents( $tempFilePdf, file_get_contents( $this->local ) );
		} else {
			return false;
		}
		/* Na vstupu je nyní PDF soubor ze kterého generuji clip, který exportuji jako SVG */
		if ( !isset( $this->page ) || ( $this->page == 0 ) ) {
			$page = 1;
		} else {
			$page = $this->page;
		}
		$command = self::BoxedCommand()
				->routeName( 'eimage-pdf' );
		if ( $this->cw > 0 ) {
			$svg = $command
				->params(
				$wgEImagePdf['app'],
					'-f', $page,
					'-l', $page,
					'-svg',
					'-x', $this->cx,
					'-y', $this->cy,
					'-W', $this->cw,
					'-H', $this->ch,
					'-nocenter',
					'-paperw', $this->cw,
					'-paperh', $this->ch,
					'input.pdf',
					'output.svg'
				)
			// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
			->inputFileFromFile( 'input.pdf', $tempFilePdf )
			->outputFileToFile( 'output.svg', $tempFileSvg )
			->execute();
		} else {
			$svg = $command
				->params(
				$wgEImagePdf['app'],
					'-f', $page,
					'-l', $page,
					'-svg',
					'input.pdf',
					'output.svg'
				)
			// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
			->inputFileFromFile( 'input.pdf', $tempFilePdf )
			->outputFileToFile( 'output.svg', $tempFileSvg )
			->execute();
		}
		if ( $svg->getStderr() ) {
			print_r( $svg->getExitCode() );
			print_r( $svg->getStderr() );
			unlink( $tempFilePdf );
			unlink( $tempFileSvg );
			return false;
		}
		// remove temporary PDF
		unlink( $tempFilePdf );
		/* insert exif tags */
		$string = file_get_contents( $tempFileSvg );
		$s = simplexml_load_string( '<metadata></metadata>' );
		$sxe = $s->addChild( 'rdf:RDF' );
		$work = $sxe->addChild( 'cc:Work' );
		$format = $work->addChild( 'dc:format', 'image/svg+xml' );
		$title = $work->addChild( 'dc:title', $this->getName() );
		$date = $work->addChild( 'dc:date', date( DATE_ATOM, mktime() ) );
		$creator = $work->addChild( 'dc:creator' );
		$agent = $creator->addChild( 'cc:Agent' );
		$author = $agent->addChild( 'dc:title', RequestContext::getMain()->getUser()->getName() );
		$right = $work->addChild( 'dc:right' );
		$rightagent = $right->addChild( 'cc:Agent' );
		$copyright = $rightagent->addChild( 'dc:title', '(C) ' . date( "Y", mktime() ) . " ${wgMetaNamespace}" );
		$urlsource = $work->addChild( 'dc:source', $this->imgSource );
		$description = $work->addChild( 'dc:description', $this->title );
		$contributor = $work->addChild( 'dc:contributor' );
		$contribagent = $contributor->addChild( 'cc:Agent' );
		$workedby = $contribagent->addChild( 'dc:title', "Zpracováno rozšířením {$exifdata['name']} verze {$exifdata['version']}, které naprgal Aleš Kapica - Want" );
		$m = substr( $s->asXml(), 22 );
		$content = strstr( $string, '<defs>', true ) . $m . strstr( $string, '<defs>' );
		/* after write */
		$this->image = sha1( $content );
		$this->imagecontent = base64_encode( $content );
		$this->dbmimetype = 4; // důležitá změna mimetype!!!
		$svgReader = new SVGReader( $tempFileSvg );
		$metadata = $svgReader->getMetadata();
		if ( !isset( $metadata['width'] ) || !isset( $metadata['height'] ) ) {
			// echo "Problém s načtením SVG souboru\n";
			return false;
		} else {
			// mohu nastavit rozměry $this->iw a $this->ih
			$this->iw = $this->vectorPixel( $metadata['originalWidth'] )[0];
			$this->ih = $this->vectorPixel( $metadata['originalHeight'] )[0];
			// ..a vygenerovat náhled, pokud není $this->cache false
			if ( $this->cache ) {
				$dirpath = self::getCacheLocal() . DIRECTORY_SEPARATOR . 'thumbs';
				if ( !is_dir( $dirpath ) ) {
					// mode 755 is need for www access
					mkdir( $dirpath, 0755, true );
				}
				$thumbnail = $dirpath . DIRECTORY_SEPARATOR . $this->image . '.png';
				$width = 180;
				$height = $metadata['height'] / $metadata['width'] * $width;
				$handler = new SvgHandler;
				$res = $handler->rasterize(
					$tempFileSvg,
					$thumbnail,
					$width,
					$height
				);
				unset( $thumbnail );
			}
			unlink( $tempFileSvg );
		}
		return true;
	}

	/**
	 * PHP script pro výřez a přeškálování náhledu obrázku.
	 * @return string base64 encode image
	 */
	function cropBitmapImage() {
		$original = base64_decode( $this->originalsource );
		$params = getimagesizefromstring( $original );
		$this->mime = $params['mime'];
		unset( $this->originalsource );
		if ( !$this->cw ) {
			// Není nastaven crop, je třeba zjistit další informace z exifu
			// především šířku a výšku. Ale nejprve – budeme dělat resize?
			if ( $this->iw ) {
				// obrázek se bude škálovat
				$this->cx = 0;
				$this->cy = 0;
				if ( $this->exif ) {
					$this->cw = intval( $this->exif[0]['ImageWidth'] ); // integer
					$this->ch = intval( $this->exif[0]['ImageHeight'] ); // integer
				} else {
					$this->cw = intval( $params[0] );
					$this->ch = intval( $params[1] );
				}
				$this->resize = $this->iw / $this->cw;
			} else {
				// obrázek se použije v původní velikosti
				// PNG (bitmapy)
				$this->cx = 0;
				$this->cy = 0;
				if ( $this->exif ) {
					$this->cw = intval( $this->exif[0]['ImageWidth'] ); // integer
					$this->ch = intval( $this->exif[0]['ImageHeight'] ); // integer
				} else {
					$this->cw = intval( $params[0] );
					$this->ch = intval( $params[1] );
				}
				$this->resize = 1;
			}
		} else {
			if ( $this->iw ) {
				$this->resize = $this->iw / $this->cw;
			}
		}
		$resize = floatval( $this->resize ); // přeškálování výřezu; 0.01 < (default 1) < 3
		$draft = imagecreatefromstring( $original );
		imagealphablending( $draft, false );
		// Resize podle parametru width=
		if ( $this->iw > 0 ) {
			// echo "Scale before crop by default width {$iw}";
			$resampled = imagescale( imagecreatefromstring( $original ), $this->iw, -1, IMG_BICUBIC_FIXED );
		}
		/* $this->original is over */
		unset( $original );
		// echo "{$this->getName()} :  {$params['mime']}; width: {$width}px; height: {$height}px;\n";
		if ( $this->cw == 0 ) {
			/* Original without resize */
			if ( isset( $resampled ) ) {
				return $this->exportNewImage( $this->mime, $resampled );
			} else {
				return $this->exportNewImage( $this->mime, $draft );
			}
		} else {
			// CROP
			if ( isset( $resampled ) ) {
				$image_c = imagecrop( $resampled, [ 'x' => abs( $this->cx ), 'y' => abs( $this->cy ), 'width' => $this->cw, 'height' => $this->ch ] );
			} else {
				// Rozměry nového obrázku s posunem
				$image_p = imagecreatetruecolor( $this->cw, $this->ch );
				$black = imagecolorallocate( $image_p, 0, 0, 0 );
				imagecolortransparent( $image_p, $black );
				// Naplácnutí posunutého obsahu na obrázek o původní velikosti
				imagecopyresampled( $image_p, $draft, abs( $this->cx ), abs( $this->cy ), 0, 0, $this->cw, $this->ch, $this->cw, $this->ch );
				$image_c = imagecrop( $image_p, [ 'x' => 0, 'y' => 0, 'width' => $this->cw, 'height' => $this->ch ] );
				// Výřez z posunutého obrázku
				imagedestroy( $image_p );
			}
		}
		// …a přeškálování
		if ( isset( $resize ) ) {
			// Změna velikost
			if ( $resize > 3 && $resize < 0.01 ) {
				die( 'Minimal value of the resize is 0.01 and max 3' );
			}
			// Vypočítanou hodnotu je třeba zaokrouhlit na celé pixely
			$neww = round( $this->cw * $resize );
			$newh = round( $this->ch * $resize );
			// echo "Rescale after crop to {$neww}x{$newh} by resize ${resize}\n";
			// Zmenšení obrázku podle nastaveného poměru
			$image_r = imagecreatetruecolor( $neww, $newh );
			$black = imagecolorallocate( $image_r, 0, 0, 0 );
			imagecolortransparent( $image_r, $black );
			imagecopyresampled( $image_r, $image_c, 0, 0, 0, 0, $neww, $newh, $this->cw, $this->ch );
			imagedestroy( $image_c );
			$draft = $image_r;
		} else {
			// Odeslání výřezu bez přeškálování – image_c se zruší ve funkci exportNewImage()
			$draft = $image_c;
		}
		return $this->exportNewImage( $this->mime, $draft );
	}

	/**
	 * Je-li předáno pouze ID klipu, generuje funkce náhled.
	 * Je-li předáno jako druhý parametr 'false', je to signál, že se pro dané id má odstranit náhled i klip.
	 * V obou předchozích případech je návratová hodnota 'true'
	 * Návratová hodnota 'false' je signál, že klip neexistuje a tudíž ho bude nutné generovat znovu
	 *
	 * @param string $id
	 * @param bool $create
	 * @return bool
	 */
	function clipThumbnail( $id, $create = true ) {
		$cache = $this->getCacheLocal() . DIRECTORY_SEPARATOR;
		$d = glob( $cache . $id . '.*', GLOB_BRACE );
		foreach ( $d as $clip ) {
			$thumbnail = $cache . 'thumbs' . DIRECTORY_SEPARATOR . $id . '.png';
			if ( $create ) {
				if ( !file_exists( $thumbnail ) ) {
					$this->createThumbnail( $id, base64_encode( file_get_contents( $clip ) ) );
				}
				return true;
			} else {
				if ( file_exists( $thumbnail ) ) {
					unlink( $thumbnail );
				}
				unlink( $clip );
				return true;
			}
		}
		return false;
	}

	/**
	 * Create thumbnail from clip, if cache activate
	 *
	 * @param string $id - sha1 checksum of the clip content
	 * @param string $string of the image content base64 encoded
	 * @return bool
	 */
	public static function createThumbnail( $id, $string = '' ) {
		if ( $id ) {
			$dirpath = self::getCacheLocal() . DIRECTORY_SEPARATOR . 'thumbs';
			if ( !is_dir( $dirpath ) ) {
				// mode 755 is need for www access
				mkdir( $dirpath, 0755, true );
			}
			$path = $dirpath . DIRECTORY_SEPARATOR . $id . '.png';
			switch ( substr( $string, 0, 7 ) ) {
			case 'QVQmVEZ': // djvu
			case 'JVBERi0': // pdf
			case '0M8R4KG': // doc
			case '/9j/4AA': // jpg
			case 'R0lGODl': // gif
			case 'iVBORw0': // png
			case '/9j/2wB': // jpg
				$thumb = imagescale( imagecreatefromstring( base64_decode( $string ) ), 180, -1, IMG_BICUBIC_FIXED );
				imagepng( $thumb, $path );
				unset( $string );
				unset( $path );
				break;
			case 'PD94bWw': // svg
				$tempFileSvg = tempnam( sys_get_temp_dir(), "SVG" );
				$tempFilePng = tempnam( sys_get_temp_dir(), "PNG" );
				file_put_contents( $tempFileSvg, base64_decode( $string ) );
				unset( $string );
				$svgReader = new SVGReader( $tempFileSvg );
				$metadata = $svgReader->getMetadata();
				if ( !isset( $metadata['width'] ) || !isset( $metadata['height'] ) ) {
					// echo "Problém s načtením SVG souboru\n";
					unlink( $tempFileSvg );
					unlink( $tempFilePng );
					unset( $path );
				} else {
					$width = 180;
					$height = $metadata['height'] / $metadata['width'] * $width;
					$handler = new SvgHandler;
					$res = $handler->rasterize(
						$tempFileSvg,
						$path,
						$width,
						$height
					);
					unlink( $tempFileSvg );
					unlink( $tempFilePng );
					unset( $path );
				}
				break;
			default:
				// zatím s tím nic nenadělám
				break;
			}
			return true;
		}
		return false;
	}

	/**
	 * Return image as base64 string for CSS style
	 */
	public function cssImage() {
		global $wgLocalFileRepo, $wgEImageCache, $wgEImageImgElement;
		if ( $this->imagecontent ) {
			$params = getimagesizefromstring( base64_decode( $this->imagecontent ) );
			// prohledávám pole před vložením obrázku
			$key = array_search( '%;', $this->style );
			if ( $key ) {
				preg_match( '/width:([0-9]+)\%/', $this->style[$key], $m );
				if ( $m[1] ) {
					$scale = "scale:" . strval( intval( $m[1] ) / 100 ) . ";";
					unset( $this->style[$key] );
				}
			}
			if ( $wgEImageImgElement ) {
				if ( isset( $wgEImageCache['storage'] ) ) {
					$this->src = strstr( $wgLocalFileRepo['directory'], '/wiki' ) . DIRECTORY_SEPARATOR . $wgEImageCache['path'] . DIRECTORY_SEPARATOR . $this->image . $this->suffix[$this->dbmimetype];
				} else {
					$this->src = "data:" . $this->mimetype[ $this->dbmimetype ] . ";charset=utf-8;base64," . $this->imagecontent;
				}
			} else {
				if ( isset( $wgEImageCache['storage'] ) ) {
					$this->style[] = "background-image:url('" . strstr( $wgLocalFileRepo['directory'], '/wiki' ) . DIRECTORY_SEPARATOR . $wgEImageCache['path'] . DIRECTORY_SEPARATOR . $this->image . $this->suffix[$this->dbmimetype] . "');background-size:contain;";
				} else {
					$this->style[] = "background-image:url(data:" . $this->mimetype[ $this->dbmimetype ] . ";base64," . $this->imagecontent . ");background-size:contain;";
				}
			}
			if ( $key ) {
				$this->style[] = $scale;
			}
		}
	}

	/**
	 * DATABASE METHODS
	 * Insert new item into the database
	 *
	 * @return int
	 */
	function dbAddItem() {
		if ( $this->cache == false ) {
			return false;
		}
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$item = $dbw->selectField(
			'ei_cache',
			'ei_id',
			[ 'ei_eid' => $this->eid ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
		if ( $item > 0 ) {
			$this->id = $item;
		} else {
			$dbw->startAtomic( __METHOD__ );
			$dbw->insert(
				'ei_cache',
				[
					'ei_eid' => strval( $this->eid ),
					'ei_file' => strval( $this->image ),
					'ei_clip' => FormatJson::encode( $this->clip ),
					'ei_ctime' => $dbw->timestamp( date( DATE_ATOM, mktime() ) ),
					'ei_origin_exif' => FormatJson::encode( $this->exif, true ),
					'ei_width' => intval( $this->iw ),
					'ei_height' => intval( $this->ih ),
					'ei_type' => $this->dbmimetype
				],
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
			$this->dbPage();
		}
		return true;
	}

	/**
	 * Odstranění duplicitních záznamů z tabulky 'ei_pages' a vrácení pole unikátních záznamů
	 *
	 * @param string $id - sha1 checksum of the clip (field 'ei_image')
	 * @return array
	 */
	function dbClipArrayGet( $id ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$pages = $dbw->select(
			'ei_pages',
			[ 'ei_page', 'ei_image' ],
			[ 'ei_image' => $id ],
			__METHOD__
		);
		if ( count( $pages ) == 0 ) {
			return true;
		}
		$items = [];
		foreach ( $pages as $p ) {
			// extrakce záznamů
			$items[] = [ 'ei_page' => $p->ei_page, 'ei_image' => $p->ei_image ];
		}
		return $items;
	}

	/**
	 * Delete items from table 'ei_pages' by ei_image and 'ei_cache' by ei_file
	 * Note: Used by maintainer script
	 *
	 * @param string $id - sha1 checksum of the clip content
	 * @return bool
	 */
	function dbDeleteId( $id ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete(
			'ei_pages',
			[ 'ei_image' => $id ],
			__METHOD__
		);
		$dbw->delete(
			'ei_cache',
			[ 'ei_file' => $id ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		return true;
	}

	/**
	 * Delete item form the database by id
	 */
	function dbDeleteItem() {
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'ei_cache',
			[
			'ei_id' => $this->id
			],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Try get item from the database by the eid string
	 *
	 * @return bool
	 */
	function dbGetClip() {
		// RequestContext::getMain()->getUser()->getName(); // jméno uživatele
		// RequestContext::getMain()->getUser()->mId ); // id uživatele
		// RequestContext::getMain()->getWikiPage()->getId() ); // id aktuální stránky
		// RequestContext::getMain()->getActionName() ); // aktuální akce
		if ( !$this->cache ) {
			return false;
		}
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->select(
			'ei_cache',
			[
				'ei_id',
				'ei_clip',
				'ei_file',
				'ei_width',
				'ei_height',
				'ei_type'
			],
			[ 'ei_eid' => $this->eid ],
			__METHOD__
			);
		if ( count( $result ) > 0 ) {
			foreach ( $result as $row ) {
				$this->id = $row->ei_id;
				$this->image = $row->ei_file;
				$clip = FormatJson::decode( $row->ei_clip, true );
				$this->imgSource = $clip['source'];
				$this->page = $clip['page'];
				$this->dpi = $clip['dpi'];
				$this->cx = $clip['cx'];
				$this->cy = $clip['cy'];
				$this->cw = $clip['cw'];
				$this->ch = $clip['ch'];
				$this->iw = $row->ei_width;
				$this->ih = $row->ei_height;
				switch ( $row->ei_type ) {
				case 1:
				case 26:
					$this->mime = 'image/jpeg';
					$this->dbmimetype = 1;
					$this->imgStorage = $this->getCacheLocal( $this->image . $this->suffix[1] );
					break;
				case 2:
					$this->mime = 'image/gif';
					$this->dbmimetype = 2;
					$this->imgStorage = $this->getCacheLocal( $this->image . $this->suffix[2] );
					break;
				case 3:
					$this->mime = 'image/png';
					$this->dbmimetype = 3;
					$this->imgStorage = $this->getCacheLocal( $this->image . $this->suffix[3] );
					break;
				case 4:
				case 20:
					$this->mime = 'image/svg+xml';
					$this->dbmimetype = 4;
					$this->imgStorage = $this->getCacheLocal( $this->image . $this->suffix[4] );
					break;
				default:
					$this->mime = null;
					$this->dbmimetype = null;
					break;
				}
			}
		}
		$dbw->endAtomic( __METHOD__ );
		if ( $this->id ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Insert new item into the database
	 *
	 * @return bool
	 */
	function dbPage() {
		$dbw = wfGetDB( DB_PRIMARY );
		$idpage = (int)RequestContext::getMain()->getWikiPage()->getId();
		$dbw->startAtomic( __METHOD__ );
		$item = $dbw->selectField(
			'ei_pages',
			'ei_page',
			[
			'ei_page' => $idpage,
			'ei_image' => $this->image
			],
			__METHOD__
		);
		if ( $item ) {
			// duplicitní položka
		} else {
			$dbw->insert(
				'ei_pages',
				[
				'ei_page' => $idpage,
				'ei_image' => strval( $this->image )
				],
				__METHOD__
			);
		}
		$dbw->endAtomic( __METHOD__ );
		return true;
	}

	/**
	 * Insert new item into the database
	 *
	 * @param string $eid - sha1 checksum of the atributtes in JSON
	 * @return bool
	 */
	public static function dbSetExpirationTime( $eid ) {
		global $wgEImageCache;
		$dbw = wfGetDB( DB_PRIMARY );
		$date = new DateTimeImmutable();
		$timestamp = (int)$date->getTimestamp() + $wgEImageCache['expire'];
		$dbw->startAtomic( __METHOD__ );
		$counter = $dbw->selectField(
			'ei_cache',
			'ei_counter',
			[ 'ei_eid' => $eid ],
			__METHOD__
			);
		$dbw->update(
			'ei_cache',
			[
				'ei_counter' => $counter + 1,
				'ei_expire' => $timestamp
			],
			[ 'ei_eid' => $eid ],
			__METHOD__
			);
		$dbw->endAtomic( __METHOD__ );
		return true;
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
	public static function eimageArea( $parser, $name = '' ) {
		$file = self::dbTitleResolve( $name );
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
		// global $wgEImageExif;
		$file = self::dbTitleResolve( $name );
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
		$file = self::dbTitleResolve( $name );
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
		$file = self::dbTitleResolve( $name );
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
	public function eimageMime( $parser, $name = '' ) {
		$file = self::dbTitleResolve( $name );
		if ( $file instanceof File ) {
			$mimetype = $file->getMimeType();
			switch ( $mimetype ) {
			case 'application/xml':
			case 'image/svg+xml':
				$this->mime = 4;
				return 'image/svg+xml';
				break;
			default:
				return $mimetype;
				break;
			}
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
		$file = self::dbTitleResolve( $name );
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
		$file = self::dbTitleResolve( $name );
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
	private static function dbTitleResolve( $text ) {
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
		$file = self::dbTitleResolve( $name );
		if ( $file instanceof File ) {
			return $file->getWidth();
		}
		return self::ERR_UNKNOWN_VALUE;
	}

	/**
	 * Write resource as image by mimetype
	 *
	 * @param string $mime mimetype
	 * @param resource $image
	 * @return mixed
	 */
	function exportNewImage( $mime, $image ) {
		global $wgEImageExif, $wgEImageAvif;
		$name = $this->getName();
		$path = tempnam( sys_get_temp_dir(), "NEW" );
		switch ( $mime ) {
		case 'image/jpeg':
			imagejpeg( $image, $path );
			$this->dbmimetype = 1;
			break;
		case 'image/gif':
			imagegif( $image, $path );
			$this->dbmimetype = 2;
			break;
		case 'image/png':
			imagepng( $image, $path );
			$this->dbmimetype = 3;
			break;
		}
		imagedestroy( $image );
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
			if ( $wgEImageAvif['use'] ) {
				$content = '';
				$toavif = $result->getStdout();
				$command = self::BoxedCommand()
					->routeName( 'eimage-avif' );
				switch ( $this->dbmimetype ) {
				case 1:
					$resultavif = $command
					->params(
						$wgEImageAvif['app'],
						'-A',
						'-o', 'file.avif',
						'file.jpg'
					)
					// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
					->inputFileFromString( 'file.jpg', $toavif )
					->outputFileToString( 'file.avif' )
					->execute();
					break;
				case 2:
					break;
				case 3:
					$resultavif = $command
					->params(
						$wgEImageAvif['app'],
						'-A',
						'-o', 'file.avif',
						'file.png'
					)
					// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
					->inputFileFromString( 'file.png', $toavif )
					->outputFileToString( 'file.avif' )
					->execute();
					break;
				default:
					break;
				}
				if ( $resultavif->getStderr() ) {
					print_r( $resultavif->getExitCode() );
					print_r( $resultavif->getStderr() );
					return false;
				}
				$this->mime = 'image/avif';
				$this->dbmimetype = 30;
				$content = $resultavif->getFileContents( 'file.avif' );
			} else {
				$content = $result->getStdout();
			}
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

	function getCache() {
		return $this->cache;
	}

	/**
	 * STORAGE
	 * Method of the cache - return path to the cache directory, or path of the clip
	 *
	 * @param string $string - name based on sha1 sum and mimetype of the file
	 * @return string path to the local storage of files
	 */
	public function getCacheLocal( $string = '' ) {
		global $wgLocalFileRepo, $wgEImageCache;
		$cache = $wgLocalFileRepo['directory'] . DIRECTORY_SEPARATOR . $wgEImageCache['path'];
		// mode 755 is need for www access
		if ( !is_dir( $cache ) ) {
			mkdir( $cache, 0755, true );
		}
		if ( $string ) {
			$this->imgStorage = $cache . DIRECTORY_SEPARATOR . $string;
			return $this->imgStorage;
		} else {
			return $cache;
		}
	}

	/**
	 * Method of the cache - read content of the clip file into $this->imagecontent
	 *
	 * @return bool
	 */
	function getContent() {
		if ( file_exists( $this->imgStorage ) ) {
			$content = file_get_contents( $this->imgStorage );
			if ( $content ) {
				$this->imagecontent = base64_encode( $content );
				switch ( $this->base64id[substr( $this->imagecontent, 0, 7 )] ) {
				case 1:
				case 26:
					$this->dbmimetype = 1;
					break;
				case 4:
				case 20:
					$this->dbmimetype = 4;
					break;
				}
				return true;
			}
		}
		return false;
	}

	// Vrací šířku výřezu
	function getHeightClip() {
		return $this->ch;
	}

	// vrací div, s obrázkem na pozadí o rozměrech onoho obrázku
	function getHtml() {
		global $wgEImageImgElement;
		if ( $wgEImageImgElement ) {
			return Html::rawElement( 'img',
				[
					'class' => implode( ' ', $this->class ),
					'title' => $this->content,
					'onclick' => $this->onclick,
					'src' => $this->src,
					'width' => $this->width . '%',
					'style' => 'position:relative;' . $this->getStyle()
				],
				''
				);
		} else {
			return Html::rawElement( 'div',
				[
					'class' => implode( ' ', $this->class ),
					'title' => $this->content,
					'onclick' => $this->onclick,
					'style' => 'position:relative;' . $this->getStyle()
				],
				''
				);
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
	 * @return bool
	 */
	function getImage() {
		global $wgEImageCache;
		// nocache
		if ( isset( $this->attribute['name']['cache'] ) ) {
			$this->setCache( $this->attribute['name']['cache'] );
		}
		// EID attributes
		if ( isset( $this->attribute['name']['page'] ) ) {
			$this->setEidPage( $this->attribute['name']['page'] );
		}
		if ( isset( $this->attribute['name']['crop'] ) ) {
			$this->setEidCrop( $this->attribute['name']['crop'] );
		}
		if ( isset( $this->attribute['name']['width'] ) ) {
			$this->setEidWidth( $this->attribute['name']['width'] );
		}
		$this->setEidSource( $this->attribute['index'][0] );
		$this->getEid();
		// Next attributes
		if ( isset( $this->attribute['name']['location'] ) ) {
			$this->setLocation( $this->attribute['name']['location'] );
		}
		if ( $wgEImageCache ) {
			if ( $this->dbGetClip() ) {
				if ( $this->cache ) {
					if ( $this->getContent() ) {
						// Add hit about using of the cached file
						$this->dbSetExpirationTime( $this->eid );
						// Add new item into 'ei_pages' table
						$this->dbPage();
						// Z parametrů je třeba vytáhnout potřebná nastavení a doplnit co chybí
						// ...
						// a v tomto momentu se rozhoduje co se bude vracet
						$this->cssImage();
						// $this->style[] = "width:" . $this->iw . "px;";
						// $this->style[] = "height:" . $this->ih . "px;";
						// $this->style[] = "transform: scale(" . $this->width / 100 . "); transform-origin: 0% 0%;" ;
						// $this->style[] = "transform: scale( " . 100 / $this->iw * ( $this->iw / $this->ih ) * $this->width / 10 . "); transform-origin: 0% 0%;" ;
						return true;
					} else {
						// echo "Soubor " . $this->name . "s ID " . $this->id ." není nakešován je ptřeba smazet položku";
						// File $this->image not exist in storage, remove orphan item from DB
						$this->dbDeleteItem();
						unset( $this->id );
					}
				} else {
					// Object has state 'nocache', before item remove must delete clip
					// echo "Soubor " . $this->name ."s ID " . $this->id . " je v keši, ale nemá být";
					$this->dbDeleteItem();
					unset( $this->id );
					if ( file_exists( $this->imgStorage ) ) {
						unlink( $this->imgStorage );
					}
				}
			} else {
			// print_r( "Nepodařilo se načíst: " . $this->eid . " - ");
			}
		} else {
			$this->cache = false;
		}
		// Set source local path from MW DB if exists
		$this->setLocalPath();
		$this->getOriginal();
		// Jsou-li k dispozici exif tagy originálního obrázku, je možné již v tomto
		// okamžiku vypočítat kolik RAM bude zhruba potřeba ke zpracování obrázku.
		// Ovšem je nutné tuhle operaci nechat na později, protože v souboru
		// LocalSettings.php může být použití nástroje exiftool omezeno volbou
		//
		//   $wgEImageExif = false;
		//
		// Pokud je použití exiftool povoleno, byly původní exif data uloženy jako
		// pole do $this->exif
		$gdtest = gd_info();
		switch ( $this->dbmimetype ) {
		case 4: /* vektorové formáty SVG */
		case 5: /* PDF */
		case 20: /* vektorové formáty XML */
			$this->cropPdfPage();
			break;
		case 7: /* DjVu */
			$this->cropDjVuPage();
			break;
		case 0: // test support for bitmap formats
			if ( !$gdtest['BMP Support'] ) {
				// vrátit zprávu o tom, že BMP formát nemá podporu
				return false;
			}
		case 1:
		case 26: // test JPEG support
			if ( !$gdtest['JPEG Support'] ) {
				// vrátit zprávu o tom, že JPEG formát nemá podporu
				return false;
			}
		case 2: // test GIF format support
			if ( !$gdtest['GIF Create Support'] ) {
				// vrátit zprávu o tom, že GIF formát nemá podporu
				return false;
			}
		case 3:
			if ( !$gdtest['PNG Support'] ) {
				// vrátit zprávu o tom, že PNG formát nemá podporu
				return false;
			}
			$this->cropBitmapImage();
			break;
		default: // neznámý formát
			return false;
		}
		if ( strlen( $this->eid ) == 0 ) {
		// print_r('Prázdné eid');
		} else {
			if ( $this->dbAddItem() ) {
				// Přidávám položku do databáze
				$this->getCacheLocal( $this->image . $this->suffix[ $this->dbmimetype ] );
				$this->pushContent();
			}
		}
		$this->cssImage();
// width je v procentech, takže pokud má hodnotu 15%, bude šířka obrázku $this->iw odppovídat těm 15 procentům.
// Tedy $this->iw / $this->width je 1% v pixelech
// Procenta odpovídají hodnotě vw, takže místo $this->iw bude hodnota $this->width
// Ale výšku je třeba přepočítat!!!
// takže $this->ih / $this->iw / $this->width by mělo dát výšku ve vw
//		$this->style[] = "width:" . $this->iw . "px;";
//		$this->style[] = "height:" . $this->ih . "px;";
//		$this->style[] = "width:" . $this->width . "vw;";
//		$this->style[] = "height:" . $this->ih / $this->iw * $this->width . "vw;";
//print_r( $this->width . ' - ' . $this->iw . ' - ' .$this->ih );
//		$this->style[] = "transform: scale( " . 100 / $this->iw * ( $this->iw / $this->ih ) * $this->width / 10 . "); transform-origin: 0% 0%;" ;
//		$this->style[] = "transform: scale(" . $this->width / 100 . "); transform-origin: 0% 0%;" ;
//		$this->style[] = "transform: scale(); transform-origin: 0% 0%;" ;
		return true;
	}

	/**
	 * Return mimetype
	 *
	 * @return string
	 */
	function getMimetype() {
		return $this->mime;
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

	/**
	 * Set local path from MW DB if file exists
	 */
	function setLocalPath() {
		$file = self::dbTitleResolve( $this->imgSource );
		if ( $file instanceof File ) {
			$this->local = $file->getLocalRefPath();
		}
	}

	/**
	 * Get source content, encode into base64 and set as value
	 * $this->originalsource for next reworking.
	 * Before get exif tags from the original file as JSON, if
	 * exiftool allowed, convert to array, and set this as
	 * $this->exif value
	 *
	 * @return bool
	 */
	function getOriginal() {
		global $wgEImageExif;
		if ( $this->local == 'none' ) {
			// Remote file must be downloaded
			ini_set( 'user_agent', 'EImage/3.3 (https://www.thewoodcraft.org/; eimagebot@thewoodcraft.org)' );
			$content = file_get_contents( $this->imgSource );
		} else {
			$content = file_get_contents( $this->local );
		}
		if ( $content === false ) {
			return false;
		}
		// Zpracování exif tagů může být vypnuto..
		if ( $wgEImageExif ) {
			$command = self::BoxedCommand()
				->routeName( 'eimage-exif' );
			$result = $command
				->params(
					$wgEImageExif['app'],
					'-j', '-b',
					'original'
					)
				// Založí soubor v dočasném adresáři a nakrmí do něj obsah souboru $object->getName()
				->inputFileFromString( 'original', $content )
				->execute();
			$this->exif = FormatJson::decode( $result->getStdout(), true );
			if ( $this->exif ) {
				// Korekce a zrušení exiftagů, přepsaných díky použití v BoxedCommand
				$this->exif[0]['SourceFile'] = $this->getName();// every 'original'
				unset( $this->exif[0]['ExifToolVersion'] );     // be rewrited in next step
				unset( $this->exif[0]['FileName'] );            // every 'original'
				unset( $this->exif[0]['Directory'] );           // every '.'
				unset( $this->exif[0]['FileModifyDate'] );      // rewrited
				unset( $this->exif[0]['FileAccessDate'] );      // rewrited
				unset( $this->exif[0]['FileInodeChangeDate'] ); // rewrited
				unset( $this->exif[0]['FilePermissions'] );     // rewrited
			} else {
				echo $result->getStderr();
				return false;
			}
		}
		$this->originalsource = base64_encode( $content );
		unset( $content );
		$this->dbmimetype = $this->base64id[ substr( $this->originalsource, 0, 7 ) ];
		return true;
	}

	/**
	 * Return path to the original source of the image file
	 * @return string
	 */
	function getSource() {
		return $this->imgSource;
	}

	// Vrací aktuální hodnotu výchozí šířky
	function getSourceWidth() {
		return $this->iw;
	}

	/**
	 * Type of this object
	 * @return string type
	 */
	function getType() {
		return $this->type;
	}

	// Vrací šířku výřezu
	function getWidthClip() {
		return $this->cw;
	}

	/**
	 * Push content into path
	 * @return bool
	 */
	function pushContent() {
		return file_put_contents( $this->imgStorage, base64_decode( $this->imagecontent ) );
	}

	/**
	 * EImage ID is based on source of the image, crop and resize parameters
	 * Zero values of the width signalize using original size of source
	 * Zero values of the height signalize using proportional scale of source
	 * Default value of the resize is 1 == 100% orginal size
	 */
	function getEid() {
		$this->clip = [
			'source' => $this->imgSource,
			'page' => $this->page,
			'dpi' => $this->dpi,
			'iw' => $this->iw,
			'ih' => $this->ih,
			'cx' => $this->cx,
			'cy' => $this->cy,
			'cw' => $this->cw,
			'ch' => $this->ch,
			'resize' => $this->resize
		];
		$this->eid = sha1( FormatJson::encode( $this->clip, true ) );
		// $this->eid = sha1( "{$this->imgSource}!{$this->cx} {$this->cy} {$this->cw} {$this->ch}!{$this->resize}!{$this->width}" );
	}

	/**
	 * Set EImage ID to get the exist clip from cache
	 *
	 * @param string $hash eid
	 */
	public function setEid( $hash ) {
		$this->eid = $hash;
	}

	/**
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 * @param string $string is width and height of the crop area (in pixels)
	 */
	function setEidArea( $string = '' ) {
		$area = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $area[0] ) ) {
			$this->cw = (int)$area[0];
		}
		if ( isset( $area[1] ) ) {
			$this->ch = (int)$area[1];
		}
	}

	/**
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 * @param string $string X and Y coordinate from left-top corner, where is the zero point for shift of the crop area
	 */
	function setEidAxes( $string = '' ) {
		$axes = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $axes[0] ) ) {
			$this->cx = (int)$axes[0];
		}
		if ( isset( $axes[1] ) ) {
			$this->cy = (int)$axes[1];
		}
	}

	/**
	 * 3. Parameters are parts of the EImageIMG object ID.
	 */
	function setEidCrop() {
		// If not set crop, use original size
		if ( isset( $this->attribute['name']['crop'] ) ) {
			$crop = explode( ' ',
				trim( preg_replace( '/[\t\n\r\s]+/', ' ',
				$this->attribute['name']['crop'] )
				) );
			if ( isset( $crop[0] ) ) {
				$this->cx = (int)$crop[0];
			}
			if ( isset( $crop[1] ) ) {
				$this->cy = (int)$crop[1];
			}
			if ( isset( $crop[2] ) ) {
				$this->cw = (int)$crop[2];
			}
			if ( isset( $crop[3] ) ) {
				$this->ch = (int)$crop[3];
			}
			if ( isset( $crop[4] ) ) {
				$this->resize = $crop[4];
			} else {
				$this->resize = 1;
			}
		}
	}

	/**
	 * 2. If source is multipage, use EImage by default for clip first page (default 0)
	 */
	function setEidPage() {
		if ( isset( $this->attribute['name']['page'] ) ) {
			$this->page = $this->attribute['name']['page'];
		}
	}

	/**
	 * Parameter is part of the EImageIMG object ID.
	 *
	 * @param string $string is URL, path or name of the source image
	 * @return bool
	 */
	function setEidSource( $string = '' ) {
		if ( empty( $string ) ) {
			if ( isset( $this->parameters['index'][0] ) ) {
				$string = $this->parameters['index'][0];
			}
		}
		if ( substr( $string, 0, 4 ) !== 'http' ) {
			$this->imgSource = str_replace( ' ', '_', $string );
		} else {
			$this->imgSource = $string;
		}
	}

	/**
	 * Basic width of the original source image
	 *
	 * @param string $string set width by attribute
	 */
	function setEidWidth( $string = '' ) {
		// Resize original to new default width
		if ( empty( $string ) ) {
			if ( isset( $this->attribute['name']['width'] ) ) {
				$string = $this->attribute['name']['width'];
			}
		}
		$this->iw = (int)trim( $string );
	}

	/**
	 * 1. disable cache for this EImage instance is set 'nocache'
	 *
	 * @param bool $value false if is cache true
	 */
	function setCache( $value ) {
		$this->cache = $value;
	}

	/**
	 * EImage image as active link
	 *
	 * @param string $string is URL of the target
	 */
	function setLocation( $string = '' ) {
		global $wgScript;
		$this->location = $string;
		$this->style[] = "cursor:pointer;";
		if ( substr( $string, 0, 4 ) == 'http' ) {
			$this->onclick = "window.location.href='{$string}'";
		} elseif ( substr( $string, 0, 1 ) == '#' ) {
			$this->onclick = "window.location.href='{$string}'";
		} else {
			$this->onclick = "window.location.href='{$wgScript}/{$string}'";
		}
	}

	// Šířka boxu v procentech (2 pozice)
	function setPercentualWidth() {
		if ( is_numeric( $this->attribute['index'][1] ) ) {
			$this->width = $this->attribute['index'][1];
		}
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
	 * Parameters are parts of the EImageIMG object ID.
	 *
	 */
	// Tuhle funkci je třeba volat po načtení souboru
	function setOrigSize() {
		$crop = explode( ' ', trim( preg_replace( '/[\t\n\r\s]+/', ' ', $string ) ) );
		if ( isset( $crop[0] ) ) {
			$this->cx = "{$crop[0]}";
		}
		if ( isset( $crop[1] ) ) {
			$this->cy = "{$crop[1]}";
		}
		if ( isset( $crop[2] ) ) {
			$this->cw = "{$crop[2]}";
		}
		if ( isset( $crop[3] ) ) {
			$this->ch = "{$crop[3]}";
		}
		if ( isset( $crop[4] ) ) {
			$this->resize = "{$crop[4]}";
		} else {
			$this->resize = "1";
		}
	}

	/**
	 * Parameter is part of the EImageIMG object ID.
	 *
	 * @param string $string is resize value of the cropped area
	 */
	function setResize( $string = '' ) {
		$this->resize = trim( $string );
	}

	/**
	 * EImage content as alternative text
	 *
	 * @param string $string is the last item from the set of attributes
	 */
	function setTitle( $string = '' ) {
		$this->title = $string;
	}

	/**
	 * Converse string measures to float numbers
	 *
	 * @param string $string measure
	 * @return array (float, typ)
	 */
	public static function vectorPixel( $string = '' ) {
		switch ( substr( $string, -2 ) ) {
		case 'pt':
			$rozmer = floatval( substr( $string, 0, -2 ) ) * 1.333333333; // vypočtený rozměr v pixelech
			$jednotka = 'pt';
			break;
		case 'px':
			$rozmer = floatval( substr( $string, 0, -2 ) );
			$jednotka = 'px';
			break;
		case 'mm':
			$rozmer = floatval( substr( $string, 0, -2 ) ) * 3.78; // vypočtený rozměr v pixelech
			$jednotka = 'mm';
			break;
		case 'in':
			$rozmer = floatval( substr( $string, 0, -2 ) ) * 96; // vypočtený rozměr v pixelech
			$jednotka = 'in';
			break;
		case 'pc':
			$rozmer = floatval( substr( $string, 0, -2 ) ) * 16; // vypočtený rozměr v pixelech
			$jednotka = 'pc';
			break;
		default:
			$rozmer = floatval( $string ); // je to jen číslo
			$jednotka = '';
			break;
		}
		return [ 0 => $rozmer, 1 => $jednotka ];
	}
}
