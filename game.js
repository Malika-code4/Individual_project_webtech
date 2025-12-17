/**
 * Memory Card Game Logic
 * Game App Edition (Audio + Juice)
 */

const API_Base = 'api.php';
const Auth_Base = 'auth.php';

// Sound System (Web Audio API)
// Sound Effects & Music
const SFX = {
    ctx: new (window.AudioContext || window.webkitAudioContext)(),

    play(type) {
        if (this.ctx.state === 'suspended') this.ctx.resume();
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.connect(gain);
        gain.connect(this.ctx.destination);

        const now = this.ctx.currentTime;

        switch (type) {
            case 'flip':
                osc.type = 'sine';
                osc.frequency.setValueAtTime(300, now);
                osc.frequency.exponentialRampToValueAtTime(500, now + 0.1);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
                osc.start(now);
                osc.stop(now + 0.1);
                break;
            case 'match':
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(400, now);
                osc.frequency.setValueAtTime(600, now + 0.1);
                osc.frequency.setValueAtTime(1000, now + 0.2);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.linearRampToValueAtTime(0, now + 0.4);
                osc.start(now);
                osc.stop(now + 0.4);
                break;
            case 'win':
                osc.type = 'square';
                // Simple arpeggio
                [523.25, 659.25, 783.99, 1046.50].forEach((freq, i) => {
                    const o = this.ctx.createOscillator();
                    const g = this.ctx.createGain();
                    o.connect(g);
                    g.connect(this.ctx.destination);
                    o.frequency.value = freq;
                    g.gain.setValueAtTime(0.1, now + i * 0.1);
                    g.gain.exponentialRampToValueAtTime(0.01, now + i * 0.1 + 0.3);
                    o.start(now + i * 0.1);
                    o.stop(now + i * 0.1 + 0.3);
                });
                break;
        }
    },

    // Legacy support
    playFlip() { this.play('flip'); },
    playMatch() { this.play('match'); },
    playWin() { this.play('win'); },
    playMismatch() {
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.value = 150;
        osc.connect(gain);
        gain.connect(this.ctx.destination);
        gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.3);
        osc.start();
        osc.stop(this.ctx.currentTime + 0.3);
    }
};

const Music = {
    ctx: SFX.ctx,
    isPlaying: false,
    interval: null,

    start() {
        if (this.isPlaying) return;
        this.isPlaying = true;
        this.playLoop();
        this.interval = setInterval(() => this.playLoop(), 4000); // Loop every 4s
    },

    stop() {
        this.isPlaying = false;
        clearInterval(this.interval);
    },

    playLoop() {
        if (this.ctx.state === 'suspended') this.ctx.resume();
        const now = this.ctx.currentTime;

        // Ambient Chord (Cm9)
        const freqs = [261.63, 311.13, 392.00, 466.16];
        freqs.forEach((f, i) => {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = f;

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            // Soft envelope
            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.03, now + 1); // Fade in
            gain.gain.linearRampToValueAtTime(0, now + 4);    // Fade out

            osc.start(now);
            osc.stop(now + 4);
        });
    }
};

// Game State
const State = {
    player: null,
    gameId: null,
    gridSize: 16,
    difficulty: 'easy',
    mode: 'solo', // 'solo', 'cpu', 'local'
    cards: [],
    flipped: [],
    matchedBytes: [], // Use card IDs to track matched
    moves: 0,
    startTime: null,
    timer: null,
    processing: false,

    // VS Mode State
    turn: 0, // 0 = P1, 1 = P2/CPU
    scores: [0, 0],
    cpuMemory: {}, // { cardIndex: emoji }
    cpuDifficulty: 0.7 // Probability of remembering
};

const Emojis = [
    '⚡', '🔥', '💧', '🌿', '🔮', '🛡️', '⚔️', '🏹',
    '🐲', '🦄', '🦁', '🐺', '💀', '💎', '🩸', '🪐',
    '👑', '💍', '🧿', '🧩', '🚀', '🛸', '👾', '🤖'
];

