/**
 * app.js
 * Frontend controller for Tien Len Game
 */

// Web Audio API Sound Synthesizer
const AudioSynth = {
    ctx: null,
    init() {
        if (!this.ctx) {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
    },
    isSoundEnabled() {
        return localStorage.getItem('tienlen_sound_enabled') !== 'false';
    },
    playCard() {
        if (!this.isSoundEnabled()) return;
        this.init();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(320, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(120, this.ctx.currentTime + 0.12);
        gain.gain.setValueAtTime(0.4, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.12);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.12);
    },
    playPass() {
        if (!this.isSoundEnabled()) return;
        this.init();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(180, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(280, this.ctx.currentTime + 0.2);
        gain.gain.setValueAtTime(0.2, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.2);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.2);
    },
    playWin() {
        if (!this.isSoundEnabled()) return;
        this.init();
        const now = this.ctx.currentTime;
        const playNote = (freq, start, duration) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, start);
            gain.gain.setValueAtTime(0.15, start);
            gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
            osc.start(start);
            osc.stop(start + duration);
        };
        playNote(523.25, now, 0.12); // C5
        playNote(659.25, now + 0.12, 0.12); // E5
        playNote(783.99, now + 0.24, 0.12); // G5
        playNote(1046.50, now + 0.36, 0.35); // C6
    },
    playError() {
        if (!this.isSoundEnabled()) return;
        this.init();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(100, this.ctx.currentTime);
        gain.gain.setValueAtTime(0.25, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.25);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.25);
    },
    playShuffle() {
        if (!this.isSoundEnabled()) return;
        this.init();
        const now = this.ctx.currentTime;
        for (let i = 0; i < 7; i++) {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(380 + Math.random() * 220, now + i * 0.07);
            gain.gain.setValueAtTime(0.12, now + i * 0.07);
            gain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.07 + 0.04);
            osc.start(now + i * 0.07);
            osc.stop(now + i * 0.07 + 0.05);
        }
    }
};

// Game State & Controllers
let gameState = {};
let selectedCards = [];
let botTimerId = null;
let turnInterval = null;
let gameOverChipsUpdated = false;
let lastTimerTurn = -1;
let lastTimerTrickCards = '';

// Multiplayer Online State
let isMultiplayer = false;
let roomCode = '';
let myPlayerId = '';
let mySeatIndex = -1;
let pollingIntervalId = null;
let myPlayerName = localStorage.getItem('tienlen_player_name') || 'អ្នកលេង';
let myPlayerAvatar = localStorage.getItem('tienlen_player_avatar') || 'avatar_male.png';
let selectedAvatarFilename = myPlayerAvatar;

// Singleplayer Bots Definition (Avatars are randomly assigned per round)
let singleplayerBots = [
    { name: 'Bot 1', avatar: 'public/assets/images/avatar_male.png' },
    { name: 'Bot 2', avatar: 'public/assets/images/avatar_female.png' },
    { name: 'Bot 3', avatar: 'public/assets/images/avatar_male.png' }
];

function randomizeSingleplayerBots() {
    singleplayerBots = [
        { name: 'Bot 1', avatar: Math.random() < 0.5 ? 'public/assets/images/avatar_male.png' : 'public/assets/images/avatar_female.png' },
        { name: 'Bot 2', avatar: Math.random() < 0.5 ? 'public/assets/images/avatar_male.png' : 'public/assets/images/avatar_female.png' },
        { name: 'Bot 3', avatar: Math.random() < 0.5 ? 'public/assets/images/avatar_male.png' : 'public/assets/images/avatar_female.png' }
    ];
}

// DOM Elements
const humanHandContainer = document.getElementById('human-hand-container');
const trickCardsContainer = document.getElementById('trick-cards-container');
// const trickTypeInfo = document.getElementById('trick-type-info');
const errorBanner = document.getElementById('error-banner');
const btnPlay = document.getElementById('btn-play');
const btnPass = document.getElementById('btn-pass');
const btnRestart = document.getElementById('btn-restart');
const btnHelp = document.getElementById('btn-help');
const btnCloseHelp = document.getElementById('btn-close-help');
const helpModal = document.getElementById('help-modal');
const winnerModal = document.getElementById('winner-modal');
const winnerTitle = document.getElementById('winner-title');
const winnerDesc = document.getElementById('winner-desc');
const modalScoreboard = document.getElementById('modal-scoreboard');
const btnModalRestart = document.getElementById('btn-modal-restart');
const historyLog = document.getElementById('history-log');

// Lobby Screen & Suggest Elements
const lobbyScreen = document.getElementById('lobby-screen');
const btnCreateTable = document.getElementById('btn-create-table');
const btnSuggest = document.getElementById('btn-suggest');

// Side panels & New Board controls
const gameSidebar = document.getElementById('game-sidebar');
const btnToggleLog = document.getElementById('btn-toggle-log');
const btnCloseSidebar = document.getElementById('btn-close-sidebar');
const btnExit = document.getElementById('btn-exit');
const btnSettings = document.getElementById('btn-settings');
const btnBotChat = document.getElementById('btn-bot-chat');
const btnBoardRestart = document.getElementById('btn-board-restart');
const btnBoardHelp = document.getElementById('btn-board-help');
const btnBoardLog = document.getElementById('btn-board-log');
const btnFullscreen = document.getElementById('btn-fullscreen');
// Modals
const settingsModal = document.getElementById('settings-modal');
const btnCloseSettings = document.getElementById('btn-close-settings');
const btnResetChips = document.getElementById('btn-reset-chips');

// Checkboxes
const settingSound = document.getElementById('setting-sound');
const settingPremiumCards = document.getElementById('setting-premium-cards');

// Setup suits characters and names
const SUIT_CHARS = { 0: '♠', 1: '♣', 2: '♦', 3: '♥' };
const SUIT_NAMES = { 0: 'spades', 1: 'clubs', 2: 'diamonds', 3: 'hearts' };
const VAL_NAMES = { 3:'3', 4:'4', 5:'5', 6:'6', 7:'7', 8:'8', 9:'9', 10:'10', 11:'J', 12:'Q', 13:'K', 14:'A', 15:'2' };

// Initialize Game
window.addEventListener('load', () => {
    // Unique ID for each tab
    myPlayerId = sessionStorage.getItem('tienlen_player_id');
    if (!myPlayerId) {
        myPlayerId = 'p_' + Math.random().toString(36).substring(2, 11);
        sessionStorage.setItem('tienlen_player_id', myPlayerId);
    }

    loadSettings();
    loadPoints();
    setupEventListeners();
    initAutoFullscreenLandscape();
    randomizeSingleplayerBots(); // Initialize random bot avatars

    // Check URL parameters for direct room joining
    const urlParams = new URLSearchParams(window.location.search);
    const queryRoom = urlParams.get('room');
    if (queryRoom) {
        roomCode = queryRoom.toUpperCase();
    }

    const savedName = localStorage.getItem('tienlen_player_name');
    if (!savedName) {
        showProfileSetup(false);
    } else {
        updateLobbyProfileHeader();
        if (roomCode) {
            showOnlineSetupForm(true);
        } else {
            showModeSelector();
        }
    }
});

