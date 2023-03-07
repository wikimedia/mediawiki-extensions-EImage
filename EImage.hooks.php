<?php

use MediaWiki\MediaWikiServices;

class EImageHooks {

	/**
	 * Tag <accesscontrol> must be registered to the Parser. It is need,
	 *  because if the tag not in register, can't be replaced content of
	 *  this tag element on the page.
	 *
	 * @param Parser $parser instance of Parser
	 * @return bool true
	 */
	public static function eImageExtension(
		Parser $parser
		) {
		// Function hook for annotation layers
		$parser->setFunctionHook( 'eimagea', [ 'EImageStaticAnnot', 'annotation' ], SFH_OBJECT_ARGS );
		// Function hook for base image container
		$parser->setFunctionHook( 'eimage', [ 'EImageStaticMain', 'readInput'], SFH_OBJECT_ARGS );
		// Function hook for width img file
		$parser->setFunctionHook( "eimgw", "EImageHooks::eimageWidth" );
		// Function hook for height img file
		$parser->setFunctionHook( "eimgh", "EImageHooks::eimageHeight" );
		// Function hook for path to local img file
		$parser->setFunctionHook( "epath", "EImageHooks::eimageLocalPath" );
		// Function hook for dimensions img file, formated as: width x height
		$parser->setFunctionHook( "earea", "EImageHooks::eimageArea" );
		// Function hook for size img file (for humans),
		$parser->setFunctionHook( "eimgsize", "EImageHooks::eimageSize" );
		// Function hook for img file mime info (type of file) - usable for detection another types
		$parser->setFunctionHook( "eimgmime", "EImageHooks::eimageMime" );
		// Function hook for summary count pages of the multipage img file as PDF, or DjVu
		$parser->setFunctionHook( "epages", "EImageHooks::eimagePages" );
		// Function hook for img exif info - parameter
		$parser->setFunctionHook( "eimgexif", "EImageHooks::eimageExif" );
		// Function hook for add exif info into img, if is supported - parameter
//		$parser->setFunctionHook( "eiaddexif", "EImageHooks::getImageLocalPath" );

		return true;
	}

	/**
	 * Function for when the parser object is being cleared.
	 * @see	https://www.mediawiki.org/wiki/Manual:Hooks/ParserClearState
	 *
	 * @param $parser
	 * @return bool
	 */
	static public function onParserClearState( &$parser ) {
		return true;
	}

	/**
	 * Error message constants
	 */
	const ERR_INVALID_TITLE = 'eimage-invalid-title';
	const ERR_NOT_EXIST = NULL;
	const ERR_UNKNOWN_VALUE = 0;

	/**
	 * Function to get the width of the image.
	 *
	 * @param	$parser	Parser object passed a reference
	 * @param	string	Name of the image being parsed in
	 * @return	mixed	integer of the width or error message.
	 */
	public static function eimageWidth( $parser, $name = '' ) {
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			return $file->getWidth();
		}
		return self::ERR_UNKNOWN_VALUE;
	}

	/**
	 * Function to get the height of the image.
	 *
	 * @param	$parser	Parser object passed a reference
	 * @param	string	Name of the image being parsed in
	 * @return	mixed	integer of the height or error message.
	 */
	public static function eimageHeight( $parser, $name = '' ) {
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			return $file->getHeight();
		}
		return self::ERR_UNKNOWN_VALUE;
	}

	/**
	 * Function to get the width & height of the image.
	 * Formated as string WxH for using as parameter
	 * of the 'eimage'
	 *
	 * @param	$parser	Parser object passed a reference
	 * @param	string	Name of the image being parsed in
	 * @return	string or NULL
	 */
	public static function eimageArea( $parser, $name = '' ) {
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			return $file->getWidth() . 'x' . $file->getHeight() ;
		}
		return self::ERR_UNKNOWN_VALUE;
	}

	/**
	 * Get the size of a file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @return string or NULL
	 */
	public static function eimageSize( $parser, $name = '' ) {
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			return htmlspecialchars( $parser->getTargetLanguage()->formatSize( $file->getSize() ) );
		}
		return self::ERR_NOT_EXIST;
	}

	/**
	 * Function to get the path of the image.
	 *
	 * @param	$parser	Parser object passed a reference
	 * @param	string	Name of the image being parsed in
	 * @return	string	or NULL
	 */
	static public function eimageLocalPath( $parser, $name = '' ) {
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			return parse_url($file->getURL() , PHP_URL_PATH);
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
		$file = self::resolve( $name );
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
	 * @return integer or NULL
	 */
	public static function eimagePages( $parser, $name = '' ) {
		$file = self::resolve( $name );
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
	 * Get EXIF metadata from file
	 *
	 * @param Parser $parser Calling parser
	 * @param string $name File name
	 * @param string $meta Metadata name
	 * @return string
	 */
	public static function eimageExif( $parser, $name = '', $meta = '' ) {
		global $wgEImageUseExiftool;
		$file = self::resolve( $name );
		if ( $file instanceof File ) {
			$parser->getOutput()->addImage( $file->getTitle()->getDBkey() );
			switch ($meta) {
				case 'meta':
					break ;
				default : if ( $file->getLocalRefPath() ) {
					if ( $wgEImageUseExiftool ) {
						$data =  shell_exec('exiftool -php ' . $file->getLocalRefPath() );
						eval ( "\$exiftagy = " . $data . ";" );
						if (is_array($exiftagy)) {
							if ( count( $exiftagy ) == 1 ) {
//					return print_r( array_keys($exiftagy[0][ $meta ] );
								switch ($meta) {
									case 'array' : 
										return serialize($exiftagy[0]);
										break;
									case 'list' :
										return implode( ', ' , array_keys($exiftagy[0]) );
										break;
									case 'template' :
										break;
									default : return $exiftagy[0][$meta];
								}
							}
						}
					} else {
							$fp = fopen( $file->getLocalRefPath(), 'rb' );
							if (!$fp) {
								return self::ERR_NOT_EXIST;
							}
						try {
							$headers = exif_read_data($fp);
						} catch ( Exception $e ) {
							return wfMessage( 'error_unknown_filetype' )->text();
						}
							if ($headers) {
								switch ($meta ) {
									case 'serialize' :
										return serialize($headers);
									case 'json' :
										return "<!-- " . print_r($headers) . " -->";
								default :
									break;
								}
							}
					}
				}
			}
			return $meta;
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
	private static function resolve( $text ) {
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

}
