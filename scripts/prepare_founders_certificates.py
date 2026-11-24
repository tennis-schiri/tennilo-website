"""Extract only the approved, unpersonalized certificate JPEG backgrounds."""
from pathlib import Path
from zipfile import ZipFile
from io import BytesIO
from PIL import Image

root = Path(__file__).resolve().parents[1]
codes = ["fb01","fb02","fb03","fb04","fb05","fc01","fc02","fc03","fc04"]
target = root / "public/assets/founders-certificates"
target.mkdir(parents=True, exist_ok=True)
for lang in ("de", "en"):
    names = [f"{lang}-{code}.jpg" for code in codes]
    with ZipFile(root / f"brand-assets/founders/founders-certificates-{lang}.zip") as archive:
        if sorted(archive.namelist()) != sorted(names):
            raise SystemExit("Unexpected certificate archive entries")
        for name in names:
            data = archive.read(name)
            with Image.open(BytesIO(data)) as image:
                if image.format != "JPEG" or image.mode != "RGB" or not 1.40 < image.width / image.height < 1.43:
                    raise SystemExit("Invalid certificate background: " + name)
                image.verify()
            (target / name).write_bytes(data)
print("18 approved certificate backgrounds ready")