function setupEventListeners() {
    // Mode selectors
    const btnSelectSingleplayer = document.getElementById('btn-select-singleplayer');
    const btnSelectMultiplayer = document.getElementById('btn-select-multiplayer');
    const btnBackToModes = document.getElementById('btn-back-to-modes');
    const btnMpCreateRoom = document.getElementById('btn-mp-create-room');
    const btnMpJoinRoom = document.getElementById('btn-mp-join-room');
    const btnCopyRoomLink = document.getElementById('btn-copy-room-link');
    const btnStartMultiplayerGame = document.getElementById('btn-start-multiplayer-game');
    const btnLeaveWaitingRoom = document.getElementById('btn-leave-waiting-room');

    if (btnSelectSingleplayer) {
        btnSelectSingleplayer.addEventListener('click', () => {
            isMultiplayer = false;
            resetPoints();
            lobbyScreen.classList.add('hidden');
            requestGameFullscreen();
            startNewGame();
        });
    }

    if (btnSelectMultiplayer) {
        btnSelectMultiplayer.addEventListener('click', () => {
            showOnlineSetupForm();
        });
    }

    if (btnBackToModes) {
        btnBackToModes.addEventListener('click', () => {
            showModeSelector();
        });
    }

    if (btnMpCreateRoom) {
        btnMpCreateRoom.addEventListener('click', () => {
            resetPoints();
            requestGameFullscreen();
            createOnlineRoom();
        });
    }

    if (btnMpJoinRoom) {
        btnMpJoinRoom.addEventListener('click', () => {
            resetPoints();
            requestGameFullscreen();
            joinOnlineRoom();
        });
    }

    if (btnCopyRoomLink) {
        btnCopyRoomLink.addEventListener('click', () => {
            const link = window.location.origin + window.location.pathname + '?room=' + roomCode;
            navigator.clipboard.writeText(link).then(() => {
                const oldText = btnCopyRoomLink.textContent;
                btnCopyRoomLink.textContent = '✅ បានចម្លង!';
                setTimeout(() => {
                    btnCopyRoomLink.textContent = oldText;
                }, 2000);
            }).catch(err => {
                alert('ចម្លងមិនបានជោគជ័យ៖ ' + link);
            });
        });
    }

    if (btnStartMultiplayerGame) {
        btnStartMultiplayerGame.addEventListener('click', () => {
            requestGameFullscreen();
            startMultiplayerGame();
        });
    }

    if (btnLeaveWaitingRoom) {
        btnLeaveWaitingRoom.addEventListener('click', () => {
            leaveWaitingRoom();
        });
    }

    btnSuggest.addEventListener('click', getMoveSuggestion);

    if (btnFullscreen) {
        btnFullscreen.addEventListener('click', toggleFullscreen);
    }

    btnRestart.addEventListener('click', () => {
        if (isMultiplayer) {
            if (mySeatIndex === 0) startMultiplayerGame();
        } else {
            lobbyScreen.classList.add('hidden');
            startNewGame();
        }
    });
    btnModalRestart.addEventListener('click', () => {
        if (isMultiplayer) {
            if (mySeatIndex === 0) startMultiplayerGame();
        } else {
            lobbyScreen.classList.add('hidden');
            startNewGame();
        }
    });
    btnPlay.addEventListener('click', playSelectedCards);
    btnPass.addEventListener('click', passTurn);
    
    // Modals toggling
    btnHelp.addEventListener('click', () => helpModal.classList.add('active'));
    btnCloseHelp.addEventListener('click', () => helpModal.classList.remove('active'));
    
    btnToggleLog.addEventListener('click', () => gameSidebar.classList.add('active'));
    btnCloseSidebar.addEventListener('click', () => gameSidebar.classList.remove('active'));

    if (btnBoardRestart) {
        btnBoardRestart.addEventListener('click', () => {
            lobbyScreen.classList.add('hidden');
            startNewGame();
        });
    }
    if (btnBoardHelp) {
        btnBoardHelp.addEventListener('click', () => helpModal.classList.add('active'));
    }
    if (btnBoardLog) {
        btnBoardLog.addEventListener('click', () => gameSidebar.classList.add('active'));
    }
    
    btnExit.addEventListener('click', () => {
        if (confirm('តើអ្នកពិតជាចង់ចាកចេញមែនទេ? (Do you really want to quit?)')) {
            if (botTimerId) clearTimeout(botTimerId);
            if (turnInterval) clearInterval(turnInterval);
            if (pollingIntervalId) clearInterval(pollingIntervalId);
            const tBox = document.getElementById('timer-box');
            if (tBox) tBox.style.display = 'none';

            // Reset URL params
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({ path: newUrl }, '', newUrl);

            isMultiplayer = false;
            roomCode = '';
            mySeatIndex = -1;

            lobbyScreen.classList.remove('hidden');
            showModeSelector();
        }
    });

    btnSettings.addEventListener('click', () => settingsModal.classList.add('active'));
    btnCloseSettings.addEventListener('click', () => settingsModal.classList.remove('active'));

    // Shop and gift functions are removed

    // Settings adjustments
    settingSound.addEventListener('change', () => {
        localStorage.setItem('tienlen_sound_enabled', settingSound.checked);
    });
    settingPremiumCards.addEventListener('change', () => {
        localStorage.setItem('tienlen_premium_cards', settingPremiumCards.checked);
        applyCardTheme();
    });
    btnResetChips.addEventListener('click', () => {
        if (confirm('តើអ្នកចង់កំណត់ចំនួនពិន្ទុឡើងវិញមែនទេ?')) {
            resetPoints();
            settingsModal.classList.remove('active');
            showError('ពិន្ទុត្រូវបានកំណត់ឡើងវិញរួចរាល់!');
        }
    });

    btnBotChat.addEventListener('click', showStrategicHint);

    // Avatar Selection Gallery Click
    const avatarCards = document.querySelectorAll('.avatar-option-card');
    avatarCards.forEach(card => {
        card.addEventListener('click', () => {
            const filename = card.getAttribute('data-filename');
            selectedAvatarFilename = filename;
            updateAvatarSelectionUI();
        });
    });

    // Save Profile Confirmation
    const btnSaveProfile = document.getElementById('btn-save-profile');
    if (btnSaveProfile) {
        btnSaveProfile.addEventListener('click', () => {
            const nameInput = document.getElementById('profile-nickname').value.trim();
            
            // Name validation checks
            if (nameInput === '') {
                alert('Display name cannot be empty.');
                return;
            }
            if (nameInput.length > 20) {
                alert('Display name cannot exceed 20 characters.');
                return;
            }
            if (nameInput.toLowerCase().startsWith('bot')) {
                alert("Names starting with 'Bot' are reserved for AI players.");
                return;
            }
            
            // Avatar filename validation (only Male and Female avatars allowed for human setup)
            const whitelist = ['avatar_male.png', 'avatar_female.png'];
            if (!whitelist.includes(selectedAvatarFilename)) {
                alert('Invalid avatar selection.');
                return;
            }
            
            // Save to localStorage & state
            myPlayerName = nameInput;
            myPlayerAvatar = selectedAvatarFilename;
            localStorage.setItem('tienlen_player_name', myPlayerName);
            localStorage.setItem('tienlen_player_avatar', myPlayerAvatar);
            
            // Update lobby UI
            updateLobbyProfileHeader();
            
            // Proceed to next screen
            if (roomCode) {
                showOnlineSetupForm(true);
            } else {
                showModeSelector();
            }
        });
    }

    // Edit Profile Trigger
    const btnEditProfile = document.getElementById('btn-edit-profile');
    if (btnEditProfile) {
        btnEditProfile.addEventListener('click', () => {
            showProfileSetup(true);
        });
    }
}

// Mode view switcher functions
function showModeSelector() {
    updateLobbyProfileHeader();
    document.getElementById('lobby-screen').classList.remove('hidden');
    document.getElementById('lobby-profile-setup').classList.add('hidden');
    document.getElementById('lobby-mode-selector').classList.remove('hidden');
    document.getElementById('lobby-online-setup').classList.add('hidden');
    document.getElementById('lobby-waiting-room').classList.add('hidden');
}

function showOnlineSetupForm(hasRoomCode = false) {
    document.getElementById('lobby-screen').classList.remove('hidden');
    document.getElementById('lobby-profile-setup').classList.add('hidden');
    document.getElementById('lobby-mode-selector').classList.add('hidden');
    document.getElementById('lobby-online-setup').classList.remove('hidden');
    document.getElementById('lobby-waiting-room').classList.add('hidden');
    if (hasRoomCode) {
        document.getElementById('join-room-code').value = roomCode;
    }
}

// Profile panel setup views
function showProfileSetup(isEditing = false) {
    document.getElementById('lobby-screen').classList.remove('hidden');
    document.getElementById('lobby-profile-setup').classList.remove('hidden');
    document.getElementById('lobby-mode-selector').classList.add('hidden');
    document.getElementById('lobby-online-setup').classList.add('hidden');
    document.getElementById('lobby-waiting-room').classList.add('hidden');
    
    // Fill values
    document.getElementById('profile-nickname').value = isEditing ? myPlayerName : '';
    
    // If current cached avatar is no longer selectable (i.e. was avatar_user.png), default to avatar_male.png
    if (myPlayerAvatar === 'avatar_user.png') {
        myPlayerAvatar = 'avatar_male.png';
        localStorage.setItem('tienlen_player_avatar', 'avatar_male.png');
    }
    
    selectedAvatarFilename = myPlayerAvatar;
    updateAvatarSelectionUI();
}

