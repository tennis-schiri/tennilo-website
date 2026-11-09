import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const load=p=>fs.readFileSync(path.join(root,p),'utf8');
const german=load('public/index.html');
const english=load('public/en/index.html');
const css=load('public/assets/score-home.css');
const js=load('public/assets/site-interactions.js');
const manifest=new Set(load('deploy-files.txt').trim().split(/\r?\n/));

const pages=[
 ['public/index.html','de','https://tennilo.de/'],
 ['public/en/index.html','en','https://tennilo.de/en/'],
 ['public/impressum.html','de','https://tennilo.de/impressum.html'],
 ['public/en/imprint.html','en','https://tennilo.de/en/imprint.html'],
 ['public/datenschutz.html','de','https://tennilo.de/datenschutz.html'],
 ['public/en/privacy.html','en','https://tennilo.de/en/privacy.html']
];

test('Both languages have canonical and reciprocal hreflang links',()=>{
 for(const [file,lang,canonical] of pages){
  const html=load(file);
  assert.match(html,new RegExp('<html lang="'+lang+'">'),file);
  assert.ok(html.includes('<link rel="canonical" href="'+canonical+'">'),file);
  assert.match(html,/<link rel="alternate" hreflang="de"/);
  assert.match(html,/<link rel="alternate" hreflang="en"/);
  assert.match(html,/<nav class="nl-lang-switch/);
  assert.ok(html.includes('lang="'+lang+'" hreflang="'+lang+'" aria-current="page"'),'Active language missing in '+file);
  assert.ok(manifest.has(file),'Deployment missing '+file);
 }
 assert.match(german,/href="\/en\/" lang="en"/);
 assert.match(english,/href="\/" lang="de"/);
});

test('English landing includes the same functional Score content and signup forms',()=>{
 const ids=s=>[...s.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
 const a=ids(german),b=ids(english);
 assert.deepEqual(b,a,'Section and form IDs must remain identical');
 assert.equal(new Set(b).size,b.length,'No duplicated IDs');
 for(const text of ['YOUR MATCH.','YOUR POINT.','Apple Watch','DEC 2026','Bad Saarow','Tournament','Umpire','Spring 2027'])assert.ok(english.includes(text),text);
 for(const key of ['action="/newsletter.php"','action="/founders.php"','name="consent"','name="email"','name="interests[]"','name="topics[]"'])if(key!=='name="topics[]"')assert.ok(english.includes(key),key);
 assert.equal((english.match(/<form\b/g)||[]).length,2);
 assert.equal((english.match(/name="lang" value="en"/g)||[]).length,2);
 assert.match(english,/href="\/en\/privacy\.html#newsletter-datenschutz"/);
 assert.match(english,/href="\/en\/privacy\.html#founders-datenschutz"/);
 for(const hash of english.matchAll(/href="#([^"]+)"/g))assert.ok(b.includes(hash[1]),'Broken anchor '+hash[1]);
 assert.doesNotMatch(english,/testseite|test-site|app\.tennis-schiri\.de|score\.tennilo\.de/i);
});

test('Translated legal content and form-related privacy anchors exist',()=>{
 const dePrivacy=load('public/datenschutz.html');
 const enPrivacy=load('public/en/privacy.html');
 assert.equal((enPrivacy.match(/<section\b/g)||[]).length,(dePrivacy.match(/<section\b/g)||[]).length);
 for(const token of ['Controller','Server logs','Hosting','Cookies','Founders waitlist','newsletter and email preferences','Your rights','14 days','24 hours','Article 6(1)(a) GDPR','id="founders-datenschutz"','id="newsletter-datenschutz"'])assert.ok(enPrivacy.includes(token),token);
 const imprint=load('public/en/imprint.html');
 for(const token of ['Michael Wiedeck','15366 Neuenhagen','Section 5','TENNILO Score','TENNILO Tournament','TENNILO Umpire'])assert.ok(imprint.includes(token),token);
 assert.match(imprint,/href="\/en\/privacy\.html"/);
});

test('English paths, language switch design and live upload all work together',()=>{
 assert.match(css,/\.nl-lang-switch a\[aria-current="page"\]/);
 assert.match(css,/@media\(max-width:1180px\)/);
 assert.match(js,/document\.documentElement\.lang/);
 assert.match(js,/english\?'Open menu':'Menü öffnen'/);
 assert.match(js,/min-width: 1181px/);
 const assets=[...english.matchAll(/(?:href|src)="(\/assets\/[^"?]+|\/favicon\.png)/g)].map(x=>x[1]);
 assert.ok(assets.length>=6,'English page must use absolute asset paths');
 for(const asset of new Set(assets))assert.ok(fs.existsSync(path.join(root,'public'+asset)),asset);
 const sitemap=load('public/sitemap.xml');
 for(const [, ,url] of pages)assert.ok(sitemap.includes('<loc>'+url+'</loc>'),url);
 assert.match(sitemap,/xmlns:xhtml=/);
});
