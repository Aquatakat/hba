<?php

namespace HBA;

class WhiteCard extends Card {
	
	protected $text;
	
	public function __construct($text) {
		$this->text = $text;
	}

	public function __get($key) {
		return $this->get($key);
		
	}
	
	public function getText() {
		return $this->get('text');
	}
	
	public function get($key) {
		if ($key === 'raw') {
			return $this->text;
		}
		
		$ret = $this->text;
		
		if (in_array($key, ['capitalised', 'punctuated', 'text'])) {
			$ret = mb_strtoupper(mb_substr($ret, 0, 1)).mb_substr($ret, 1);
		}//*/
		
		if (in_array($key, ['punctuated', 'text'])) {
			if (preg_match('/([\\"])$/u', $ret, $matches)) {
				$ret = mb_substr($ret, 0, mb_strlen($ret) - mb_strlen($matches[1]));
				$ret .= '.'.$matches[1];
			} elseif (!preg_match('/[\\.\\?\\!]$/u', $ret)) {
				$ret .= '.';
			}
		}
		
		return $ret;
	}

}