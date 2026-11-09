/* Source fingerprint MW-TNW-2026-83e9c752 | Copyright (c) 2026 Michael Wiedeck. All rights reserved. */
/* Header and scroll controls used on TENNILO pages. No tracking or network calls. */
(()=>{'use strict';
const header=document.querySelector('.site-header');
const menu=document.querySelector('.nl-menu-toggle');
const nav=document.getElementById('nl-nav');
const topButton=document.querySelector('.nl-back-to-top');
const reduceMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
const english=document.documentElement.lang.toLowerCase().startsWith('en');
const closeMenu=()=>{
 if(!menu||!nav)return;
 nav.classList.remove('nl-nav-open');
 menu.setAttribute('aria-expanded','false');
 menu.setAttribute('aria-label',english?'Open menu':'Menü öffnen');
 menu.querySelector('span').textContent='☰';
};
if(menu&&nav){
 menu.addEventListener('click',()=>{
  const open=menu.getAttribute('aria-expanded')!=='true';
  nav.classList.toggle('nl-nav-open',open);
  menu.setAttribute('aria-expanded',String(open));
  menu.setAttribute('aria-label',open?(english?'Close menu':'Menü schließen'):(english?'Open menu':'Menü öffnen'));
  menu.querySelector('span').textContent=open?'×':'☰';
 });
 nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu()});
 document.addEventListener('click',e=>{if(!nav.contains(e.target)&&!menu.contains(e.target))closeMenu()});
 window.matchMedia('(min-width: 1181px)').addEventListener('change',closeMenu);
}
let ticking=false;
const update=()=>{
 const y=Math.max(0,window.scrollY||window.pageYOffset||0);
 if(header)header.classList.toggle('nl-scrolled',y>24);
 if(topButton){
  topButton.classList.toggle('nl-visible',y>500);
  const total=Math.max(1,document.documentElement.scrollHeight-window.innerHeight);
  topButton.style.setProperty('--nl-scroll-progress',Math.max(0,Math.min(100,y/total*100)).toFixed(1)+'%');
 }
 ticking=false;
};
window.addEventListener('scroll',()=>{if(!ticking){ticking=true;window.requestAnimationFrame(update)}},{passive:true});
window.addEventListener('resize',update,{passive:true});
if(topButton)topButton.addEventListener('click',()=>window.scrollTo({top:0,behavior:reduceMotion.matches?'auto':'smooth'}));
update();
})();
