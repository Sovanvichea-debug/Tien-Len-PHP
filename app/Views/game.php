<!DOCTYPE html>
<html lang="km" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google" content="notranslate">
    <title>ល្បែងបៀរ ទៀនឡិន - Tien Len Web Game</title>
    <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="game-header">
        <div class="logo-container">
            <h1>TIEN LEN</h1>
        </div>
        <div class="header-controls">
            <button class="btn" id="btn-toggle-log">📝 <span class="btn-text">កំណត់ត្រា</span></button>
            <button class="btn" id="btn-help">❔ <span class="btn-text">របៀបលេង</span></button>
            <button class="btn btn-primary" id="btn-restart">🔄 <span class="btn-text">ក្តារថ្មី</span></button>
        </div>
    </header>

    <div class="game-container">
        
        <!-- Slide-out Game Event History (Sidebar) -->
        <aside class="game-sidebar" id="game-sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">📝 កំណត់ត្រាហ្គេម</h2>
                <button class="btn-close-sidebar" id="btn-close-sidebar">✕</button>
            </div>
            <div class="log-list" id="history-log">
                <div class="log-item system">សូមចុច "ក្តារថ្មី" ដើម្បីចាប់ផ្តើមលេង!</div>
            </div>
        </aside>

        <!-- Main Felt Poker Table -->
        <main class="table-area">
            
            <!-- Top-Left Controls -->
            <div class="board-left-controls">
                <button class="btn-board-control" id="btn-exit" title="ចាកចេញ">🚪</button>
                <button class="btn-board-control" id="btn-fullscreen" title="ពេញអេក្រង់">⛶</button>
                <button class="btn-board-control" id="btn-board-restart" title="ក្តារថ្មី">🔄</button>
            </div>

            <!-- Top-Right Controls -->
            <div class="board-right-controls">
                <button class="btn-board-control" id="btn-board-log" title="កំណត់ត្រា">📝</button>
                <button class="btn-board-control" id="btn-board-help" title="របៀបលេង">❔</button>
                <button class="btn-board-control" id="btn-settings" title="ការកំណត់">⚙️</button>
                <button class="btn-board-control" id="btn-bot-chat" title="ជំនួយការ">🤖</button>
            </div>

            <!-- Bot 2 (Top-Right Opponent) -->
            <div class="player-slot player-top-right" id="player-2">
                <div class="avatar-card">
                    <!-- Default placeholder using avatar_female.png, replaced dynamically by JS -->
                    <img src="public/assets/images/avatar_female.png" alt="Bot 2" class="avatar-img">
                </div>
                <div class="player-name">Guest0D9..</div>
                <div class="player-chips">ពិន្ទុ: <span id="chips-val-2">0</span></div>
                <span class="player-status" id="status-2"></span>
                <span class="player-cards-count" id="count-2">0 សន្លឹក</span>
                <div class="bot-card-stack" id="stack-2"></div>
            </div>

            <!-- Bot 1 (Top-Left Opponent) -->
            <div class="player-slot player-top-left" id="player-1">
                <div class="avatar-card">
                    <!-- Default placeholder using avatar_male.png, replaced dynamically by JS -->
                    <img src="public/assets/images/avatar_male.png" alt="Bot 1" class="avatar-img">
                </div>
                <div class="player-name">Guest1D9..</div>
                <div class="player-chips">ពិន្ទុ: <span id="chips-val-1">0</span></div>
                <span class="player-status" id="status-1"></span>
                <span class="player-cards-count" id="count-1">0 សន្លឹក</span>
                <div class="bot-card-stack" id="stack-1"></div>
            </div>

            <!-- Central Arena / Table Trick -->
            <div class="center-table">
                <div class="status-banner" id="error-banner">បៀរមិនត្រឹមត្រូវ!</div>
                
                <!-- Countdown Alarm Clock -->
                <div class="timer-box" id="timer-box" style="display: none;">
                    <div class="timer-legs"></div>
                    <span class="timer-seconds" id="timer-seconds">15</span>
                </div>
                
                <div class="trick-wrapper" id="trick-cards-container">
                    <div style="color: rgba(255,255,255,0.25); font-size: 0.9rem;">មិនទាន់មានបៀរលើតុឡើយ</div>
                </div>
            </div>

            <!-- Bot 3 (Middle-Right Opponent) -->
            <div class="player-slot player-middle-right" id="player-3">
                <div class="avatar-card">
                    <!-- Default placeholder using avatar_male.png, replaced dynamically by JS -->
                    <img src="public/assets/images/avatar_male.png" alt="Bot 3" class="avatar-img">
                </div>
                <div class="player-name">mee ko</div>
                <div class="player-chips">ពិន្ទុ: <span id="chips-val-3">0</span></div>
                <span class="player-status" id="status-3"></span>
                <span class="player-cards-count" id="count-3">0 សន្លឹក</span>
                <div class="bot-card-stack" id="stack-3"></div>
            </div>

            <!-- Human Player (Bottom-Left) -->
            <div class="player-slot player-bottom-left" id="player-0">
                <div class="avatar-card">
                    <img src="public/assets/images/avatar_user.png" alt="User" class="avatar-img">
                </div>
                <div class="player-name" id="player-name-0">ខ្ញុំ</div>
                <div class="player-chips-bar">
                    <span>ពិន្ទុ: </span>
                    <span class="chips-value" id="chips-val-0">0</span>
                </div>
                <span class="player-status" id="status-0"></span>
            </div>

            <!-- Human player hand (fanned cards absolute overlay inside felt) -->
            <div class="human-deck-container">
                <div class="human-hand" id="human-hand-container">
                    <!-- User cards will be fanned out here -->
                </div>
            </div>

            <!-- Player controls floating above hand -->
            <div class="action-controls" id="player-action-controls">
                <div class="combination-indicator" id="combination-indicator"></div>
                <div class="action-buttons-row">
                    <button class="btn btn-play" id="btn-play" disabled>បញ្ចេញបៀរ</button>
                    <button class="btn btn-suggest" id="btn-suggest" disabled>ណែនាំ</button>
                    <button class="btn btn-pass" id="btn-pass" disabled>រំលង</button>
                </div>
            </div>

        </main>

    </div>

    <!-- Victory / Scoreboard Modal -->
    <div class="modal-overlay" id="winner-modal">
        <div class="modal-content">
            <h2 class="modal-title" id="winner-title">🏆 លទ្ធផលការប្រកួត!</h2>
            <div class="modal-body">
                <p id="winner-desc">ហ្គេមបានបញ្ចប់ដោយជោគជ័យ។</p>
                <div class="scoreboard" id="modal-scoreboard">
                    <!-- Rankings rows will be injected here -->
                </div>
            </div>
            <button class="btn btn-primary" id="btn-modal-restart" style="margin: 0 auto; width: 200px;">🔄 លេងម្តងទៀត (Play Again)</button>
        </div>
    </div>

    <!-- Help & Guide Modal -->
    <div class="modal-overlay" id="help-modal">
        <div class="modal-content help-modal">
            <h2 class="modal-title">📖 របៀបលេងបៀរ Tien Len</h2>
            <div class="modal-body">
                
                <div class="help-section">
                    <h3>១. ការលំដាប់កម្លាំងបៀរ</h3>
                    <p><strong>លេខបៀរ៖</strong> 3 &lt; 4 &lt; 5 &lt; 6 &lt; 7 &lt; 8 &lt; 9 &lt; 10 &lt; J &lt; Q &lt; K &lt; A &lt; 2 (លេខ ២ ធំបំផុត)។</p>
                    <p><strong>ទឹកបៀរ៖</strong> ប៊ិច (♠️) &lt; គូ (♣️) &lt; ការ៉ូ (♦️) &lt; បេះដូង (♥️)。</p>
                </div>

                <div class="help-section">
                    <h3>២. ឈុតបៀរស្របច្បាប់</h3>
                    <ul>
                        <li><strong>បៀរសន្លឹក (Single)៖</strong> បៀរ ១ សន្លឹកទោល។</li>
                        <li><strong>បៀរគូ (Pair)៖</strong> បៀរលេខដូចគ្នា ២ សន្លឹក។</li>
                        <li><strong>បៀរត្រីសេ (Triple)៖</strong> បៀរលេខដូចគ្នា ៣ សន្លឹក។</li>
                        <li><strong>បៀរខ្សែ (Sequence)៖</strong> ៣ សន្លឹកឡើងទៅដែលមានលេខរៀបតាមលំដាប់គ្នា (មិនគិតទឹកឡើយ)។ លេខ ២ មិនអាចនៅក្នុងខ្សែឡើយ។</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>៣. ឈុតពិសេស (គ្រាប់បែកកាត់លេខ ២)</h3>
                    <ul>
                        <li><strong>គូខ្សែ ៣ គូជាប់គ្នា៖</strong> (ឧទាហរណ៍៖ 3-3, 4-4, 5-5) អាចកាត់លេខ ២ ទោលបាន ១ សន្លឹក។</li>
                        <li><strong>បៀរការ៉េ (Four of a kind)៖</strong> (ឧទាហរណ៍៖ 8-8-8-8) អាចកាត់លេខ ២ ទោលបាន, កាត់គូខ្សែ ៣ គូបាន និងកាត់ការ៉េតូចជាងបាន។</li>
                        <li><strong>គូខ្សែ ៤ គូជាប់គ្នា៖</strong> (ឧទាហរណ៍៖ 7-7, 8-8, 9-9, 10-10) អាចកាត់លេខ ២ មួយគូបាន, កាត់ការ៉េបាន និងកាត់គូខ្សែ ៣ គូបាន។ ឈុតនេះអាចទម្លាក់កាត់បានភ្លាមៗ ទោះបីជាមិនដល់វេនខ្លួន ឬធ្លាប់ Pass ក៏ដោយ។</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>៤. ច្បាប់ស្អុយអាត់ (លេខ ២)</h3>
                    <p>នៅពេលហ្គេមបញ្ចប់ បើអ្នកនៅសល់លេខ ២ ក្នុងដៃ នឹងត្រូវចាត់ទុកថា «ស្អុយអាត់» ហើយត្រូវរងការពិន័យជាប្រាក់ ឬពិន្ទុទ្វេដង。</p>
                </div>

            </div>
            <button class="btn" id="btn-close-help" style="margin: 0 auto; width: 120px;">បិទ (Close)</button>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal-content">
            <h2 class="modal-title">⚙️ การកំណត់ (Settings)</h2>
            <div class="modal-body" style="text-align: left; padding: 10px;">
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 0.95rem; color: #fff;">🔊 បើកសំឡេង (Sound Effects)</span>
                    <input type="checkbox" id="setting-sound" checked style="width: 20px; height: 20px; cursor: pointer;">
                </div>
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 0.95rem; color: #fff;">🃏 បៀរប្រណិត (Premium Card Faces)</span>
                    <input type="checkbox" id="setting-premium-cards" checked style="width: 20px; height: 20px; cursor: pointer;">
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <button class="btn" id="btn-reset-chips" style="background: linear-gradient(180deg, #e74c3c 0%, #c0392b 100%); border-color: #ff6b6b; padding: 6px 12px;">💸 កំណត់លុយឡើងវិញ</button>
                </div>
            </div>
            <button class="btn" id="btn-close-settings" style="margin: 20px auto 0; width: 120px;">បិទ (Close)</button>
        </div>
    </div>

    <!-- Lobby Home Screen Overlay -->
    <div class="lobby-overlay" id="lobby-screen">
        
        <!-- 0. Player Profile Setup Panel (Glassmorphism Design) -->
        <div class="lobby-content glass-panel" id="lobby-profile-setup">
            <h2 class="lobby-title">👤 រៀបចំប្រវត្តិរូប (Set Up Profile)</h2>
            <p class="lobby-subtitle">សូមរៀបចំឈ្មោះ និងរូបតំណាងរបស់អ្នក</p>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="profile-nickname" style="display: block; text-align: left; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: #fff;">ឈ្មោះលេងរបស់អ្នក (Display Name)</label>
                <input type="text" id="profile-nickname" placeholder="វាយឈ្មោះនៅទីនេះ..." maxlength="20" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.4); color: white; font-family: inherit; font-size: 0.95rem; outline: none; box-sizing: border-box;">
            </div>
            
            <div class="avatar-gallery-container" style="margin-bottom: 20px;">
                <label style="display: block; text-align: left; margin-bottom: 8px; font-size: 0.85rem; font-weight: 600; color: #fff;">ជ្រើសរើសរូបតំណាង (Choose Avatar)</label>
                <div class="avatar-gallery" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div class="avatar-option-card active" data-filename="avatar_male.png">
                        <img src="public/assets/images/avatar_male.png" alt="Male">
                        <span>Male</span>
                    </div>
                    <div class="avatar-option-card" data-filename="avatar_female.png">
                        <img src="public/assets/images/avatar_female.png" alt="Female">
                        <span>Female</span>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-primary" id="btn-save-profile" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; border-radius: 12px; margin-top: 10px; box-sizing: border-box;">💾 រក្សាទុកប្រវត្តិរូប (Save Profile)</button>
        </div>

        <!-- 1. Lobby Mode Selector -->
        <div class="lobby-content hidden" id="lobby-mode-selector">
            <!-- Profile header (top-right of lobby selector) -->
            <div class="lobby-profile-header">
                <div class="profile-header-info">
                    <img src="public/assets/images/avatar_user.png" id="lobby-user-avatar" class="lobby-header-avatar" alt="Avatar">
                    <span id="lobby-user-name" class="lobby-header-name">អ្នកលេង</span>
                </div>
                <button class="btn btn-suggest btn-small" id="btn-edit-profile">✏️ កែប្រែ (Edit)</button>
            </div>

            <h1 class="lobby-title" style="margin-top: 10px;">♣️ ទៀនឡិន កាស៊ីណូ ♦️</h1>
            <p class="lobby-subtitle">សូមស្វាគមន៍មកកាន់ហ្គេមបៀរវៀតណាមបែបប្រណិត</p>
            
            <div class="lobby-rules-summary">
                <h3>📜 ច្បាប់លេងត្រួសៗ៖</h3>
                <ul>
                    <li>បៀរចែកម្នាក់ៗចំនួន ១៣ សន្លឹក។</li>
                    <li>បៀរតូចជាងគេគឺលេខ ៣ ហើយធំជាងគេគឺលេខ ២។</li>
                    <li>លំដាប់ទឹកបៀរ៖ ♠️ ប៊ិច &lt; ♣️ គូ &lt; ♦️ ការ៉ូ &lt; ♥️ បេះដូង។</li>
                </ul>
            </div>
            
            <div class="mode-cards-container">
                <button class="mode-card" id="btn-select-singleplayer">
                    <span class="mode-icon">🤖</span>
                    <span class="mode-name">លេងជាមួយ Bot</span>
                    <span class="mode-desc">លេងកំសាន្តម្នាក់ឯងជាមួយ offline AI</span>
                </button>
                <button class="mode-card" id="btn-select-multiplayer">
                    <span class="mode-icon">👥</span>
                    <span class="mode-name">លេងអនឡាញ</span>
                    <span class="mode-desc">បង្កើតបន្ទប់លេងជាមួយមិត្តភក្តិរបស់អ្នក</span>
                </button>
            </div>
        </div>

        <!-- 2. Online Multiplayer Connection Panel (Nickname removed since it is handled by profile) -->
        <div class="lobby-content hidden" id="lobby-online-setup">
            <h2 class="lobby-title">👥 រៀបចំការលេងអនឡាញ</h2>
            <p class="lobby-subtitle">សូមជ្រើសរើសបង្កើតបន្ទប់ ឬចូលរួមបន្ទប់មិត្តភក្តិ</p>
            
            <div class="online-actions" style="display: flex; gap: 20px; text-align: left; margin-top: 20px;">
                <div class="action-box" style="flex: 1; background: rgba(0,0,0,0.25); padding: 15px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; justify-content: space-between;">
                    <h3 style="font-size: 0.95rem; color: var(--gold); margin-bottom: 6px;">👑 បង្កើតបន្ទប់ថ្មី</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;">បង្កើតបន្ទប់លេងថ្មីមួយ រួចចែករំលែកលេខកូដបន្ទប់ទៅកាន់មិត្តភក្តិ</p>
                    <button class="btn btn-primary" id="btn-mp-create-room" style="width: 100%; justify-content: center; padding: 10px 16px;">👑 បង្កើតបន្ទប់</button>
                </div>

                <div class="action-box-divider" style="display: flex; align-items: center; color: var(--text-muted); font-size: 0.8rem; font-weight: 800; text-transform: uppercase;"><span>ឬ</span></div>

                <div class="action-box" style="flex: 1; background: rgba(0,0,0,0.25); padding: 15px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; justify-content: space-between;">
                    <h3 style="font-size: 0.95rem; color: var(--gold); margin-bottom: 6px;">🚪 ចូលរួមបន្ទប់លេង</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;">វាយបញ្ចូលលេខកូដបន្ទប់ (Room Code) របស់មិត្តភក្តិអ្នក</p>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="join-room-code" placeholder="កូដបន្ទប់" maxlength="4" style="width: 60%; padding: 8px 10px; border-radius: 10px; border: 1.5px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.4); color: white; text-transform: uppercase; text-align: center; font-weight: 700; outline: none;">
                        <button class="btn btn-suggest" id="btn-mp-join-room" style="flex: 1; justify-content: center; padding: 8px 12px;">ចូលរួម</button>
                    </div>
                </div>
            </div>

            <button class="btn" id="btn-back-to-modes" style="margin-top: 25px; padding: 6px 16px; font-size: 0.85rem; border-color: rgba(255,255,255,0.15);">← ត្រឡប់ក្រោយ</button>
        </div>

        <!-- 3. Online Waiting Room Lobby -->
        <div class="lobby-content hidden" id="lobby-waiting-room">
            <h2 class="lobby-title">⏳ បន្ទប់រង់ចាំ</h2>
            
            <div class="room-code-display-box" style="display: flex; align-items: center; justify-content: center; gap: 10px; background: rgba(0,0,0,0.4); padding: 12px 20px; border-radius: 16px; border: 1.5px solid var(--gold-highlight); margin: 15px auto 25px; width: fit-content;">
                <span class="code-label" style="font-size: 0.85rem; color: var(--text-muted);">លេខកូដបន្ទប់៖</span>
                <span class="code-value" id="display-room-code" style="font-size: 1.6rem; font-weight: 900; color: #fff; letter-spacing: 2px;">----</span>
                <button class="btn btn-suggest" id="btn-copy-room-link" style="padding: 6px 12px; font-size: 0.72rem; border-radius: 8px; border-color: rgba(255,255,255,0.15);">📋 ចម្លងតំណ</button>
            </div>

            <div class="players-seats-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0;">
                <div class="seat-box host" id="seat-0" style="background: rgba(255,255,255,0.03); border: 1.5px solid rgba(212,175,55,0.3); border-radius: 14px; padding: 12px; text-align: left; display: flex; flex-direction: column; gap: 6px;">
                    <span class="seat-status" style="font-size: 0.65rem; font-weight: 800; color: var(--gold); text-transform: uppercase;">👑 ម្ចាស់បន្ទប់ (កៅអី ១)</span>
                    <div class="seat-player-row" style="display: flex; align-items: center; gap: 8px;">
                        <img src="public/assets/images/avatar_user.png" id="seat-avatar-0" class="seat-avatar" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid rgba(212,175,55,0.4); display: none;" alt="">
                        <span class="seat-player-name" id="seat-player-0" style="font-size: 0.9rem; font-weight: 700; color: #fff;">រង់ចាំអ្នកលេង...</span>
                    </div>
                </div>
                <div class="seat-box" id="seat-1" style="background: rgba(255,255,255,0.03); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 12px; text-align: left; display: flex; flex-direction: column; gap: 6px;">
                    <span class="seat-status" style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">👤 កៅអី ២</span>
                    <div class="seat-player-row" style="display: flex; align-items: center; gap: 8px;">
                        <img src="public/assets/images/avatar_user.png" id="seat-avatar-1" class="seat-avatar" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2); display: none;" alt="">
                        <span class="seat-player-name" id="seat-player-1" style="font-size: 0.9rem; font-weight: 700; color: #fff;">រង់ចាំអ្នកលេង...</span>
                    </div>
                </div>
                <div class="seat-box" id="seat-2" style="background: rgba(255,255,255,0.03); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 12px; text-align: left; display: flex; flex-direction: column; gap: 6px;">
                    <span class="seat-status" style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">👤 កៅអី ៣</span>
                    <div class="seat-player-row" style="display: flex; align-items: center; gap: 8px;">
                        <img src="public/assets/images/avatar_user.png" id="seat-avatar-2" class="seat-avatar" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2); display: none;" alt="">
                        <span class="seat-player-name" id="seat-player-2" style="font-size: 0.9rem; font-weight: 700; color: #fff;">រង់ចាំអ្នកលេង...</span>
                    </div>
                </div>
                <div class="seat-box" id="seat-3" style="background: rgba(255,255,255,0.03); border: 1.5px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 12px; text-align: left; display: flex; flex-direction: column; gap: 6px;">
                    <span class="seat-status" style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">👤 កៅអី ៤</span>
                    <div class="seat-player-row" style="display: flex; align-items: center; gap: 8px;">
                        <img src="public/assets/images/avatar_user.png" id="seat-avatar-3" class="seat-avatar" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2); display: none;" alt="">
                        <span class="seat-player-name" id="seat-player-3" style="font-size: 0.9rem; font-weight: 700; color: #fff;">រង់ចាំអ្នកលេង...</span>
                    </div>
                </div>
            </div>

            <div class="lobby-settings-box" id="host-settings-only" style="display: none; margin-top: 20px; background: rgba(0,0,0,0.2); padding: 12px 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); text-align: left;">
                <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.8rem; color: #fff; font-weight: 500;">
                    <input type="checkbox" id="chk-add-bots" checked style="width: 18px; height: 18px; cursor: pointer;">
                    បន្ថែម Bot បំពេញកៅអីដែលទំនេរ (Fill empty seats with bots)
                </label>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px; width: 100%;">
                <button class="btn btn-lobby-start" id="btn-start-multiplayer-game" style="display: none; padding: 10px 30px; margin: 0;">✨ ចាប់ផ្តើមលេង ✨</button>
                <button class="btn" id="btn-leave-waiting-room" style="background: linear-gradient(180deg, #e74c3c 0%, #c0392b 100%); border-color: #ff6b6b; padding: 10px 24px; font-weight: 800; border-radius: 20px;">🚪 ចាកចេញ</button>
            </div>
        </div>
    </div>

    <!-- Script loading -->
    <script src="public/assets/js/app.js"></script>
</body>
</html>
