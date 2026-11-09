import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root=path.resolve(import.meta.dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const de=read('public/index.html');
const en=read('public/en/index.html');
const css=read('public/assets/court-edition.css');
const source=read('public/assets/score-demo.js');

test('New editorial design replaces the former dashboard and keeps original branding',()=>{
 for(const html of [de,en]){
  for(const token of ['tc-hero','tc-score-stage','tc-demo','tc-manifesto','tc-beta','tc-feature-line','tc-project-spread','tc-news-item','tc-signups','tc-founders','court-edition.css'])assert.ok(html.includes(token),token);
  assert.ok(!html.includes('score-home.css'), 'Old homepage stylesheet should not load');
  assert.ok(!html.includes('score-creative.css'), 'Old overlay stylesheet should not load');
  assert.ok(html.includes('tennilo-logo.png'));
  assert.ok(html.includes('tennilo-score-logo.png'));
  assert.ok(html.includes('tennilo-tournament-logo.png'));
  assert.ok(html.includes('tennilo-umpire-logo.png'));
 }
 assert.match(css,/--tc-paper:#f8f5ee/);
 assert.match(css,/--tc-royal:#2333c9/);
 assert.match(css,/--tc-signal:#ff6541/);
 assert.match(css,/\.tc-hero-grid\{display:grid/);
 assert.match(css,/\.tc-project-spread\{display:grid/);
});

test('Light Founders section uses dark legible text without white-on-white',()=>{
 assert.match(css,/\.tc-founders\{background:#deddf5;color:var\(--tc-ink\)/);
 assert.match(css,/\.tc-founders \.tc-signup-intro p strong\{color:#061c3a\}/);
 assert.match(css,/\.tc-home \.founders-form\{box-shadow:8px 8px 0 var\(--tc-royal\)/);
 assert.match(css,/\.tc-home \.founders-form fieldset label\{[^}]*color:#243c56!important/);
 assert.match(css,/\.tc-home \.founders-consent,[^}]*color:#2e435e!important/);
 assert.match(css,/@media\(max-width:680px\)/);
 assert.match(css,/@media\(prefers-reduced-motion:reduce\)/);
 assert.match(en,/MAKE MORE/);
 assert.match(de,/GEMEINSAM MEHR/);
});

function setup(lang='de'){
 function node(){
  const callbacks={};
  return {textContent:'',callbacks,addEventListener(type,callback){callbacks[type]=callback;},click(){callbacks.click?.();}};
 }
 const point=[node(),node()],games=[node(),node()],announcement=node(),reset=node();
 const add=[node(),node()];add.forEach((button,i)=>button.getAttribute=name=>name==='data-add-point'?String(i):null);
 const rootElement={
  querySelector(selector){
   const m=selector.match(/data-demo-(score|games)="([01])"/);
   if(m)return (m[1]==='score'?point:games)[Number(m[2])];
   if(selector==='[data-demo-announcement]')return announcement;
   if(selector==='[data-demo-reset]')return reset;
   throw Error('Unknown selector '+selector);
  },
  querySelectorAll(selector){if(selector==='[data-add-point]')return add;throw Error('Unknown list '+selector);}
 };
 const document={documentElement:{lang},querySelector(selector){return selector==='[data-demo]'?rootElement:null;}};
 vm.runInNewContext(source,{document},{timeout:1000});
 return {point,games,add,reset,announcement};
}

test('Demo applies tennis point, deuce, advantage, game and reset rules',()=>{
 const demo=setup('de');
 assert.deepEqual(demo.point.map(x=>x.textContent),['0','0']);
 demo.add[0].click();demo.add[0].click();demo.add[0].click();
 assert.equal(demo.point[0].textContent,'40');
 demo.add[1].click();demo.add[1].click();demo.add[1].click();
 assert.deepEqual(demo.point.map(x=>x.textContent),['40','40']);
 demo.add[0].click();assert.equal(demo.point[0].textContent,'V');
 demo.add[1].click();assert.deepEqual(demo.point.map(x=>x.textContent),['40','40']);
 demo.add[1].click();assert.equal(demo.point[1].textContent,'V');
 demo.add[0].click();assert.deepEqual(demo.point.map(x=>x.textContent),['40','40']);
 demo.add[0].click();demo.add[0].click();
 assert.deepEqual(demo.games.map(x=>x.textContent),['1','0']);
 assert.deepEqual(demo.point.map(x=>x.textContent),['0','0']);
 demo.reset.click();
 assert.deepEqual(demo.games.map(x=>x.textContent),['0','0']);
 assert.match(demo.announcement.textContent,/Demo zurückgesetzt/);
});

test('English demo respects English language labels',()=>{
 const demo=setup('en');
 demo.add[1].click();assert.match(demo.announcement.textContent,/Point for opponent/);
 demo.add[1].click();demo.add[1].click();demo.add[1].click();
 assert.equal(demo.games[1].textContent,'1');
 assert.match(demo.announcement.textContent,/Game for opponent/);
 demo.reset.click();assert.equal(demo.point[0].textContent,'0');
});