// DOM Elements
const Elements = {
    board: document.getElementById('gameBoard'),
    panelSolo: document.getElementById('soloStats'),
    panelVS: document.getElementById('vsStats'),
    moves: document.getElementById('movesCount'),
    time: document.getElementById('timeCount'),
    pairs: document.getElementById('pairsCount'),
    p1ScoreBox: document.getElementById('p1ScoreBox'),
    p2ScoreBox: document.getElementById('p2ScoreBox'),
    p1Score: document.getElementById('p1Score'),
    p2Score: document.getElementById('p2Score'),
    p2Name: document.getElementById('p2Name'),
    playerName: document.getElementById('playerName'),
    avatar: document.getElementById('playerAvatar'),
    modal: document.getElementById('victoryModal'),
    modalOverlay: document.getElementById('modalOverlay')
};

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    // Resume audio context and start music on interaction
    const initAudio = () => {
        if (SFX.ctx.state === 'suspended') SFX.ctx.resume();
        Music.start();
        document.body.removeEventListener('click', initAudio);
        document.body.removeEventListener('touchstart', initAudio);
    };
    document.body.addEventListener('click', initAudio);
    document.body.addEventListener('touchstart', initAudio);

    // Check Auth
    const token = localStorage.getItem('session_token');
    if (!token) {
        window.location.href = 'landing.html';
        return;
    }

    const playerStore = localStorage.getItem('player');
    if (playerStore) {
        State.player = JSON.parse(playerStore);
        updatePlayerUI();
    } else {
        window.location.href = 'login.html';
    }

    // Listeners
    document.getElementById('newGameBtn').addEventListener('click', startNewGame);
    document.getElementById('playAgainBtn').addEventListener('click', resetToDashboard);
    document.getElementById('showLeaderboardBtn').addEventListener('click', () => {
        document.getElementById('leaderboardModal').classList.add('active');
        loadLeaderboard(State.gridSize);
    });
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);

    // Profile Click
    // Note: Onclick binding in HTML handles this now for robustness

    // Mode Selection
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            State.mode = e.target.dataset.mode;

            // Update UI
            if (State.mode === 'solo') {
                Elements.panelSolo.style.display = 'flex';
                Elements.panelVS.style.display = 'none';
            } else {
                Elements.panelSolo.style.display = 'none';
                Elements.panelVS.style.display = 'flex';
                Elements.p2Name.textContent = State.mode === 'cpu' ? 'CPU' : 'P2';
            }
        });
    });

    // Difficulty
    document.querySelectorAll('.difficulty-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.difficulty-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            State.gridSize = parseInt(e.target.dataset.grid);
            State.difficulty = e.target.textContent.split(' ')[0].toLowerCase();
        });
    });
});

async function startNewGame() {
    // Reset Common
    Music.start(); // Ensure music plays
    State.cards = [];
    State.flipped = [];
    State.matchedBytes = [];
    State.moves = 0;
    State.processing = false;
    stopTimer();

    // Reset VS Logic
    State.turn = 0; // P1 starts
    State.scores = [0, 0];
    State.cpuMemory = {};

    // UI Reset
    Elements.board.innerHTML = '';
    Elements.moves.textContent = '0';
    Elements.time.textContent = '0:00';
    Elements.pairs.textContent = '0';
    Elements.p1Score.textContent = '0';
    Elements.p2Score.textContent = '0';
    updateTurnUI();

    const colClass = State.gridSize === 16 ? 'grid-4x4' : State.gridSize === 24 ? 'grid-6x4' : 'grid-6x6';
    Elements.board.className = `grid ${colClass}`;

    // Reset main button
    const mainBtn = document.getElementById('newGameBtn');
    mainBtn.textContent = 'RESTART GAME';
    mainBtn.style.animation = 'none';

    // API Create Game (Only for Solo currently)
    if (State.mode === 'solo') {
        try {
            const res = await apiCall('create_game', 'POST', {
                player_id: State.player.id,
                grid_size: State.gridSize,
                difficulty_level: State.difficulty
            });
            if (res.success) State.gameId = res.game_id;
        } catch (e) { console.error(e); }
    }

    // Logic
    const pairs = State.gridSize / 2;
    const selected = Emojis.slice(0, pairs);
    const list = [...selected, ...selected].sort(() => Math.random() - 0.5);

    list.forEach((emoji, i) => {
        const card = createCard(emoji, i);
        Elements.board.appendChild(card);
        State.cards.push({ id: i, emoji, flipped: false, matched: false });
    });
}

function createCard(emoji, id) {
    const card = document.createElement('div');
    card.className = 'card';
    card.dataset.id = id;
    card.innerHTML = `
        <div class="card-face card-back"></div>
        <div class="card-face card-front">${emoji}</div>
    `;
    card.addEventListener('click', () => handleCardClick(id));
    return card;
}

function handleCardClick(id) {
    if (State.processing) return;
    // Block input during CPU turn
    if (State.mode === 'cpu' && State.turn === 1) return;

    flipCard(id);
}

