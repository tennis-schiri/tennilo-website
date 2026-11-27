import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const manifest=new Set(read('deploy-files.txt').trim().split(/\r?\n/));
test('Founders campaigns are bilingual, locally hosted, deployable and waitlist-only',()=>{
 for(const [p,home,lang,url] of [['public/founders/index.html','public/index.html','de','/founders/'],['public/en/founders/index.html','public/en/index.html','en','/en/founders/']]){
  const html=read(p);
  assert.ok(html.includes('<html lang="'+lang+'">'));
  assert.ok(html.includes('rel="canonical" href="https://tennilo.de'+url+'"'));
  assert.ok(read(home).includes('href="'+url+'"'));
  assert.ok(manifest.has(p));
  assert.equal((html.match(/<form\b/g)||[]).length,1);
  assert.ok(html.includes('action="/founders.php"'));
  assert.ok(html.includes('name="action" value="join"'));
  assert.ok(html.includes('name="consent" value="yes" required'));
  for(const topic of ['founders','crowdfunding','beta'])assert.ok(html.includes('value="'+topic+'"'));
  for(const name of ['Bronze','Silver','Gold','Platinum','Diamond','Club','Club Plus','Founding Partner','Founding Premium Partner'])assert.ok(html.includes(name));
  assert.doesNotMatch(html,/checkout\.stripe|paypal\.com|<form[^>]+action="https?:/i);
  const ids=[...html.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
  assert.equal(new Set(ids).size,ids.length);
  for(const m of html.matchAll(/href="#([^"]+)"/g))assert.ok(ids.includes(m[1]),m[1]);
  for(const m of html.matchAll(/(?:href|src)="(\/assets\/[^"?#]+|\/favicon\.png)/g)){const f='public'+m[1];assert.ok(manifest.has(f),f);assert.ok(fs.existsSync(path.join(root,f)),f)}
 }
});
test('Both themes use approved Founders originals with transparent alpha',()=>{
 for(const id of ['tf09','tf12'])for(const mode of ['light','dark']){
  const img=fs.readFileSync(path.join(root,'public/assets/tennilo-founders-'+id+'-'+mode+'.png'));
  const original=fs.readFileSync(path.join(root,'brand-assets/founders/TENNILO-Founders-'+id.toUpperCase()+'-'+mode+'.png'));
  assert.deepEqual(img,original);
  assert.equal(img[25],6);
 }
 assert.ok(read('public/assets/founders-campaign.css').includes('html[data-theme="dark"]'));
});

test('Nine approved price points and all five funding goals appear only on campaign pages',()=>{
 for(const [file,lang] of [['public/founders/index.html','de'],['public/en/founders/index.html','en']]){
  const html=read(file);
  const prices=[10,29,69,149,299,69,199,599,999];
  const section=html.split('id="pakete"')[1].split('</section>')[0];
  assert.equal((section.match(/class="tf-card tf-tier"/g)||[]).length,9);
  const found=[...section.matchAll(/<b>([^<]+)<\/b>/g)].map(m=>Number(m[1].replace(/\D/g,'')));
  assert.deepEqual(found,prices);
  const goals=html.split('id="ziele"')[1].split('</section>')[0];
  assert.deepEqual([...goals.matchAll(/<b>([^<]+)<\/b>/g)].map(m=>Number(m[1].replace(/\D/g,''))),[2781,5000,10000,15000,25000]);
 }
});

test('Financial support is exclusively through Startnext, including unrestricted free contributions',()=>{
 for(const [file,lang] of [['public/founders/index.html','de'],['public/en/founders/index.html','en']]){
  const html=read(file);
  assert.ok(html.includes('id="freie-unterstuetzung"'));
  assert.ok(html.includes(lang==='de'?'individuellen Betrag ab 5 €':'your own amount from €5'));
  assert.ok(html.includes(lang==='de'?'ausschließlich über Startnext':'exclusively through Startnext'));
  assert.doesNotMatch(html,/eigener Vorverkauf|direkter Vorverkauf|direct pre-sale|crowdfunding and pre-sale|Crowdfunding- und Vorverkaufsinitiative/i);
  assert.equal((html.match(/<form\b/g)||[]).length,1);
 }
});

test('Founders supports all four products on equal terms independently of release order',()=>{
 for(const [file,lang] of [['public/founders/index.html','de'],['public/en/founders/index.html','en']]){
  const html=read(file);
  for(const product of ['Score','Tournament','Umpire','ClubDisplay'])assert.ok(html.includes('<h3>TENNILO '+product+'</h3>'));
  assert.doesNotMatch(html,/01 \/ PRIORITÄT|01 \/ PRIORITY|Zuerst steht Score im Fokus|Score comes first|alle drei Projekte|all three projects|Drittelung|three-way split/i);
  assert.ok(html.includes(lang==='de'?'gleichwertige Bestandteile der Kampagne':'equal parts of the campaign'));
 }
});

test('Nine approved badge images are used on each campaign page',()=>{
 const names=["fb01-bronze","fb02-silver","fb03-gold","fb04-platinum","fb05-diamond","fc01-club","fc02-club-plus","fc03-founding-partner","fc04-founding-premium-partner"];
 for(const page of ['public/founders/index.html','public/en/founders/index.html']){
  const html=read(page);
  assert.equal((html.match(/class="tf-tier-badge"/g)||[]).length,9);
  for(const name of names){const file='public/assets/founders-badges/'+name+'.webp';assert.ok(html.includes('src="/assets/founders-badges/'+name+'.webp"'),name);assert.ok(manifest.has(file),file);assert.ok(fs.existsSync(path.join(root,file)),file);}
 }
});

test('ClubDisplay pilot is included in the four-project Founders grid and homepage teaser',()=>{
 for(const [page,home,lang] of [['public/founders/index.html','public/index.html','de'],['public/en/founders/index.html','public/en/index.html','en']]){
 const h=read(page);const section=h.split('class="tf-grid tf-project-grid"')[1].split('</section>')[0];
 assert.equal((section.match(/<article class="tf-card">/g)||[]).length,4);
 assert.ok(section.includes('TENNILO ClubDisplay')&&section.includes('BASIC')&&section.includes('PLUS'));
 assert.ok(section.includes(lang==='de'?'href="/clubdisplay/"':'href="/en/clubdisplay/"'));
 assert.ok(read(home).split('id="founders"')[1].split('</section>')[0].includes('ClubDisplay'));
 }
});

 test('Tournament Founders draft licence terms are available in both languages',()=>{
 for(const [file,lang] of [['public/founders/index.html','de'],['public/en/founders/index.html','en']]){
  const html=read(file);const section=html.split('id="tournament-lizenzbedingungen"')[1].split('</article>')[0];
  assert.ok(section.includes(lang==='de'?'ENTWURF V1.0':'DRAFT V1.0'));
  assert.ok(section.includes(lang==='de'?'31.01.2028':'31 January 2028'));
  assert.ok(section.includes(lang==='de'?'Standardhosting':'standard hosting'));
  assert.equal((section.match(/<p><strong>\d+\./g)||[]).length,9);
 }
});


test("Public Startnext project is linked from both homepages and Founders pages",()=>{
 for(const p of ["public/index.html","public/en/index.html","public/founders/index.html","public/en/founders/index.html"]){
  const html=read(p);assert.ok(html.includes('href="https://www.startnext.com/tennilo-founders" target="_blank" rel="noopener noreferrer"'),p);
 }
});
