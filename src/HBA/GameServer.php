<?php
// c'mon ratchet hello world tutorial messages
// with PHP_EOL for no fucking reason
namespace HBA;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

date_default_timezone_set('America/Edmonton');
mb_internal_encoding('UTF-8');

class GameServer implements MessageComponentInterface {
    private $clients;
    private $players = [];
    private $games;
    private $decks;
	private $error_codes = [
		0 => 'name'
	];
	public $time = false;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->games = new GameList;
        $this->decks = new DeckList;
    }
	
	public function isNameInUse($name) {
		foreach ($this->players as $player) {
			if ($player->name === $name) return true;
		}
		return false;
	}

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->players[$conn->resourceId] = new Player($conn, $this);
		$this->games->addViewer($this->players[$conn->resourceId]);
        echo "New connection ({$conn->resourceId})", PHP_EOL;
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        		
        echo date('Y-m-d G:i:s'), ' from ', $from->resourceId, ': ',$msg, PHP_EOL;

        $player = $this->players[$from->resourceId];

        list($action, $data) = json_decode($msg);
		
		try {

			switch ($action) {

				case 'name':
					$player->name = $data;
					$player->send('name', $player->name);
					break;

				case 'leave':
					$player->leaveGame();
					$this->games->sendAllGames($player);
					break;

				case 'create':
					$this->games->create($from, $this->decks, $player);
					break;

				case 'join':
					$this->games->findById($data)->join($player);
					break;
				
				case 'set':
					if (!$player->isOwner()) throw new \Exception('Somehow you\'re trying to set settings for a game you\'re not the owner of. This is very concerning.');
					$player->game->settings($data);
					break;
				
				case 'settings':
					if (!$player->game) throw new \Exception('Somehow you\'re trying to get settings for a game you\'re not in. This is very perplexing.');
					$player->send('settings', $player->game->settings());
					break;
					
				case 'cardcast':
					if (!$player->isOwner()) throw new \Exception('Where are you adding these cards to? Outer space?');
					$player->game->addDeck($this->decks->get($data));
					break;
				
				case 'start':
					if (!$player->isOwner()) throw new \Exception('I\'m so scared because you\'re not the owner of the game and you tried to start it.');
					$player->game->beginRound();
					break;
					
				case 'chat':
					if (!$player->game) throw new \Exception('You sent a chat message while not in a game. What.');
					$player->game->chat($player, $data);
					break;
				
				case 'play':
					if (!$player->game) throw new \Exception('Something is so very wrong. You have cards, but you\'re not in a game? Oh dear.');
					$player->play($data);
					break;
				
				case 'select':
					if (!$player->game) throw new \Exception('I\'m frightened and afraid because you tried to select a card and you\'re not even in a game and I don\'t even know who I am anymore.');
					$player->select($data);
					break;
					
				case 'timeout':
					if ($player->isOwner()) {
						$player->game->interruptRound();
					}
					break;
				
				case 'kill':
					$player->sendError('kill', 'You\'re the worst. Don\'t press this.');
					//exit('Server killed by '.$player->name ?: $from->resourceId);

			}
			
		} catch (PasswordException $e) {
			$player->sendError('password', $e->getMessage());
		} catch (\Exception $e) {
			$player->sendError($action, $e->getMessage());
		}

        if ($player->name == '') {
			$player->sendError('name');
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->players[$conn->resourceId]->destruct();
		$this->games->removeViewer($this->players[$conn->resourceId]);
		unset($this->players[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected", PHP_EOL;
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}", PHP_EOL;
        $conn->close();
    }

}