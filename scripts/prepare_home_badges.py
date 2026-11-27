#!/usr/bin/env python3
"""Build matching alpha-framed badges from approved TENNILO source logos."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter
from prepare_transparent_logos import tournament_cutout

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "public" / "assets"
BRANDS = ROOT / "brand-assets" / "clubdisplay"

def curve(p0, p1, p2, p3, steps=80):
    return [
        tuple((1-t)**3*p0[i] + 3*(1-t)**2*t*p1[i] + 3*(1-t)*t*t*p2[i] + t*t*t*p3[i] for i in [0,1])
        for t in (k/steps for k in range(1,steps+1))
    ]

def plate_mask(size):
    """Mask the real slanted TT04/SC01 plate; omit rectangular background glow."""
    w,h = size
    points = [(162,15),(1232,15)] + curve((1232,15),(1281,15),(1314,52),(1305,108)) + \
        [(1248,307)] + curve((1248,307),(1236,353),(1202,381),(1168,381)) + \
        [(83,381)] + curve((83,381),(28,381),(-8,341),(12,282)) + \
        [(77,89)] + curve((77,89),(93,40),(124,15),(162,15))
    scale = 3
    mask = Image.new("L",(w*scale,h*scale))
    ImageDraw.Draw(mask).polygon([(round(x*w/1361*scale),round(y*h/390*scale)) for x,y in points],fill=255)
    return mask.resize((w,h),Image.Resampling.LANCZOS)

def framed_cutout(source,target):
    image = Image.open(source).convert("RGBA")
    if image.size != (1361,390): raise ValueError(f"Unexpected framed logo dimensions in {source}: {image.size}")
    image.putalpha(plate_mask(image.size))
    image.save(target,optimize=True)

def clubdisplay_badge():
    """Use the verified framed ClubDisplay web master from the official brand assets."""
    source = BRANDS/"TENNILO_ClubDisplay_Rahmenlogo_Web_Transparent.png"
    image = Image.open(source).convert("RGBA")
    if image.size != (1361,390): raise ValueError("Unexpected ClubDisplay frame dimensions")
    image.save(ASSETS/"tn-overview-clubdisplay.png",optimize=True)


def umpire_badge():
    original = Image.open(ASSETS/"tennilo-umpire-logo.png").convert("RGBA")
    image = original.crop(original.getchannel("A").getbbox())
    image.thumbnail((1361,390),Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA",(1361,390))
    canvas.alpha_composite(image,((1361-image.width)//2,(390-image.height)//2))
    canvas.save(ASSETS/"tn-overview-umpire.png",optimize=True)

def main():
    framed_cutout(ASSETS/"tennilo-score-badge.png",ASSETS/"tn-overview-score.png")
    # Both badges share the repaired TT04 plate, never the uncorrected original.
    tournament_cutout(ASSETS/"tennilo-tournament-logo.png",ASSETS/"tennilo-tournament-cutout.png")
    framed_cutout(ASSETS/"tennilo-tournament-cutout.png",ASSETS/"tn-overview-tournament.png")
    clubdisplay_badge()
    umpire_badge()
    for label in ("score","clubdisplay","tournament","umpire"):
        path = ASSETS/f"tn-overview-{label}.png"
        with Image.open(path) as image:
            if image.mode != "RGBA" or image.size != (1361,390) or image.getchannel("A").getextrema()[0] != 0:
                raise ValueError(f"Transparent 1361x390 PNG required: {path}")
        print(f"Prepared TENNILO {label} badge: {path}")

if __name__ == "__main__":
    main()
