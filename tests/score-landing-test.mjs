import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..');
const load=p=>fs.readFileSync(path.join(root,p),'utf8');
const manifest=new Set(load('deploy-files.txt').trim().split(/\r?\n/));
const home={de:load('public/index.html'),en:load('public/en/index.html')};
const source=(lang,slug)=>load('public/'+(lang==='en'?'en/':'')+slug+'/index.html');
const products=['score','clubdisplay','tournament','umpire'];
const allPages=['public/index.html','public/en/index.html','public/score/index.html','public/en/score/index.html',
'public/clubdisplay/index.html','public/en/clubdisplay/index.html',
'public/tournament/index.html','public/en/tournament/index.html',
'public/umpire/index.html','public/en/umpire/index.html',
'public/updates/index.html','public/en/updates/index.html'];

test('Homepage is a concise overview with four independently linked products',()=>{
 for(const [lang,html] of Object.entries(home)){
  const prefix=lang==='en'?'/en':'';
  assert.match(html,/class="tn-overview-hero"/);
  assert.match(html,/class="tn-founders-focus"/);
  assert.match(html,/class="tn-product-grid"/);
  assert.equal((html.match(/<article class="tn-product tn-product-/g)||[]).length,4);
  assert.ok(html.indexOf('id="founders"')<html.indexOf('id="projekte"'));
  assert.ok(html.includes('href="'+prefix+'/founders/"'));
  for(const slug of products)assert.ok(html.includes('href="'+prefix+'/'+slug+'/"'),slug+' detail link');
  assert.ok(html.includes('href="'+prefix+'/updates/"'));
  assert.ok(!html.includes('id="funktionen"'),'Detailed features must not occupy homepage');
  assert.ok(!html.includes('id="einblicke"'),'Score screenshots belong to score page');
  assert.ok(!html.includes('id="meilensteine"'),'Roadmap belongs to score page');
  assert.ok(!html.includes('data-demo'),'Interactive demo belongs to score page');
  assert.equal((html.match(/<form\b/g)||[]).length,1);
  assert.match(html,/action="\/newsletter\.php"/);
  assert.match(html,/name="consent" value="yes" required/);
  assert.ok(html.includes(lang==='de'?'/datenschutz.html#newsletter-datenschutz':'/en/privacy.html#newsletter-datenschutz'));
 }
});

test('Score detail preserves beta facts, interactive demo, authentic screenshots and roadmap',()=>{
 for(const lang of ['de','en']){
  const html=source(lang,'score');
  assert.ok(html.includes('data-demo'));
  for(const value of ['data-add-point="0"','data-add-point="1"','data-demo-reset','id="funktionen"','id="einblicke"','id="meilensteine"','Apple Watch','Bad Saarow'])assert.ok(html.includes(value),value+' '+lang);
  for(const name of ['score-demo-watch.png','score-demo-phone.png','score-demo-scoreboard.png']){
    assert.ok(html.includes(name),name);
    assert.ok(manifest.has('public/assets/'+name));
  }
  assert.match(html,/27\.12\.2026|DEC 27, 2026/);
  assert.ok(html.includes(lang==='de'?'kostenlos':'free of charge'));
  assert.ok(html.includes('score-demo.js?v=2'));
 }
 for(const name of ['watch','phone','scoreboard']){
  const img=fs.readFileSync(path.join(root,'public/assets','score-demo-'+name+'.png'));
  assert.equal(img.subarray(1,4).toString(),'PNG');
  assert.equal(img.readUInt8(25),6,'Screenshot must have alpha channel');
 }
});

test('ClubDisplay product data sheets preserve functions, hosting and indicative pricing',()=>{
 for(const lang of ['de','en']){
  const html=source(lang,'clubdisplay');
  for(const id of ['funktionen','pakete','hosting','preise'])assert.ok(html.includes('id="'+id+'"'),id);
  for(const value of ['BASIC','PLUS','19 €','39 €','149','299','NTC','PHP','eBuSy'])assert.ok(html.includes(value),lang+' '+value);
  assert.ok(html.includes('https://clubdisplay.tennilo.de/'));
  assert.ok(html.includes('Founders'));
 }
});

test('Tournament and Umpire have distinct detailed product concepts in both languages',()=>{
 for(const lang of ['de','en']){
  for(const slug of ['tournament','umpire']){
   const html=source(lang,slug);
   assert.match(html,/class="tn-page-hero"/);
   assert.ok(html.includes('id="funktionen"'));
   assert.ok(html.includes('id="stand"'));
   assert.equal((html.match(/class="tn-detail-card"/g)||[]).length,9,slug+' must include six feature and three audience cards');
   assert.equal((html.match(/class="tn-detail-step"/g)||[]).length,3);
   assert.ok(html.includes('href="'+(lang==='en'?'/en':'')+'/founders/"'));
   assert.match(html,/Concept|Konzeption|Entwicklung|development/);
  }
 }
 assert.match(source('de','tournament'),/Schleifchenformate/);
 assert.match(source('de','umpire'),/Scoreboards/);
 assert.match(source('en','tournament'),/doubles/);
 assert.match(source('en','umpire'),/scoreboards/);
});

test('Latest updates are moved to standalone bilingual pages, newest first',()=>{
 for(const lang of ['de','en']){
  const html=source(lang,'updates');
  const news=html.split('<div class="tc-news-list">')[1];
  assert.ok(news,'News list missing');
  assert.deepEqual([...news.matchAll(/<time datetime="([^"]+)">/g)].map(m=>m[1]),
    ['2026-11-25','2026-11-09','2026-11-09','2026-11-07']);
  assert.ok(news.includes('TENNILO ClubDisplay BASIC'));
  assert.ok(news.includes('href="'+(lang==='en'?'/en':'')+'/clubdisplay/"'));
 }
});

test('All overview and product links resolve, assets are deployable, ids are unique',()=>{
 for(const file of allPages){
  const html=load(file);assert.ok(manifest.has(file),file+' missing from deploy manifest');
  for(const m of html.matchAll(/(?:href|src)="(\/assets\/[^"?#]+|\/favicon\.png)/g)){
   const asset='public'+m[1];
   assert.ok(fs.existsSync(path.join(root,asset)),file+' missing asset '+asset);
   assert.ok(manifest.has(asset),file+' asset not deployed: '+asset);
  }
  const ids=[...html.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
  assert.equal(new Set(ids).size,ids.length,'Duplicate ids on '+file);
  for(const match of html.matchAll(/href="#([^"]+)"/g))
    assert.ok(ids.includes(match[1]),'Broken #'+match[1]+' on '+file);
  assert.match(html,/href="\/assets\/professional-home\.css\?v=1"/,file);
  assert.match(html,/href="\/assets\/(?:home-overview|product-pages)\.css\?v=\d+"/,file);
 }
 const sitemap=load('public/sitemap.xml');
 for(const slug of ['score','clubdisplay','tournament','umpire','updates']){
  for(const prefix of ['','en/'])assert.ok(sitemap.includes('<loc>https://tennilo.de/'+prefix+slug+'/</loc>'),slug+' sitemap');
 }
});

test('Responsive navigation, progress-to-top and theme toggle remain functional',()=>{
 const css=load('public/assets/professional-home.css');
 const overview=load('public/assets/home-overview.css');
 const productCSS=load('public/assets/product-pages.css');
 const js=load('public/assets/site-interactions.js');
 const progress=load('public/assets/back-to-top.css');
 assert.match(css,/\.tc-header\{position:sticky;top:0/);
 assert.match(css,/\.tc-nav\.nl-nav-open\{display:flex/);
 assert.match(css,/@media\(max-width:680px\)/);
 assert.match(overview,/\.tn-founders-feature\{display:grid/);
 assert.match(overview,/@media\(max-width:680px\)/);
 assert.match(productCSS,/\.tn-detail-page \.tn-page-hero/);
 assert.match(productCSS,/@media\(max-width:680px\)/);
 assert.match(js,/classList\.toggle\('nl-visible',y>500\)/);
 assert.match(js,/scrollTo\(\{top:0,behavior:/);
 assert.match(progress,/conic-gradient\(from -90deg/);
 for(const f of [...allPages,'public/impressum.html','public/datenschutz.html','public/en/imprint.html','public/en/privacy.html'])
   assert.match(load(f),/class="[^"]*tc-progress-button/,'Scroll-to-top missing on '+f);
});

test('No unreleased Tournament/Umpire claims or prices are made on the homepage',()=>{
 for(const html of Object.values(home)){
  assert.ok(html.includes('In Konzeption')||html.includes('Concept stage'));
  assert.ok(!html.includes('19 €'),'Price detail belongs to ClubDisplay sheet');
  assert.ok(!html.includes('STARTNEXT checkout'));
 }
});

test('All four overview logos are approved, transparent and consistently sized',()=>{
 for(const file of ['public/index.html','public/en/index.html']){
  const html=load(file);
  assert.equal((html.match(/class="tn-product-art tn-product-art-badge"/g)||[]).length,4,file);
  for(const name of ['score','clubdisplay','tournament','umpire']){
   const asset='public/assets/tn-overview-'+name+'.png';
   assert.ok(html.includes('src="/assets/tn-overview-'+name+'.png"'),file+' missing '+name);
   assert.ok(manifest.has(asset),asset+' not in deploy list');
   const image=fs.readFileSync(path.join(root,asset));
   assert.equal(image.subarray(1,4).toString(),'PNG',asset);
   assert.equal(image.readUInt32BE(16),1361,asset+' width');
   assert.equal(image.readUInt32BE(20),390,asset+' height');
   assert.equal(image.readUInt8(25),6,asset+' must use alpha');
  }
  assert.ok(!html.includes('tn-product-badge-label'),file+' fabricated ClubDisplay badge');
  assert.doesNotMatch(html,/src="\/assets\/tennilo-(?:score|tournament|umpire)-cutout(?:-dark)?\.png"/);
 }
});


test('Clickable products dropdown lists all four localised product pages',()=>{
 const css=load('public/assets/product-navigation.css');
 const js=load('public/assets/site-interactions.js');
 assert.ok(manifest.has('public/assets/product-navigation.css'));
 assert.match(css,/\.tc-nav \.tc-nav-products\[open\]/);
 assert.match(css,/@media\(max-width:1180px\)/);
 assert.match(js,/closeProducts/);
 for(const file of allPages){
  const html=load(file);
  const en=file.startsWith('public/en/');
  const nav=html.match(/<nav class="nl-nav tc-nav"[\s\S]*?<\/nav>/)?.[0];
  assert.ok(nav,'Navigation missing on '+file);
  assert.match(nav,/<details class="tc-nav-products/);
  assert.ok(nav.includes('<summary>'+(en?'Products':'Produkte')),'Summary missing on '+file);
  assert.ok(html.includes('href="/assets/product-navigation.css?v=1"'),file);
  for(const slug of products)
   assert.ok(nav.includes('href="'+(en?'/en':'')+'/'+slug+'/"'),file+' dropdown missing '+slug);
 }
});

test('Detail pages feature exactly the same approved product badges as homepage',()=>{
 for(const lang of ['de','en']){
  for(const slug of products){
   const html=source(lang,slug);
   assert.match(html,/class="tn-page-art tn-page-art-badge"/);
   assert.ok(html.includes('src="/assets/tn-overview-'+slug+'.png"'),lang+' '+slug);
   assert.ok(home[lang].includes('src="/assets/tn-overview-'+slug+'.png"'));
   assert.ok(!html.includes('tn-page-logo-dark'),'Old theme-specific cutout on '+slug);
  }
 }
});

test('Score concept demo uses the original Apple Watch controls, not the former table',()=>{
 const css=load('public/assets/score-watch-demo.css');
 assert.ok(manifest.has('public/assets/score-watch-demo.css'));
 assert.match(css,/\.ts-watch-case/);
 assert.match(css,/\.ts-watch-points/);
 assert.match(css,/\.ts-watch-undo/);
 for(const lang of ['de','en']){
  const html=source(lang,'score');
  for(const key of ['class="ts-watch-case"','class="ts-watch-screen"','data-demo-undo','data-demo-clock','data-demo-serve="0"','data-demo-game-number','/assets/score-watch-demo.css?v=1'])
   assert.ok(html.includes(key),lang+' missing '+key);
  assert.ok(!html.includes('class="tc-demo-head"'),lang+' old rectangular score table');
 }
});

test('ClubDisplay BASIC/PLUS matrix is readable and retains agreed launch scope in both languages',()=>{
 const css=load('public/assets/clubdisplay-comparison.css');
 assert.ok(manifest.has('public/assets/clubdisplay-comparison.css'));
 assert.match(css,/\.cd-compare-table/);
 assert.match(css,/\.cd-compare-scroll/);
 for(const lang of ['de','en']){
  const html=source(lang,'clubdisplay');
  const section=html.split('<section id="pakete"')[1]?.split('<section id="screenshots"')[0]||'';
  assert.ok(section,lang+' missing feature matrix');
  assert.match(section,/class="cd-compare-section"/);
  assert.match(section,/class="cd-compare-table"/);
  assert.equal((section.match(/<tbody class="cd-compare-group">/g)||[]).length,6,lang+' group count');
  assert.equal((section.match(/<tr><th scope="row">/g)||[]).length,21,lang+' feature count');
  assert.equal((section.match(/class="cd-compare-excluded"/g)||[]).length,11,lang+' PLUS-only features');
  assert.ok(html.includes('href="/assets/clubdisplay-comparison.css?v=1"'));
  assert.ok(html.includes('href="#pakete"'));
  for(const name of ['BASIC','PLUS','eBuSy','nuLiga'])assert.ok(section.includes(name),lang+' '+name);
 }
});


test('TENNILO homepage clearly links to BASIC/PLUS and the genuine original ClubDisplay PDFs',()=>{
 const files=['public/index.html','public/en/index.html'];
 for(const file of files){
  const html=load(file);
  const marker=html.indexOf('class="tn-product tn-product-clubdisplay"');
  assert.ok(marker>0);
  const card=html.slice(marker,html.indexOf('</article>',marker));
  assert.match(card,/class="tn-club-tiers"/);
  assert.match(card,/clubdisplay\/#pakete/);
  assert.ok(card.includes('tennilo-clubdisplay-produktdatenblatt-basic-plus.pdf'));
  assert.ok(html.includes('home-overview.css?v=5'));
 }
});

test('ClubDisplay data sheets are actual standalone PDFs, not a printout of the web page',()=>{
 const docs=[
  'public/assets/tennilo-clubdisplay-produktdatenblatt-basic-plus.pdf',
  'public/assets/tennilo-clubdisplay-funktionsuebersicht.pdf'
 ];
 for(const file of docs){
  assert.ok(manifest.has(file),file+' missing in deploy manifest');
  const bytes=fs.readFileSync(path.join(root,file));
  assert.equal(bytes.subarray(0,5).toString('ascii'),'%PDF-',file+' must contain PDF bytes');
  assert.ok(bytes.length>25000,file+' too small');
 }
 for(const lang of ['de','en']){
  const html=source(lang,'clubdisplay');
  assert.ok(html.indexOf('<section id="pakete"')<html.indexOf('<section id="funktionen"'),lang+' comparison should appear first');
  assert.ok(html.includes('cd-datasheet-grid'),lang+' PDF cards missing');
  assert.doesNotMatch(html,/window\.print\(\)/,lang+' browser print must not be used as PDF');
  for(const file of docs){
   assert.ok(html.includes('href="/'+file.slice('public/'.length)+'"'),lang+' missing PDF link '+file);
  }
  assert.ok(html.includes('product-pages.css?v=3'));
 }
});


test('ClubDisplay datasheets on website are the reconciled 27 Nov 2026 documents',()=>{
 const docs=[
  ['public/assets/tennilo-clubdisplay-produktdatenblatt-basic-plus.pdf',1000000],
  ['public/assets/tennilo-clubdisplay-funktionsuebersicht.pdf',500000]
 ];
 for(const [file,minBytes] of docs){
  const pdf=fs.readFileSync(path.join(root,file));
  assert.equal(pdf.subarray(0,5).toString('ascii'),'%PDF-',file);
  assert.ok(pdf.length>=minBytes,file+' is an older small document, not the current revision');
 }
 for(const lang of ['de','en']){
  const html=source(lang,'clubdisplay');
  assert.ok(html.includes('v0.5.7'),lang+' version label missing');
  assert.ok(html.includes('6 pages')||html.includes('6 Seiten'),lang+' six-page sheet missing');
  assert.ok(!html.includes('PDF · 3 Seiten · 26.11.2026'),lang+' outdated sheet label');
  assert.ok(!html.includes('PDF · 3 pages · DE · 26 Nov 2026'),lang+' outdated English sheet label');
 }
});
