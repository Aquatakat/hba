<?php

namespace HBA;

class DeckList {
	
	private $decks;
	
	public function __construct() {
		$this->decks = [];
	}
	
	public function get($what) {
		if (isset($this->decks[$what])) {
			return $this->decks[$what];
		} else {
			$new_deck = new Deck($what);
			$this->decks[$what] = $new_deck;
			return $new_deck;
		}
	}
	
}