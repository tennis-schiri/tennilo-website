"""Capture actual public TENNILO demo; no NTC data or admin credentials."""
from pathlib import Path
from playwright.sync_api import sync_playwright
import shutil
import re

OUT = Path("public/assets")
with sync_playwright() as p:
    exe = shutil.which("google-chrome") or shutil.which("chromium")
    browser = p.chromium.launch(executable_path=exe, args=["--no-sandbox"] if exe else [])
    page = browser.new_page(viewport={"width":1920,"height":1080}, device_scale_factor=1, locale="de-DE", timezone_id="Europe/Berlin")
    page.goto("https://clubdisplay.tennilo.de/", wait_until="domcontentloaded", timeout=45000)
    page.locator("footer .version").wait_for(timeout=20000)
    v=page.locator("footer .version").inner_text().strip()
    assert tuple(map(int,re.findall(r"\d+",v))) >= (0,4,80), v
    for name, selector in [
        ("start",".home-tiles-six .home-tile:nth-child(6)"),
        ("partners",".partner-board-card.has-request-qr:nth-child(4)"),
        ("teams",".fixture-card"),
        ("news",".matchball-card"),
        ("courts","#screen [data-action]")
    ]:
        action = "home" if name=="start" else name
        page.locator("#nav button[data-action='"+action+"']").click(timeout=15000)
        page.locator(selector).first.wait_for(state="visible",timeout=20000)
        if name=="start":
            page.locator(".community-demo-qr").nth(1).wait_for(timeout=15000)
        page.wait_for_timeout(700)
        page.screenshot(path=str(OUT/f"clubdisplay-demo-{name}.jpg"),type="jpeg",quality=88,animations="disabled")
        print("Captured",name,v)
    browser.close()
