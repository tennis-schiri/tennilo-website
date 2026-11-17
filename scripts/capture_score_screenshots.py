#!/usr/bin/env python3
"""Capture genuine UI screenshots from the currently checked-out TENNILO Score demo.

Do not recreate score screens or invent styling. Screenshots are rendered from
tennilo-score/prototype/index.html in a headless Chromium browser.
"""
from pathlib import Path
import os
import shutil
from playwright.sync_api import sync_playwright

SOURCE = Path(os.environ.get("SCORE_DEMO_HTML", ".score-source/prototype/index.html"))
DEST = Path("public/assets")
DEST.mkdir(parents=True, exist_ok=True)

def capture(locator, filename):
    locator.wait_for(state="visible", timeout=15000)
    locator.screenshot(path=str(DEST / filename), animations="disabled", timeout=30000)
    print(f"Captured authentic demo UI: {filename} ({(DEST / filename).stat().st_size} bytes)", flush=True)

def main():
    if not SOURCE.exists():
        raise SystemExit(f"Demo source missing: {SOURCE}")
    with sync_playwright() as playwright:
        browser_binary = shutil.which("google-chrome") or shutil.which("chromium") or shutil.which("chromium-browser")
        launch = {"headless": True, "args": ["--no-sandbox"]}
        if browser_binary:
            launch["executable_path"] = browser_binary
        browser = playwright.chromium.launch(**launch)
        context = browser.new_context(
            viewport={"width": 1540, "height": 1100},
            device_scale_factor=2,
            locale="de-DE",
            timezone_id="Europe/Berlin",
            reduced_motion="reduce",
        )
        page = context.new_page()
        page.goto(SOURCE.resolve().as_uri(), wait_until="load", timeout=30000)
        # Hide the actual demo splash animation; no UI is redrawn or replaced.
        page.locator(".demo-splash").evaluate_all(
            "(elements) => elements.forEach(el => el.classList.add('hidden'))"
        )
        page.locator("#mode").select_option("single")
        page.locator("#apply").click()
        page.locator("#firstServeA").click(timeout=12000)
        # A real, plausible in-game score for the product showcase.
        for side in ("A", "A", "B", "A", "B"):
            page.locator("#point" + side).click(timeout=12000)
        page.locator("#view-match").wait_for(state="visible")
        page.locator("#phoneInsightsStats").click()
        page.wait_for_timeout(350)
        capture(page.locator("#watchHousing"), "score-demo-watch.png")
        capture(page.locator("#phone"), "score-demo-phone.png")
        # This is the existing demo's landscape scoreboard view, not an illustration.
        page.locator("#phoneScoreboardOpen").click()
        page.locator("#phoneScoreboard").wait_for(state="visible")
        capture(page.locator("#phoneScoreboard"), "score-demo-scoreboard.png")
        context.close()
        browser.close()

if __name__ == "__main__":
    main()
