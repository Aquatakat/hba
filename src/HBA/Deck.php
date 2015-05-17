<?php

namespace HBA;

class Deck {

	public $black_cards;
	public $white_cards;
	public $name = '';

	public function __construct($from) {
		$this->black_cards = new \SplObjectStorage();
		$this->white_cards = new \SplObjectStorage();
		if (is_int($from)) {
			$this->importFromLibrary($from);
		} else {
			$this->createFromCardcast($from);
		}
	}

	private function importFromLibrary($id) {

		$file = file_get_contents(dirname(__FILE__)."/../../lib/decks/".$id.".json");
		if ($file === false) {
			throw new \Exception('Goodness me. Where did that deck go? Try selecting a new one.');
		}

		$deck = json_decode($file);
		if (json_last_error() !== \JSON_ERROR_NONE) {
			throw new \Exception('Oh sorry. Somehow I managed to fuck up that deck and it didn\'t import right. OOPS: '.json_last_error_msg());
		}

		if (isset($deck->black)) {
			foreach ($deck->black as $text) {
				$this->black_cards->attach(new BlackCard($text));
			}
		}
		
		if (isset($deck->white)) {
			foreach ($deck->white as $text) {
				$this->white_cards->attach(new WhiteCard($text));
			}
		}

	}

	private function createFromCardcast($fivecode = false) {
		
		if (!$fivecode) {
			throw new \Exception('That box to put a CardCast deck code isn\'t just there to be pretty.');
		}
		
		if (!preg_match('#^[a-zA-Z0-9]{5}$#', $fivecode)) {
			throw new \Exception('CardCast codes are five digits, just letters and numbers henny.');
		}
		
		$data = file_get_contents('https://api.cardcastgame.com/v1/decks/'.$fivecode.'/cards');
		if ($data === false) {
			throw new \Exception('I\'m having serious trouble connecting to the CardCast API. Sorry.');
		}
		
		$data = json_decode($data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \Exception('Something has gone terribly wrong with the CardCast API. You probably can\'t fix this.');
		}
		
		if (!isset($data['calls']) || !isset($data['responses']) || (isset($data['id']) && $data['id'] == 'not found')) {
			throw new \Exception('This CardCast deck doesn\'t appear to exist. This is terrible.');
		}
		
		foreach ($data['calls'] as $black_card) {
			if (!isset($black_card['text']) || !is_array($black_card['text'])) continue;
			$this->black_cards->attach(new BlackCard(implode('_', $black_card['text'])));
		}
		
		foreach ($data['responses'] as $white_card) {
			if (!isset($white_card['text']) || !is_array($white_card['text'])) continue;
			$this->white_cards->attach(new WhiteCard(implode('', $white_card['text'])));
		}
		
		// I don't know what to do after this.
	}

}