#!/usr/bin/env python3
"""Prepare true-alpha TENNILO brand marks for the site without changing source logos.

Run in CI and before the manual FTPS upload: python scripts/prepare_transparent_logos.py
Requires Pillow. The source RGB/RGBA logos remain immutable.
"""
from pathlib import Path
from PIL import Image, ImageDraw

ASSETS = Path("public/assets")


def score_cutout(source, target, dark=False):
    """Isolate the exact original SCORE lettering from its navy photo backdrop."""
    image = Image.open(source).convert("RGB")
    width, height = image.size
    source_pixels = image.load()
    result = Image.new("RGBA", image.size, (0, 0, 0, 0))
    target_pixels = result.load()
    for y in range(height):
        ratio = y / max(1, height - 1)
        for x in range(width):
            red, green, blue = source_pixels[x, y]
            top, bottom = source_pixels[x, 0], source_pixels[x, height - 1]
            background = tuple(top[c] * (1 - ratio) + bottom[c] * ratio for c in range(3))
            difference = max(abs(value - background[c]) for c, value in enumerate((red, green, blue)))
            alpha = max(0.0, min(1.0, (difference - 19) / 75))
            alpha = alpha * alpha * (3 - 2 * alpha)
            if alpha <= 0.01:
                continue
            colored = blue - red > 42 and green - red > 24
            if not dark and not colored and red > 115 and green > 115 and blue > 115:
                foreground = (7, 30, 52)
            elif colored or dark:
                foreground = tuple(max(0, min(255, int(
                    (value - (1 - alpha) * background[c]) / max(alpha, 0.18)
                ))) for c, value in enumerate((red, green, blue)))
            else:
                foreground = (7, 30, 52)
            target_pixels[x, y] = (*foreground, round(255 * alpha))
    result.save(target, optimize=True)


def tournament_cutout(source, target):
    """Clip ONLY the original TT04 outer silhouette; preserve original frame & glyphs."""
    image = Image.open(source).convert("RGBA")
    width, height = image.size

    def curve(p0, p1, p2, p3, steps=25):
        result = []
        for k in range(1, steps + 1):
            t = k / steps
            u = 1 - t
            result.append((
                int(u**3 * p0[0] + 3*u*u*t*p1[0] + 3*u*t*t*p2[0] + t**3*p3[0]),
                int(u**3 * p0[1] + 3*u*u*t*p1[1] + 3*u*t*t*p2[1] + t**3*p3[1])
            ))
        return result

    # Original TT04 is 1361 x 390. Smooth, tilted rounded-plate silhouette.
    points = [(156, 13), (1245, 13)]
    points += curve((1245, 13), (1323, 13), (1353, 45), (1339, 121))
    points += [(1284, 308)]
    points += curve((1284, 308), (1273, 366), (1225, 384), (1190, 387))
    points += [(90, 387)]
    points += curve((90, 387), (11, 385), (-7, 352), (8, 283))
    points += [(77, 90)]
    points += curve((77, 90), (94, 31), (123, 13), (156, 13))
    polygon = [(round(x*width/1361), round(y*height/390)) for x, y in points]
    scale = 3
    mask = Image.new("L", (width*scale, height*scale), 0)
    ImageDraw.Draw(mask).polygon([(x*scale, y*scale) for x, y in polygon], fill=255)
    mask = mask.resize((width, height), Image.Resampling.LANCZOS)
    image.putalpha(mask)
    image.save(target, optimize=True)


def umpire_cutout(source, target):
    """Keep existing alpha if present; otherwise remove the opaque edge background."""
    image = Image.open(source)
    if image.mode in ("RGBA", "LA") and image.getextrema()[-1][0] < 255:
        image.save(target, optimize=True)
        return
    original = image.convert("RGB")
    width, height = original.size
    pixels = original.load()
    result = Image.new("RGBA", original.size, (0, 0, 0, 0))
    result_pixels = result.load()
    for y in range(height):
        ratio = y / max(1, height - 1)
        for x in range(width):
            red, green, blue = pixels[x, y]
            top, bottom = pixels[x, 0], pixels[x, height-1]
            background = tuple(top[c] * (1-ratio) + bottom[c]*ratio for c in range(3))
            diff = max(abs(v-background[c]) for c, v in enumerate((red, green, blue)))
            alpha = max(0.0, min(1.0, (diff - 23)/70))
            if alpha < 0.015:
                continue
            result_pixels[x, y] = (red, green, blue, round(255*alpha))
    result.save(target, optimize=True)


def umbrella_dark(source, target):
    """Make only the navy glyphs light for dark-mode; retain the TENNILO cyan accent."""
    image = Image.open(source).convert("RGBA")
    data = image.load()
    width, height = image.size
    for y in range(height):
        for x in range(width):
            r, g, b, a = data[x, y]
            if a > 0 and r < 80 and g < 125 and b < 160:
                data[x, y] = (239, 248, 255, a)
    image.save(target, optimize=True)


def verify_alpha(path):
    image = Image.open(path)
    if image.mode != "RGBA" or image.getextrema()[3][0] != 0:
        raise RuntimeError(f"Transparent PNG expected: {path}")
    print(f"Prepared {path}: {image.width}x{image.height}, RGBA")


def main():
    score_cutout(ASSETS/"tennilo-score-logo.png", ASSETS/"tennilo-score-cutout.png")
    score_cutout(ASSETS/"tennilo-score-logo.png", ASSETS/"tennilo-score-cutout-dark.png", dark=True)
    tournament_cutout(ASSETS/"tennilo-tournament-logo.png", ASSETS/"tennilo-tournament-cutout.png")
    umpire_cutout(ASSETS/"tennilo-umpire-logo.png", ASSETS/"tennilo-umpire-cutout.png")
    umbrella_dark(ASSETS/"tennilo-logo.png", ASSETS/"tennilo-logo-dark.png")
    for name in ("tennilo-score-cutout.png", "tennilo-score-cutout-dark.png",
                 "tennilo-tournament-cutout.png", "tennilo-umpire-cutout.png",
                 "tennilo-logo-dark.png"):
        verify_alpha(ASSETS/name)


if __name__ == "__main__":
    main()
