<?php
/**
 * MediaWiki extension, which allows users to display images from external
 * image hosts as if they were stored locally. But not only. Image may be
 * thumbnailed/resized/framed just like local images and the syntax used
 * is very similar to MediaWiki's Images syntax, but must feature is that,
 * than EImage can also overlay text on top of images and return info,
 * which can be used in templates.
 *
 * Version 2.0.0 by Robert Pooley
 * Version 3.0 by Aleš Kapica
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Robert Pooley, Aleš Kapica
 * @copyright 2013 Robert Pooley
 * @copyright 2019-2023 Aleš Kapica
 * @license GPL-2.0-or-later
 */

// PHP 8
if ( function_exists( 'match' ) ) { return 'Go PHP 8.0!';

}

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'EImage' );
	$wgMessageDirs['EImage'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for EImage extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
} else {
	die( 'This version of the EImage extension requires MediaWiki 1.39+' );
}
