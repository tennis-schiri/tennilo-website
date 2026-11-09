import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const de=read('public/index.html');
const en=read('public/en/index.html');
const oldCss=read('public/assets/styles.css');
const css=read('public/assets/score-home.css');
const creative=read('public/assets/score-creative.css');
const allowlist=read('deploy-files.txt');

test('TENNILO Founders text has explicit non-white foreground on light background',()=>{
 assert.match(oldCss,/\.founders-copy strong\{color:#fff\}/);
 assert.match(css,/\.nl-home \.founders-copy p strong,[\s\S]*?\{color:#073a58!important/);
 assert.match(css,/\.nl-home \.founders-panel h2\{color:#052d49!important/);
 assert.match(creative,/\.nl-home \.founders-copy>p:not\(\.eyebrow\) strong\{color:#00354f!important\}/);
 assert.match(creative,/\.nl-home \.founders-panel h2 em\{[^}]*color:#006ea4!important/);
 assert.match(creative,/\.nl-home \.founders-panel\.nl-founders-ticket\{[^}]*background:#fff/);
 assert.match(creative,/\.nl-home \.founders-form\{background:#f7fcff/);
 assert.match(creative,/\.nl-home \.founders-form fieldset label\{color:#244f6b!important/);
});

test('Experimental layout is present in German and English with the same structure',()=>{
 for(const html of [de,en]){
  for(const token of ['nl-hero-oversize','nl-court-schematic','nl-motion-marquee','nl-statement','nl-founders-ticket','nl-founder-serial','score-creative.css?v=1'])assert.ok(html.includes(token),token);
  assert.match(html,/<form method="post" action="\/founders\.php"/);
  assert.match(html,/<form method="post" action="\/newsletter\.php"/);
  assert.ok(html.indexOf('id="score"')<html.indexOf('id="meilensteine"'));
  assert.ok(html.indexOf('id="meilensteine"')<html.indexOf('id="projekte"'));
  assert.ok(html.indexOf('id="newsletter"')<html.indexOf('id="founders"'));
 }
 for(const [file,tag] of [['public/index.html','de'],['public/en/index.html','en']])assert.ok(read(file).includes('<html lang="'+tag+'">'));
 assert.match(en,/Apple Watch<\/strong>, keep track of the score/);
});

test('Original logos, privacy and deployment stay intact',()=>{
 for(const p of ['public/assets/tennilo-score-logo.png','public/assets/tennilo-tournament-logo.png','public/assets/tennilo-umpire-logo.png'])assert.ok(fs.statSync(path.join(root,p)).size>1000,p);
 for(const p of ['public/assets/score-creative.css','public/assets/score-home.css','public/index.html','public/en/index.html'])assert.ok(allowlist.includes(p),p);
 for(const html of [de,en])assert.match(html,/name="consent" value="yes" required/);
 assert.ok(en.includes('href="/en/privacy.html#founders-datenschutz"'));
 assert.ok(de.includes('href="/datenschutz.html#founders-datenschutz"'));
});

test('Visual movement respects reduced motion and retains sticky navigation',()=>{
 assert.match(creative,/@media\(prefers-reduced-motion:reduce\)/);
 assert.match(creative,/\.nl-motion-track\{animation:none!important/);
 assert.match(css,/\.nl-home \.nl-header\{position:sticky;top:0/);
 assert.match(creative,/@media\(max-width:620px\)/);
 assert.match(creative,/nl-founder-serial/);
});
