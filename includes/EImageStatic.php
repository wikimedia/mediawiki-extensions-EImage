<?php
/**
 * Copyright (c) 2013, Robpol86
 * Copyright (c) 2019, Want
 * This software is made available under the terms of the MIT License that can
 * be found in the LICENSE.txt file.
 */

class EImageStatic {
	/**
	 * Inspired by preg_match and Python's str.startswith(). Returns true if $haystack starts with $key. Optionally
	 * chops off $key from $haystack and sets $value to the remainder (value in a key=value pair).
	 *
	 * @param string $haystack Haystack to search through.
	 * @param string $key Needle at the beginning of $haystack.
	 * @param string|null &$value If set, will be overwritten by the haystack with the leading needle removed.
	 * @return bool true if $haystack starts with $needle, false otherwise.
	 */
	public static function startsWith( $haystack, $key, &$value = null ) {
		if ( !strncmp( $haystack, $key, strlen( $key ) ) ) {
			// $haystack starts with $key.
			$value = (string)substr( $haystack, strlen( $key ) );
			return true;
		}
		return false;
	}

	/**
	 * Returns null instead of empty strings or integers with a value of 0. Otherwise return the input unchanged.
	 *
	 * @param string $s
	 * @return string
	 */
	public static function nullIfEmpty( $s ) {
		if ( $s == '' || $s == 0 ) {
			return null;
		}
		return $s;
	}

	/**
	 * Queries an image host for image metadata.
	 *
	 * @param string $url The URL to query.
	 * @param array $pvars POST variables to send with the query.
	 * @param array|null &$json If set, will be overwritten by the JSON response encoded in an associative array.
	 * @param array[] $headers
	 * @return int Returns the HTTP status of the query. Usually 200 indicates success.
	 */
	public static function curl( $url, $pvars = [], &$json = null, $headers = [] ) {
		$c = curl_init();
		curl_setopt( $c, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $c, CURLOPT_URL, $url );
		if ( !empty( $headers ) ) {
			curl_setopt( $c, CURLOPT_HTTPHEADER, $headers );
		}
		if ( !empty( $pvars ) ) {
			curl_setopt( $c, CURLOPT_POST, 1 );
			curl_setopt( $c, CURLOPT_POSTFIELDS, $pvars );
		}
		$json = (array)json_decode( curl_exec( $c ), true );
		$status = (int)curl_getinfo( $c, CURLINFO_HTTP_CODE );
		curl_close( $c );
		return $status;
	}

	/**
	 * Converts HTML links to wiki-text links. Mainly used for Flickr titles and comments/descriptions. A lot of
	 * Flickr images have links in those fields, which come to EImage as HTML. MediaWiki doesn't whitelist HTML links
	 * by default so they must be converted to wiki-text before sending them to the parser.
	 *
	 * @param string $text The string to be converted.
	 * @return string The converted string.
	 */
	public static function href( $text ) {
		require_once 'JSLikeHTMLElement.php';
		while ( true ) {
			// Replace links one by one. replaceChild() breaks loops so I have to do everything ever iteration.
			$doc = new DOMDocument();
			$doc->registerNodeClass( 'DOMElement', 'JSLikeHTMLElement' );
			$doc->loadHTML(
				'<!doctype html><html><head><meta charset="UTF-8"/></head><body>' .
				$text .
				'</body></html>'
			);
			$links = $doc->getElementsByTagName( 'a' );
			if ( $links->length < 1 ) {
				break;
			}
			$link = $links->item( 0 );
			$href = (string)$link->getAttribute( 'href' );
			$label = (string)$link->nodeValue;
			$replacement = $label !== '' ? "[{$href} {$label}]" : $href;
			$link->parentNode->replaceChild( $doc->createTextNode( $replacement ), $link );
			$text = $doc->getElementsByTagName( 'body' )->item( 0 )->innerHTML;
		}
		return $text;
	}

	/**
	 * In order to avoid having the MediaWiki parser double-parse the output of EImage, we encode the output during
	 * processing and then have the parser decode the output at the end.
	 *
	 * @param Parser &$parser
	 * @param string &$text
	 * @return bool Always true.
	 */
	public static function decode( &$parser, &$text ) {
		$count = 0;
		do {
			$text = preg_replace_callback(
				'/ENCODED_EIMAGE_CONTENT ([0-9a-zA-Z\/+]+=*)* END_ENCODED_EIMAGE_CONTENT/sm',
				static function ( $matches ) {
					return base64_decode( $matches[1] );
				},
				$text,
				-1,
				$count
			);
		} while ( $count );
		return true;
	}
}