function updateAvatarSelectionUI() {
    // Highlight gallery active card
    const cards = document.querySelectorAll('.avatar-option-card');
    cards.forEach(card => {
        const fn = card.getAttribute('data-filename');
        if (fn === selectedAvatarFilename) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}

function updateLobbyProfileHeader() {
    const avatarEl = document.getElementById('lobby-user-avatar');
    const nameEl = document.getElementById('lobby-user-name');
    if (avatarEl) {
        avatarEl.src = `public/assets/images/${myPlayerAvatar}`;
    }
    if (nameEl) {
        nameEl.textContent = myPlayerName;
    }
}

function createOnlineRoom() {
    fetch(`api/index.php?action=create_room&name=${encodeURIComponent(myPlayerName)}&avatar=${encodeURIComponent(myPlayerAvatar)}&player_id=${myPlayerId}`)
        .then(res => res.json())
        .then(data => {
            if (data.result && data.result.success) {
                isMultiplayer = true;
                roomCode = data.room_code;
                mySeatIndex = data.my_seat;
                
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?room=' + roomCode;
                window.history.pushState({ path: newUrl }, '', newUrl);
                
                enterWaitingRoom(data);
            } else {
                alert(data.message || 'បង្កើតបន្ទប់មិនបានជោគជ័យ!');
            }
        })
        .catch(err => console.error('Error creating room:', err));
}

function joinOnlineRoom() {
    const codeInput = document.getElementById('join-room-code').value.trim().toUpperCase();
    if (codeInput.length !== 4) {
        alert('សូមវាយលេខកូដបន្ទប់ចំនួន ៤ ខ្ទង់!');
        return;
    }
    
    fetch(`api/index.php?action=join_room&code=${codeInput}&name=${encodeURIComponent(myPlayerName)}&avatar=${encodeURIComponent(myPlayerAvatar)}&player_id=${myPlayerId}`)
        .then(res => res.json())
        .then(data => {
            if (data.result && !data.result.success) {
                alert(data.result.message);
            } else if (data.success === false) {
                alert(data.message);
            } else {
                isMultiplayer = true;
                roomCode = data.room_code;
                mySeatIndex = data.my_seat;
                
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?room=' + roomCode;
                window.history.pushState({ path: newUrl }, '', newUrl);
                
                enterWaitingRoom(data);
            }
        })
        .catch(err => console.error('Error joining room:', err));
}

function enterWaitingRoom(data) {
    document.getElementById('lobby-mode-selector').classList.add('hidden');
    document.getElementById('lobby-online-setup').classList.add('hidden');
    document.getElementById('lobby-waiting-room').classList.remove('hidden');
    
    document.getElementById('display-room-code').textContent = roomCode;
    
    if (mySeatIndex === 0) {
        document.getElementById('host-settings-only').style.display = 'block';
        document.getElementById('btn-start-multiplayer-game').style.display = 'block';
    } else {
        document.getElementById('host-settings-only').style.display = 'none';
        document.getElementById('btn-start-multiplayer-game').style.display = 'none';
    }
    
    updateWaitingRoomPlayers(data.players);
    
    if (pollingIntervalId) clearInterval(pollingIntervalId);
    pollingIntervalId = setInterval(pollRoomStatus, 1500);
}

function updateWaitingRoomPlayers(players) {
    for (let s = 0; s < 4; s++) {
        const seatBox = document.getElementById(`seat-${s}`);
        const nameEl = document.getElementById(`seat-player-${s}`);
        const avatarEl = document.getElementById(`seat-avatar-${s}`);
        if (seatBox && nameEl) {
            seatBox.classList.remove('occupied');
            nameEl.textContent = 'រង់ចាំអ្នកលេង...';
        }
        if (avatarEl) {
            avatarEl.style.display = 'none';
        }
    }
    
    players.forEach(p => {
        const seatBox = document.getElementById(`seat-${p.seat}`);
        const nameEl = document.getElementById(`seat-player-${p.seat}`);
        const avatarEl = document.getElementById(`seat-avatar-${p.seat}`);
        if (seatBox && nameEl) {
            seatBox.classList.add('occupied');
            nameEl.textContent = p.name + (p.id === myPlayerId ? ' (ខ្ញុំ)' : '');
        }
        if (avatarEl) {
            avatarEl.src = p.avatar; // path is constructed safely on server side
            avatarEl.style.display = 'block';
        }
    });
}

function pollRoomStatus() {
    if (!isMultiplayer || !roomCode) return;
    
    fetch(`api/index.php?action=room_status&code=${roomCode}&player_id=${myPlayerId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success === false) {
                leaveWaitingRoom();
                alert(data.message || 'បន្ទប់លេងត្រូវបានបិទ!');
                return;
            }
            
            if (data.room_status === 'waiting') {
                updateWaitingRoomPlayers(data.players);
            } else if (data.room_status === 'playing') {
                document.getElementById('lobby-screen').classList.add('hidden');
                updateUI(data);
            }
        })
        .catch(err => console.error('Error polling room status:', err));
}

function startMultiplayerGame() {
    if (mySeatIndex !== 0) return;
    const addBots = document.getElementById('chk-add-bots').checked ? 1 : 0;
    
    fetch(`api/index.php?action=start_room_game&code=${roomCode}&player_id=${myPlayerId}&add_bots=${addBots}`)
        .then(res => res.json())
        .then(data => {
            if (data.result && !data.result.success) {
                alert(data.result.message);
            } else {
                document.getElementById('lobby-screen').classList.add('hidden');
                updateUI(data);
            }
        })
        .catch(err => console.error('Error starting room game:', err));
}

function leaveWaitingRoom() {
    if (pollingIntervalId) clearInterval(pollingIntervalId);
    pollingIntervalId = null;
    isMultiplayer = false;
    roomCode = '';
    mySeatIndex = -1;
    
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.pushState({ path: newUrl }, '', newUrl);
    
    showModeSelector();
}

function requestGameFullscreen() {
    const docEl = document.documentElement;
    const requestFS = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullscreen || docEl.msRequestFullscreen;
    if (requestFS) {
        requestFS.call(docEl).catch(err => console.log('Fullscreen rejected:', err));
    }
}

function initAutoFullscreenLandscape() {
    const startFullscreen = () => {
        const docEl = document.documentElement;
        const requestFS = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullscreen || docEl.msRequestFullscreen;
        if (requestFS) {
            requestFS.call(docEl).then(() => {
                if (screen.orientation && screen.orientation.lock) {
                    screen.orientation.lock('landscape').catch(err => {
                        console.log('Orientation lock error:', err);
                    });
                }
            }).catch(err => {
                console.log('Auto fullscreen error:', err);
            });
        }
        // Remove listeners
        document.removeEventListener('click', startFullscreen);
        document.removeEventListener('touchstart', startFullscreen);
    };

    document.addEventListener('click', startFullscreen);
    document.addEventListener('touchstart', startFullscreen);
}

// Settings loader
function loadSettings() {
    // Sound enabled
    const sound = localStorage.getItem('tienlen_sound_enabled') !== 'false';
    settingSound.checked = sound;

    // Premium cards
    const premium = localStorage.getItem('tienlen_premium_cards') !== 'false';
    settingPremiumCards.checked = premium;
    applyCardTheme();
}

function applyCardTheme() {
    if (settingPremiumCards.checked) {
        document.body.classList.remove('classic-theme');
    } else {
        document.body.classList.add('classic-theme');
    }
}

// Points scoring system using localStorage
function loadPoints() {
    if (!localStorage.getItem('tienlen_game_points_0')) {
        localStorage.setItem('tienlen_game_points_0', '0');
        localStorage.setItem('tienlen_game_points_1', '0');
        localStorage.setItem('tienlen_game_points_2', '0');
        localStorage.setItem('tienlen_game_points_3', '0');
    }
    for (let p = 0; p < 4; p++) {
        const valEl = document.getElementById(`chips-val-${p}`);
        if (valEl) {
            const points = parseInt(localStorage.getItem(`tienlen_game_points_${p}`)) || 0;
            valEl.textContent = points.toLocaleString();
        }
    }
}

function resetPoints() {
    localStorage.setItem('tienlen_game_points_0', '0');
    localStorage.setItem('tienlen_game_points_1', '0');
    localStorage.setItem('tienlen_game_points_2', '0');
    localStorage.setItem('tienlen_game_points_3', '0');
    loadPoints();
}

function updatePointsGameOver(data) {
    if (gameOverChipsUpdated) return;
    gameOverChipsUpdated = true;

    const pointsDelta = { 0: 0, 1: 0, 2: 0, 3: 0 };
    const rankPoints = [300, 100, -100, -300];

    // Assign base rank points
    for (let i = 0; i < 4; i++) {
        const pid = data.winner_order[i];
        pointsDelta[pid] = rankPoints[i];
    }

    // Apply stinky player penalty
    data.stinky_players.forEach(pid => {
        pointsDelta[pid] -= 100;
        pointsDelta[data.winner_order[0]] += 100;
    });

    // Update localStorage for all players
    for (let p = 0; p < 4; p++) {
        let currentPoints = parseInt(localStorage.getItem(`tienlen_game_points_${p}`)) || 0;
        currentPoints += pointsDelta[p];
        localStorage.setItem(`tienlen_game_points_${p}`, currentPoints.toString());
    }

    loadPoints();
}

// Bot Chat strategic hint messages
function showStrategicHint() {
    const hints = [
        "ជំនួយការ៖ យុទ្ធសាស្ត្រល្អបំផុតគឺរក្សាទុកលេខ ២ និងគ្រាប់បែកសម្រាប់កាត់គូប្រជែងនៅចុងហ្គេម! 🃏",
        "ជំនួយការ៖ ព្យាយាមចេញបៀរខ្សែវែងៗមុន ដើម្បីកាត់បន្ថយចំនួនសន្លឹកក្នុងដៃយ៉ាងលឿន! 🏃‍♂️",
        "ជំនួយការ៖ កុំទម្លាក់បៀរធំៗលឿនពេក បើអ្នកគ្មានបៀរកាត់បន្ត! រង់ចាំឱកាសល្អ។ ⏳",
        "ជំនួយការ៖ សង្កេតមើលចំនួនបៀរដែលគូប្រជែងនៅសល់។ បើគេសល់បៀរតិច ត្រូវចេញបៀរធំកាត់ភ្លាមៗ! 👁️",
        "ជំនួយការ៖ គូខ្សែ ៣ គូជាប់គ្នា អាចកាត់លេខ ២ ធម្មតាបាន ១ សន្លឹក។ ចាំកាត់ឱ្យបានល្អ! 🔥"
    ];
    const randomHint = hints[Math.floor(Math.random() * hints.length)];
    alert(randomHint);
}

// Start Game
function startNewGame() {
    if (botTimerId) clearTimeout(botTimerId);
    if (turnInterval) clearInterval(turnInterval);
    
    randomizeSingleplayerBots(); // Randomize bot avatars for the new singleplayer round
    AudioSynth.playShuffle();
    winnerModal.classList.remove('active');
    gameOverChipsUpdated = false;
    lastTimerTurn = -1;
    lastTimerTrickCards = '';
    
    fetch('api/index.php?action=start')
        .then(res => res.json())
        .then(data => {
            selectedCards = [];
            updateUI(data);
        })
        .catch(err => console.error('Error starting game:', err));
}

// Fetch current game state
function fetchGameState() {
    fetch(`api/index.php?action=status&t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            updateUI(data);
        })
        .catch(err => console.error('Error fetching status:', err));
}

// Play Selected Cards
function playSelectedCards() {
    if (selectedCards.length === 0) return;

    const url = isMultiplayer
        ? `api/index.php?action=room_play&code=${roomCode}&player_id=${myPlayerId}`
        : 'api/index.php?action=play';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cards: selectedCards })
    })
    .then(res => res.json())
    .then(data => {
        if (data.result && !data.result.success) {
            showError(data.result.message);
            AudioSynth.playError();
        } else {
            selectedCards = [];
            AudioSynth.playCard();
            updateUI(data);
        }
    })
    .catch(err => console.error('Error playing cards:', err));
}

