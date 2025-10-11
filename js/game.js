// js/game.js

const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const btnLeft  = document.getElementById('btnLeft');
const btnRight = document.getElementById('btnRight');

const plateWidth  = 100;
const plateHeight = 20;
let plateX        = (canvas.width - plateWidth) / 2;
const plateY      = canvas.height - plateHeight - 10;
const plateSpeed  = 7;

let pizzas    = [];
const pizzaSize  = 40;
const pizzaSpeed = 2;

let score = 0;
let lives = 3;
let rightActive = false;
let leftActive  = false;
let gameRunning = true;
let spawnInterval = null;
let animationId = null;

// Клавиатура: стрелки, A/D, Ф/В
document.addEventListener('keydown', e => {
    const key = e.key.toLowerCase();
    if (e.code === 'ArrowRight' || key === 'd' || key === 'в') rightActive = true;
    if (e.code === 'ArrowLeft'  || key === 'a' || key === 'ф') leftActive  = true;
});
document.addEventListener('keyup', e => {
    const key = e.key.toLowerCase();
    if (e.code === 'ArrowRight' || key === 'd' || key === 'в') rightActive = false;
    if (e.code === 'ArrowLeft'  || key === 'a' || key === 'ф') leftActive  = false;
});

// Кнопки мыши/тач
const bindBtn = (el, dir) => {
    el.addEventListener('touchstart', e => { if(dir==='left') leftActive=true; else rightActive=true; e.preventDefault(); });
    el.addEventListener('touchend',   e => { if(dir==='left') leftActive=false; else rightActive=false; e.preventDefault(); });
    el.addEventListener('mousedown',  () => dir==='left' ? leftActive=true : rightActive=true);
    el.addEventListener('mouseup',    () => dir==='left' ? leftActive=false: rightActive=false);
};
bindBtn(btnLeft,  'left');
bindBtn(btnRight, 'right');

// Pointer управление
let dragging = false;
canvas.addEventListener('pointerdown', e => { dragging=true; movePlate(e); });
canvas.addEventListener('pointermove', e => { if(dragging) movePlate(e); });
canvas.addEventListener('pointerup',   ()=>dragging=false);
canvas.addEventListener('pointerleave',()=>dragging=false);

function movePlate(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left - plateWidth/2;
    plateX = Math.max(0, Math.min(canvas.width - plateWidth, x));
}

// Спавн пицц
function spawnPizza() {
    if(!gameRunning) return;
    const x = Math.random()*(canvas.width-pizzaSize);
    pizzas.push({x,y:-pizzaSize});
}

// Отрисовка
function drawPlate() {
    ctx.fillStyle='#f26822';
    ctx.fillRect(plateX,plateY,plateWidth,plateHeight);
}
function drawPizzas() {
    ctx.fillStyle='#f5e1a4';
    pizzas.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x+pizzaSize/2,p.y+pizzaSize/2,pizzaSize/2,0,Math.PI*2);
        ctx.fill();
        ctx.closePath();
    });
}

function updatePlate(){
    if(rightActive && plateX < canvas.width-plateWidth) plateX += plateSpeed;
    if(leftActive  && plateX > 0)                       plateX -= plateSpeed;
}

function updatePizzas(){
    pizzas.forEach((p,idx)=>{
        p.y+=pizzaSpeed;
        if(p.y+pizzaSize>=plateY && p.x+pizzaSize>=plateX && p.x<=plateX+plateWidth){
            pizzas.splice(idx,1);
            score++; document.getElementById('score').textContent=score;
        } else if(p.y>canvas.height){
            pizzas.splice(idx,1);
            lives--; document.getElementById('lives').textContent=lives;
            if(lives<=0){ 
                gameRunning = false;
                if(spawnInterval) clearInterval(spawnInterval);
                if(animationId) cancelAnimationFrame(animationId);
                alert('Игра окончена. Начните заново.'); 
                resetGame(); 
            }
        }
    });
}

function resetGame(){
    // Очистка старых интервалов и анимаций
    if(spawnInterval) clearInterval(spawnInterval);
    if(animationId) cancelAnimationFrame(animationId);
    
    // Сброс состояния
    pizzas = [];
    score = 0;
    lives = 3;
    gameRunning = true;
    plateX = (canvas.width - plateWidth) / 2;
    
    document.getElementById('score').textContent = score;
    document.getElementById('lives').textContent = lives;
    
    // Запуск новой игры
    spawnInterval = setInterval(spawnPizza, 1500);
    gameLoop();
}

function gameLoop(){
    if(!gameRunning) return;
    
    ctx.clearRect(0,0,canvas.width,canvas.height);
    drawPlate(); 
    drawPizzas(); 
    updatePlate(); 
    updatePizzas();
    
    animationId = requestAnimationFrame(gameLoop);
}

// Первый запуск
spawnInterval = setInterval(spawnPizza, 1500);
gameLoop();
