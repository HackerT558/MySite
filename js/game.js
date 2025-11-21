// js/game.js - ОБНОВЛЕННАЯ ВЕРСИЯ: время выживания вместо уровня
class PizzaGame {
    constructor() {
        const canvas = document.getElementById('gameCanvas');
        if (!canvas) {
            console.error('❌ Canvas не найден!');
            return;
        }
        this.canvas = canvas;
        this.ctx = this.canvas.getContext('2d');
        this.gameState = 'start';
        this.score = 0;
        this.lives = 3;
        this.survivalTime = 0; // Время в секундах
        this.lastTime = 0;
        this.endReason = null;
        this.floatingTexts = [];
        this.gameStartTime = 0;
        this.leaderboard = [];
        this.lostLives = [];
        
        this.platform = {
            x: this.canvas.width / 2 - 75,
            y: this.canvas.height - 80,
            width: 150,
            height: 30,
            speed: 8,
            image: null,
            imageLoaded: false
        };
        
        this.loadPlatformImage();
        this.loadLeaderboard();
        
        this.fallingObjects = [];
        this.objectTypes = ['pizza', 'bomb', 'heart'];
        this.spawnTimer = 0;
        this.spawnInterval = 1000;
        this.baseDifficulty = 1000;
        
        this.keys = {};
        this.mouseX = this.canvas.width / 2;
        this.useMouseControl = false;
        this.soundEnabled = true;
        
        this.init();
    }
    
    loadPlatformImage() {
        console.log('📦 Загрузка изображения коробки...');
        this.platform.image = new Image();
        this.platform.image.onload = () => {
            this.platform.imageLoaded = true;
            console.log('✅ Изображение коробки успешно загружено!');
        };
        this.platform.image.onerror = (error) => {
            this.platform.imageLoaded = false;
            console.error('❌ ОШИБКА загрузки изображения коробки!');
        };
        this.platform.image.src = '../uploads/pizza-box.png';
    }
    
    async loadLeaderboard() {
        try {
            const response = await fetch('../api/get_leaderboard.php?limit=10');
            const result = await response.json();
            
            if (result.success) {
                this.leaderboard = result.data;
                console.log('📊 Таблица лидеров загружена из БД:', this.leaderboard);
            } else {
                console.error('❌ Ошибка загрузки лидерборда:', result.error);
                this.leaderboard = [];
            }
        } catch (error) {
            console.error('❌ Ошибка загрузки лидерборда:', error);
            this.leaderboard = [];
        }
    }
    
    async saveScoreToDB(score, survivalTime) {
        try {
            const response = await fetch('../api/save_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    score: score,
                    survival_time: survivalTime
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Результат сохранен в БД');
                await this.loadLeaderboard();
                return true;
            } else {
                console.error('❌ Ошибка сохранения в БД:', result.error);
                return false;
            }
        } catch (error) {
            console.error('❌ Ошибка сохранения в БД:', error);
            return false;
        }
    }
    
    async addToLeaderboard(score, survivalTime) {
        const saved = await this.saveScoreToDB(score, survivalTime);
        
        if (!saved) {
            console.warn('⚠️ Используем локальное хранилище как запасной вариант');
            this.addToLocalLeaderboard(score, survivalTime);
        }
    }
    
    addToLocalLeaderboard(score, survivalTime) {
        const saved = localStorage.getItem('pizzaGameLeaderboard');
        let leaderboard = saved ? JSON.parse(saved) : [];
        
        const username = sessionStorage.getItem('username') || 'Игрок';
        
        // Форматируем время для локального хранения
        const minutes = Math.floor(survivalTime / 60);
        const seconds = survivalTime % 60;
        const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Проверяем существующий рекорд пользователя
        const existingIndex = leaderboard.findIndex(entry => entry.username === username);
        
        if (existingIndex !== -1) {
            if (score > leaderboard[existingIndex].score) {
                leaderboard[existingIndex].score = score;
                leaderboard[existingIndex].survival_time = formattedTime;
                leaderboard[existingIndex].date = new Date().toLocaleString();
            }
        } else {
            leaderboard.push({
                username,
                score,
                survival_time: formattedTime,
                date: new Date().toLocaleString()
            });
        }
        
        leaderboard.sort((a, b) => b.score - a.score);
        
        // Убираем дубликаты по username
        const uniqueLeaderboard = [];
        const usernamesSeen = new Set();
        
        for (const entry of leaderboard) {
            if (!usernamesSeen.has(entry.username)) {
                usernamesSeen.add(entry.username);
                uniqueLeaderboard.push(entry);
            }
            if (uniqueLeaderboard.length >= 10) break;
        }
        
        localStorage.setItem('pizzaGameLeaderboard', JSON.stringify(uniqueLeaderboard));
        this.leaderboard = uniqueLeaderboard;
    }
    
