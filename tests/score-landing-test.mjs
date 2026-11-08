import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const load=file=>fs.readFileSync(path.join(root,file),'utf8');
const landing=load('public/index.html');
const css=load('public/assets/score-home.css');
const behavior=load('public/assets/site-interactions.js');
const manifest=load('deploy-files.txt').trim().split(/\r?\n/).filter(Boolean);

test('Score steht vor Tournament und Umpire; TENNILO bleibt Dachmarke',()=>{
 const start=landing.indexOf('id="score"');
 const others=landing.indexOf('id="projekte"');
 assert.ok(start>0&&others>start);
 for(const token of ['TENNILO Score','TENNILO Tournament','TENNILO Umpire','Dachmarke','Apple Watch','27. Dezember 2026','Bad Saarow','Coming Spring 2027'])assert.ok(landing.includes(token),token);
 assert.ok(landing.includes('kostenlos'));
 assert.ok(!/app\.tennis-schiri\.de|score\.tennilo\.de|testseite|test-site/iu.test(landing),'No public reference to internal test website');
 assert.match(landing,/<meta property="og:site_name" content="TENNILO">/);
});

test('Rechtliches und Newsletter/Founders bleiben intakt',()=>{
 assert.match(landing,/action="\/newsletter\.php"/);
 assert.match(landing,/action="\/founders\.php"/);
 assert.ok((landing.match(/<form\b/gi)||[]).length===2);
 assert.match(landing,/id="newsletter-email"/);
 assert.match(landing,/id="founder-email"/);
 assert.match(landing,/datenschutz\.html#founders-datenschutz/);
 assert.match(landing,/datenschutz\.html#newsletter-datenschutz/);
 for(const p of ['public/impressum.html','public/datenschutz.html']){
  const page=load(p);
  assert.match(page,/site-interactions\.js\?v=1/);
  assert.match(page,/nl-back-to-top/);
 }
});

test('Alle Logo-, CSS- und JS-Dateien sind vor dem Live-Upload vorhanden',()=>{
 const requested=[...landing.matchAll(/(?:src|href)="\.\/([^"#?]+)(?:\?[^"]*)?"/g)].map(m=>m[1]).filter(p=>/\.(png|css|js)$/i.test(p));
 for(const name of new Set([...requested,'assets/score-home.css','assets/site-interactions.js'])) {
  const rel='public/'+name;
  assert.ok(fs.existsSync(path.join(root,rel)),rel);
  assert.ok(manifest.includes(rel),'Not in FTP allowlist: '+rel);
 }
 assert.match(landing,/tennilo-logo\.png/);
 for(const p of ['tennilo-score-logo.png','tennilo-tournament-logo.png','tennilo-umpire-logo.png'])assert.ok(requested.some(v=>v.endsWith(p)),p);
});

test('Navigation und Nach-oben-Button reagieren auf Scrollposition',()=>{
 assert.match(css,/\.nl-home \.nl-header\{position:sticky;top:0/);
 assert.match(css,/\.nl-home \.site-shell\{overflow:visible/);
 assert.match(css,/\.nl-back-to-top\.nl-visible/);
 assert.match(behavior,/classList\.toggle\('nl-visible',y>500\)/);
 assert.match(behavior,/scrollTo\(\{top:0,behavior:/);
 assert.match(behavior,/aria-expanded/);
 assert.match(css,/@media\(max-width:860px\)/);
 const ids=[...landing.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
 assert.equal(new Set(ids).size,ids.length,'Duplicate IDs');
 for(const hash of landing.matchAll(/href="#([^"]+)"/g))assert.ok(ids.includes(hash[1]),'Unresolved nav anchor '+hash[1]);
});
