// js/game.js - ФИНАЛЬНАЯ ВЕРСИЯ: пиццы поверх коробки, таблица лидеров, без таймера

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
        this.timer = 60;
        this.lastTime = 0;
        this.endReason = null;
        this.floatingTexts = [];
        this.gameDuration = 60000; // 60 секунд в миллисекундах
        this.gameStartTime = 0;
        this.leaderboard = [];
        
        // Хитбокс платформы - нормальный размер для коллизий
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
        this.objectTypes = ['pizza', 'bomb'];
        this.spawnTimer = 0;
        this.spawnInterval = 1000;
        this.keys = {};
        this.mouseX = this.canvas.width / 2;
        this.useMouseControl = false;
        this.soundEnabled = true;
        this.pizzaEmoji = '🍕';
        this.bombEmoji = '💣';
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
    
    loadLeaderboard() {
        // Загружаем таблицу лидеров из localStorage
        const saved = localStorage.getItem('pizzaGameLeaderboard');
        if (saved) {
            try {
                this.leaderboard = JSON.parse(saved);
                console.log('📊 Таблица лидеров загружена:', this.leaderboard);
            } catch (e) {
                this.leaderboard = [];
            }
        }
    }
    
    saveLeaderboard() {
        localStorage.setItem('pizzaGameLeaderboard', JSON.stringify(this.leaderboard));
    }
    
    addToLeaderboard(username, score) {
        this.leaderboard.push({ username, score, date: new Date().toLocaleString() });
        // Сортируем по убыванию и берем топ 10
        this.leaderboard.sort((a, b) => b.score - a.score);
        this.leaderboard = this.leaderboard.slice(0, 10);
        this.saveLeaderboard();
    }
    
    init() {
        this.startBtn = document.getElementById('startButton');
        this.restartBtn = document.getElementById('restartButton');
        this.pauseBtn = document.getElementById('pauseButton');
        this.resumeBtn = document.getElementById('resumeButton');
        this.muteBtn = document.getElementById('muteButton');
        
        if (this.startBtn) this.startBtn.addEventListener('click', () => this.startGame());
        if (this.restartBtn) this.restartBtn.addEventListener('click', () => this.restartGame());
        if (this.pauseBtn) this.pauseBtn.addEventListener('click', () => this.togglePause());
        if (this.resumeBtn) this.resumeBtn.addEventListener('click', () => this.togglePause());
        if (this.muteBtn) this.muteBtn.addEventListener('click', () => this.toggleSound());
        
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
        this.gameStartTime = performance.now();
        this.fallingObjects = [];
        this.floatingTexts = [];
        this.endReason = null;
        
        const startScreen = document.getElementById('startScreen');
        if (startScreen) startScreen.style.display = 'none';
        
        this.updateUI();
        this.lastTime = performance.now();
        this.gameLoop(this.lastTime);
    }
    
    restartGame() {
        const gameOverScreen = document.getElementById('gameOverScreen');
        if (gameOverScreen) gameOverScreen.style.display = 'none';
        this.startGame();
    }
    
    togglePause() {
        if (this.gameState === 'playing') {
            this.gameState = 'paused';
            const pauseScreen = document.getElementById('pauseScreen');
            if (pauseScreen) pauseScreen.style.display = 'flex';
        } else if (this.gameState === 'paused') {
            this.gameState = 'playing';
            const pauseScreen = document.getElementById('pauseScreen');
            if (pauseScreen) pauseScreen.style.display = 'none';
            this.lastTime = performance.now();
            this.gameLoop(this.lastTime);
        }
    }
    
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        if (this.muteBtn) this.muteBtn.textContent = this.soundEnabled ? '🔊 Звук' : '🔇 Без звука';
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
        
        // Проверяем, закончилось ли время (60 секунд)
        if (elapsedTime >= this.gameDuration) {
            this.gameState = 'gameover';
            this.endReason = 'timeout';
            this.gameOver();
        }
        
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
            if (this.keys['ArrowLeft'] || this.keys['a']) this.platform.x -= this.platform.speed;
            if (this.keys['ArrowRight'] || this.keys['d']) this.platform.x += this.platform.speed;
        }
        
        if (this.platform.x < 0) this.platform.x = 0;
        if (this.platform.x + this.platform.width > this.canvas.width)
            this.platform.x = this.canvas.width - this.platform.width;
    }
    
    spawnObjects(deltaTime) {
        this.spawnTimer += deltaTime;
        
        if (this.spawnTimer >= this.spawnInterval) {
            this.spawnTimer = 0;
            
            const type = Math.random() < 0.8 ? 'pizza' : 'bomb';
            const minX = 0;
            const maxX = this.canvas.width - 40;
            
            const obj = {
                x: Math.random() * (maxX - minX) + minX,
                y: -40,
                width: 40,
                height: 40,
                speed: 2 + Math.random() * 2,
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
        this.lives--;
        
        if (this.lives <= 0) {
            this.lives = 0;
            this.gameState = 'gameover';
            this.endReason = 'life';
            this.gameOver();
        }
        
        this.updateUI();
    }
    
    gameOver() {
        this.gameState = 'gameover';
        
        const gameOverScreen = document.getElementById('gameOverScreen');
        const gameOverTitle = document.getElementById('gameOverTitle');
        const finalScore = document.getElementById('finalScore');
        const leaderboardTable = document.getElementById('leaderboardTable');
        
        if (gameOverScreen && gameOverTitle && finalScore) {
            if (this.endReason === 'timeout') {
                gameOverTitle.textContent = 'Время вышло!';
            } else if (this.endReason === 'life') {
                gameOverTitle.textContent = 'Игра окончена!';
            }
            
            finalScore.textContent = `Ваш счет: ${this.score}`;
            
            // Добавляем счет в таблицу лидеров
            const username = sessionStorage.getItem('username') || 'Игрок';
            this.addToLeaderboard(username, this.score);
            
            // Отрисовываем таблицу лидеров
            if (leaderboardTable) {
                leaderboardTable.innerHTML = this.renderLeaderboard();
            }
            
            gameOverScreen.style.display = 'flex';
        }
    }
    
    renderLeaderboard() {
        let html = '<h3>🏆 Таблица лидеров</h3><table class="leaderboard"><thead><tr><th>#</th><th>Игрок</th><th>Счет</th><th>Дата</th></tr></thead><tbody>';
        
        this.leaderboard.forEach((entry, index) => {
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : (index + 1);
            html += `<tr><td>${medal}</td><td>${entry.username}</td><td><strong>${entry.score}</strong></td><td>${entry.date}</td></tr>`;
        });
        
        html += '</tbody></table>';
        return html;
    }
    
    showFloatingText(text, x, y, color) {
        this.floatingTexts.push({
            text: text,
            x: x,
            y: y,
            color: color,
            alpha: 1,
            life: 60
        });
    }
    
    playSound(type) {
        if (!this.soundEnabled) return;
    }
    
    updateUI() {
        const scoreEl = document.getElementById('score');
        const timerEl = document.getElementById('timer');
        const livesEl = document.getElementById('lives');
        
        if (scoreEl) scoreEl.textContent = this.score;
        
        // Убираем отсчет времени, показываем только "60с"
        if (timerEl) timerEl.textContent = '60с';
        
        if (livesEl) {
            const hearts = '❤️'.repeat(this.lives);
            const emptyHearts = '🖤'.repeat(3 - this.lives);
            livesEl.textContent = hearts + emptyHearts;
        }
    }
    
    render() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.drawBackground();
        this.drawPlatform();          // СНАЧАЛА рисуем коробку
        this.drawFallingObjects();    // ПОТОМ рисуем пиццы ПОВЕРХ коробки
        this.drawFloatingTexts();
    }
    
    drawBackground() {
        const gradient = this.ctx.createLinearGradient(0, 0, 0, this.canvas.height);
        gradient.addColorStop(0, '#fcfcf9');
        gradient.addColorStop(1, '#e8f5f7');
        this.ctx.fillStyle = gradient;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        this.ctx.strokeStyle = 'rgba(33, 128, 141, 0.1)';
        this.ctx.lineWidth = 1;
        for (let i = 0; i < this.canvas.height; i += 50) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, i);
            this.ctx.lineTo(this.canvas.width, i);
            this.ctx.stroke();
        }
    }
    
    drawPlatform() {
        this.ctx.save();
        
        if (this.platform.imageLoaded && this.platform.image) {
            const displayWidth = this.platform.width * 1.4;
            const displayHeight = this.platform.height * 4;
            
            const displayX = this.platform.x + (this.platform.width - displayWidth) / 2;
            const displayY = this.platform.y - (displayHeight - this.platform.height) / 2;
            
            this.ctx.imageSmoothingEnabled = true;
            this.ctx.imageSmoothingQuality = 'high';
            
            this.ctx.drawImage(
                this.platform.image,
                displayX,
                displayY,
                displayWidth,
                displayHeight
            );
        } else {
            this.ctx.fillStyle = '#e67961';
            this.ctx.fillRect(
                this.platform.x,
                this.platform.y,
                this.platform.width,
                this.platform.height
            );
            
            this.ctx.fillStyle = '#fff';
            this.ctx.font = '14px Arial';
            this.ctx.textAlign = 'center';
            this.ctx.fillText('📦', this.platform.x + this.platform.width / 2, this.platform.y + 20);
        }
        
        this.ctx.restore();
    }
    
    drawFallingObjects() {
        // КЛЮЧЕВОЕ ИЗМЕНЕНИЕ: ПИЦЦЫ РИСУЮТСЯ ПОВЕРХ КОРОБКИ
        this.fallingObjects.forEach(obj => {
            this.ctx.save();
            
            this.ctx.translate(obj.x + obj.width / 2, obj.y + obj.height / 2);
            this.ctx.rotate(obj.rotation);
            
            this.ctx.font = '36px Arial';
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            
            if (obj.type === 'pizza') {
                this.ctx.fillText(this.pizzaEmoji, 0, 0);
            } else if (obj.type === 'bomb') {
                this.ctx.fillText(this.bombEmoji, 0, 0);
            }
            
            this.ctx.restore();
        });
    }
    
    drawFloatingTexts() {
        if (!this.floatingTexts || this.floatingTexts.length === 0) return;
        
        for (let i = this.floatingTexts.length - 1; i >= 0; i--) {
            const text = this.floatingTexts[i];
            
            this.ctx.save();
            this.ctx.globalAlpha = text.alpha;
            this.ctx.fillStyle = text.color;
            this.ctx.font = 'bold 20px Arial';
            this.ctx.textAlign = 'center';
            this.ctx.fillText(text.text, text.x, text.y);
            this.ctx.restore();
            
            text.y -= 1;
            text.alpha -= 0.02;
            text.life--;
            
            if (text.life <= 0) {
                this.floatingTexts.splice(i, 1);
            }
        }
    }
}

// Инициализация игры
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎮 Игра инициализируется...');
    new PizzaGame();
    console.log('✅ Игра инициализирована');
});