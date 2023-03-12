<?php
/**
 * Copyright (c) 2023, Aleš Kapica
 * This software is made available under the terms of the GPL-2.0 License that can
 * be found in the LICENSE.txt file.
 */

class EImageQR extends EImageBOX {
	public $width;
	public $heigth;
	public $content;
	private $type = 'eqrcode';
	private $name = 'none';
	private $eid; // identifikátor lokálního souboru

	function getType() {
		return $this->type;
	}

	function getBgName() {
		$this->eid = md5( $this->name );
		$this->eid .= md5( $this->content );
		return $this->eid;
	}
}
