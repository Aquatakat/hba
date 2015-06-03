<?php
namespace HBA;
use Ratchet\ConnectionInterface;

class Player {
	
	const READY = 0;
	const PLAYING = 1;
	const PLAYED = 2;
	const WAITING_FOR_CZAR = 3;
	const WAITING_FOR_PLAYERS = 4;
	const JUDGING = 5;

    private $name = '';
    public $game = false;
	public $server;
    private $conn;
	public $hand;
	public $score = 0;
    public $id;
	public $state = self::READY;

    public function __construct($conn, $server) {
        $this->conn = $conn;
		$this->server = $server;
        $this->id = $conn->resourceId;
		$this->hand = [];
		array_unshift($this->hand, 'pickle surprise');
        unset($this->hand[0]); // if this array starts at 0 some of the javascript doesn't like it
    }
    
    /*public function __destruct() {
        $this->leaveGame();
    }*/

    public function __get($key) {
        switch ($key) {
            case "name":
				return $this->name;
			case "state_text":
				switch ($this->state) {
					case self::READY: return 'ready';
					case self::PLAYING: return 'playing';
					case self::PLAYED: return 'played';
					case self::WAITING_FOR_CZAR: return 'waitingforczar';
					case self::WAITING_FOR_PLAYERS: return 'waitingforplayers';
					case self::JUDGING: return 'judging';
				}
        }
    }

    public function __set($key, $value) {
        switch ($key) {
			
            case "name":
			
				$value = trim($value);
				$value = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', $value);
				$value = preg_replace('/\\s+/u', ' ', $value);
				
				if (empty($value)) {
					throw new \Exception('Your name is suspiciously empty. You should type one in.');
				}
				
				$value = mb_substr($value, 0, 40);
				if ($this->server->isNameInUse($value, $this) !== false) {
					throw new \Exception('Someone\'s already using that name. You must be more original.');
				}
                $this->name = $value;
                if ($this->game) {
					$this->game->sendPlayerToAll($this);
				}
                break;
				
        }
    }
	
	public function destruct() {
		$this->leaveGame();
	}
	
	public function draw(Card $card) {
		$this->hand[] = $card;
		end($this->hand);
		$this->send('draw', [
			'id' => key($this->hand),
			'text' => $card instanceof BlankCard ? null : $card->getText()
		]);
	}
	
	public function isCzar() {
		if (!$this->game) return false;
		if (!$this->game->isCzar($this)) return false;
		return true;
	}
    
    public function isOwner() {
        if (!$this->game) return false;
		return $this->game->owner == $this;
    }

    public function joinGame($new_game) {
        if ($this->game != $new_game) {
            $this->leaveGame();
            $this->game = $new_game;
        }
    }

    public function leaveGame() {
		$this->returnAllCards();
		$this->server->games->sendAllGames($this);
		if (!$this->game) return;
        $this->game->leave($this);
		$this->game = false;
		$this->score = 0;
    }

    public function notify($text) {
        $this->send('notify', $text);
    }
	
	public function play($cards) {
		if (!$this->game) return;
		if ($this->isCzar) {
			throw new \Exception('You are no pleb. You cannot play a card. You must choose. ... This is an error message.');
		}
		foreach ($cards as $card) {
			if (!isset($this->hand[$card->id])) {
				throw new \Exception('You totes don\'t have that card in your hand anymore.');
			}
		}
		if ($this->game->black_card->responses !== count($cards)) {
			throw new \Exception('You appear to not be playing the correct number of cards. This is terrible.');
		}
		foreach ($cards as $card) {
			if ($this->hand[$card->id] instanceof BlankCard) {
				if (is_string($card->text)) {
					$this->hand[$card->id]->setText($card->text);
				} else {
					throw new \Exception('I don\'t have any good text to put in this error message because I\'m watching Roseanne right now and my brain doesn\'t work quite right when exposed to John Goodman.');
				}
			}
			$this->game->table($this->hand[$card->id], $this);
			unset($this->hand[$card->id]);
			$this->send('return', $card->id);
		}
	}
	
	public function select($card_id) {
		if (!$this->isCzar()) throw new \Exception('Only a czar may pass judgement on the plebs. ... This is an error message.');
		$this->game->select($card_id);
	}
	
	public function returnCard($card_id) {
		if (!$this->game) return;
		if (!isset($this->hand[$card_id])) throw new \Exception('You totes don\'t have that card in your hand anymore.');
		$this->game->returnWhite($this->hand[$card_id]);
		unset($this->hand[$card_id]);
		$this->send('return', $card_id);
	}

	public function returnAllCards() {
		foreach ($this->hand as $card_id => $who_cares) {
			$this->returnCard($card_id);
		}
	}
	
    public function send($what, $data) {
        $thing_to_send = json_encode([$what, $data]);
		if ($thing_to_send === false) {
			echo json_last_error_msg(), PHP_EOL;
		}
        echo date('Y-m-d G:i:s'), '   to ', $this->conn->resourceId, ': ', $thing_to_send, PHP_EOL;
        $this->conn->send($thing_to_send);
    }
	
	public function sendError($what, $text = '') {
		$what = ['type' => $what];
		if ($text) $what += array('text' => $text);
		$this->send('error', $what);
	}
	
	public function sendHand() {
	}

}