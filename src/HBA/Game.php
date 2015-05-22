<?php

namespace HBA;

class Game {
	
	const READY = 0;
	const PLAYING = 1;
	const JUDGING = 2;

    private $players;
    private $conn;
    public $id;
    private $owner;
    public $game_list;
	private $czar;
	private $czar_queue;
	private $black_cards;
	private $white_cards;
	public $black_card;
	private $black_cards_played = 0;
	private $white_cards_played = 0;
	private $total_black_cards = 0;
	private $total_white_cards = 0;
	private $max_points = 10;
	private $max_players = 10;
	private $hand_size = 10;
	private $password = '';
	private $blank_cards = 5;
	private $timeout = 45;
	private $timeout_cards = 15;
	private $timeout_players = 7;
	private $last_timeout;
	private $state = self::READY;
	public $round = 0;
	private $table = [];

    public function __construct($conn, $games, $decks) {
        $this->players = new \SplObjectStorage; // I don't know why I'm doing this.
		$this->czar_queue = new \SplObjectStorage;
        $this->conn = $conn;
        $this->game_list = $games;
		$this->black_cards = [];
		$this->white_cards = [];
		$this->black_card = false;
		$this->addDeck($decks->get(1));
    }

    public function __get($key) {
        if (in_array($key, ['owner', 'max_points'])) {
            return $this->$key;
        }
    }

    public function __set($key, $value) {
        switch ($key) {
			
            case 'owner':
				$this->makeOwner($value);
				break;
				
        }
	}
    
    public function addDeck(Deck $deck) {
		foreach ($deck->black_cards as $card) {
			$this->black_cards[] = $card;
			$this->total_black_cards++;
		}
		foreach ($deck->white_cards as $card) {
			$this->white_cards[] = $card;
			$this->total_white_cards++;
		}
		shuffle($this->black_cards);
		shuffle($this->white_cards);
    }
	
	public function beginJudging() {
		
		if ($this->state !== self::PLAYING) {
			throw new \Exception('Who are you, Judge Judy!? Somehow game judging started at the wrong time. I\'m so perplexed.');
		}
		
		$this->shuffleTable();
		$this->state = self::JUDGING;
		$this->last_timeout =
			time()
			+ $this->timeout_players * $this->black_card->responses * $this->players->count()
			+ $this->timeout;
		
		$played_players = new \SplObjectStorage;
		foreach ($this->table as $table_player) {
			$played_players->attach($table_player['player']);
		}
		foreach ($this->players as $player) {
			if (!$played_players->contains($player) && !$player->isCzar()) {
				$this->sendGameMessage($player, 'You have been skipped this round');
			}
		}
		
		if (empty($this->table)) {
			$this->beginRound();
			return;
		}
		
		foreach ($this->players as $player) {
			$this->processState($player);
		}
		
	}
	
	public function beginRound() {
		
		if ($this->state === self::PLAYING) {
			throw new \Exception('Somehow you managed to press the start button but the game has already started? I don\'t understand.');
		}
		
		if ($winner = $this->hasSomeoneWon()) {
			$this->sendToAll('gamewinner', $winner->name);
			foreach ($this->players as $player) {
				$player->score = 0;
				$player->returnAllCards();
			}
			$this->round = 0;
		} else {
			
			$this->round++;
			$this->determineCzar();
			$this->dealBlack();
			$this->state = self::PLAYING;
			$this->last_timeout = time() + $this->timeout_cards * $this->black_card->responses + $this->timeout;
			
		}
		
		foreach ($this->players as $player) {
			$this->processState($player);
		}
		
		$players = [];
		foreach ($this->players as $player) {
			$players[] = $player;
		}
		foreach ($players as $player) {
			$this->sendPlayerToAll($player);
		}

	}
	
	public function chat(Player $player, $text) {
		$this->sendToAll('chat', ['from' => $player->name, 'text' => $text]);
	}
	
	private function checkPlayersPlayed() {
		foreach ($this->players as $player) {
			if (!$player->isCzar()) {
				if ($player->state !== Player::PLAYED) return false;
			}
		}
		return true;
	}
	
	public function countPlayers() {
		return $this->players->count();
	}
	
	public function dealBlack() {
		if ($this->black_card) {
			$this->black_cards[] = $this->black_card;
		}
		$this->black_card = array_shift($this->black_cards);
		$this->black_cards_played++;
		if ($this->black_cards_played >= $this->total_black_cards) {
			shuffle($this->black_cards);
		}
		return $this->black_card;
	}
	
