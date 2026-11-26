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

test('Professional product-led homepage keeps interactive features and all original product brands',()=>{
 for(const html of [de,en]){
  for(const token of ['tn-hero-grid','tn-product-grid','tn-product-score','tn-product-club','tn-product-tournament','tn-product-umpire','tn-club-grid','tn-score-grid','tn-milestone-grid','tc-news-list','tn-newsletter','tc-founders-teaser','professional-home.css'])assert.ok(html.includes(token),token);
  for(const obsolete of ['court-edition.css?v=1','score-gallery.css?v=1','tc-hero-bottom','tc-ball','tc-outro','tc-beta-ticket'])assert.ok(!html.includes(obsolete),obsolete+' remains');
  for(const asset of ['tennilo-logo.png','tennilo-score-cutout.png','tennilo-tournament-cutout.png','tennilo-umpire-cutout.png','clubdisplay.png'])assert.ok(html.includes(asset),asset);
  assert.equal((html.match(/class="tn-product tn-product-/g)||[]).length,4,'Exactly four distinct product cards');
  assert.equal((html.match(/data-add-point=/g)||[]).length,2,'Interactive scorer must remain usable');
 }
 const home=read('public/assets/professional-home.css');
 for(const selector of ['.tn-hero-grid','.tn-product-grid','.tn-club-grid','.tn-score-grid','.tn-milestone-grid','.tc-gallery-grid','.tn-newsletter'])assert.ok(home.includes(selector),selector);
 for(const breakpoint of ['@media(max-width:1180px)','@media(max-width:900px)','@media(max-width:680px)'])assert.ok(home.includes(breakpoint),breakpoint);
 assert.ok(home.includes('html[data-theme="dark"]'));
 assert.ok(home.includes('@media(prefers-reduced-motion:reduce)'));
});

test('Compact Founders teaser and separate campaign retain legibility and both themes',()=>{
 const campaign=read('public/assets/founders-campaign.css');
 assert.match(campaign,/\.tc-home \.tc-founders-teaser\{background:#f0e6d2;color:#071f32/);
 assert.match(campaign,/html\[data-theme="dark"\] \.tc-home \.tc-founders-teaser\{background:#25262b;color:#eff8ff/);
 assert.match(campaign,/\.tf-page \.founders-form label\{[^}]*color:var\(--tf-ink\)/);
 assert.match(campaign,/\.tf-page \.founders-form input\[type=email\],/);
 assert.match(css,/@media\(max-width:680px\)/);
 assert.match(css,/@media\(prefers-reduced-motion:reduce\)/);
 assert.ok(read('public/founders/index.html').includes('Gemeinsam mehr'));
 assert.ok(read('public/en/founders/index.html').includes('Make more'));
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
