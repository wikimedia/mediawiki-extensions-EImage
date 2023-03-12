<?php
/**
 * Copyright (c) 2013, Robpol86
 * This software is made available under the terms of the MIT License that can
 * be found in the LICENSE.txt file.
 */

class EImageQuery {
	protected $parser; // MediaWiki parser object.
	protected $frame; // The frame to use for expanding any template variables.
	protected $data; // EImageData instance.
	protected $ei_image = null; // Image ID.
	protected $ei_host = null; // Image host.
	protected $wiki_title = null; // Base64 encoded title of the wiki article.
	protected $stale = null; // True if data in the DB is older than wgEImageStaleMinutes. Virgin image if null.

	function __construct( Parser $parser, PPFrame $frame, EImageData &$data, $image ) {
		$this->parser = $parser;
		$this->frame = $frame;
		$this->data = $data;
		$this->ei_image = $image;
		$this->wiki_title = base64_encode( $parser->getTitle()->getText() );
	}

	/**
	 * Sets image metadata.
	 *
	 * @return bool
	 */
	public function queryAPI() {
		if ( $this->data->getWidth() === null ) {
			return $this->errorImage( wfMessage( 'eimage-rawmissingwidth' ) );
		}
		if ( $this->data->getHeight() === null ) {
			return $this->errorImage( wfMessage( 'eimage-rawmissingheight' ) );
		}
		$this->data->set_ei_errormsg( null );
		$this->data->set_ei_width( $this->data->getWidth() );
		$this->data->set_ei_height( $this->data->getHeight() );
		$this->data->add_ei_imgurl( $this->data->get_ei_width(), $this->ei_image );
		return true;
	}

	/**
	 * Post-query cleanup such as replacing !!TITLE!! in captions with the actual title from the API, etc.
	 *
	 * @return bool Always true.
	 */
	public function cleanup() {
		$this->data->replaceFromHost();
		$this->data->linkJuggle();
		$this->data->amendWidth();
		$this->data->amendAltTitle();
		return true;
	}

	/**
	 * Build HTML and return to parser. Output is encoded in the static method to work around the <p><br /></p>
	 * problem. Borrowed from Widget extension.
	 *
	 * @return string Final encoded HTML.
	 */
	public function output() {
		$parsed_data = EImageStaticHtml::output( $this->data );
		return 'ENCODED_EIMAGE_CONTENT ' . base64_encode( $parsed_data ) . ' END_ENCODED_EIMAGE_CONTENT';
	}

	/**
	 * Sets the error image.
	 *
	 * @param string $s Error message to display.
	 * @return bool Always false.
	 */
	public function errorImage( $s ) {
		global $wgEImageEmptyPng;
		$this->data->set_ei_width( 200 );
		$this->data->set_ei_height( 200 );
		$this->data->set_ei_errormsg( $s );
		$this->data->set_ei_filename( basename( $wgEImageEmptyPng ) );
		$this->data->set_ei_imgurlfs( $wgEImageEmptyPng );
		// $this->data->set_ei_title( $this->parser, $this->frame, $this->data->get_ei_errormsg() );
		//$this->data->set_ei_comment( $this->parser, $this->frame, $this->data->get_ei_errormsg() );
		$this->data->add_ei_imgurl( $this->data->get_ei_width(), $this->data->get_ei_imgurlfs(), true );
		return false;
	}

}