	public function dealWhite(Player $player) {
		if (!$this->black_card) {
			throw new \Exception('For some reason I\'m trying to deal white cards before a black card has been dealt.');
		}
		$how_many = $this->hand_size + $this->black_card->responses - count($player->hand) - 1;
		if (!$player->isCzar()) {
			for ($i = 0; $i < $how_many; $i++) {
				if (count($this->white_cards) < 1) continue;
				if (mt_rand(0, 100) <= $this->blank_cards) {
					$player->draw(new BlankCard);
				} else {
					$player->draw(array_shift($this->white_cards));
					$this->white_cards_played++;
					if ($this->white_cards_played >= $this->total_white_cards) {
						shuffle($this->white_cards);
					}
				}
			}
		}
	}
	
	private function determineCzar() {
		$random_czar = true;
		
		if ($this->czar) {
			$this->czar_queue->rewind();
			while ($this->czar_queue->valid()) {
				if ($this->czar == $this->czar_queue->current()) {
					$this->czar_queue->next();
					$random_czar = false;
					break;
				}
				$this->czar_queue->next();
			}
		}
		
		if ($random_czar) {
			$first_czar = mt_rand(0, $this->czar_queue->count() - 1);
			$this->czar_queue->rewind();
			for ($i = 0; $i < $first_czar; $i++) {
				$this->czar_queue->next();
			}
		}
		
		if (!$this->czar_queue->valid()) {
			$this->czar_queue->rewind();
		}
		$this->czar = $this->czar_queue->current();
		return $this->czar;
	}
    
	public function hasSomeoneWon() {
		foreach ($this->players as $player) {
			if ($player->score >= $this->max_points) return $player;
		}
		return false;
	}
	
    public function leave(Player $player) {
        if ($this->players->contains($player)) {
			$this->players->detach($player);
			$this->czar_queue->detach($player);
		}
		$this->game_list->addViewer($player);
        $this->sendRemovePlayer($player);
		$this->game_list->sendGameToAll($this);
		if (count($this->players) < 1) {
			$this->game_list->destroy($this);
        } else {
			if ($this->isCzar($player) && $this->state !== self::READY) {
				$this->interruptRound();
			}
			if ($this->isOwner($player)) {
				$this->players->rewind();
				$this->makeOwner($this->players->current());
				$this->sendIsOwner($this->players->current());
			}
			$this->returnCardFromTable($player);
			if ($this->state === self::PLAYING && $this->checkPlayersPlayed()) {
				$this->beginJudging();
			} elseif ($this->state === self::JUDGING) {
				if (empty($this->table)) {
					$this->beginRound();
					return;
				}
			}
		}
    }
	
	public function listing() {
		return [
			'id' => $this->id,
			'owner' => $this->owner->name,
			'players' => $this->players->count(),
			'maxPlayers' => $this->max_players
		];
	}
	
	public function interruptRound() {
		
		switch ($this->state) {
			
			case self::READY:
				throw new \Exception('There was an attempt to interrupt this round for no reason. Whatever you just did, NEVER DO IT AGAIN.');
				break;
				
			case self::PLAYING:
				$this->beginJudging();
				break;
				
			case self::JUDGING:
				$this->state = self::READY;
				$this->returnCardsFromTableToPlayers();
				$this->sendGameMessage($this->czar, 'You have been skipped this round');
				$this->beginRound();
				break;
				
		}
	}
	
	public function isCzar(Player $player) {
		return $this->czar == $player;
	}
	
	public function isOwner(Player $player) {
		return $this->owner == $player;
	}

    public function join(Player $player, $owner = false) {
		if ($this->players->count() >= $this->max_players) {
			throw new \Exception('This game is exceptionally full. Bursting at the seams almost.');
		}
        if (!$this->players->contains($player)) {
			$this->players->attach($player); // oh I figured it out
			$this->czar_queue->attach($player);
		}
		$player->joinGame($this);
        $player->send('join', $this->id);
		if ($owner) {
			$this->makeOwner($player);
		}
		$this->sendIsOwner($player);
		$this->sendAllPlayers($player);
        $this->sendPlayerToAll($player);
		$this->processState($player);
		$this->game_list->removeViewer($player);
		$this->game_list->sendGameToAll($this);
    }
	
    public function makeOwner(Player $owner) {
        $this->owner = $owner;
		$this->game_list->sendGameToAll($this);
    }
	
	public function processState(Player $player) {
		switch ($this->state) {
						
			case self::READY:
				$player->state = Player::READY;
				$this->sendPlayerState($player);
				break;
			
			case self::PLAYING:
				$player->state = $player->isCzar()
					? Player::WAITING_FOR_PLAYERS
					: Player::PLAYING;
				$this->sendPlayerState($player);
				$this->sendBlackCard($player);
				$this->dealWhite($player);
				$this->sendTimeout($player);
				break;
				
			case self::JUDGING:
				$player->state = $player->isCzar()
					? Player::JUDGING
					: Player::WAITING_FOR_CZAR;
				$this->sendPlayerState($player);
				$this->sendTable($player);
				$this->sendTimeout($player);
				break;
				
		}
		//$this->sendPlayerToAll($player);
		//$this->sendGameState($player);
	}
	
