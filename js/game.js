// js/game.js
const canvas         = document.getElementById('gameCanvas');
const ctx            = canvas.getContext('2d');
const gameContainer  = document.getElementById('gameContainer');
const btnLeft        = document.getElementById('btnLeft');
const btnRight       = document.getElementById('btnRight');
const fullscreenBtn  = document.getElementById('fullscreenBtn');
const pauseBtn       = document.getElementById('pauseBtn');
const pausedOverlay  = document.getElementById('pausedOverlay');
const gameOverEl     = document.getElementById('gameOver');
const restartBtn     = document.getElementById('restartBtn');
const controls       = document.getElementById('controls');

const plateWidth  = 100, plateHeight = 20;
let plateX        = (canvas.width - plateWidth) / 2;
const plateY      = canvas.height - plateHeight - 10;
const plateSpeed  = 7;
const pizzaSize   = 40;
const pizzaSpeed  = 2;
const spawnDelay  = 1500;

let pizzas        = [];
let score         = 0;
let lives         = 3;
let rightActive   = false;
let leftActive    = false;
let gameRunning   = true;
let isPaused      = false;
let isFullscreen  = false;

let spawnInterval = null;
let animationId   = null;

// Клавиатура
document.addEventListener('keydown', e => {
  const key = e.key.toLowerCase();
  if (!isPaused) {
    if (['arrowright','d','в'].includes(e.code.toLowerCase())|| key==='d'|| key==='в') rightActive = true;
    if (['arrowleft','a','ф'].includes(e.code.toLowerCase()) || key==='a'|| key==='ф') leftActive = true;
  }
  if (key==='pause' || key==='p') togglePause();
});
document.addEventListener('keyup', e => {
  const key = e.key.toLowerCase();
  if (!isPaused) {
    if (['arrowright','d','в'].includes(e.code.toLowerCase())|| key==='d'|| key==='в') rightActive = false;
    if (['arrowleft','a','ф'].includes(e.code.toLowerCase()) || key==='a'|| key==='ф') leftActive = false;
  }
});

// Мышь/тач
let dragging = false;
canvas.addEventListener('pointerdown', e => { if (!isPaused) { dragging = true; movePlate(e); } });
canvas.addEventListener('pointermove', e => { if (dragging && !isPaused) movePlate(e); });
canvas.addEventListener('pointerup',   () => dragging = false);
canvas.addEventListener('pointerleave',()=> dragging = false);
function movePlate(e) {
  const rect = canvas.getBoundingClientRect();
  const x = e.clientX - rect.left - plateWidth/2;
  plateX = Math.max(0, Math.min(canvas.width - plateWidth, x));
}

// Кнопки управления
function bindBtn(el, dir) {
  el.addEventListener('mousedown',  () => { if (!isPaused) dir==='left'? leftActive=true:rightActive=true; });
  el.addEventListener('mouseup',    () => { if (!isPaused) dir==='left'? leftActive=false:rightActive=false; });
  el.addEventListener('touchstart', e => { if (!isPaused) dir==='left'? leftActive=true:rightActive=true; e.preventDefault(); });
  el.addEventListener('touchend',   e => { if (!isPaused) dir==='left'? leftActive=false:rightActive=false; e.preventDefault(); });
}
bindBtn(btnLeft, 'left'); bindBtn(btnRight,'right');

// Полноэкранный режим
function enterFullscreen() {
  gameContainer.classList.add('fullscreen'); controls.classList.add('fullscreen');
  gameContainer.appendChild(controls);
  fullscreenBtn.textContent='Выйти';
  isFullscreen=true;
  gameContainer.requestFullscreen?.() || gameContainer.webkitRequestFullscreen?.() || gameContainer.msRequestFullscreen?.();
}
function exitFullscreen() {
  gameContainer.classList.remove('fullscreen'); controls.classList.remove('fullscreen');
  document.body.appendChild(controls);
  fullscreenBtn.textContent='Полный экран';
  isFullscreen=false;
  document.exitFullscreen?.() || document.webkitExitFullscreen?.() || document.msExitFullscreen?.();
}
fullscreenBtn.addEventListener('click',()=> isFullscreen?exitFullscreen():enterFullscreen());
document.addEventListener('fullscreenchange',()=>{ if(!document.fullscreenElement) exitFullscreen(); });

// Пауза
pauseBtn.addEventListener('click', togglePause);
document.addEventListener('visibilitychange', ()=>{ if(document.hidden) pauseGame(); else unpauseGame(); });

function togglePause() {
  isPaused? unpauseGame() : pauseGame();
}

function pauseGame() {
  isPaused = true;
  pausedOverlay.classList.add('show');
  clearInterval(spawnInterval);
  cancelAnimationFrame(animationId);
}

function unpauseGame() {
  if (!gameRunning) return;
  isPaused = false;
  pausedOverlay.classList.remove('show');
  spawnInterval = setInterval(spawnPizza, spawnDelay);
  gameLoop();
}

// Спавн и игровой цикл
function spawnPizza() {
  if (!gameRunning || isPaused) return;
  const x = Math.random()*(canvas.width-pizzaSize);
  pizzas.push({x,y:-pizzaSize});
}

function drawPlate() {
  ctx.fillStyle='#f26822'; ctx.fillRect(plateX,plateY,plateWidth,plateHeight);
}
function drawPizzas() {
  ctx.fillStyle='#f5e1a4'; pizzas.forEach(p=>{ctx.beginPath();ctx.arc(p.x+pizzaSize/2,p.y+pizzaSize/2,pizzaSize/2,0,Math.PI*2);ctx.fill();ctx.closePath();});
}
function updatePlate() {
  if (rightActive && plateX<canvas.width-plateWidth) plateX+=plateSpeed;
  if (leftActive  && plateX>0)                       plateX-=plateSpeed;
}
function updatePizzas() {
  pizzas.forEach((p,idx)=> {
    p.y+=pizzaSpeed;
    if(p.y+pizzaSize>=plateY && p.x+pizzaSize>=plateX && p.x<=plateX+plateWidth) {
      pizzas.splice(idx,1); score++; document.getElementById('score').textContent=score;
    } else if(p.y>canvas.height) {
      pizzas.splice(idx,1); lives--; document.getElementById('lives').textContent=lives;
      if(lives<=0) {
        gameRunning=false; clearInterval(spawnInterval); cancelAnimationFrame(animationId);
        gameOverEl.classList.add('show');
      }
    }
  });
}

restartBtn.addEventListener('click',()=>{
  pizzas=[]; score=0; lives=3; gameRunning=true; plateX=(canvas.width-plateWidth)/2;
  document.getElementById('score').textContent=score; document.getElementById('lives').textContent=lives;
  gameOverEl.classList.remove('show');
  if(!isPaused) { clearInterval(spawnInterval); spawnInterval=setInterval(spawnPizza,spawnDelay); gameLoop(); }
});

function gameLoop(){
  if(!gameRunning||isPaused) return;
  ctx.clearRect(0,0,canvas.width,canvas.height);
  drawPlate(); drawPizzas(); updatePlate(); updatePizzas();
  animationId=requestAnimationFrame(gameLoop);
}

spawnInterval=setInterval(spawnPizza,spawnDelay);
gameLoop();