// Pass Turn
function passTurn() {
    const url = isMultiplayer
        ? `api/index.php?action=room_pass&code=${roomCode}&player_id=${myPlayerId}&t=${Date.now()}`
        : `api/index.php?action=pass&t=${Date.now()}`;

    fetch(url)
    .then(res => res.json())
    .then(data => {
        if (data.result && !data.result.success) {
            showError(data.result.message);
            AudioSynth.playError();
        } else {
            selectedCards = [];
            AudioSynth.playPass();
            updateUI(data);
        }
    })
    .catch(err => console.error('Error passing:', err));
}

// Bot Play Loop
function triggerBotPlay() {
    if (botTimerId) clearTimeout(botTimerId);
    console.log('Bot action trigger called for player seat:', gameState.current_turn);
    botTimerId = setTimeout(() => {
        fetch(`api/index.php?action=bot&t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            if (data.result) {
                console.log('Bot Selected Cards', data.result.played_cards);
                if (data.result.played_cards && data.result.played_cards.length > 0) {
                    AudioSynth.playCard();
                } else {
                    AudioSynth.playPass();
                }
            }
            updateUI(data);
        })
        .catch(err => console.error('Error in bot play:', err));
    }, 1300);
}


// Show Error Banner
function showError(msg) {
    errorBanner.textContent = msg;
    errorBanner.classList.add('active');
    setTimeout(() => {
        errorBanner.classList.remove('active');
    }, 2500);
}

// Check if selected cards can cut out-of-turn
function checkCanCutOutOfTurn(data) {
    if (data.current_turn === (isMultiplayer ? mySeatIndex : 0) || !data.current_trick) return false;
    
    // Check if table contains a 2, a pair of 2s, or a bomb
    const tVal = 3 + Math.floor(data.current_trick.highest_card / 4);
    const tType = data.current_trick.type;
    const tIsCuttable = (tType === 'single' && tVal === 15) || 
                        (tType === 'pair' && tVal === 15) || 
                        ['bomb_3pair', 'four_of_a_kind', 'bomb_4pair'].includes(tType);
    
    if (!tIsCuttable) return false;

    // Check if user selected 4 pairs of sequences
    if (selectedCards.length === 8) {
        const sorted = [...selectedCards].sort((a,b) => a - b);
        const getVal = id => 3 + Math.floor(id / 4);
        const v = sorted.map(getVal);
        
        const is4PairsSeq = (v[0] === v[1] &&
                             v[2] === v[3] &&
                             v[4] === v[5] &&
                             v[6] === v[7] &&
                             v[2] === v[0] + 1 &&
                             v[4] === v[2] + 1 &&
                             v[6] === v[4] + 1);
        if (is4PairsSeq) {
            return true;
        }
    }
    return false;
}

// Turn countdown clock timer
function startTurnTimer(playerIndex, gameData) {
    if (turnInterval) clearInterval(turnInterval);
    const timerBox = document.getElementById('timer-box');
    const timerSecs = document.getElementById('timer-seconds');
    if (!timerBox || !timerSecs) return;

    timerBox.style.display = 'flex';
    timerBox.classList.remove('urgent');
    timerBox.classList.remove('timeout');
    
    let seconds = 20;
    timerSecs.textContent = seconds.toString().padStart(2, '0');

    turnInterval = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(turnInterval);
            timerSecs.textContent = '00';
            timerBox.classList.add('timeout');
            
            // Auto turn play or pass for Human player
            if (playerIndex === (isMultiplayer ? mySeatIndex : 0) && !gameData.game_over) {
                if (btnPass.disabled === false) {
                    passTurn();
                } else {
                    autoPlayLowestCard();
                }
            }
        } else {
            timerSecs.textContent = seconds.toString().padStart(2, '0');
            if (seconds <= 5) {
                timerBox.classList.add('urgent');
            }
        }
    }, 1000);
}

function autoPlayLowestCard() {
    const myHand = isMultiplayer ? gameState.hands[mySeatIndex] : gameState.hands[0];
    if (myHand && myHand.length > 0) {
        const lowestCard = myHand[0]; // sorted
        selectedCards = [lowestCard];
        playSelectedCards();
    }
}

// Render UI Components
function updateUI(data) {
    gameState = data;
    
    // Log turn rotation, active player checks, and locks for debugging
    const currentPlayerIndex = data.current_turn;
    const activePlayerIsBot = !isMultiplayer ? (data.current_turn !== 0) : (data.players.find(p => p.seat === data.current_turn)?.is_bot || false);
    const currentPlayer = { seat: data.current_turn, isBot: activePlayerIsBot };
    console.log('Current Turn:', currentPlayerIndex);
    console.log('Bot Turn Check', currentPlayer);
    console.log('Game State Lock Check:', {
        gameOver: data.game_over,
        isMultiplayer: isMultiplayer,
        currentTurn: data.current_turn
    });
    
    // Hide winner modal if a new game starts in multiplayer room
    if (isMultiplayer && data.room_status === 'playing' && !data.game_over) {
        winnerModal.classList.remove('active');
    }

    // 1. Render History Logs
    renderHistory(data.history);

    // 2. Update players slots status & avatars
    for (let p = 0; p < 4; p++) {
        const slotIndex = isMultiplayer ? (p - mySeatIndex + 4) % 4 : p;
        const slot = document.getElementById(`player-${slotIndex}`);
        const statusEl = document.getElementById(`status-${slotIndex}`);
        const countEl = document.getElementById(`count-${slotIndex}`);
        
        // Resolve dynamic names and avatars
        let name = 'Bot';
        let avatarUrl = 'public/assets/images/avatar_male.png'; // default fallback
        
        if (isMultiplayer && data.players) {
            const ply = data.players.find(x => x.seat === p);
            if (ply) {
                name = ply.name + (ply.id === myPlayerId ? ' (ខ្ញុំ)' : '');
                avatarUrl = ply.avatar || (ply.is_bot ? 'public/assets/images/avatar_male.png' : 'public/assets/images/' + myPlayerAvatar);
            }
        } else {
            if (p === 0) {
                name = myPlayerName;
                avatarUrl = `public/assets/images/${myPlayerAvatar}`;
            } else {
                const botInfo = singleplayerBots[p - 1];
                name = botInfo.name;
                avatarUrl = botInfo.avatar;
            }
        }
        const nameEl = slotIndex === 0 ? document.getElementById('player-name-0') : slot.querySelector('.player-name');
        if (nameEl) nameEl.textContent = name;

        // Update avatar image dynamically
        const imgEl = slot.querySelector('.avatar-img');
        if (imgEl && avatarUrl) {
            imgEl.src = avatarUrl;
            imgEl.alt = name;
        }

        // Active Turn glow
        if (data.current_turn === p && !data.game_over) {
            slot.classList.add('active');
            if (slotIndex !== 0) {
                statusEl.textContent = 'កំពុងគិត...';
            } else {
                statusEl.textContent = 'វេនខ្ញុំលេង';
            }
        } else {
            slot.classList.remove('active');
            statusEl.textContent = '';
        }

        // Remove old badges
        const oldBadges = slot.querySelectorAll('.player-badge');
        oldBadges.forEach(b => b.remove());

        // Add badges for passed/stinky/winner
        if (data.passed[p] && !data.game_over && data.current_trick !== null) {
            const badge = document.createElement('span');
            badge.className = 'player-badge pass';
            badge.textContent = 'Pass';
            slot.querySelector('.avatar-card').appendChild(badge);
        }

        if (data.winner_order.includes(p)) {
            const rank = data.winner_order.indexOf(p) + 1;
            const badge = document.createElement('span');
            badge.className = 'player-badge';
            badge.textContent = `លេខ ${rank}`;
            slot.querySelector('.avatar-card').appendChild(badge);
        }

        if (data.game_over && data.stinky_players.includes(p)) {
            const badge = document.createElement('span');
            badge.className = 'player-badge stinky';
            badge.textContent = 'ស្អុយអាត់';
            slot.querySelector('.avatar-card').appendChild(badge);
        }

        // Card counts for bots or online opponents
        if (slotIndex !== 0) {
            const count = data.game_over ? data.hands[p].length : (typeof data.hands[p] === 'number' ? data.hands[p] : data.hands[p].length);
            if (countEl) countEl.textContent = `${count} សន្លឹក`;
            
            // Render basic graphic stacks for bot/opponent cards
            const stackContainer = document.getElementById(`stack-${slotIndex}`);
            if (stackContainer) {
                stackContainer.innerHTML = '';
                const stubCount = Math.min(count, 8); // clamp stack size for display
                for (let i = 0; i < stubCount; i++) {
                    const stub = document.createElement('div');
                    stub.className = 'card-back-stub';
                    stackContainer.appendChild(stub);
                }
            }
        }
    }

    // 3. Render Human player cards fanned out
    const myHandCards = isMultiplayer ? data.hands[mySeatIndex] : data.hands[0];
    renderHumanHand(myHandCards, !data.game_over);

    // 4. Render Table Trick cards in center
    renderTableTrick(data.current_trick);

    // 5. Update bottom control buttons state
    const isMyTurn = (isMultiplayer ? (data.current_turn === mySeatIndex) : (data.current_turn === 0)) && !data.game_over;
    btnPass.disabled = !isMyTurn || data.current_trick === null;
    btnSuggest.disabled = !isMyTurn;
    updateCombinationIndicator();

    // 6. Handle turn countdown timer
    const currentTrickCards = data.current_trick ? data.current_trick.cards.join(',') : '';
    if (!data.game_over) {
        if (data.current_turn !== lastTimerTurn || currentTrickCards !== lastTimerTrickCards) {
            lastTimerTurn = data.current_turn;
            lastTimerTrickCards = currentTrickCards;
            startTurnTimer(data.current_turn, data);
        }
    } else {
        if (turnInterval) clearInterval(turnInterval);
        const tBox = document.getElementById('timer-box');
        if (tBox) tBox.style.display = 'none';
        lastTimerTurn = -1;
        lastTimerTrickCards = '';
    }

    // 7. Handle automatic bot turns (Only offline)
    if (!isMultiplayer && data.current_turn !== 0 && !data.game_over) {
        triggerBotPlay();
    }

    // 8. Check if game over, display modal and update points
    if (data.game_over) {
        if (botTimerId) clearTimeout(botTimerId);
        updatePointsGameOver(data);
        setTimeout(() => {
            showWinnerModal(data);
        }, 1200);
    }
}

// Helper to generate dynamic card face and layout
function getCardInnerHTML(cardVal, cardSuit) {
    const valueText = VAL_NAMES[cardVal];
    const suitChar = SUIT_CHARS[cardSuit];

    let centerHTML = '';
    if (cardVal === 14) { // Ace
        centerHTML = `<div class="card-center-suit ace">${suitChar}</div>`;
    } else if (cardVal === 15) { // 2 (Boss Card)
        centerHTML = `
            <div class="card-center-suit boss">
                <span class="boss-crown">👑</span>
                <span class="boss-suit">${suitChar}</span>
            </div>
        `;
    } else if (cardVal === 11) { // J
        centerHTML = `
            <div class="card-center-suit royal">
                <span class="royal-emblem">💂</span>
                <span class="royal-suit">${suitChar}</span>
            </div>
        `;
    } else if (cardVal === 12) { // Q
        centerHTML = `
            <div class="card-center-suit royal">
                <span class="royal-emblem">👸</span>
                <span class="royal-suit">${suitChar}</span>
            </div>
        `;
    } else if (cardVal === 13) { // K
        centerHTML = `
            <div class="card-center-suit royal">
                <span class="royal-emblem">🤴</span>
                <span class="royal-suit">${suitChar}</span>
            </div>
        `;
    } else { // 3-10
        centerHTML = `
            <div class="card-center-suit number-card">
                <span class="center-suit-char">${suitChar}</span>
                <span class="center-value-num">${valueText}</span>
            </div>
        `;
    }

    return `
        <div class="card-corner top">
            <span>${valueText}</span>
            <span>${suitChar}</span>
        </div>
        ${centerHTML}
        <div class="card-corner bottom">
            <span>${valueText}</span>
            <span>${suitChar}</span>
        </div>
    `;
}

// Encapsulated Combination Analyzer (exact JavaScript port of App\Models\TienLenGame::analyzeCombination)
const CombinationAnalyzer = {
    getCardValue(id) {
        return 3 + Math.floor(id / 4);
    },
    getCardSuit(id) {
        return id % 4;
    },
    analyze(cardIds) {
        const count = cardIds.length;
        if (count === 0) return null;

        const cards = [...cardIds].sort((a, b) => a - b);
        const vals = cards.map(id => this.getCardValue(id));
        const suits = cards.map(id => this.getCardSuit(id));

        // 1. Single
        if (count === 1) {
            return {
                type: 'single',
                highest_card: cards[0],
                cards: cards
            };
        }

        // 2. Pair
        if (count === 2) {
            if (vals[0] === vals[1]) {
                return {
                    type: 'pair',
                    highest_card: cards[1],
                    cards: cards
                };
            }
            return null;
        }

        // 3. Triple
        if (count === 3) {
            if (vals[0] === vals[1] && vals[1] === vals[2]) {
                return {
                    type: 'triple',
                    highest_card: cards[2],
                    cards: cards
                };
            }
            return null;
        }

        // 4. Four of a kind
        if (count === 4) {
            if (vals[0] === vals[1] && vals[1] === vals[2] && vals[2] === vals[3]) {
                return {
                    type: 'four_of_a_kind',
                    highest_card: cards[3],
                    cards: cards
                };
            }
        }

        // 5. Sequences (3 or more cards)
        const hasTwo = vals.includes(15);
        if (!hasTwo && count >= 3) {
            let isSeq = true;
            for (let i = 1; i < count; i++) {
                if (vals[i] !== vals[i - 1] + 1) {
                    isSeq = false;
                    break;
                }
            }
            if (isSeq) {
                return {
                    type: 'sequence',
                    highest_card: cards[count - 1],
                    cards: cards
                };
            }
        }

        // 6. 3 Pairs of sequences (6 cards)
        if (count === 6) {
            if (vals[0] === vals[1] &&
                vals[2] === vals[3] &&
                vals[4] === vals[5] &&
                vals[2] === vals[0] + 1 &&
                vals[4] === vals[2] + 1) {
                return {
                    type: 'bomb_3pair',
                    highest_card: cards[5],
                    cards: cards
                };
            }
        }

        // 7. 4 Pairs of sequences (8 cards)
        if (count === 8) {
            if (vals[0] === vals[1] &&
                vals[2] === vals[3] &&
                vals[4] === vals[5] &&
                vals[6] === vals[7] &&
                vals[2] === vals[0] + 1 &&
                vals[4] === vals[2] + 1 &&
                vals[6] === vals[4] + 1) {
                return {
                    type: 'bomb_4pair',
                    highest_card: cards[7],
                    cards: cards
                };
            }
        }

        return null;
    }
};

// Hand rendering & interaction state variables
let lastCardClickTime = 0;
let lastHandCardIds = [];

// Dynamically adjust negative margins and centering alignment to prevent overflow
function adjustCardSpacing() {
    const cards = humanHandContainer.querySelectorAll('.card');
    if (cards.length <= 1) {
        humanHandContainer.style.setProperty('--card-margin', '0px');
        humanHandContainer.style.justifyContent = 'center';
        return;
    }

    const containerWidth = humanHandContainer.parentElement.clientWidth;
    const firstCard = cards[0];
    const cardWidth = firstCard ? firstCard.offsetWidth : (window.innerWidth <= 768 ? 52 : 80);
    
    const maxWidth = containerWidth - 40;
    const count = cards.length;
    
    // Calculate overlap margin needed: count * cardWidth + (count - 1) * margin = maxWidth
    let margin = (maxWidth - count * cardWidth) / (count - 1);
    
    // Clamp standard default spacing: -32px on desktop (40% of 80px card width)
    const maxMarginVal = -cardWidth * 0.40;
    if (margin > maxMarginVal) {
        margin = maxMarginVal;
    }
    
    // Enforce 70% maximum overlap so card face remains readable
    const minMarginVal = -cardWidth * 0.70;
    let handOverflows = false;
    if (margin < minMarginVal) {
        margin = minMarginVal;
        handOverflows = true;
    }
    
    humanHandContainer.style.setProperty('--card-margin', `${margin}px`);
    
    // Flex-start alignment enables natural horizontal scroll if the cards exceed viewport
    if (handOverflows) {
        humanHandContainer.style.justifyContent = 'flex-start';
    } else {
        humanHandContainer.style.justifyContent = 'center';
    }
}

// Map validator type to display string
function getCombinationDisplayName(combo) {
    if (!combo) return '';
    const count = combo.cards.length;
    switch (combo.type) {
        case 'single':
            return 'Single';
        case 'pair':
            return 'Pair';
        case 'triple':
            return 'Three of a Kind';
        case 'four_of_a_kind':
            return 'Four of a Kind';
        case 'sequence':
            return `Straight (${count})`;
        case 'bomb_3pair':
            return 'Double Straight (3 Pairs)';
        case 'bomb_4pair':
            return 'Double Straight (4 Pairs)';
        default:
            return '';
    }
}

// Update local UI selection state, type labels, and play button enabling without server calls
function updateCombinationIndicator() {
    const indicator = document.getElementById('combination-indicator');
    if (!indicator) return;

    if (selectedCards.length === 0) {
        indicator.textContent = '';
        indicator.className = 'combination-indicator';
        btnPlay.disabled = true;
        btnPlay.classList.remove('btn-primary');
        return;
    }

    const combo = CombinationAnalyzer.analyze(selectedCards);
    const isMyTurn = (isMultiplayer ? (gameState.current_turn === mySeatIndex) : (gameState.current_turn === 0)) && !gameState.game_over;
    const canCut = checkCanCutOutOfTurn(gameState);

    if (combo) {
        const displayName = getCombinationDisplayName(combo);
        const count = combo.cards.length;
        indicator.innerHTML = `✓ Selected: ${displayName} (${count} card${count > 1 ? 's' : ''})`;
        indicator.className = 'combination-indicator valid';

        const playEnabled = isMyTurn || canCut;
        btnPlay.disabled = !playEnabled;
        if (playEnabled) {
            btnPlay.classList.add('btn-primary');
        } else {
            btnPlay.classList.remove('btn-primary');
        }
    } else {
        indicator.innerHTML = '✗ Invalid Combination';
        indicator.className = 'combination-indicator invalid';
        btnPlay.disabled = true;
        btnPlay.classList.remove('btn-primary');
    }

    if (canCut && combo) {
        btnPlay.textContent = '🔥 កាត់បៀរ';
    } else {
        btnPlay.textContent = '🃏 បញ្ចេញបៀរ';
    }
}

// Fast in-place class and z-index syncing to prevent frame drops or flickering during room updates
function syncHandSelectionState(cards) {
    selectedCards = selectedCards.filter(id => cards.includes(id));
    const combo = CombinationAnalyzer.analyze(selectedCards);
    const isValidCombo = combo !== null && combo.type !== 'single';

    const cardElements = humanHandContainer.querySelectorAll('.card');
    cardElements.forEach(cardDiv => {
        const cardId = parseInt(cardDiv.dataset.id);
        const index = cards.indexOf(cardId);
        if (index === -1) return;

        const isSelected = selectedCards.includes(cardId);
        if (isSelected) {
            cardDiv.classList.add('selected');
            cardDiv.style.zIndex = index + 50;
            if (isValidCombo) {
                cardDiv.classList.add('valid-combination');
            } else {
                cardDiv.classList.remove('valid-combination');
            }
        } else {
            cardDiv.classList.remove('selected');
            cardDiv.classList.remove('valid-combination');
            cardDiv.style.zIndex = index + 1;
        }
    });

    if (selectedCards.length > 0) {
        humanHandContainer.classList.add('has-selected');
    } else {
        humanHandContainer.classList.remove('has-selected');
    }

    updateCombinationIndicator();
}

// Full DOM regeneration for when card lists actually change
function rebuildHumanHandDOM(cards, isInteractive) {
    humanHandContainer.innerHTML = '';

    if (selectedCards.length > 0) {
        humanHandContainer.classList.add('has-selected');
    } else {
        humanHandContainer.classList.remove('has-selected');
    }

    const combo = CombinationAnalyzer.analyze(selectedCards);
    const isValidCombo = combo !== null && combo.type !== 'single';

    cards.forEach((cardId, index) => {
        const cardVal = 3 + Math.floor(cardId / 4);
        const cardSuit = cardId % 4;

        const cardDiv = document.createElement('div');
        cardDiv.className = `card ${SUIT_NAMES[cardSuit]}`;
        cardDiv.dataset.id = cardId;

        const isSelected = selectedCards.includes(cardId);
        if (isSelected) {
            cardDiv.classList.add('selected');
            cardDiv.style.zIndex = index + 50;
            if (isValidCombo) {
                cardDiv.classList.add('valid-combination');
            }
        } else {
            cardDiv.style.zIndex = index + 1;
        }

        cardDiv.innerHTML = getCardInnerHTML(cardVal, cardSuit);

        if (isInteractive) {
            cardDiv.addEventListener('click', () => {
                // Prevent duplicate click/taps within 100ms
                const now = Date.now();
                if (now - lastCardClickTime < 100) return;
                lastCardClickTime = now;

                const alreadySelected = selectedCards.includes(cardId);
                if (alreadySelected) {
                    selectedCards = selectedCards.filter(id => id !== cardId);
                    cardDiv.classList.remove('selected');
                    cardDiv.classList.remove('valid-combination');
                    cardDiv.style.zIndex = index + 1;
                } else {
                    selectedCards.push(cardId);
                    cardDiv.classList.add('selected');
                    cardDiv.style.zIndex = index + 50;
                }

                // Recalculate combo validations in-place without rebuilding DOM
                const currentCombo = CombinationAnalyzer.analyze(selectedCards);
                const currentValidCombo = currentCombo !== null && currentCombo.type !== 'single';

                const cardElements = humanHandContainer.querySelectorAll('.card');
                cardElements.forEach(el => {
                    if (el.classList.contains('selected') && currentValidCombo) {
                        el.classList.add('valid-combination');
                    } else {
                        el.classList.remove('valid-combination');
                    }
                });

                if (selectedCards.length > 0) {
                    humanHandContainer.classList.add('has-selected');
                } else {
                    humanHandContainer.classList.remove('has-selected');
                }

                updateCombinationIndicator();
            });
        }

        humanHandContainer.appendChild(cardDiv);
    });

    adjustCardSpacing();
    updateCombinationIndicator();
}

// Main Render Human Hand entry point
function renderHumanHand(cards, isInteractive) {
    if (!cards) cards = [];

    // 1. Sort cards numerically to keep: 3 -> 4 -> ... -> A -> 2 and suit priority
    cards.sort((a, b) => a - b);

    // 2. Filter selected cards by current hand cards
    selectedCards = selectedCards.filter(id => cards.includes(id));

    // 3. Fast check if hand list changed
    const isIdentical = cards.length === lastHandCardIds.length &&
                        cards.every((id, i) => id === lastHandCardIds[i]);

    if (isIdentical) {
        syncHandSelectionState(cards);
    } else {
        lastHandCardIds = [...cards];
        rebuildHumanHandDOM(cards, isInteractive);
    }
}

// Adjust spacing on window resizing
window.addEventListener('resize', () => {
    adjustCardSpacing();
});

// Render Table Center Trick
function renderTableTrick(trick) {
    trickCardsContainer.innerHTML = '';
    
    if (!trick || trick.cards.length === 0) {
        trickCardsContainer.innerHTML = '<div style="color: rgba(255,255,255,0.25); font-size: 0.9rem;">មិនទាន់មានបៀរលើតុឡើយ</div>';
        // if (trickTypeInfo) trickTypeInfo.style.display = 'none';
        return;
    }

    // Display trick descriptor
    const playerNames = ['អ្នកលេង (ខ្ញុំ)', 'Bot 1', 'Bot 2', 'Bot 3'];
    const typeLabel = {
        'single': 'បៀរសន្លឹក (Single)',
        'pair': 'បៀរគូ (Pair)',
        'triple': 'បៀរត្រីសេ (Triple)',
        'sequence': 'បៀរខ្សែ (Sequence)',
        'bomb_3pair': 'គ្រាប់បែក ៣ គូ',
        'four_of_a_kind': 'ការ៉េ ៤ សន្លឹក',
        'bomb_4pair': 'គ្រាប់បែក ៤ គូ'
    };
    
    /*
    if (trickTypeInfo) {
        trickTypeInfo.textContent = `${playerNames[trick.played_by]}៖ ${typeLabel[trick.type]}`;
        trickTypeInfo.style.display = 'block';
    }
    */

    trick.cards.forEach((cardId, index) => {
        const cardVal = 3 + Math.floor(cardId / 4);
        const cardSuit = cardId % 4;

        const cardDiv = document.createElement('div');
        cardDiv.className = `card table-card ${SUIT_NAMES[cardSuit]}`;
        
        // Add random natural looking rotation based on index and card id
        const rotation = (cardId % 7 - 3) * 4;
        cardDiv.style.transform = `rotate(${rotation}deg)`;
        cardDiv.style.zIndex = index + 1;

        cardDiv.innerHTML = getCardInnerHTML(cardVal, cardSuit);

        trickCardsContainer.appendChild(cardDiv);
    });
}

// Render Action History Logs
function renderHistory(logs) {
    historyLog.innerHTML = '';
    
    if (logs.length === 0) {
        historyLog.innerHTML = '<div class="log-item system">មិនទាន់មានកំណត់ត្រាឡើយ</div>';
        return;
    }

    const resolvedNames = [];
    for (let p = 0; p < 4; p++) {
        if (isMultiplayer && gameState.players) {
            const ply = gameState.players.find(x => x.seat === p);
            resolvedNames[p] = ply ? ply.name + (ply.id === myPlayerId ? ' (ខ្ញុំ)' : '') : 'Bot';
        } else {
            resolvedNames[p] = p === 0 ? myPlayerName : singleplayerBots[p - 1].name;
        }
    }

    logs.forEach(log => {
        const logItem = document.createElement('div');
        logItem.className = `log-item player-${log.player}`;
        
        let desc = log.desc;
        if (log.action === 'pass') {
            desc = 'ចម្លង/រំលង (Pass) ⏭️';
        } else if (log.action === 'new_round') {
            desc = 'បានសិទ្ធិបើកជុំថ្មី 🌟';
        } else if (log.action === 'white_win') {
            desc = `ឈ្នះផ្តាច់ភ្លាមៗ! 🎉 (${log.desc})`;
        }
        
        logItem.innerHTML = `
            <div class="log-time">${new Date(log.timestamp * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})}</div>
            <strong>${resolvedNames[log.player]}</strong>៖ ${desc}
        `;
        historyLog.appendChild(logItem);
    });

    // Auto-scroll to bottom of history
    historyLog.scrollTop = historyLog.scrollHeight;
}

// Show Winner Scoreboard Modal
// Show Winner Scoreboard Modal
function showWinnerModal(data) {
    // Resolve dynamic player names
    const resolvedNames = [];
    for (let p = 0; p < 4; p++) {
        if (isMultiplayer && data.players) {
            const ply = data.players.find(x => x.seat === p);
            resolvedNames[p] = ply ? ply.name + (ply.id === myPlayerId ? ' (ខ្ញុំ)' : '') : 'Bot';
        } else {
            resolvedNames[p] = p === 0 ? 'ខ្ញុំ' : singleplayerBots[p - 1].name;
        }
    }

    if (data.white_win_player !== null) {
        winnerTitle.innerHTML = '🎉 ឈ្នះផ្តាច់ភ្លាមៗ! (White Win)';
        winnerDesc.textContent = `${resolvedNames[data.white_win_player]} ទទួលបានជ័យជម្នះភ្លាមៗដោយសារមាន៖ ${data.white_win_reason}`;
        if (data.white_win_player === (isMultiplayer ? mySeatIndex : 0)) {
            AudioSynth.playWin();
        }
    } else {
        const winnerName = resolvedNames[data.winner_order[0]];
        winnerTitle.innerHTML = `🏆 ជ័យជម្នះបានទៅលើ ${winnerName}!`;
        winnerDesc.textContent = 'ហ្គេមត្រូវបានបញ្ចប់រួចរាល់ហើយ។ នេះជាចំណាត់ថ្នាក់របស់អ្នកលេងទាំងអស់៖';
        if (data.winner_order[0] === (isMultiplayer ? mySeatIndex : 0)) {
            AudioSynth.playWin();
        }
    }

    modalScoreboard.innerHTML = '';

    // Calculate round points delta for scoreboard display
    const pointsDelta = { 0: 0, 1: 0, 2: 0, 3: 0 };
    const rankPoints = [300, 100, -100, -300];

    // Assign base rank points
    for (let i = 0; i < 4; i++) {
        const pid = data.winner_order[i];
        pointsDelta[pid] = rankPoints[i];
    }

    // Apply stinky player penalty
    data.stinky_players.forEach(pid => {
        pointsDelta[pid] -= 100;
        pointsDelta[data.winner_order[0]] += 100;
    });

    // Construct player list with total points
    const playersList = [];
    for (let p = 0; p < 4; p++) {
        const total = parseInt(localStorage.getItem(`tienlen_game_points_${p}`)) || 0;
        // Find their finish rank in this round:
        const roundRank = data.winner_order.indexOf(p) + 1;
        playersList.push({
            pid: p,
            total: total,
            roundRank: roundRank
        });
    }

    // Sort by total points descending. If total points are equal, sort by roundRank ascending.
    playersList.sort((a, b) => {
        if (b.total !== a.total) {
            return b.total - a.total;
        }
        return a.roundRank - b.roundRank;
    });

    playersList.forEach((playerInfo, index) => {
        const rank = index + 1; // Leaderboard rank
        const pid = playerInfo.pid;
        const total = playerInfo.total;
        const roundRank = playerInfo.roundRank;

        const row = document.createElement('div');
        row.className = `score-row ${rank === 1 ? 'winner' : ''}`;
        
        let statusText = 'រួចរាល់';
        let statusClass = 'clear';

        if (data.stinky_players.includes(pid)) {
            statusText = 'ស្អុយអាត់ ⚠️';
            statusClass = 'stinky';
        } else if (roundRank === 1) {
            statusText = 'អ្នកឈ្នះ 🥇';
            statusClass = 'clear';
        } else if (roundRank === 2) {
            statusText = 'លេខ ២ 🥈';
            statusClass = 'clear';
        } else if (roundRank === 3) {
            statusText = 'លេខ ៣ 🥉';
            statusClass = 'clear';
        } else {
            // Count cards left (Round Rank 4)
            let cardsCount = 0;
            if (Array.isArray(data.hands[pid])) {
                cardsCount = data.hands[pid].length;
            } else {
                cardsCount = data.hands[pid]; // if count is just a number
            }
            statusText = `សល់ ${cardsCount} សន្លឹក`;
            statusClass = 'pass';
        }

        const delta = pointsDelta[pid];
        const deltaText = delta > 0 ? `+${delta}` : `${delta}`;
        const deltaClass = delta > 0 ? 'delta-positive' : 'delta-negative';

        // Resolve scoreboard player avatar URL
        let avatarUrl = 'public/assets/images/avatar_user.png';
        if (isMultiplayer && data.players) {
            const ply = data.players.find(x => x.seat === pid);
            if (ply && ply.avatar) {
                avatarUrl = ply.avatar;
            }
        } else {
            if (pid === 0) {
                avatarUrl = `public/assets/images/${myPlayerAvatar}`;
            } else {
                avatarUrl = singleplayerBots[pid - 1].avatar;
            }
        }

        row.innerHTML = `
            <span class="score-rank">#${rank}</span>
            <span class="score-name" style="display: flex; align-items: center; gap: 8px;">
                <img src="${avatarUrl}" class="score-avatar" alt="Avatar">
                ${resolvedNames[pid]}
            </span>
            <span class="score-delta ${deltaClass}">${deltaText} ពិន្ទុ<small style="display:block; font-size:0.68rem; color:#9ca3af; font-weight:normal; text-align:right; margin-top:2px;">សរុប: ${total.toLocaleString()}</small></span>
            <span class="score-status ${statusClass}">${statusText}</span>
        `;
        modalScoreboard.appendChild(row);
    });

    // Control restart button visibility in multiplayer
    if (isMultiplayer) {
        if (mySeatIndex === 0) {
            btnModalRestart.style.display = 'block';
            btnModalRestart.textContent = '🔄 ចាប់ផ្តើមវគ្គថ្មី (Start New Round)';
        } else {
            btnModalRestart.style.display = 'none';
            // Show a waiting message text if not already added
            let waitMsg = document.getElementById('waiting-host-restart-msg');
            if (!waitMsg) {
                waitMsg = document.createElement('p');
                waitMsg.id = 'waiting-host-restart-msg';
                waitMsg.style.color = '#e74c3c';
                waitMsg.style.fontWeight = 'bold';
                waitMsg.style.marginTop = '15px';
                waitMsg.style.fontSize = '0.95rem';
                waitMsg.textContent = '⏳ រង់ចាំម្ចាស់បន្ទប់ចាប់ផ្តើមវគ្គថ្មី...';
                btnModalRestart.parentNode.appendChild(waitMsg);
            }
        }
    } else {
        btnModalRestart.style.display = 'block';
        btnModalRestart.textContent = '🔄 លេងម្តងទៀត (Play Again)';
        const waitMsg = document.getElementById('waiting-host-restart-msg');
        if (waitMsg) waitMsg.remove();
    }

    winnerModal.classList.add('active');
}

// Fetch suggested move from API and select cards
function getMoveSuggestion() {
    const url = isMultiplayer
        ? `api/index.php?action=room_suggest&code=${roomCode}&player_id=${myPlayerId}`
        : 'api/index.php?action=suggest';

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cards) {
                if (data.cards.length === 0) {
                    showError('គ្មានបៀរណែនាំដែលអាចកាត់បានឡើយ! (No valid suggestion found!)');
                } else {
                    selectedCards = [...data.cards];
                    // Redraw hand to highlight the selected cards
                    const myHand = isMultiplayer ? gameState.hands[mySeatIndex] : gameState.hands[0];
                    if (myHand) {
                        renderHumanHand(myHand, true);
                    }
                    // Update play button state
                    updateCombinationIndicator();
                }
            } else {
                showError(data.message || 'មិនអាចណែនាំបានឡើយ!');
            }
        })
        .catch(err => {
            console.error('Error fetching suggestion:', err);
            showError('មានបញ្ហាក្នុងការទទួលបានការណែនាំ!');
        });
}

// Fullscreen API Helper with Webkit compatibility
function toggleFullscreen() {
    const docEl = document.documentElement;
    const requestFS = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullscreen || docEl.msRequestFullscreen;
    const exitFS = document.exitFullscreen || document.mozCancelFullScreen || document.webkitExitFullscreen || document.msExitFullscreen;

    const isFS = document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement;

    if (!isFS) {
        if (requestFS) {
            requestFS.call(docEl).catch(err => {
                console.log('Error triggering fullscreen:', err);
                showError('មិនអាចបើកពេញអេក្រង់បានទេ! (Cannot trigger fullscreen)');
            });
        } else {
            showError('កម្មវិធីរុករករបស់អ្នកមិនគាំទ្រ Fullscreen ទេ! (Fullscreen not supported)');
        }
    } else {
        if (exitFS) {
            exitFS.call(document);
        }
    }
}
