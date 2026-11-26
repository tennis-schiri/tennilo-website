#!/usr/bin/env python3
"""Verify uploaded TENNILO pages and their shared framed product logos."""
import io
import os
import time
import urllib.request

from PIL import Image

ROOT = "https://tennilo.de"
BUILD = os.getenv("BUILD_SHA", "manual")[:12]
PRODUCTS = ("score", "clubdisplay", "tournament", "umpire")


def retrieve(path):
    sep = "&" if "?" in path else "?"
    url = f"{ROOT}{path}{sep}deploy_check={BUILD}"
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "TENNILO-Deploy-Verification/2.0",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache",
        },
    )
    with urllib.request.urlopen(request, timeout=18) as response:
        return response.read()


def verify_site():
    for lang in ("de", "en"):
        prefix = "/en" if lang == "en" else ""
        homepage = retrieve(f"{prefix}/").decode("utf-8")
        if "TENNILO®" in homepage or "TENNILO (R)" in homepage:
            raise RuntimeError(f"{lang}: unwanted trademark on the homepage")
        if '<details class="tc-nav-products' not in homepage:
            raise RuntimeError(f"{lang}: products dropdown missing")
        if "product-navigation.css" not in homepage:
            raise RuntimeError(f"{lang}: navigation stylesheet missing")
        if "transparent-logos.css" not in homepage:
            raise RuntimeError(f"{lang}: transparency stylesheet missing")

        for product in PRODUCTS:
            image_path = f'/assets/tn-overview-{product}.png'
            if f'src="{image_path}"' not in homepage:
                raise RuntimeError(f"{lang}: homepage badge missing: {product}")
            if f'href="{prefix}/{product}/"' not in homepage:
                raise RuntimeError(f"{lang}: product link missing: {product}")
            details = retrieve(f"{prefix}/{product}/").decode("utf-8")
            if f'src="{image_path}"' not in details:
                raise RuntimeError(f"{lang}: detail page badge missing: {product}")
            if '<details class="tc-nav-products' not in details:
                raise RuntimeError(f"{lang}: detail page navigation missing: {product}")

        if 'class="tn-club-tiers"' not in homepage:
            raise RuntimeError(f"{lang}: BASIC/PLUS teaser missing from homepage")
        clubdisplay = retrieve(f"{prefix}/clubdisplay/").decode("utf-8")
        if "cd-datasheet-grid" not in clubdisplay or "cd-compare-table" not in clubdisplay:
            raise RuntimeError(f"{lang}: ClubDisplay comparison / PDF cards not live")
        if "window.print()" in clubdisplay:
            raise RuntimeError(f"{lang}: obsolete print-data-sheet button is still live")
        founders = retrieve(f"{prefix}/founders/").decode("utf-8")
        for product in ("Score", "Tournament", "Umpire", "ClubDisplay"):
            if f"<h3>TENNILO {product}</h3>" not in founders:
                raise RuntimeError(f"{lang}: founders project missing: {product}")

    for document in (
        "tennilo-clubdisplay-produktdatenblatt-basic-plus.pdf",
        "tennilo-clubdisplay-funktionsuebersicht.pdf",
    ):
        content = retrieve(f"/assets/{document}")
        if not content.startswith(b"%PDF-") or len(content) < 25000:
            raise RuntimeError(f"{document}: published PDF is missing or invalid")

    for product in PRODUCTS:
        badge = Image.open(io.BytesIO(retrieve(f"/assets/tn-overview-{product}.png")))
        if badge.mode != "RGBA" or badge.size != (1361, 390):
            raise RuntimeError(f"{product}: expected RGBA badge with size 1361x390")
        if badge.getpixel((0, 0))[3] != 0:
            raise RuntimeError(f"{product}: logo has an opaque outer corner")

    print("Live verification passed: DE/EN product navigation, detail pages and transparent badges.")


def main():
    for attempt in range(1, 5):
        try:
            verify_site()
            return
        except Exception as exc:
            print(f"Verification attempt {attempt}/4 failed: {exc}", flush=True)
            if attempt == 4:
                raise
            time.sleep(8)


if __name__ == "__main__":
    main()
