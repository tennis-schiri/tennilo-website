#!/usr/bin/env python3
"""Capture genuine UI screenshots from the currently checked-out TENNILO Score demo.

Do not recreate score screens or invent styling. Screenshots are rendered from
tennilo-score/prototype/index.html in a headless Chromium browser.
"""
from pathlib import Path
from collections import deque
import os
import shutil
from PIL import Image, ImageFilter
from playwright.sync_api import sync_playwright

SOURCE = Path(os.environ.get("SCORE_DEMO_HTML", ".score-source/prototype/index.html"))
DEST = Path("public/assets")
DEST.mkdir(parents=True, exist_ok=True)

def capture(locator, filename):
    locator.wait_for(state="visible", timeout=15000)
    locator.screenshot(path=str(DEST / filename), animations="disabled", timeout=30000)
    print(f"Captured authentic demo UI: {filename} ({(DEST / filename).stat().st_size} bytes)", flush=True)

def remove_device_background(filename):
    """Cut the screenshot's *external* rectangular corner matte away.

    The TENNILO device bezel, original screen and all typography remain
    pixel-for-pixel from the actual interactive demo. We only remove the
    contiguous outer flat background reachable from image corners.
    The transparent alpha survives on light and dark website backgrounds.
    """
    path = DEST / filename
    original = Image.open(path).convert("RGBA")
    width, height = original.size
    pixels = original.load()
    alpha = Image.new("L", (width, height), 255)
    output = alpha.load()
    seeds = [(0, 0), (width - 1, 0), (0, height - 1), (width - 1, height - 1)]
    background_colors = [pixels[x, y][:3] for x, y in seeds]
    # The flat color around the real rounded bezel is always connected
    # to the corners. The different-color watch/phone casing is a barrier.
    queue = deque(seeds)
    examined = bytearray(width * height)
    removed = 0
    while queue:
        x, y = queue.popleft()
        pos = y * width + x
        if examined[pos]:
            continue
        examined[pos] = 1
        color = pixels[x, y][:3]
        if min(max(abs(color[c] - bg[c]) for c in range(3))
               for bg in background_colors) > 19:
            continue
        output[x, y] = 0
        removed += 1
        if x > 0: queue.append((x - 1, y))
        if x + 1 < width: queue.append((x + 1, y))
        if y > 0: queue.append((x, y - 1))
        if y + 1 < height: queue.append((x, y + 1))

    # Remove the last dark, anti-aliased pixel fringe from the old matte
    # and produce a clean, soft silhouette with true alpha transparency.
    alpha = alpha.filter(ImageFilter.MinFilter(3))
    alpha = alpha.filter(ImageFilter.GaussianBlur(0.55))
    original.putalpha(alpha)
    if removed < 50:
        raise RuntimeError(f"No background matte found around {filename}")
    for x, y in seeds:
        if original.getpixel((x, y))[3] != 0:
            raise RuntimeError(f"Opaque corner remains in {filename}: {x},{y}")
    if original.getpixel((width // 2, height // 2))[3] < 240:
        raise RuntimeError(f"Screen contents accidentally removed in {filename}")
    original.save(path, optimize=True)
    print(f"Freigestellt {filename}: {removed} background pixels removed, RGBA, {width}x{height}", flush=True)


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
        for filename in ("score-demo-watch.png", "score-demo-phone.png", "score-demo-scoreboard.png"):
            remove_device_background(filename)
        context.close()
        browser.close()

if __name__ == "__main__":
    main()
