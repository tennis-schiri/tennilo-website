#!/usr/bin/env python3
"""Build TENNILO footer MW monograms from wiedeck.de's approved original artwork.

The exact source image is copied from tennis-schiri/wiedeck-website at
branding/mw-monogram-original.png (SHA fae8923791e5dedec2eac9b52103c7dca58e110f).
Reuses the cropping and alpha extraction applied to wiedeck.de.
No text is drawn or typeface substituted.
"""
from pathlib import Path
from PIL import Image, ImageFilter, ImageOps

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "branding" / "mw-monogram-original.png"
OUTPUT = ROOT / "public" / "assets"

original = Image.open(SOURCE).convert("RGB")
assert original.size == (379, 155), "Unexpected approved MW source dimensions"
art = original.crop((2, 8, 327, 131))
gray = ImageOps.grayscale(art)
alpha = gray.point(lambda p: 255 if p < 243 else 0)
alpha = alpha.filter(ImageFilter.MedianFilter(3)).filter(ImageFilter.GaussianBlur(0.55))
light = art.convert("RGBA")
light.putalpha(alpha)

levels = [min(255, round(145 + 104 * (p / 255) ** .85)) for p in range(256)]
base = gray.point(levels)
channels = [base.point(lambda x, offset=o: min(255, x + offset)) for o in (4, 9, 14)]
dark = Image.merge("RGBA", (*channels, alpha))

for mode, mark in (("light", light), ("dark", dark)):
    symbol = mark.resize((650, 246), Image.Resampling.LANCZOS)
    path = OUTPUT / f"mw-symbol-{mode}.png"
    symbol.save(path, optimize=True)
    assert symbol.mode == "RGBA", "MW mark must be RGBA"
    assert symbol.getpixel((0, 0))[3] == 0, "Outer MW background must be transparent"
    assert symbol.getchannel("A").getbbox(), "MW mark is empty"
    print(f"Prepared approved MW {mode} logo without text: {path}")