	public function removeDeck(Deck $deck) {
		$this->black_cards->removeAll($deck->black_cards);
		$this->white_cards->removeAll($deck->white_cardS);
	}
	
	public function returnCardsFromTable() {
		foreach ($this->table as $key => $table_player) {
			foreach ($table_player['cards'] as $card) {
				$this->returnWhite($card);
			}
		}
		$this->table = [];
	}
	
	public function returnCardFromTable(Player $player) {
		foreach ($this->table as $key => $table_player) {
			if ($table_player['player'] === $player) {
				foreach ($table_player['cards'] as $card) {
					$this->returnWhite($card);
				}
				if ($this->state === self::JUDGING) {
					$this->sendRemoveTabledCard($key);
				}
				unset($this->table[$key]);
				break;
			}
		}
	}
	
	// I wrote the name for this function while 4-8 (it's hazy) drinks drunk and I'm KEEPING IT
	public function returnCardsFromTableToPlayers() {
		foreach ($this->table as $key => $table_player) {
			foreach ($table_player['cards'] as $card) {
				if ($card instanceof BlankCard) {
					$card->setText(false);
				}
				$table_player['player']->draw($card);
			}
			unset($this->table[$key]);
		}
	}
	
	public function returnWhite(Card $card) {
		if (!$card instanceof BlankCard) {
			$this->white_cards[] = $card;
		}
	}
	
	public function select($card_id) {
		if ($this->state !== self::JUDGING) {
			throw new \Exception('Oops! I think you were too late judging this card.');
		}
		// while I was writing this function Sean was being the worst.
		$card_id = (int)$card_id;
		if (!isset($this->table[$card_id])) {
			throw new \Exception('Somehow you judged that a nonexistent user is the winner. That\'s almost as bad as picking the "This answer is postmodern" card.');
		}
		$this->sendWinner($this->table[$card_id]);
		$winner = $this->table[$card_id]['player'];
		$winner->score++;
		$this->sendPlayerToAll($winner);
		$this->state = self::READY;
		$this->returnCardsFromTable();
		foreach ($this->players as $player) {
			$this->processState($player);
		}
	}
	
	public function sendBlackCard(Player $player) {
		$player->send('blackcard', [
			'text' => $this->black_card->text,
			'responses' => $this->black_card->responses
		]);
	}
	
	public function sendGameMessage(Player $player, $message) {
		$player->send('chat', ['text' => $message]);
	}
	
	public function sendGameState(Player $player) {
		$game_states = [self::READY => 'ready', self::PLAYING => 'playing', self::JUDGING => 'judging'];
		$player->send('gamestate', $game_states[$this->state]);
	}
	
	public function sendPlayerState(Player $player) {
		$player_states = [
			Player::READY => 'ready',
			Player::PLAYING => 'playing',
			Player::PLAYED => 'played',
			Player::WAITING_FOR_CZAR => 'waitingforczar',
			Player::WAITING_FOR_PLAYERS => 'waitingforplayers',
			Player::JUDGING => 'judging'
		];
		$player->send('state', $player_states[$player->state]);
	}
	
	public function sendIsOwner(Player $player) {
		$player->send('owner', $player->isOwner());
	}
	
	public function sendPlayer(Player $player, Player $to) {
		$to_send = [
			'id' => $player->id,
			'name' => $player->name,
			'score' => $player->score,
			'maxScore' => $this->max_points,
			'state' => $player->state_text
		];
		$to->send('player', $to_send);
	}
	
	public function sendPlayerToAll(Player $player) {
		foreach ($this->players as $recipient) {
			$this->sendPlayer($player, $recipient);
		}
	}
	
	public function sendRemovePlayer(Player $player) {
		foreach ($this->players as $recipient) {
			$recipient->send('removeplayer', $player->id);
		}
	}
    
	public function sendRemoveTabledCard($id) {
		$this->sendToAll('removetable', $id);
	}
	
    public function sendAllPlayers(Player $to) {
        foreach ($this->players as $player) {
            $this->sendPlayer($player, $to);
        }
    }
	
	public function sendTable(Player $player) {
	
		foreach ($this->table as $key => $table_cards) {
			$player->send('table', ['id' => $key, 'cards' => $table_cards['cards_text']]);
		}
	}