function flipCard(id) {
    const card = State.cards[id];
    if (card.flipped || card.matched) return;

    if (State.mode === 'solo' && State.moves === 0 && !State.timer) startTimer();

    SFX.playFlip();

    const el = document.querySelector(`.card[data-id="${id}"]`);
    el.classList.add('flipped');
    card.flipped = true;
    State.flipped.push(card);

    // CPU Memory Registration
    if (State.mode === 'cpu') {
        State.cpuMemory[id] = card.emoji;
    }

    if (State.flipped.length === 2) {
        if (State.mode === 'solo') {
            State.moves++;
            Elements.moves.textContent = State.moves;
        }
        State.processing = true;

        // Slightly faster match check for smoother VS flow
        setTimeout(checkMatch, 600);
    }
}

function checkMatch() {
    const [c1, c2] = State.flipped;
    const el1 = document.querySelector(`.card[data-id="${c1.id}"]`);
    const el2 = document.querySelector(`.card[data-id="${c2.id}"]`);

    if (c1.emoji === c2.emoji) {
        // Match
        SFX.playMatch();
        c1.matched = true;
        c2.matched = true;
        el1.classList.add('matched');
        el2.classList.add('matched');

        State.matchedBytes.push(c1.id, c2.id); // Track matched
        // SFX played via global object

        // Remove from CPU memory (optimization)
        delete State.cpuMemory[c1.id];
        delete State.cpuMemory[c2.id];

        if (State.mode === 'solo') {
            Elements.pairs.textContent = State.matchedBytes.length / 2;
        } else {
            // VS Scoring
            State.scores[State.turn]++;
            updateVSScoreUI();
        }

        const totalPairs = State.gridSize / 2;
        if (State.matchedBytes.length / 2 === totalPairs) {
            handleWin();
        } else if (State.mode !== 'solo') {
            // In VS, matching keeps turn!
            // If CPU matched, it goes again
            if (State.mode === 'cpu' && State.turn === 1) {
                setTimeout(cpuMove, 1000);
            }
        }
    } else {
        // Mismatch
        SFX.playMismatch();
        el1.classList.add('shake');
        el2.classList.add('shake');
        setTimeout(() => {
            el1.classList.remove('flipped', 'shake');
            el2.classList.remove('flipped', 'shake');
            c1.flipped = false;
            c2.flipped = false;

            // Switch Turn
            if (State.mode !== 'solo') {
                State.turn = 1 - State.turn;
                updateTurnUI();

                if (State.mode === 'cpu' && State.turn === 1) {
                    setTimeout(cpuMove, 1000); // Trigger AI
                }
            }
        }, 500);
    }

    State.flipped = [];
    State.processing = false;
}

// CPU Logic
function cpuMove() {
    if (State.matchedBytes.length === State.gridSize) return;

    // 1. Check memory for a pair
    // Find if we know two locations of same emoji
    const memory = State.cpuMemory;
    const known = {};
    let targetPair = null;

    // Scan memory for duplicates
    for (const [id, emoji] of Object.entries(memory)) {
        if (State.cards[id].matched) continue;
        if (known[emoji]) {
            targetPair = [known[emoji], id];
            break;
        }
        known[emoji] = id;
    }

    // Difficulty Check: Only use "perfect memory" sometimes
    if (targetPair && Math.random() < State.cpuDifficulty) {
        flipCard(targetPair[0]);
        setTimeout(() => flipCard(targetPair[1]), 800);
        return;
    }

    // 2. Random Guess
    // Find unknown cards
    const available = State.cards.filter(c => !c.matched && !c.flipped).map(c => c.id);
    if (available.length === 0) return;

    const firstId = available[Math.floor(Math.random() * available.length)];
    flipCard(firstId);

    setTimeout(() => {
        // After first flip, check if we know the match in memory now
        const firstCard = State.cards[firstId];
        let secondId = null;

        // Do we know the pair?
        for (const [id, emoji] of Object.entries(memory)) {
            if (id != firstId && emoji === firstCard.emoji && !State.cards[id].matched) {
                if (Math.random() < State.cpuDifficulty) {
                    secondId = id;
                }
                break;
            }
        }

        if (!secondId) {
            // Pick random again
            const remaining = available.filter(id => id !== firstId);
            if (remaining.length > 0) {
                secondId = remaining[Math.floor(Math.random() * remaining.length)];
            }
        }

        if (secondId !== null) flipCard(secondId);
    }, 800);
}

