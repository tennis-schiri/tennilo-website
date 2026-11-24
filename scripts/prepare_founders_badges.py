"""Extract the nine approved web badge images from the versioned brand archive."""
from pathlib import Path
from zipfile import ZipFile
from io import BytesIO
from PIL import Image

root = Path(__file__).resolve().parents[1]
names = ["fb01-bronze.webp","fb02-silver.webp","fb03-gold.webp","fb04-platinum.webp","fb05-diamond.webp","fc01-club.webp","fc02-club-plus.webp","fc03-founding-partner.webp","fc04-founding-premium-partner.webp"]
target = root / "public/assets/founders-badges"
target.mkdir(parents=True, exist_ok=True)
with ZipFile(root / "brand-assets/founders/TENNILO-Founders-Webabzeichen-V1.zip") as archive:
    if sorted(archive.namelist()) != sorted(names):
        raise SystemExit("Unexpected badge archive entries")
    for name in names:
        data = archive.read(name)
        with Image.open(BytesIO(data)) as image:
            if image.format != "WEBP" or image.size != (360, 360) or image.mode != "RGBA":
                raise SystemExit("Invalid badge: " + name)
            if image.getchannel("A").getextrema()[0] != 0:
                raise SystemExit("No transparency: " + name)
        (target / name).write_bytes(data)
print("Nine approved Founders badges ready")
