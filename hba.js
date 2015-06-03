// don't look at this. it's spaghetti.

// thanks Pointy on stackoverflow <3
function pad(n, width, z) {
  z = z || '0';
  n = n + '';
  return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}

window.addEventListener("DOMContentLoaded", function() {
	
	var noises = false;
	var textToSpeech = false;
	var winRoundNoise = false;
	var loseRoundNoise = false;

    function notify(text) {
        var p = document.createElement("p");
        var hiding;
		var removing;
		p.appendChild(document.createTextNode(text));
        document.getElementById("notify").appendChild(p);

        var hide = function(duration) {
            hiding = window.setTimeout(function() {
                if (p.classList) p.classList.add("hidden");
				removing = window.setTimeout(function() {
					p.style.display = "none";
				}, 1000);
            }, duration);
        };
		
		hide(3000);

        p.addEventListener("mouseover", function(e) {
            window.clearTimeout(hiding);
			window.clearTimeout(removing);
            p.classList.remove("hidden");
        });

        p.addEventListener("mouseout", function(e) { hide(1000); });
        p.addEventListener("click", function(e) { hide(0); });
    }

    var lightboxBox = document.getElementById("d_lightbox");
	var lightboxClosing;

    function closeLightbox() {
        lightboxBox.classList.add("hidden");
        var notLightbox = document.querySelectorAll("body > :not(.unblurrable)");
        for (var i = 0; i < notLightbox.length; i++) {
            notLightbox[i].classList.remove("blur");
        }
        lightboxClosing = window.setTimeout(function() { lightboxBox.hidden = true; }, 250);
    }

    function openLightbox(show) {
		window.clearTimeout(lightboxClosing);
        var sections = lightboxBox.querySelectorAll("section");
        for (var i = 0; i < sections.length; i++) {
            sections[i].hidden = true;
        }
        var notLightbox = document.querySelectorAll("body > :not(.unblurrable)");
        for (var i = 0; i < notLightbox.length; i++) {
            notLightbox[i].classList.add("blur");
        }
        document.getElementById(show).hidden = false;
		document.getElementById(show).classList.remove("hidden");
        lightboxBox.hidden = false;
		var input = document.getElementById(show).querySelector("input");
		if (input && input.select) {
			input.select();
		}
        window.setTimeout(function() { lightboxBox.classList.remove("hidden"); }, 50);
    }

    lightboxBox.addEventListener("click", function(e) {
        if (e.target === lightboxBox) closeLightbox();
    });
	
	function refreshNav(show) {
		
		var navElements = document.querySelector("nav > ul").children;
		for (var i = 0; i < navElements.length; i++) {
			if (navElements[i].classList) {
				if (
					(show.owner && navElements[i].classList.contains("owner"))
					|| (show.gamelist && navElements[i].classList.contains("gamelist"))
					|| (show.game && navElements[i].classList.contains("game"))
				) {
					navElements[i].classList.remove("hidden");
				} else {
					navElements[i].classList.add("hidden");
				}
			}
		}
		
		var playerMenuElements = document.querySelector("#d_playermenu ul").children;
		for (var i = 0; i < playerMenuElements.length; i++) {
			if (playerMenuElements[i].classList) {
				if (playerMenuElements[i].classList.contains("owner")) {
					if (show.owner) {
						playerMenuElements[i].classList.remove("hidden");
					} else {
						playerMenuElements[i].classList.add("hidden");
					}
				}
			}
		}
		
	}

    var gameListBox = document.getElementById("d_gamelist");
    var gameBox = document.getElementById("d_game");
	var gameTable = document.getElementById("t_gamelist");
	var games = {};

    function buildGameList(data) {
		clearScoreboard();
		refreshNav({gamelist:1});
		games = {};
		gameTable.tBodies[0].textContent = "";
		gameListBox.hidden = false;
		gameBox.hidden = true;
    }
	
	function addGame(data) {
		
		if (games[data.id]) {
			var row = games[data.id];
		} else {
			var row = gameTable.tBodies[0].insertRow();
			row.insertCell();
			row.insertCell();
			row.insertCell();
			row.insertCell();
			games[data.id] = row;
		}
		
		row.cells[0].textContent = data.id;
		row.cells[1].textContent = data.owner;
		row.cells[2].textContent = data.players + "/" + data.maxPlayers;
		row.cells[3].innerHTML = '<a href="#' + data.id + '">Join</a>';
		
		if (data.players <= 0) {
			row.classList.add("disabled");
		}
		
	}

    var scoreboardBox = document.getElementById("d_scoreboard");
	var players = {};
	
	var playerMenu = document.getElementById("d_playermenu");
	var playerMenuShowing = false;
	var playerMenuPlayer = false;
	
	function showPlayerMenu(id) {
		if (!players[id]) return;
		playerMenuPlayer = id;
		document.querySelector("#d_playermenu h2").textContent = "Options for " + players[id].getElementsByTagName("td")[0].textContent;
		openLightbox("d_playermenu");
	}

    function addPlayer(data) {
		
		if (data.name == "") {
			data.name = "Player " + data.id;
		}
		
		if (players[data.id]) {
			row = players[data.id];
			var oldName = row.getElementsByTagName("td")[0].textContent;
			if (data.name != oldName) {
				chat({text: oldName + " is now known as " + data.name});
			}
			row.textContent = "";
		} else {
			chat({text: data.name + " has entered the game"});
			var row = scoreboardBox.insertRow();
			row.addEventListener("click", function() {
				showPlayerMenu(data.id);
			});
		}
		
		var nameCell = row.insertCell();
		nameCell.textContent = data.name;
		
		var scoreCell = row.insertCell();
		var scoreText = row.insertCell();
		
		var scoreMeter = document.createElement("meter");
		scoreText.textContent = data.score + " Aloha Point" + (data.score != 1 ? "s" : "");
		if ("value" in scoreMeter) {
			scoreMeter.max = data.maxScore;
			scoreMeter.value = data.score;
			scoreMeter.textContent = data.score + "/" + data.maxScore;
			scoreCell.appendChild(scoreMeter);
		}
		
		row.className = "";
		row.classList.add(data.state);
		
		players[data.id] = row;
    }
	
	function removePlayer(data) {
		chat({text: players[data].getElementsByTagName("td")[0].textContent + " has left the game"});
		scoreboardBox.firstChild.removeChild(players[data]);
		delete players[data];
	}
	
	function clearScoreboard() {
		for (var i = 0; i < players.length; i++) {
			players[i].parentNode.removeNode(players[i]);
			delete players[i];
		}
	}
	
	var chatBox = document.getElementById("d_chat");
		
	function chat(data) {
		
		if (textToSpeech && "speechSynthesis" in window) {
			var say = data.text;
			if (data.from) {
				say = data.from + " says " + say;
			}
			speechSynthesis.speak(new SpeechSynthesisUtterance(say));
		}
		
		var currentTime = new Date();
		var newChatItem = document.createElement("p");
		
		if ("from" in data) {
			var newChatFrom = document.createElement("b");
			newChatFrom.textContent = data.from + ":";
		}
		
		var newChatTime = document.createElement("time");
		newChatTime.dateTime = currentTime.toISOString();
		newChatTime.title = currentTime.toString();
		newChatTime.textContent = pad(currentTime.getHours().toString(), 2) + ":" + pad(currentTime.getMinutes().toString(), 2);
		
		var newChatMessage = document.createElement("span");
		if ("html" in data) {
			newChatMessage.innerHTML = data.text;
		} else {
			newChatMessage.textContent = data.text;
		}
		
		newChatItem.appendChild(newChatTime);
		if (data.from) {
			newChatItem.appendChild(document.createTextNode(" "));
			newChatItem.appendChild(newChatFrom);
		} else {
			newChatMessage.classList.add("game_message");
		}
		newChatItem.appendChild(document.createTextNode(" "));
		newChatItem.appendChild(newChatMessage);

		chatBox.appendChild(newChatItem);
		
		chatBox.scrollTop = chatBox.scrollHeight;
		return newChatItem;
	}

	var hand = document.getElementById("d_hand");
	var table = document.getElementById("d_table");
	var played = document.getElementById("d_played");
	var confirm = document.getElementById("d_confirm");
	var blackCard = document.getElementById("d_blackcard");
	var responses;
	var isCzar = false;
	var isOwner = false;

	var cards;
	var cardsPlayed;
	var playedCards;
	var playedCardPlayed;
	var blankTexts;
	
	var warningTimeout;
	var timeoutTimer;
	var interruptAt;
	
	function handleTimeout(data) {
		window.clearTimeout(warningTimeout);
		window.clearInterval(timeoutTimer);
			
		interruptAt = new Date(Date.now() + data * 1000);
		
		if (data <= 0) {
			interruptRound();
		}
		
		if (data > 5) {
			warningTimeout = window.setTimeout(startTimer, (data - 5) * 1000);
		} else {
			startTimer(data);
		}
		
	}
	
	function interruptRound(timerElement) {
		if (isOwner) {
			send("timeout", false);
		}
		window.clearTimeout(warningTimeout);
		window.clearInterval(timeoutTimer);
		if (timerElement) {
			timerElement.parentNode.parentNode.parentNode.removeChild(timerElement.parentNode.parentNode);
		}
	}
	
	function startTimer() {
		var timerElement = chat({text: "This round will end in <time>5.0</time> seconds", html: true});
		// Math.round((interruptAt.getTime() - Date.now()) / 1000)
		timeoutTimer = window.setInterval(updateTimer, 100, timerElement.querySelector("span time"));
	}
	
	function updateTimer(timerElement) {
		if (interruptAt.getTime() - Date.now() <= 0) {
			window.clearInterval(timeoutTimer);
			interruptRound(timerElement);
		} else {
			timerElement.textContent = ((interruptAt.getTime() - Date.now()) / 1000).toFixed(1);
		}
	}
	
	function clearGame() {
		blackCard.classList.add("hidden");
		
		cards = {};
		cardsPlayed = [];
		
		playedCards = {};
		playedCardPlayed = false;
		
		blankTexts = {};
		
		hand.textContent = "";
		for (var i = 2; i < table.children.length; i++) {
			table.children[i].parentNode.removeNode(table.children[i]);
		}
		
	}
	
	clearGame();
	
    function buildGame(data) {
		clearScoreboard();
		refreshNav({game:1});
        gameListBox.hidden = true;
        gameBox.hidden = false;
		clearGame();
		chatBox.textContent = "";
    }
	
	function generateBlank(card) {
		card.textContent = "";
		card.classList.add("blank");
		var blank = document.createElement("span");
		blank.classList.add("blank");
		card.appendChild(blank);
		card.appendChild(document.createTextNode("."));
	}

	var resizeCard = document.createElement("div");
	resizeCard.id = "resizecard";
	resizeCard.classList.add("card");
	resizeCard.classList.add("resize");
	resizeCard.appendChild(document.createElement("span"));
	document.body.appendChild(resizeCard);
	
	function innerHeight(el) {
		var style = window.getComputedStyle(el);
		return el.clientHeight
			- parseFloat(style.getPropertyValue('padding-top'))
			- parseFloat(style.getPropertyValue('padding-bottom'));
	}
	function innerWidth(el) {
		var style = window.getComputedStyle(el);
		return el.clientWidth
			- parseFloat(style.getPropertyValue('padding-left'))
			- parseFloat(style.getPropertyValue('padding-right'));
	}
	function fitCardText(el) {
		el.style.fontSize = "1.2rem";
		resizeCard.style.fontSize = "1.2rem";
		var span = resizeCard.children[0];
		span.innerHTML = el.innerHTML;
		var height = innerHeight(resizeCard);
		var width = innerWidth(resizeCard);
		for (var i = 1.2; span.offsetHeight > height || span.offsetWidth > width; i -= 0.05) {
			resizeCard.style.fontSize = i + "rem";
		}
		if (resizeCard.style.fontSize) {
			el.style.fontSize = resizeCard.style.fontSize;
		}
	}
	
	var resizeTimeout;
	
	function fitAllCardTexts() {
		var cards = document.querySelectorAll('.card:not(.resize)');
		for (var i = 0; i < cards.length; i++) {
			fitCardText(cards[i]);
		}
	}
	
	window.addEventListener("resize", function() {
		window.clearTimeout(resizeTimeout);
		resizeTimeout = window.setTimeout(fitAllCardTexts, 100);
	});
	
	function addWhiteCard(data) {
		
		var card = document.createElement("div");
		
		if (data.text === null) {
			generateBlank(card);
		} else {
			card.textContent = data.text;
		}
		
		card.classList.add("card");
		card.classList.add("white");
		hand.appendChild(card);
		cards[data.id] = card;
		
		var checkConfirm = function() {
			if (cardsPlayed.length >= responses) {
				confirm.classList.remove("hidden");
			} else {
				confirm.classList.add("hidden");
			}
		};
		
		var cardHandler = function(e) {
			e.preventDefault();
			var card_id = data.id;
			if (!card.parentNode.classList.contains("disabled")) {
				if (card.parentNode == hand && cardsPlayed.length < responses) {
					if (card.classList.contains("blank")) {
						// goodbye onsubmit event listener
						var clonee = document.getElementById("f_blank_input");
						var clone = clonee.cloneNode(true);
						clonee.parentNode.replaceChild(clone, clonee);
						
						var blankCardInputInput = document.getElementById("i_blank_input");						
						blankCardInputInput.value = "";
						openLightbox("d_blank_input");
						
						document.getElementById("f_blank_input").addEventListener("submit", function(e) {
							e.preventDefault();
							card.textContent = blankCardInputInput.value;
							fitCardText(card);
							blankTexts[card_id] = card.textContent;
							tableWhiteCard(card, card_id);
							closeLightbox();
							checkConfirm();
						});
					} else {
						tableWhiteCard(card, card_id);
					}
				} else if (card.parentNode == table) {
					if (card.classList.contains("blank")) {
						generateBlank(card);
					}
					unTableWhiteCard(card, card_id);
				}
				checkConfirm();
			}
		};
		
		card.addEventListener("click", cardHandler);
		
		fitCardText(card);
	}

	function removeWhiteCard(data) {
		if (cards[data]) {
			cards[data].parentNode.removeChild(cards[data]);
			delete cards[data];
		}
	}
	
	function tableWhiteCard(card, id, text) {
		table.appendChild(card);
		cardsPlayed.push(id);
	}
	
	function unTableWhiteCard(card, id) {
		hand.appendChild(card);
		cardsPlayed.splice(cardsPlayed.indexOf(id), 1);
	}
	
	function addPlayedCard(data) {
		var cardCombo = document.createElement("div");
		cardCombo.classList.add("cardcombo");
		if (data.cards.length > 1) {
			cardCombo.classList.add("multiple");
		}
		data.cards.forEach(function(cardtext) {
			var card = document.createElement("div");
			card.textContent = cardtext;
			card.classList.add("card");
			cardCombo.appendChild(card);
			fitCardText(card);
		});
		played.appendChild(cardCombo);
		playedCards[data.id] = cardCombo;
		if (isCzar) {
			cardCombo.addEventListener("click", function (e) {
				if (!cardCombo.parentNode.classList.contains("disabled")) {
					e.preventDefault();
					if (cardCombo.parentNode == played && playedCardPlayed === false) {
						tablePlayedCard(cardCombo, data.id);
						confirm.classList.remove("hidden");
					} else if (cardCombo.parentNode == table) {
						unTablePlayedCard(cardCombo, data.id);
						confirm.classList.add("hidden");
					}
				}
			});
		}
	}
	
	function removePlayedCard(data) {
		playedCards[data].parentNode.removeChild(playedCards[data]);
		delete playedCards[data];
	}
	
	function tablePlayedCard(cardCombo, id) {
		table.appendChild(cardCombo);
		playedCardPlayed = id;;
	}
	
	function unTablePlayedCard(cardCombo, id) {
		played.appendChild(cardCombo);
		playedCardPlayed = false;
	}
	
	confirm.addEventListener("click", function(e) {
		e.preventDefault();
		if (isCzar) {
			send("select", playedCardPlayed);
		} else {
			var toSend = [];
			cardsPlayed.forEach(function(card_id) {
				if (card_id in blankTexts) {
					toSend.push({"id": card_id, "text": blankTexts[card_id]});
				} else {
					toSend.push({"id": card_id});
				}
			});
			send("play", toSend);
		}
		confirm.classList.add("hidden");
	});
	
	function setBlackCard(data) {
		blackCard.textContent = "";
		var split = data.text.split("_");
		var addBlank = function() {
			var blank = document.createElement("span");
			blank.classList.add("blank");
			blackCard.appendChild(blank);
		}
		split.forEach(function(textNode, i, array) {
			if (i > 0) addBlank(blackCard);
			blackCard.appendChild(document.createTextNode(textNode));
		});
		if (split.length < 2) {
			blackCard.textContent += " ";
			addBlank(blackCard);
		}
		fitCardText(blackCard);
	}
	
	function displayPoint(data) {
		var pointBox = document.getElementById("d_point");
		pointBox.removeChild(pointBox.querySelector(".card"));
		
		if (isCzar || data.isYou) {
			winRoundNoise.play();
		} else {
			loseRoundNoise.play();
		}
		
		pointBox.querySelector("h2").textContent = data.player + " wins the round";
		chat({text: pointBox.querySelector("h2").textContent});
		
		var winCard = document.getElementById("d_blackcard").cloneNode(true);
		var blanks = winCard.querySelectorAll("span");
		data.cards.forEach(function(card, i) {
			blanks[i].textContent = card;
		});
		pointBox.appendChild(winCard);
		
		openLightbox("d_point");
		window.setTimeout(function() {
			closeLightbox();
			if (isOwner) {
				send("start", null);
			}
			cardsPlayed = [];
			playedCards = {};
			playedCardPlayed = false;
		}, 4000);
		
		fitCardText(winCard);
	}
	
	function displayWinner(data) {
		var winner = document.querySelector("#d_winner span");
		winner.textContent = data;
		openLightbox("d_winner");
		chat({text: data + " wins the game"});
		clearGame();
	}
	
	function updateGameStatus(data) {
		
		/*if (data !== "ready") {
			closeLightbox();
		}*/
		
		var gameStatus = document.getElementById("d_gamestatus");
		var texts = {
			ready: "Waiting for the game to start",
			waitingforplayers: "You are the card czar; wait for the other players",
			waitingforczar: "Waiting for the card czar",
			playing: (responses == 1 ? "Play a card" : "Play " + responses + " cards"),
			played: "Waiting for the other players",
			judging: "Select the best combination"
		}
		
		if (texts[data]) {
			gameStatus.textContent = texts[data];
		}
		
		isCzar = false;
		
		var handStates = {
			ready: "hidden",
			waitingforplayers: "hidden",
			waitingforczar: "hidden disabled",
			playing: "",
			played: "disabled",
			judging: "hidden"
		}
		
		hand.className = handStates[data];
		
		var playedStates = {
			ready: "hidden",
			waitingforplayers: "hidden",
			waitingforczar: "disabled",
			playing: "hidden",
			played: "hidden",
			judging: ""
		}
		
		played.className = playedStates[data];
		
		var tableStates = {
			ready: "disabled",
			waitingforplayers: "disabled",
			waitingforczar: "disabled",
			playing: "",
			played: "disabled",
			judging: ""
		}
		
		table.className = tableStates[data];
		
		if (data == "ready" || data == "playing" || data == "judging") {
			cardsPlayed = [];
			playedCardPlayed = false;
		}
		
		if (data != "played") {
			window.clearTimeout(warningTimeout);
			window.clearInterval(timeoutTimer);
		}
		
		if (data == "ready") {
			
			blackCard.classList.add("hidden");
			
			played.textContent = "";
			
			var tabledCards = table.querySelectorAll(".cardcombo, .card");
			for (var i = 2; i < tabledCards.length; i++) {
				tabledCards[i].parentNode.removeChild(tabledCards[i]);
			}
			
		} else {
			blackCard.classList.remove("hidden");
		}
		
		isCzar = (data == "judging" || data == "waitingforplayers");
		
		if (hand.className == "hidden" && played.className == "hidden") {
			gameStatus.classList.add("alone");
		} else {
			gameStatus.classList.remove("alone");
		}
		
	}
	
	function fillSettings(data) {
		data.forEach(function(setting, i, array) {
			document.getElementById("i_" + setting.id).value = setting.value;
		});
		blankCardsUpdate();
	}
	
	var settingsForm = document.getElementById("f_settings");

	function enableSettings(enable) {
		for (var i = 0; i < settingsForm.elements.length; i++) {
			settingsForm.elements[i].disabled = !enable;
		}
	}

    var gameId = false;

    var socket = new WebSocket("ws://" + window.location.hostname + ":82");
    socket.onopen = function(e) {
        notify("Hoo boy, here's trouble. You've connected to the server.");
        // I don't know how to name functions.
        function doPage(e) {

            var newGameId;
            if (newGameId = window.location.hash.match(/^#([0-9]+)$/)) {
                if (parseInt(newGameId[1]) !== gameId) {
                    send("join", parseInt(newGameId[1]));
                }
            } else {
                send("leave", null);
                gameId = false;
            }
        }

        window.addEventListener("hashchange", doPage);
        doPage();
    };

    socket.onerror = function(e) {
        openLightbox("d_noserver");
        document.getElementById("s_noserver").addEventListener("click", function(e) {
            e.preventDefault();
            closeLightbox();
        });
    };

    socket.onmessage = function(e) {
        // this used to look like this: [action, data] = JSON.parse(e.data);
        // but webkit is a piece of garbage.
        var fuckWebkit = JSON.parse(e.data);
        action = fuckWebkit[0];
        data = fuckWebkit[1];
        switch (action) {

            case "name":
                document.querySelector("#nav_name a").textContent = data;
                break;

            case "gamelist":
                buildGameList(data);
                break;
				
			case "game":
				addGame(data);
				break;

            case "join":
				gameId = parseInt(data);
				if (window.location.hash != "#" + data) window.location.hash = "#" + data;
                buildGame(data);
                break;

            case "player":
                addPlayer(data);
                break;
				
			case "removeplayer":
				removePlayer(data);
				break;

			case "blackcard":
				responses = data.responses;
				setBlackCard(data);
				break;
				
			case "draw":
				addWhiteCard(data);
				break;

			case "return":
				removeWhiteCard(data);
				break;
				
			case "state":
				updateGameStatus(data);
				break;
				
			case "point":
				displayPoint(data);
				break;
				
			case "owner":
				if (data) {
					refreshNav({game: 1, owner: 1});
				} else {
					refreshNav({game: 1});
				}
				enableSettings(data);
				isOwner = data;
				break;
				
			case "table":
				addPlayedCard(data);
				break;
				
			case "notify":
				notify(data);
				break;
				
			case "settings":
				fillSettings(data);
				openLightbox("d_settings");
				break;
				
			case "gamewinner":
				displayWinner(data);
				break;
				
			case "chat":
				chat(data);
				break;
				
			case "timeout":
				handleTimeout(data);
				break;
				
			case "removetable":
				removePlayedCard(data);
				break;

            case "error":
                switch (data.type) {
                    case "name": openLightbox("d_name"); break;
					case "join": case "create": window.location.hash = "#"; break;
					case "set": openLightbox("d_settings"); break;
                }
				if (data.text) {
					notify(data.text);
				}
                break;

        }
    };

    function send(type, data) {
        socket.send(JSON.stringify([type, data]));
    }

    document.querySelector("#nav_name a").addEventListener("click", function(e) {
        e.preventDefault();
		document.getElementById("i_noises").checked = noises;
		document.getElementById("i_texttospeech").checked = textToSpeech;
        openLightbox("d_name");
    });
	
	document.querySelector("#nav_create a").addEventListener("click", function(e) {
		e.preventDefault();
		send("create", null);
	});

    document.getElementById("f_name").addEventListener("submit", function(e) {
        e.preventDefault();
        closeLightbox();
        send("name", document.getElementById("i_name").value);
		noises = document.getElementById("i_noises").checked;
		if (noises) {
			if (!winRoundNoise || !loseRoundNoise) {
				winRoundNoise = new Audio("win.ogg");
				loseRoundNoise = new Audio("lose.ogg");
			}
		}
		textToSpeech = document.getElementById("i_texttospeech").checked;
    });
	
	settingsForm.addEventListener("submit", function(e) {
		e.preventDefault();
		closeLightbox();
		for (var i = 0; i < settingsForm.elements.length; i++) {
			if (settingsForm.elements[i].id) {
				send("set", {
					"id": settingsForm.elements[i].id.substring(2),
					"value": settingsForm.elements[i].value
				});
			}
		}
	});

    document.getElementById("s_noserver").addEventListener("click", function(e) {
        e.preventDefault();
        document.location.reload();
    });
	
	document.querySelector("#nav_settings a").addEventListener("click", function (e) {
		e.preventDefault();
		send("settings", null);
	});

    document.querySelector("#nav_kill a").addEventListener("click", function(e) {
        e.preventDefault();
        send("kill", null);
    });

    document.querySelector("#nav_deck a").addEventListener("click", function(e) {
        e.preventDefault();
        openLightbox("d_cardcast");
    });
	
	document.querySelector("#nav_start a").addEventListener("click", function(e) {
		e.preventDefault();
		send("start", null);
	});
	
/*	document.querySelector("#pm_mute a").addEventListener("click", function(e) {
		e.preventDefault();
		send("mute", playerMenuPlayer);
		closeLightbox();
	});*/
	
	document.querySelector("#pm_kick a").addEventListener("click", function(e) {
		e.preventDefault();
		send("kick", playerMenuPlayer);
		closeLightbox();
	});
	
/*	document.querySelector("#pm_ban a").addEventListener("click", function(e) {
		e.preventDefault();
		send("ban", playerMenuPlayer);
		closeLightbox();
	});*/
		
	document.getElementById("f_chat").addEventListener("submit", function(e) {
		e.preventDefault();
		send("chat", document.getElementById("i_chat").value);
		document.getElementById("i_chat").value = "";
	});

    document.getElementById("f_cardcast").addEventListener("submit", function(e) {
         e.preventDefault();
         send("cardcast", document.getElementById("i_cardcast").value);
         closeLightbox();
    });
	
	document.getElementsByTagName("h1")[0].addEventListener("dblclick", function(e) {
		e.preventDefault();
		openLightbox("d_about");
	});
	
	var blankCardsInput = document.getElementById("i_blank_cards");
	function blankCardsUpdate(e) {
		document.getElementById("i_blank_cards_display").textContent = "" + blankCardsInput.value + "%";
	}
	blankCardsInput.addEventListener("input", blankCardsUpdate);
	blankCardsUpdate();

});