    init() {
        this.startBtn = document.getElementById('startButton');
        this.restartBtn = document.getElementById('restartButton');
        this.pauseBtn = document.getElementById('pauseButton');
        this.resumeBtn = document.getElementById('resumeButton');
        
        if (this.startBtn) this.startBtn.addEventListener('click', () => this.startGame());
        if (this.restartBtn) this.restartBtn.addEventListener('click', () => this.restartGame());
        if (this.pauseBtn) this.pauseBtn.addEventListener('click', () => this.togglePause());
        if (this.resumeBtn) this.resumeBtn.addEventListener('click', () => this.togglePause());
        
        document.addEventListener('keydown', (e) => {
            this.keys[e.key] = true;
            if (e.key === ' ' && this.gameState === 'playing') {
                e.preventDefault();
                this.togglePause();
            } else if (e.key === ' ' && this.gameState === 'paused') {
                e.preventDefault();
                this.togglePause();
            }
        });
        
        document.addEventListener('keyup', (e) => {
            this.keys[e.key] = false;
        });
        
        this.canvas.addEventListener('mousemove', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            this.mouseX = e.clientX - rect.left;
            this.useMouseControl = true;
        });
        
        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const rect = this.canvas.getBoundingClientRect();
            this.mouseX = e.touches[0].clientX - rect.left;
            this.useMouseControl = true;
        });
        
        this.render();
    }
    
    startGame() {
        this.gameState = 'playing';
        this.score = 0;
        this.lives = 3;
        this.survivalTime = 0;
        this.gameStartTime = performance.now();
        this.fallingObjects = [];
        this.floatingTexts = [];
        this.lostLives = [];
        this.endReason = null;
        
        const startScreen = document.getElementById('startScreen');
        if (startScreen) startScreen.classList.add('hidden');
        
        this.updateUI();
        this.lastTime = performance.now();
        this.gameLoop(this.lastTime);
    }
    
    restartGame() {
        const gameOverScreen = document.getElementById('gameOverScreen');
        if (gameOverScreen) gameOverScreen.classList.add('hidden');
        this.startGame();
    }
    
    togglePause() {
        if (this.gameState === 'playing') {
            this.gameState = 'paused';
            const pauseScreen = document.getElementById('pauseScreen');
            if (pauseScreen) pauseScreen.classList.remove('hidden');
        } else if (this.gameState === 'paused') {
            this.gameState = 'playing';
            const pauseScreen = document.getElementById('pauseScreen');
            if (pauseScreen) pauseScreen.classList.add('hidden');
            this.lastTime = performance.now();
            this.gameLoop(this.lastTime);
        }
    }
    
    gameLoop(currentTime) {
        if (this.gameState !== 'playing') return;
        
        const deltaTime = currentTime - this.lastTime;
        this.lastTime = currentTime;
        
        this.updateTimer(currentTime);
        this.update(deltaTime);
        this.render();
        
        requestAnimationFrame((time) => this.gameLoop(time));
    }
    
    updateTimer(currentTime) {
        const elapsedTime = currentTime - this.gameStartTime;
        this.survivalTime = Math.floor(elapsedTime / 1000); // Конвертируем в секунды
        this.updateUI();
    }
    
    update(deltaTime) {
        this.updatePlatform();
        this.spawnObjects(deltaTime);
        this.updateFallingObjects(deltaTime);
        this.checkCollisions();
    }
    
    updatePlatform() {
        if (this.useMouseControl) {
            this.platform.x = this.mouseX - this.platform.width / 2;
        } else {
            if (this.keys['ArrowLeft'] || this.keys['a']) {
                this.platform.x -= this.platform.speed;
            }
            if (this.keys['ArrowRight'] || this.keys['d']) {
                this.platform.x += this.platform.speed;
            }
        }
        
        if (this.platform.x < 0) this.platform.x = 0;
        if (this.platform.x + this.platform.width > this.canvas.width) {
            this.platform.x = this.canvas.width - this.platform.width;
        }
    }
    
    spawnObjects(deltaTime) {
        // Используем время для увеличения сложности
        const difficultyLevel = Math.floor(this.survivalTime / 60) + 1;
        const currentSpawnInterval = Math.max(400, this.baseDifficulty - (difficultyLevel - 1) * 50);
        
        this.spawnTimer += deltaTime;
        if (this.spawnTimer >= currentSpawnInterval) {
            this.spawnTimer = 0;
            
            const rand = Math.random();
            let type = 'pizza';
            
            if (rand < 0.05) {
                type = 'heart';
            } else if (rand < 0.3) {
                type = 'bomb';
            } else {
                type = 'pizza';
            }
            
            const minX = 0;
            const maxX = this.canvas.width - 40;
            const obj = {
                x: Math.random() * (maxX - minX) + minX,
                y: -40,
                width: 40,
                height: 40,
                speed: 2 + Math.random() * 2 + (difficultyLevel - 1) * 0.3,
                type: type,
                rotation: Math.random() * Math.PI * 2
            };
            
            if (obj.x < 0) obj.x = 0;
            if (obj.x + obj.width > this.canvas.width) obj.x = this.canvas.width - obj.width;
            
            this.fallingObjects.push(obj);
        }
    }
    
    updateFallingObjects(deltaTime) {
        for (let i = this.fallingObjects.length - 1; i >= 0; i--) {
            const obj = this.fallingObjects[i];
            obj.y += obj.speed;
            obj.rotation += 0.05;
            
            if (obj.y > this.canvas.height) {
                this.fallingObjects.splice(i, 1);
                if (obj.type === 'pizza') {
                    this.loseLife();
                }
            }
        }
    }
    
    checkCollisions() {
        for (let i = this.fallingObjects.length - 1; i >= 0; i--) {
            const obj = this.fallingObjects[i];
            
            if (this.isColliding(obj, this.platform)) {
                this.fallingObjects.splice(i, 1);
                
                if (obj.type === 'pizza') {
                    this.score += 10;
                    this.showFloatingText('+10', obj.x, obj.y, '#32b8c6');
                    this.playSound('catch');
                } else if (obj.type === 'bomb') {
                    this.loseLife();
                    this.showFloatingText('-1 ❤️', obj.x, obj.y, '#ff5459');
                    this.playSound('hit');
                } else if (obj.type === 'heart') {
                    this.gainLife();
                    this.showFloatingText('+1 ❤️', obj.x, obj.y, '#ff5459');
                    this.playSound('catch');
                }
                
                this.updateUI();
            }
        }
    }
    
    isColliding(obj1, obj2) {
        return obj1.x < obj2.x + obj2.width &&
               obj1.x + obj1.width > obj2.x &&
               obj1.y < obj2.y + obj2.height &&
               obj1.y + obj1.height > obj2.y;
    }
    
    loseLife() {
        this.lostLives.push({
            startTime: Date.now(),
            duration: 500
        });
        
        this.lives--;
        if (this.lives <= 0) {
            this.lives = 0;
            this.gameState = 'gameover';
            this.endReason = 'life';
            this.gameOver();
        }
        this.updateUI();
    }
    
    gainLife() {
        if (this.lives < 3) {
            this.lives++;
            this.updateUI();
        }
    }
    
    gameOver() {
        this.gameState = 'gameover';
        const gameOverScreen = document.getElementById('gameOverScreen');
        const gameOverTitle = document.getElementById('gameOverTitle');
        const finalScore = document.getElementById('finalScore');
        const leaderboardTable = document.getElementById('leaderboardTable');
        
        if (gameOverScreen && gameOverTitle && finalScore) {
            gameOverTitle.textContent = 'Игра окончена!';
            finalScore.textContent = `Ваш счет: ${this.score}`;
            
            this.addToLeaderboard(this.score, this.survivalTime).then(() => {
                if (leaderboardTable) {
                    leaderboardTable.innerHTML = this.renderLeaderboard();
                }
            });
            
            gameOverScreen.classList.remove('hidden');
        }
    }
    
    renderLeaderboard() {
        let html = '<table class="leaderboard-table">';
        html += '<thead><tr><th>#</th><th>Игрок</th><th>Счет</th><th>Время</th><th>Дата</th></tr></thead>';
        html += '<tbody>';
        
        if (this.leaderboard.length === 0) {
            html += '<tr><td colspan="5" style="text-align: center; padding: 20px;">Пока нет результатов</td></tr>';
        } else {
            this.leaderboard.forEach((entry, index) => {
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `${index + 1}`;
                html += `<tr>
                    <td>${medal}</td>
                    <td class="leaderboard-name">${this.escapeHtml(entry.username)}</td>
                    <td>${entry.score}</td>
                    <td>${entry.survival_time}</td>
                    <td class="leaderboard-date">${entry.date}</td>
                </tr>`;
            });
        }
        
        html += '</tbody></table>';
        return html;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showFloatingText(text, x, y, color) {
        this.floatingTexts.push({
            text: text,
            x: x,
            y: y,
            color: color,
            life: 1000,
            startTime: Date.now()
        });
    }
    
    playSound(type) {
        if (!this.soundEnabled) return;
        
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const now = audioContext.currentTime;
        
        if (type === 'catch') {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            
            osc.connect(gain);
            gain.connect(audioContext.destination);
            
            osc.frequency.setValueAtTime(800, now);
            osc.frequency.exponentialRampToValueAtTime(1200, now + 0.1);
            
            gain.gain.setValueAtTime(0.1, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
            
            osc.start(now);
            osc.stop(now + 0.1);
        } else if (type === 'hit') {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            
            osc.connect(gain);
            gain.connect(audioContext.destination);
            
            osc.frequency.setValueAtTime(200, now);
            osc.frequency.exponentialRampToValueAtTime(100, now + 0.2);
            
            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
            
            osc.start(now);
            osc.stop(now + 0.2);
        }
    }
    
    updateUI() {
        const scoreDisplay = document.getElementById('scoreDisplay');
        const timerDisplay = document.getElementById('timerDisplay');
        const livesDisplay = document.getElementById('livesDisplay');
        
        if (scoreDisplay) {
            scoreDisplay.innerHTML = `Счет: <span>${this.score}</span>`;
        }
        if (timerDisplay) {
            // Форматируем время в минуты:секунды
            const minutes = Math.floor(this.survivalTime / 60);
            const seconds = this.survivalTime % 60;
            const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            timerDisplay.innerHTML = `Время: <span>${formattedTime}</span>`;
        }
        if (livesDisplay) {
            let heartsDisplay = '❤️'.repeat(this.lives);
            const lostCount = 3 - this.lives;
            if (lostCount > 0) {
                heartsDisplay += '🖤'.repeat(lostCount);
            }
            livesDisplay.innerHTML = `Жизни: <span>${heartsDisplay}</span>`;
        }
    }
    
    render() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Рисуем падающие объекты
        this.fallingObjects.forEach(obj => {
            this.ctx.save();
            this.ctx.translate(obj.x + obj.width / 2, obj.y + obj.height / 2);
            this.ctx.rotate(obj.rotation);
            
            this.ctx.font = '30px Arial';
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            
            if (obj.type === 'pizza') {
                this.ctx.fillText('🍕', 0, 0);
            } else if (obj.type === 'bomb') {
                this.ctx.fillText('💣', 0, 0);
            } else if (obj.type === 'heart') {
                this.ctx.fillText('❤️', 0, 0);
            }
            
            this.ctx.restore();
        });
        
        // Рисуем платформу (коробку)
        if (this.platform.imageLoaded) {
            this.ctx.drawImage(this.platform.image, this.platform.x, this.platform.y, this.platform.width, this.platform.height);
        } else {
            this.ctx.fillStyle = '#D2691E';
            this.ctx.fillRect(this.platform.x, this.platform.y, this.platform.width, this.platform.height);
            this.ctx.strokeStyle = '#8B4513';
            this.ctx.lineWidth = 2;
            this.ctx.strokeRect(this.platform.x, this.platform.y, this.platform.width, this.platform.height);
        }
        
        // Плавающий текст
        this.floatingTexts = this.floatingTexts.filter(text => {
            const elapsed = Date.now() - text.startTime;
            if (elapsed > text.life) return false;
            
            const alpha = 1 - (elapsed / text.life);
            this.ctx.fillStyle = text.color;
            this.ctx.globalAlpha = alpha;
            this.ctx.font = 'bold 20px Arial';
            this.ctx.textAlign = 'center';
            this.ctx.fillText(text.text, text.x, text.y - (elapsed / 100));
            this.ctx.globalAlpha = 1;
            
            return true;
        });
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    const game = new PizzaGame();
});