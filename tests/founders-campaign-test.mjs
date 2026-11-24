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
  for(const name of ['Bronze','Silver','Gold','Platinum','Club','Club Plus','Founding Partner'])assert.ok(html.includes(name));
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
