<?php

namespace HBA;

class BlackCard extends Card {

	private $text;

	public function __construct($text) {
		$this->text = $text;
	}
	
	public function countResponses() {
		return max(substr_count($this->text, '_'), 1);
	}
	
	public function getText() {
		return $this->text;
	}
	
	public function responseTypes() {
		$response_types = [];
		$responses = explode('_', $this->text);
		array_pop($responses);

		foreach ($responses as $some_text) {
			if ($some_text === '' || preg_match('/^\\s*[("]?[.!?]/u', strrev($some_text))) {
				$response_types[] = 'capitalised';
			} else {
				$response_types[] = 'raw';
			}
		}
		
		if (empty($response_types)) $response_types = array('punctuated');
		
		print_r($response_types);
		return $response_types;
	}

	public function __get($key) {
		switch ($key) {
			case 'text': return $this->getText();
			case 'responses': return $this->countResponses();
			case 'response_types': return $this->responseTypes();
		}
	}

}