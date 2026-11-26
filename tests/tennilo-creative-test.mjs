import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
const root=path.resolve(import.meta.dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const de=read('public/index.html');
const en=read('public/en/index.html');
const source=read('public/assets/score-demo.js');
test('Founders is prominent and all four products are linked to dedicated pages',()=>{
 const css=read('public/assets/home-overview.css');
 for(const [lang,html] of [['de',de],['en',en]]){
  const prefix=lang==='de'?'':'/en';
  assert.match(html,/class="tn-founders-focus"/);
  assert.ok(html.indexOf('id="founders"')<html.indexOf('id="projekte"'));
  assert.match(html,/tennilo-founders-tf12-light.png/);
  assert.match(html,/tennilo-founders-tf12-dark.png/);
  for(const slug of ['score','clubdisplay','tournament','umpire'])assert.ok(html.includes('href="'+prefix+'/'+slug+'/"'),slug);
 }
 assert.match(css,/\.tn-founders-feature\{display:grid/);
 assert.match(css,/html\[data-theme="dark"\] \.tn-home \.tn-founders-focus/);
 assert.ok(read('public/founders/index.html').includes('Gemeinsam mehr'));
 assert.ok(read('public/en/founders/index.html').includes('Make more'));
});
test('The score counting logic remains available only on the separate product page',()=>{
 assert.doesNotMatch(de,/data-add-point/);
 assert.doesNotMatch(en,/data-add-point/);
 assert.match(read('public/score/index.html'),/data-add-point="0"/);
 assert.match(read('public/en/score/index.html'),/data-add-point="1"/);
});

function setup(lang='de'){
 function node(){
  const callbacks={};
  return {textContent:'',callbacks,addEventListener(type,callback){callbacks[type]=callback;},click(){callbacks.click?.();}};
 }
 const point=[node(),node()],games=[node(),node()],announcement=node(),reset=node(),undo=node(),clock=node(),gameNumber=node();
 const serves=[node(),node()];
 const add=[node(),node()];add.forEach((button,i)=>button.getAttribute=name=>name==='data-add-point'?String(i):null);
 const rootElement={
  querySelector(selector){
   const m=selector.match(/data-demo-(score|games)="([01])"/);
   if(m)return (m[1]==='score'?point:games)[Number(m[2])];
   if(selector==='[data-demo-announcement]')return announcement;
   if(selector==='[data-demo-reset]')return reset;
   if(selector==='[data-demo-undo]')return undo;
   if(selector==='[data-demo-clock]')return clock;
   if(selector==='[data-demo-game-number]')return gameNumber;
   throw Error('Unknown selector '+selector);
  },
  querySelectorAll(selector){
   if(selector==='[data-add-point]')return add;
   if(selector==='[data-demo-serve]')return serves;
   throw Error('Unknown list '+selector);
  }
 };
 const document={documentElement:{lang},querySelector(selector){return selector==='[data-demo]'?rootElement:null;}};
 vm.runInNewContext(source,{document},{timeout:1000});
 return {point,games,add,reset,undo,clock,gameNumber,serves,announcement};
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

test('Watch-style demo can undo a point and displays game and service position',()=>{
 const demo=setup('de');
 assert.equal(demo.undo.disabled,true);
 assert.equal(demo.gameNumber.textContent,'1');
 assert.equal(demo.serves[0].hidden,false);
 demo.add[0].click();
 assert.equal(demo.point[0].textContent,'15');
 assert.equal(demo.undo.disabled,false);
 demo.undo.click();
 assert.equal(demo.point[0].textContent,'0');
 assert.equal(demo.undo.disabled,true);
 for(let i=0;i<4;i++)demo.add[1].click();
 assert.equal(demo.games[1].textContent,'1');
 assert.equal(demo.gameNumber.textContent,'2');
 assert.equal(demo.serves[1].hidden,false);
 demo.reset.click();
 assert.equal(demo.gameNumber.textContent,'1');
 assert.equal(demo.clock.textContent,'00:00');
});
