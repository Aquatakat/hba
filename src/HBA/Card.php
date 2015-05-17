<?php

namespace HBA;

class Card {

    private $text;

    function __construct($text) {
		$this->text = $text;
    }
	
	function __get($key) {
		if ($key == 'text') {
			return $this->text; // ucfirst($this->text).'.';
		}
	}

}