	public function sendTimeout(Player $player) {
		if ($this->timeout > 0) {
			$player->send('timeout', $this->last_timeout - time());
		}
	}
	
    public function sendToAll($what, $data, Player $except_to = null) {
        foreach ($this->players as $player) {
            if ($player != $except_to) {
				$player->send($what, $data);
			}
        }
    }
	
	private function sendWinner($hand) {
		foreach ($this->black_card->response_types as $key => $response_type) {
			$cards[$key] = $hand['cards'][$key]->$response_type;
		}
		$this->sendToAll('point', ['player' => $hand['player']->name, 'cards' => $cards]);
	}
	
	public function settings($data = null) {
		
		$keys = ['max_points', 'max_players', 'blank_cards', 'timeout', 'timeout_cards', 'timeout_players', 'hand_size'];
		
		if ($data === null) {
			
			$return = [];
			foreach ($keys as $key) {
				$return[] = ['id' => $key, 'value' => $this->$key];
			}
			return $return;
			
		} else {
			
			$data = (array)$data;
			
			if (!isset($data['id']) || !isset($data['value'])) {
				throw new \Exception('Some terrible thing is happening with the settings window. You probably can\'t fix it. Sorry.');
			}
			
			// who even fucking cares
			// this language is a pile of shit
			
			switch ($data['id']) {
				
				case 'max_points':
				
					if (false === filter_var(
						$data['value'],
						\FILTER_VALIDATE_INT,
						['options' => ['min_range' => 1, 'max_range' => 147]]
					)) { throw new \Exception('The points to win has to be between 1 and the not at all arbitrary number 147.'); }
					
					$this->max_points = (int)$data['value'];
					break;
					
				case 'max_players':
					if (false === filter_var(
						$data['value'],
						\FILTER_VALIDATE_INT,
						['options' => ['min_range' => 2, 'max_range' => 47]]
					)) { throw new \Exception('The maximum number of players must be between 2 and the not at all arbitrary number 47.'); }
					
					$old_max_players = $this->max_players;
					$this->max_players = (int)$data['value'];
					if ($this->blank_cards <> $old_max_players) {
						$this->game_list->sendGameToAll($this);
					}
					
					break;
					
				case 'blank_cards':
				
					if (false === filter_var(
						$data['value'],
						\FILTER_VALIDATE_INT,
						['options' => ['min_range' => 0, 'max_range' => 100]]
					)) { throw new \Exception('What did you do to my beautiful slider that works in most browsers I think? Blank cards must be between 0 and 100.'); }
					
					$this->blank_cards = (int)$data['value'];
					break;
					
				case 'timeout': case 'timeout_cards': case 'timeout_players':
				
					if (false === filter_var(
						$data['value'],
						\FILTER_VALIDATE_INT,
						['options' => ['min_range' => 0, 'max_range' => 600]]
					)) { throw new \Exception('Hey you. Timeouts have to be between 0 and 600 seconds (10 minutes).'); }
					
					$this->{$data['id']} = (int)$data['value'];
					break;
					
				case 'hand_size':
					if (false === filter_var(
						$data['value'],
						\FILTER_VALIDATE_INT,
						['options' => ['min_range' => 1, 'max_range' => 47]]
					)) { throw new \Exception('For some delightful reason the number of cards in your hand must be between 1 and the not at all arbitrary number 47.'); }
					$this->hand_size = $data['value'];
					break;
					
			}
		}
	}
	
	private function shuffleTable() {
		shuffle($this->table);
		// there used to be a reason this function existed
		// but instead I destroyed self::table()
	}
	
	public function table(Card $card, Player $player) {
		
		if ($this->state !== self::PLAYING) {
			throw new \Exception('Whoops! Too late. This round has ended.');
		}
		if ($player->state !== Player::PLAYING) {
			throw new \Exception('Hey, hey, hey! You played enough white cards.');
		}
		
		$card_count = false;
		
		foreach ($this->table as $key => $table_player) {
			if ($table_player['player'] == $player) {
				$this->table[$key]['cards'][] = $card;
				$this->table[$key]['cards_text'] [] = $card->getText();
				$card_count = count($this->table[$key]['cards']);
				break;
			}
		}
		
		if ($card_count === false) {
			$this->table[] = [
				'player' => $player,
				'cards' => [$card],
				'cards_text' => [$card->text]
			];
			$card_count = 1;
		}
		
		if ($card_count >= $this->black_card->responses) {
			$player->state = Player::PLAYED;
			$this->sendPlayerState($player);
			$this->sendPlayerToAll($player);
		}
		
		if ($this->checkPlayersPlayed()) {
			$this->beginJudging();
		}
	}

}