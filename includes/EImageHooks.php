<?php

class EImageHooks {

	/**
	 * Tag <accesscontrol> must be registered to the Parser. It is need,
	 *  because if the tag not in register, can't be replaced content of
	 *  this tag element on the page.
	 *
	 * @param Parser $parser instance of Parser
	 * @return bool true
	 */
	public static function eImageExtension( Parser $parser ) {
		// Function hook for annotation layers
		$parser->setFunctionHook( 'eimagea', [ 'EImageStaticAnnot', 'annotation' ], SFH_OBJECT_ARGS );
		// Function hook for base image container
		$parser->setFunctionHook( 'eimage', [ 'EImageStaticMain', 'readInput' ], SFH_OBJECT_ARGS );
		// Function hook for base image container
		$parser->setFunctionHook( 'eimg', [ 'EImageStaticDiv', 'image' ], SFH_OBJECT_ARGS );
		// Function hook for base block container
		$parser->setFunctionHook( 'eibox', [ 'EImageStaticDiv', 'block' ], SFH_OBJECT_ARGS );
		// Function hook for width img file
		$parser->setFunctionHook( 'eimgw', [ 'EImageIMG', 'eimageWidth' ] );
		// Function hook for height img file
		$parser->setFunctionHook( 'eimgh', [ 'EImageIMG', 'eimageHeight' ] );
		// Function hook for path to local img file
		$parser->setFunctionHook( 'epath', [ 'EImageIMG', 'eimageLocalPath' ] );
		// Function hook for dimensions img file, formated as: width x height
		$parser->setFunctionHook( 'earea', [ 'EImageIMG', 'eimageArea' ] );
		// Function hook for size img file (for humans),
		$parser->setFunctionHook( 'eimgsize', [ 'EImageIMG', 'eimageSize' ] );
		// Function hook for img file mime info (type of file) - usable for detection another types
		$parser->setFunctionHook( 'eimgmime', [ 'EImageIMG', 'eimageMime' ] );
		// Function hook for summary count pages of the multipage img file as PDF, or DjVu
		$parser->setFunctionHook( 'epages', [ 'EImageIMG', 'eimagePages' ] );
		// Function hook for img exif info - parameter
		$parser->setFunctionHook( 'eimgexif', [ 'EImageIMG', 'eimageExif' ] );

		return true;
	}

	/**
	 * Function for when the parser object is being cleared.
	 * @see	https://www.mediawiki.org/wiki/Manual:Hooks/ParserClearState
	 *
	 * @param Parser &$parser
	 * @return bool
	 */
	public static function onParserClearState( &$parser ) {
		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function createTable( DatabaseUpdater $updater ) {
		global $wgEImageCache;
		if ( ! $wgEImageCache ) {
			// DatabaseUpdater does not support other databases, so skip
			return;
		}
		$db = $updater->getDB();
		$dbType = $db->getType();
		$dir = dirname( __DIR__ ) . '/schema';
		$updater->addExtensionTable( 'ei_cache', "$dir/$dbType/tables-generated.sql" );
	}

}
