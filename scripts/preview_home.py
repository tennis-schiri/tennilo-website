#!/usr/bin/env python3
"""Capture private design QA previews of the static TENNILO landing page.

The previews are committed under design-preview/ in GitHub. They are never
included in deploy-files.txt and are not exposed on the live website.
"""
from __future__ import annotations
import functools
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from threading import Thread
import shutil
from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[1]
OUT = BASE / 'design-preview'
OUT.mkdir(exist_ok=True)

class SilentHandler(SimpleHTTPRequestHandler):
    def log_message(self, *_args):
        pass

handler = functools.partial(SilentHandler, directory=str(BASE / "public"))
server = ThreadingHTTPServer(("127.0.0.1", 0), handler)
Thread(target=server.serve_forever, daemon=True).start()
base_url = f"http://127.0.0.1:{server.server_address[1]}"

try:
    with sync_playwright() as p:
        binary = shutil.which("google-chrome") or shutil.which("chromium") or shutil.which("chromium-browser")
        options = {"headless": True, "args": ["--no-sandbox"]}
        if binary:
            options["executable_path"] = binary
        browser = p.chromium.launch(**options)
        for width, height, label, language in [
            (1440, 980, "home-desktop", ""),
            (390, 844, "home-mobile", ""),
            (1440, 920, "home-english", "en/"),
        ]:
            context = browser.new_context(
                viewport={"width": width, "height": height},
                device_scale_factor=1,
                locale="de-DE" if not language else "en-GB",
                reduced_motion="reduce",
                color_scheme="light",
            )
            page = context.new_page()
            response = page.goto(f"{base_url}/{language}", wait_until="load")
            assert response and response.status == 200
            page.locator(".tn-product").first.wait_for()
            page.screenshot(path=str(OUT / f"{label}.jpg"), type="jpeg", quality=76)
            if language == "" and width == 1440:
                for section in ["projekte", "clubdisplay", "funktionen", "einblicke"]:
                    item = page.locator("#" + section)
                    # Native lazy-loading is asynchronous; ensure images are
                    # decoded before evaluating screenshots of the whole section.
                    for img in item.locator('img').all():
                        if img.is_visible():
                            img.scroll_into_view_if_needed()
                            img.evaluate("(el) => el.decode().catch(() => {})")
                    item.screenshot(path=str(OUT / f"section-{section}.jpg"),
                                    type="jpeg", quality=76, animations="disabled")
            if width == 390:
                assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 2"), "Mobile layout has horizontal overflow"
                for img in page.locator("#projekte img").all():
                    if img.is_visible():
                        img.scroll_into_view_if_needed()
                        img.evaluate("(el) => el.decode().catch(() => {})")
                page.locator("#projekte").screenshot(path=str(OUT / "products-mobile.jpg"),
                                                    type="jpeg", quality=75, animations="disabled")
            print(f"Rendered {label}: {width}x{height}", flush=True)
            context.close()
        # Also verify real dark-mode rendering of the redesigned page.
        dark = browser.new_context(
            viewport={"width": 1440, "height": 980},
            device_scale_factor=1,
            color_scheme="dark",
            reduced_motion="reduce",
        )
        dark_page = dark.new_page()
        dark_page.goto(f"{base_url}/", wait_until="load")
        dark_page.locator(".tn-hero-visual").wait_for()
        assert dark_page.evaluate("document.documentElement.getAttribute('data-theme')") == "dark"
        dark_page.screenshot(path=str(OUT / "home-dark.jpg"), type="jpeg", quality=76)
        for img in dark_page.locator("#projekte img").all():
            if img.is_visible():
                img.scroll_into_view_if_needed()
                img.evaluate("(el) => el.decode().catch(() => {})")
        dark_page.locator("#projekte").screenshot(path=str(OUT / "products-dark.jpg"),
                                                  type="jpeg", quality=76, animations="disabled")
        dark.close()
        browser.close()
finally:
    server.shutdown()

for file in sorted(OUT.glob("*.jpg")):
    assert file.stat().st_size > 10000, "Empty visual preview: " + file.name
    print(f"Design preview: {file.name} ({file.stat().st_size} bytes)")