function updateTurnUI() {
    if (State.mode === 'solo') return;

    if (State.turn === 0) {
        Elements.p1ScoreBox.classList.add('active-turn');
        Elements.p2ScoreBox.classList.remove('active-turn');
    } else {
        Elements.p1ScoreBox.classList.remove('active-turn');
        Elements.p2ScoreBox.classList.add('active-turn');
    }
}

function updateVSScoreUI() {
    Elements.p1Score.textContent = State.scores[0];
    Elements.p2Score.textContent = State.scores[1];
}

function handleWin() {
    stopTimer();
    Music.stop(); // Stop ambient music
    const elapsedTime = Math.floor((Date.now() - State.startTime) / 1000);
    SFX.playWin();
    fireConfetti();

    const modalTitle = document.querySelector('#victoryModal h2');
    const scoreLabel = document.querySelector('#victoryModal p');
    const finalScore = document.getElementById('finalScore');

    if (State.mode === 'solo') {
        modalTitle.textContent = 'VICTORY!';
        scoreLabel.textContent = 'Final Score';
        const score = Math.max(0, 1000 - State.moves * 5 - elapsedTime / 10).toFixed(0);
        finalScore.textContent = score;

        if (State.gameId) {
            apiCall('update_game', 'POST', {
                total_moves: State.moves,
                time_elapsed: elapsedTime,
                completed: true
            }, { id: State.gameId });
        }
    } else {
        // VS Mode Win
        const p1 = State.scores[0];
        const p2 = State.scores[1];

        scoreLabel.textContent = 'Final Score';
        finalScore.innerHTML = `<span style="color:var(--color-primary)">${p1}</span> - <span style="color:var(--color-secondary)">${p2}</span>`;

        if (p1 > p2) {
            modalTitle.textContent = 'YOU WIN! 🏆';
        } else if (p2 > p1) {
            modalTitle.textContent = State.mode === 'cpu' ? 'CPU WINS 🤖' : 'PLAYER 2 WINS 🏆';
        } else {
            modalTitle.textContent = 'DRAW! 🤝';
        }
    }

    Elements.modalOverlay.classList.add('active');

    // Update main button
    const mainBtn = document.getElementById('newGameBtn');
    mainBtn.textContent = 'PLAY NEW GAME';
    mainBtn.style.animation = 'pulse 1s infinite';
}

// Utilities
function startTimer() {
    State.startTime = Date.now();
    State.timer = setInterval(() => {
        const s = Math.floor((Date.now() - State.startTime) / 1000);
        const m = Math.floor(s / 60);
        Elements.time.textContent = `${m}:${(s % 60).toString().padStart(2, '0')}`;
    }, 1000);
}

function stopTimer() { clearInterval(State.timer); State.timer = null; }

function updatePlayerUI() {
    Elements.playerName.textContent = State.player.username;

    if (State.player.profile_picture) {
        // Use background image for full cover
        Elements.avatar.style.backgroundImage = `url(uploads/${State.player.profile_picture})`;
        Elements.avatar.style.backgroundSize = 'cover';
        Elements.avatar.style.backgroundPosition = 'center';
        Elements.avatar.textContent = '';
    } else {
        Elements.avatar.style.backgroundImage = 'none';
        Elements.avatar.textContent = State.player.username.substring(0, 2).toUpperCase();
    }
}

async function handleLogout() {
    await fetch(`${Auth_Base}?action=logout`);
    localStorage.removeItem('session_token');
    window.location.href = 'landing.html';
}

async function apiCall(action, method, data, params = {}) {
    const qs = new URLSearchParams({ action, ...params });

    const options = { method };
    // Handle FormData vs JSON
    if (data instanceof FormData) {
        options.body = data;
        // Content-Type header is auto-set for FormData
    } else {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = data ? JSON.stringify(data) : null;
    }

    const res = await fetch(`${API_Base}?${qs}`, options);
    return await res.json();
}

// Profile Logic
function openProfileModal() {
    console.log("Opening profile modal...");
    try {
        const modal = document.getElementById('profileModal');
        const usernameInput = document.getElementById('editUsername');
        const preview = document.getElementById('avatarPreview');

        if (!modal) {
            console.error("Modal not found!");
            return;
        }

        usernameInput.value = State.player.username;

        if (State.player.profile_picture) {
            preview.style.backgroundImage = `url(uploads/${State.player.profile_picture})`;
            preview.textContent = '';
        } else {
            preview.style.backgroundImage = 'none';
            preview.textContent = State.player.username.substring(0, 2).toUpperCase();
        }

        modal.classList.add('active');
    } catch (e) {
        console.error("Error opening profile modal:", e);
    }
}

