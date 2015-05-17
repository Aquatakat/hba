<?php

namespace HBA;

class BlankCard extends WhiteCard {

	protected $text = false;

	public function __construct() {
	}
	
	public function setText($text) {
		$this->text = $text;
	}

}