<?php

/*
I had this delightful idea where all of those functions in Game that applied to one player
or many players would return a Destination function and then this object would work on them
and everything would be great. And then I was lazy. This is Aqua Boards 6 all over again.
*/

namespace HBA;

class Destination {

	private $game;
	private $key;
	private $data;

	public function __construct(Game $game, $key, $data) {
		$this->game = $game;
		$this->key = $key;
		$this->data = $data;
	}
	
	public function toAll() {
		foreach ($this->game->players as $player) {
			$this->to($player);
		}
	}
	
	public function to(Player $player) {
		$player->send($this->key, $this->data);
	}

}