function closeProfileModal() {
    document.getElementById('profileModal').classList.remove('active');
}

async function handleProfileUpdate(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('player_id', State.player.id);
    formData.append('username', document.getElementById('editUsername').value);

    const fileInput = document.getElementById('editAvatar');
    if (fileInput.files[0]) {
        formData.append('profile_picture', fileInput.files[0]);
    }

    try {
        const res = await apiCall('update_profile', 'POST', formData);
        if (res.success) {
            // Update Local State
            State.player = res.player;
            localStorage.setItem('player', JSON.stringify(State.player));
            updatePlayerUI();
            closeProfileModal();
            // Show success toast/alert if implemented
        } else {
            alert(res.message || 'Update failed');
        }
    } catch (err) {
        console.error(err);
        alert('Connection Failed');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

// File Preview Logic
document.getElementById('editAvatar').addEventListener('change', function (e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const preview = document.getElementById('avatarPreview');
            preview.style.backgroundImage = `url(${e.target.result})`;
            preview.textContent = '';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Confetti Effect (Simple Implementation)
function fireConfetti() {
    const colors = ['#00ff88', '#ff3366', '#7c3aed', '#ffcc00'];
    for (let i = 0; i < 100; i++) {
        const el = document.createElement('div');
        el.style.position = 'fixed';
        el.style.left = '50%';
        el.style.top = '50%';
        el.style.width = '10px';
        el.style.height = '10px';
        el.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        el.style.transform = `translate(-50%, -50%) rotate(${Math.random() * 360}deg)`;
        el.style.zIndex = '9999';
        document.body.appendChild(el);

        const angle = Math.random() * Math.PI * 2;
        const velocity = 5 + Math.random() * 10;
        const tx = Math.cos(angle) * velocity * 20;
        const ty = Math.sin(angle) * velocity * 20;

        el.animate([
            { transform: `translate(-50%, -50%)`, opacity: 1 },
            { transform: `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px))`, opacity: 0 }
        ], {
            duration: 1000,
            easing: 'ease-out'
        }).onfinish = () => el.remove();
    }
}

// Global functions for HTML access
window.openProfileModal = openProfileModal;
window.closeProfileModal = closeProfileModal;
window.handleProfileUpdate = handleProfileUpdate;
window.hideModal = hideModal;
window.hideLeaderboard = () => document.getElementById('leaderboardModal').classList.remove('active');
window.loadLeaderboard = async (gridSize) => {
    // Update active tab style
    const pills = document.querySelectorAll('#leaderboardModal .difficulty-btn');
    pills.forEach(p => {
        p.classList.toggle('active', parseInt(p.getAttribute('onclick').match(/\d+/)[0]) === gridSize);
    });

    const body = document.getElementById('leaderboardBody');
    body.innerHTML = '<tr><td colspan="4" style="padding:2rem; text-align:center">Loading...</td></tr>';

    try {
        const data = await apiCall('get_leaderboard', 'GET', null, { grid_size: gridSize });
        body.innerHTML = '';

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" style="padding:2rem; text-align:center; color:#aaa">No records yet</td></tr>';
            return;
        }

        data.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding:1rem; color:#aaa">#${i + 1}</td>
                <td style="padding:1rem; font-weight:600">${row.username}</td>
                <td style="padding:1rem; text-align:center; color:var(--color-primary)">${row.score}</td>
                <td style="padding:1rem; text-align:right; font-family:monospace">${row.time_elapsed}s</td>
            `;
            tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            body.appendChild(tr);
        });
    } catch (e) {
        body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--error)">Failed to load</td></tr>';
    }
};

function resetToDashboard() {
    hideModal();
    State.cards = [];
    Elements.board.innerHTML = '<div style="color:white; grid-column:1/-1; text-align:center; display:flex; flex-direction:column; align-items:center; gap:1rem"><div style="font-size:3rem">🎮</div><div>Ready for next round?</div></div>';
    Elements.board.className = `grid grid-4x4`; // Default logic, updated when they start

    // Reset Stats
    Elements.moves.textContent = '0';
    Elements.time.textContent = '0:00';
    Elements.pairs.textContent = '0';

    // Reset Main Button
    const mainBtn = document.getElementById('newGameBtn');
    mainBtn.textContent = 'START GAME';
    mainBtn.style.animation = 'none';
}

function hideModal() {
    Elements.modalOverlay.classList.remove('active');
}
