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
		
		$deck = InputValidator::validate($file, ['black' => ['string'], 'white' => ['string']], true);

		foreach ($deck->black as $text) {
			$this->black_cards->attach(new BlackCard($text));
		}
		
		foreach ($deck->white as $text) {
			$this->white_cards->attach(new WhiteCard($text));
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
		
		$data = InputValidator::validate($data, [
			'calls' => [['text' => ['string']]],
			'responses' => [['text' => ['string']]]
		], true);
		
		foreach ($data->calls as $black_card) {
			$this->black_cards->attach(new BlackCard(implode('_', $black_card->text)));
		}
		
		foreach ($data->responses as $white_card) {
			$this->white_cards->attach(new WhiteCard(implode('', $white_card->text)));
		}
		
		// I don't know what to do after this.
	}

}