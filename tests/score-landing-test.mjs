import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const load=p=>fs.readFileSync(path.join(root,p),'utf8');
const de=load('public/index.html');
const en=load('public/en/index.html');
const css=load('public/assets/court-edition.css');
const behavior=load('public/assets/site-interactions.js');
const manifest=new Set(load('deploy-files.txt').trim().split(/\r?\n/));

test('Score is the lead product under the TENNILO umbrella; milestones are accurate',()=>{
 for(const [html,language] of [[de,'de'],[en,'en']]){
  assert.ok(html.indexOf('id="score"')<html.indexOf('id="projekte"'));
  for(const name of ['TENNILO Score','TENNILO Tournament','TENNILO Umpire','Apple Watch','Bad Saarow'])assert.ok(html.includes(name),name+' '+language);
  assert.match(html,/tennilo-score-logo\.png/);
  assert.match(html,/tennilo-tournament-logo\.png/);
  assert.match(html,/tennilo-umpire-logo\.png/);
  assert.ok(!/app\.tennis-schiri\.de|score\.tennilo\.de|testseite|test-site/i.test(html));
 }
 assert.match(de,/27\.12\.2026/);
 assert.match(en,/DEC 27, 2026/);
 assert.match(de,/Keine festgelegte Höchstzahl an Testern/);
 assert.match(en,/No fixed tester limit/);
 assert.match(de,/kostenlos/);
 assert.match(en,/free of charge/);
});

test('Newsletter, Founders and privacy links are preserved on both languages',()=>{
 for(const [html,language] of [[de,'de'],[en,'en']]){
  assert.match(html,/<form method="post" action="\/newsletter\.php" class="newsletter-form">/);
  assert.match(html,/<form method="post" action="\/founders\.php" class="founders-form">/);
  assert.equal((html.match(/<form\b/g)||[]).length,2,language);
  for(const id of ['newsletter-email','founder-email','founder-role'])assert.ok(html.includes('id="'+id+'"'));
  assert.match(html,/name="consent" value="yes" required/);
  const link=language==='en'?'/en/privacy.html':'/datenschutz.html';
  assert.ok(html.includes(link+'#founders-datenschutz'));
  assert.ok(html.includes(link+'#newsletter-datenschutz'));
 }
 assert.equal((en.match(/name="lang" value="en"/g)||[]).length,2);
});

test('All deployed local assets exist and all local links are valid',()=>{
 for(const html of [de,en]){
  for(const m of html.matchAll(/(?:href|src)="(\/assets\/[^"?#]+|\/favicon\.png)/g)){
   const relative='public'+m[1];
   assert.ok(fs.existsSync(path.join(root,relative)),relative);
   assert.ok(manifest.has(relative),relative+' not in FTP manifest');
  }
  const ids=[...html.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
  assert.equal(new Set(ids).size,ids.length,'Duplicate ids');
  for(const match of html.matchAll(/href="#([^"]+)"/g))assert.ok(ids.includes(match[1]),'Unresolved anchor #'+match[1]);
 }
 for(const file of ['public/index.html','public/en/index.html','public/assets/court-edition.css','public/assets/score-demo.js'])assert.ok(manifest.has(file));
});

test('Sticky navigation, responsive menu and scroll-to-top remain functional',()=>{
 assert.match(css,/\.tc-header\{position:sticky;top:0/);
 assert.match(css,/@media\(max-width:1180px\)/);
 assert.match(css,/\.tc-nav\.nl-nav-open\{display:flex/);
 assert.match(css,/\.tc-backtop\.nl-back-to-top\.nl-visible\{/);
 assert.match(behavior,/classList\.toggle\('nl-visible',y>500\)/);
 assert.match(behavior,/scrollTo\(\{top:0,behavior:/);
 assert.match(behavior,/document\.documentElement\.lang/);
});
