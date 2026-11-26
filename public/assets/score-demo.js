/* TENNILO Score: interactive Apple Watch concept (not the finished application).
   A self-contained, offline scoring preview using the original prototype's watch design. */
(() => {
  'use strict';
  const root = document.querySelector('[data-demo]');
  if (!root) return;

  const points = [0, 0];
  const games = [0, 0];
  const history = [];
  const normal = ['0','15','30','40'];
  const english = document.documentElement.lang.toLowerCase().startsWith('en');
  const names = english ? ['you','opponent'] : ['dich','Gegner'];
  const pointEls = [root.querySelector('[data-demo-score="0"]'),root.querySelector('[data-demo-score="1"]')];
  const gameEls = [root.querySelector('[data-demo-games="0"]'),root.querySelector('[data-demo-games="1"]')];
  const announcement = root.querySelector('[data-demo-announcement]');
  const undo = root.querySelector('[data-demo-undo]');
  const clock = root.querySelector('[data-demo-clock]');
  const gameNumber = root.querySelector('[data-demo-game-number]');
  const serveEls = [...root.querySelectorAll('[data-demo-serve]')];
  let elapsedSeconds = 0;
  let timerStart = null;
  let timer = null;

  function scoreLabel(side) {
    return points[side] === 4 ? (english ? 'AD' : 'V') : normal[Math.min(points[side],3)];
  }
  function timeLabel(seconds) {
    const m=Math.floor(seconds/60),s=seconds%60;
    return String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  }
  function updateClock() {
    if (!clock) return;
    const value=timerStart===null?elapsedSeconds:elapsedSeconds+Math.floor((Date.now()-timerStart)/1000);
    clock.textContent=timeLabel(value);
  }
  function startClock() {
    if (timerStart!==null) return;
    timerStart=Date.now();
    if (typeof window!=='undefined'&&typeof window.setInterval==='function') {
      timer=window.setInterval(updateClock,1000);
    }
  }
  function stopClock() {
    if(timer!==null&&typeof window!=='undefined'&&typeof window.clearInterval==='function')window.clearInterval(timer);
    timer=null;
    timerStart=null;
  }
  function draw(message='') {
    for(let i=0;i<2;i++){
      pointEls[i].textContent=scoreLabel(i);
      gameEls[i].textContent=String(games[i]);
    }
    if(gameNumber)gameNumber.textContent=String(games[0]+games[1]+1);
    serveEls.forEach((element,index)=>{element.hidden=(games[0]+games[1])%2!==index;});
    if(undo)undo.disabled=history.length===0;
    if(announcement)announcement.textContent=message;
    updateClock();
  }
  function wonGame(side) {
    games[side]+=1;
    points[0]=0;points[1]=0;
    draw((english?'Game for ':'Spiel für ')+names[side]);
  }
  function addPoint(side) {
    startClock();
    history.push({points:[...points],games:[...games]});
    const other=1-side;
    if(points[side]===4){wonGame(side);return;}
    if(points[side]>=3&&points[other]===4){
      points[other]=3;
      draw(english?'Deuce':'Einstand');return;
    }
    if(points[side]>=3&&points[other]>=3){
      points[side]=4;
      draw((english?'Advantage ':'Vorteil ')+names[side]);return;
    }
    points[side]+=1;
    if(points[side]>3&&points[other]<3){wonGame(side);return;}
    draw((english?'Point for ':'Punkt für ')+names[side]);
  }
  root.querySelectorAll('[data-add-point]').forEach(button=>{
    button.addEventListener('click',()=>{
      const side=Number(button.getAttribute('data-add-point'));
      if(side===0||side===1)addPoint(side);
    });
  });
  undo?.addEventListener('click',()=>{
    const previous=history.pop();
    if(!previous)return;
    for(let i=0;i<2;i++){points[i]=previous.points[i];games[i]=previous.games[i];}
    draw(english?'Last point undone':'Letzter Punkt zurückgenommen');
  });
  root.querySelector('[data-demo-reset]')?.addEventListener('click',()=>{
    points[0]=points[1]=games[0]=games[1]=0;
    history.length=0;
    elapsedSeconds=0;
    stopClock();
    draw(english?'Demo reset':'Demo zurückgesetzt');
  });
  draw();
})();
