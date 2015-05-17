<?php

namespace HBA;

class GameList {

	private $games = [];
	private $viewers;

	public function __construct() {
		array_unshift($this->games, 'this is to make the array start at 1 rather than 0');
		unset($this->games[0]); // and it is terrible.
		$this->viewers = new \SplObjectStorage;
	}
	
	public function addViewer(Player $player) {
		if (!$this->viewers->contains($player)) {
			$this->viewers->attach($player);
		}
	}

	public function create($conn, $decks, $player) {
		$game = new Game($conn, $this, $decks, $player);
		$this->games[] = $game;
		end($this->games);
		$game->id = key($this->games);
		$game->join($player, true);
		$this->sendGameToAll($game);
	}

	public function destroy(Game $game) {
		unset($this->games[$game->id]);
	}

	public function getGames() {
		$something = [];
		foreach ($this->games as $id => $game) {
			$something[] = ['id' => $id, 'owner' => $game->owner->name];
		}
		return $something;
	}

	public function findById($id) {
		$id = (int)$id;
		if (!isset($this->games[$id])) throw new \Exception ('Ooh gurl! Game #'.$id.' is gone!');
		return $this->games[$id];
	}
	
	public function removeViewer(Player $player) {
		$this->viewers->detach($player);
	}
	
	public function sendAllGames(Player $player) {
		$player->send('gamelist', []);
		foreach ($this->games as $game) {
			$this->sendGame($player, $game);
		}
	}
	
	public function sendGame(Player $player, Game $game) {
		$player->send('game', $game->listing());
	}
	
	public function sendGameToAll(Game $game) {
		foreach ($this->viewers as $player) {
			$this->sendGame($player, $game);
		}
	}

}