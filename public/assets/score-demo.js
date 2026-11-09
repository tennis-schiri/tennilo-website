/* Interactive visual concept only. No storage, analytics, or network requests.
   This simulates a single tennis game and counts completed games; it is not the Score app. */
(() => {
  'use strict';
  const root = document.querySelector('[data-demo]');
  if (!root) return;
  const points = [0, 0];
  const games = [0, 0];
  const normal = ['0', '15', '30', '40'];
  const pointEls = [root.querySelector('[data-demo-score="0"]'), root.querySelector('[data-demo-score="1"]')];
  const gameEls = [root.querySelector('[data-demo-games="0"]'), root.querySelector('[data-demo-games="1"]')];
  const status = root.querySelector('[data-demo-announcement]');
  const english = document.documentElement.lang.toLowerCase().startsWith('en');
  const names = english ? ['you', 'opponent'] : ['dich', 'Gegner'];
  function scoreLabel(side) {
    return points[side] === 4 ? (english ? 'AD' : 'V') : normal[Math.min(points[side], 3)];
  }
  function draw(message = '') {
    for (let i = 0; i < 2; i++) {
      pointEls[i].textContent = scoreLabel(i);
      gameEls[i].textContent = String(games[i]);
    }
    if (status) status.textContent = message;
  }
  function wonGame(side) {
    games[side] += 1;
    points[0] = 0; points[1] = 0;
    draw(english ? 'Game for ' + names[side] : 'Spiel für ' + names[side]);
  }
  function addPoint(side) {
    const other = 1 - side;
    if (points[side] === 4) { wonGame(side); return; }
    if (points[side] >= 3 && points[other] === 4) {
      points[other] = 3; // cancel opposing advantage; return to deuce
      draw(english ? 'Deuce' : 'Einstand');
      return;
    }
    if (points[side] >= 3 && points[other] >= 3) {
      points[side] = 4;
      draw(english ? 'Advantage ' + names[side] : 'Vorteil ' + names[side]);
      return;
    }
    points[side] += 1;
    if (points[side] > 3 && points[other] < 3) { wonGame(side); return; }
    draw(english ? 'Point for ' + names[side] : 'Punkt für ' + names[side]);
  }
  root.querySelectorAll('[data-add-point]').forEach(button => {
    button.addEventListener('click', () => {
      const side = Number(button.getAttribute('data-add-point'));
      if (side === 0 || side === 1) addPoint(side);
    });
  });
  root.querySelector('[data-demo-reset]')?.addEventListener('click', () => {
    points[0] = 0; points[1] = 0; games[0] = 0; games[1] = 0;
    draw(english ? 'Demo reset' : 'Demo zurückgesetzt');
  });
  draw();
})();
