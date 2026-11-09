import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root=path.resolve(import.meta.dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const german=read('public/index.html');
const english=read('public/en/index.html');
const css=read('public/assets/court-edition.css');
const js=read('public/assets/site-interactions.js');
const manifest=new Set(read('deploy-files.txt').trim().split(/\r?\n/));
const pages=[
 ['public/index.html','de','https://tennilo.de/'],
 ['public/en/index.html','en','https://tennilo.de/en/'],
 ['public/impressum.html','de','https://tennilo.de/impressum.html'],
 ['public/en/imprint.html','en','https://tennilo.de/en/imprint.html'],
 ['public/datenschutz.html','de','https://tennilo.de/datenschutz.html'],
 ['public/en/privacy.html','en','https://tennilo.de/en/privacy.html']
];

test('DE/EN pages have correct canonical links and hreflang',()=>{
 for(const [file,lang,canonical] of pages){
  const html=read(file);
  assert.ok(html.includes('<html lang="'+lang+'">'),file);
  assert.ok(html.includes('<link rel="canonical" href="'+canonical+'">'),file);
  assert.match(html,/<link rel="alternate" hreflang="de"/);
  assert.match(html,/<link rel="alternate" hreflang="en"/);
  assert.ok(manifest.has(file),'Missing from FTPS manifest: '+file);
 }
 assert.match(german,/href="\/en\/" lang="en"/);
 assert.match(english,/href="\/" lang="de"/);
 assert.match(german,/href="\/" lang="de" hreflang="de" aria-current="page"/);
 assert.match(english,/href="\/en\/" lang="en" hreflang="en" aria-current="page"/);
});

test('English version fully represents the new editorial TENNILO homepage',()=>{
 const ids=page=>[...page.matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]);
 assert.deepEqual(ids(german),ids(english),'German and English IDs must match');
 for(const value of ['GAME.','SET.','FUTURE.','Apple Watch','BAD SAAROW','TOURNAMENT','UMPIRE','SPRING 2027'])assert.ok(english.includes(value),value);
 assert.match(english,/action="\/newsletter\.php"/);
 assert.match(english,/action="\/founders\.php"/);
 assert.equal((english.match(/<form\b/g)||[]).length,2);
 assert.equal((english.match(/name="lang" value="en"/g)||[]).length,2);
 assert.match(english,/href="\/en\/privacy\.html#newsletter-datenschutz"/);
 assert.match(english,/href="\/en\/privacy\.html#founders-datenschutz"/);
 for(const match of english.matchAll(/href="#([^"]+)"/g))assert.ok(ids(english).includes(match[1]),match[1]);
 assert.doesNotMatch(english,/app\.tennis-schiri\.de|testseite|test-site/);
});

test('English legal content remains complete',()=>{
 const dePrivacy=read('public/datenschutz.html');
 const enPrivacy=read('public/en/privacy.html');
 assert.equal((dePrivacy.match(/<section\b/g)||[]).length,(enPrivacy.match(/<section\b/g)||[]).length);
 for(const value of ['Controller','Server logs','Hosting','Cookies','Founders waitlist','Your rights','14 days','24 hours','id="founders-datenschutz"','id="newsletter-datenschutz"'])assert.ok(enPrivacy.includes(value),value);
 const imprint=read('public/en/imprint.html');
 for(const value of ['Michael Wiedeck','15366 Neuenhagen','Section 5','TENNILO Score','TENNILO Tournament','TENNILO Umpire'])assert.ok(imprint.includes(value),value);
 assert.match(imprint,/href="\/en\/privacy\.html"/);
});

test('Mobile language switching, navigation and assets are present',()=>{
 assert.match(css,/@media\(max-width:680px\)/);
 assert.match(css,/\.tc-languages a\[aria-current="page"\]/);
 assert.match(js,/document\.documentElement\.lang/);
 assert.match(js,/english\?'Open menu':'Menü öffnen'/);
 assert.match(js,/min-width: 1181px/);
 for(const asset of [...english.matchAll(/(?:href|src)="(\/assets\/[^"?]+|\/favicon\.png)/g)].map(x=>x[1])){
  assert.ok(fs.existsSync(path.join(root,'public'+asset)),asset);
  assert.ok(manifest.has('public'+asset),asset);
 }
 const sitemap=read('public/sitemap.xml');
 for(const [,,url] of pages)assert.ok(sitemap.includes('<loc>'+url+'</loc>'),url);
});
