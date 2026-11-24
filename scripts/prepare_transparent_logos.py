#!/usr/bin/env python3
"""Prepare true-alpha TENNILO brand marks for the site without changing source logos.

Run in CI and before the manual FTPS upload: python scripts/prepare_transparent_logos.py
Requires Pillow. The source RGB/RGBA logos remain immutable.
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter

ASSETS = Path("public/assets")


def score_cutout(source, target, dark=False):
    """Isolate letters without carrying the backdrop glow into the alpha mask."""
    image=Image.open(source).convert('RGB'); w,h=image.size; pix=image.load()
    core=Image.new('L',image.size); cp=core.load()
    for y in range(h):
        for x in range(w):
            r,g,b=pix[x,y]
            if r>145 or (g-r>72 and b-r>88 and g>100): cp[x,y]=255
    support=core.filter(ImageFilter.MaxFilter(3)); sp=support.load()
    result=Image.new('RGBA',image.size); out=result.load()
    for y in range(h):
        ratio=y/max(1,h-1)
        for x in range(w):
            if not sp[x,y]:continue
            r,g,b=pix[x,y]; bg=tuple(pix[x,0][c]*(1-ratio)+pix[x,h-1][c]*ratio for c in range(3))
            white=r>80 and r>g*.65
            if cp[x,y]:a=1
            elif white:a=max(0,min(1,(r-bg[0])/(245-bg[0])))
            else:a=max(0,min(1,((g-r)-(bg[1]-bg[0]))/100))
            if a<.04:continue
            if white and not dark:fg=(7,30,52)
            elif a==1:fg=(r,g,b)
            else:fg=tuple(max(0,min(255,round((v-(1-a)*bg[c])/max(a,.1)))) for c,v in enumerate((r,g,b)))
            out[x,y]=(*fg,round(255*a))
    result.save(target,optimize=True)


def curve(p0,p1,p2,p3,steps=60):
    return [tuple((1-t)**3*p0[i]+3*(1-t)**2*t*p1[i]+3*(1-t)*t*t*p2[i]+t**3*p3[i] for i in (0,1)) for t in (k/steps for k in range(1,steps+1))]


def tournament_cutout(source,target):
    """Follow the actual TT04 outer plate and remove the small rectangular cyan spill."""
    im=Image.open(source).convert('RGBA'); w,h=im.size
    points=[(162,15),(1232,15)]+curve((1232,15),(1281,15),(1314,52),(1305,108))+[(1248,307)]+curve((1248,307),(1236,353),(1202,381),(1168,381))+[(83,381)]+curve((83,381),(28,381),(-8,341),(12,282))+[(77,89)]+curve((77,89),(93,40),(124,15),(162,15))
    scale=4; mask=Image.new('L',(w*scale,h*scale)); draw=ImageDraw.Draw(mask)
    def polygon(ps):return [(round(x*w/1361*scale),round(y*h/390*scale)) for x,y in ps]
    draw.polygon(polygon(points),fill=255)
    repair=Image.new('L',(w*scale,h*scale)); dr=ImageDraw.Draw(repair)
    region=[(1145,240),(1215.5,240),(1202,286)]+curve((1202,286),(1198,312),(1180,337),(1150,337))+[(1145,337)]
    dr.polygon(polygon(region),fill=255)
    repair=repair.resize(im.size,Image.Resampling.LANCZOS)
    patch=im.copy(); pp=patch.load(); ip=im.load()
    for y in range(round(235*h/390),round(337*h/390)):
        c=ip[round(1158*w/1361),y]
        for x in range(round(1144*w/1361),round(1217*w/1361)): pp[x,y]=c
    im=Image.composite(patch,im,repair); im.putalpha(mask.resize(im.size,Image.Resampling.LANCZOS));im.save(target,optimize=True)


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
            if a > 0 and r < 80 and g < 125 and b < 160 and g - r < 55:
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
