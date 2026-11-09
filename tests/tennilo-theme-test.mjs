import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = path.resolve(import.meta.dirname, '..');
const read = file => fs.readFileSync(path.join(root,file),'utf8');
const themeScript = read('public/assets/theme.js');
const themeStyles = read('public/assets/theme.css');
const localStoreKey = 'tennilo-color-theme';

function simulate({ language='de', systemDark=false, savedChoice=null, storageBlocked=false, existingStore=null }={}) {
 const store = existingStore || new Map();
 if(savedChoice) store.set(localStoreKey,savedChoice);
 const rootAttrs = new Map();
 const html={lang:language,style:{},setAttribute(k,v){rootAttrs.set(k,String(v))},getAttribute(k){return rootAttrs.get(k)||null}};
 const buttonAttrs=new Map();
 const label={textContent:''};
 const events={};
 const mediaHandlers={};
 const button={
  addEventListener(type,callback){events[type]=callback},
  querySelector(selector){return selector==='[data-theme-label]'?label:null},
  setAttribute(key,value){buttonAttrs.set(key,String(value))},
  getAttribute(key){return buttonAttrs.get(key)||null}
 };
 const metaAttrs=new Map();
 const meta={setAttribute(key,value){metaAttrs.set(key,value)},getAttribute(key){return metaAttrs.get(key)}};
 const media={
  matches:systemDark,
  addEventListener(name,callback){mediaHandlers[name]=callback}
 };
 const storage={
  getItem(k){if(storageBlocked)throw Error('storage blocked');return store.has(k)?store.get(k):null},
  setItem(k,v){if(storageBlocked)throw Error('storage blocked');store.set(k,v)}
 };
 const window={matchMedia:()=>media,localStorage:storage,addEventListener(name,callback){events['window:'+name]=callback}};
 const document={
  documentElement:html,
  readyState:'complete',
  querySelector(selector){return selector==='meta[name="theme-color"]'?meta:null},
  querySelectorAll(selector){return selector==='[data-theme-toggle]'?[button]:[]}
 };
 vm.runInNewContext(themeScript,{document,window},{timeout:1500});
 return{
  html,button,label,meta,store,media,
  click(){events.click()},
  systemChange(dark){media.matches=dark;mediaHandlers.change?.({matches:dark})},
  storageChange(key=localStoreKey){events['window:storage']?.({key})}
 };
}

test('Dark mode defaults to the OS preference and keeps controls accessible',()=>{
 const dark=simulate({systemDark:true});
 assert.equal(dark.html.getAttribute('data-theme'),'dark');
 assert.equal(dark.html.style.colorScheme,'dark');
 assert.equal(dark.meta.getAttribute('content'),'#0b1425');
 assert.equal(dark.button.getAttribute('aria-pressed'),'true');
 assert.equal(dark.button.getAttribute('aria-label'),'Hellmodus einschalten');
 assert.equal(dark.label.textContent,'Hell');
 const light=simulate({language:'en',systemDark:false});
 assert.equal(light.html.getAttribute('data-theme'),'light');
 assert.equal(light.button.getAttribute('aria-pressed'),'false');
 assert.equal(light.button.getAttribute('aria-label'),'Enable dark mode');
 assert.equal(light.meta.getAttribute('content'),'#F7F4ED');
});

test('Manual switch persists across DE/EN and overrides later system changes',()=>{
 const store=new Map();
 const de=simulate({systemDark:true,existingStore:store});
 de.click();
 assert.equal(store.get(localStoreKey),'light');
 assert.equal(de.html.getAttribute('data-theme'),'light');
 de.systemChange(false);
 de.systemChange(true);
 assert.equal(de.html.getAttribute('data-theme'),'light','Manual choice remains authoritative');
 const en=simulate({language:'en',systemDark:true,existingStore:store});
 assert.equal(en.html.getAttribute('data-theme'),'light');
 en.click();
 assert.equal(store.get(localStoreKey),'dark');
 assert.equal(en.button.getAttribute('aria-label'),'Enable light mode');
 de.storageChange();
 assert.equal(de.html.getAttribute('data-theme'),'dark','Other tabs synchronize changes');
});

test('System changes apply when no manual choice; storage may be disabled',()=>{
 const s=simulate({language:'en',systemDark:false});
 s.systemChange(true);
 assert.equal(s.html.getAttribute('data-theme'),'dark');
 s.systemChange(false);
 assert.equal(s.html.getAttribute('data-theme'),'light');
 const blocked=simulate({systemDark:false,storageBlocked:true});
 blocked.click();
 assert.equal(blocked.html.getAttribute('data-theme'),'dark','Manual toggle still works without storage');
 blocked.systemChange(true);
 assert.equal(blocked.html.getAttribute('data-theme'),'dark');
 blocked.systemChange(false);
 assert.equal(blocked.html.getAttribute('data-theme'),'light','Without storage, the system preference stays active');
});

test('All six public pages have the same early theme initialization and toggle',()=>{
 const pages=['public/index.html','public/en/index.html','public/impressum.html','public/en/imprint.html','public/datenschutz.html','public/en/privacy.html'];
 const manifest=new Set(read('deploy-files.txt').trim().split(/\r?\n/));
 for(const page of pages){
  const html=read(page);
  assert.match(html,/<script src="\/assets\/theme\.js\?v=1"><\/script>/,page);
  assert.match(html,/<link rel="stylesheet" href="\/assets\/theme\.css\?v=1">/,page);
  assert.ok(html.indexOf('theme.js?v=1')<html.indexOf('styles.css?v=9'),'Theme should be chosen before CSS loads on '+page);
  assert.match(html,/<button[^>]*data-theme-toggle[^>]*aria-pressed="false"/,page);
  assert.ok(html.includes('tc-icon-moon')&&html.includes('tc-icon-sun'));
  assert.ok(manifest.has(page),page+' missing from deploy list');
 }
 for(const file of ['public/assets/theme.js','public/assets/theme.css'])assert.ok(manifest.has(file),file+' missing from deploy list');
 assert.match(themeScript,/localStorage/);
 assert.doesNotMatch(themeScript,/fetch\(|XMLHttpRequest|document\.cookie/);
});

test('Night design styles every section, signup controls and legal pages',()=>{
 for(const selector of [
  '.tc-header','.tc-hero','.tc-score-stage','.tc-demo','.tc-manifesto',
  '.tc-features','.tc-beta','.tc-timeline','.tc-projects','.tc-updates',
  '.tc-newsletter','.tc-founders','.tc-outro','.tc-footer'
 ])assert.ok(themeStyles.includes('html[data-theme="dark"] '+selector),'Missing dark '+selector);
 assert.ok(themeStyles.includes('html[data-theme="dark"] .tc-home .founders-form'));
 assert.ok(themeStyles.includes('html[data-theme="dark"] .tc-home .founders-form fieldset label'));
 assert.ok(themeStyles.includes('html[data-theme="dark"] body:not(.tc-home) .site .legal-content'));
 assert.match(themeStyles,/@media\(max-width:680px\)/);
 assert.match(themeStyles,/\.tc-theme-toggle:focus-visible/);
});

test('Privacy pages disclose local-only appearance preference in both languages',()=>{
 const de=read('public/datenschutz.html');
 const en=read('public/en/privacy.html');
 assert.match(de,/Local Storage/);
 assert.match(de,/nicht an uns übertragen/);
 assert.match(en,/local storage/);
 assert.match(en,/not transmitted to us/);
});
