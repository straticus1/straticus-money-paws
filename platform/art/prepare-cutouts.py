"""Prepare the approved AI artwork locally; preserve every source PNG.

Requires Python 3.12 and rembg[cpu]==2.0.67. Downloads the isnet-general-use
model on first use, then runs locally. Set U2NET_HOME to a writable model cache.
Usage: python prepare-cutouts.py [dog cat fox rabbit ...]
"""
from pathlib import Path
import json
import sys

from PIL import Image
from rembg import new_session, remove

root = Path(__file__).resolve().parent
names = sys.argv[1:] or ["dog", "cat", "fox", "rabbit", "squeaky-moon", "rolling-acorn", "clover-crunch"]
allowed = {"dog", "cat", "fox", "rabbit", "squeaky-moon", "rolling-acorn", "clover-crunch"}
if not set(names) <= allowed:
    raise SystemExit("Unknown asset name")
dest = root / "cutouts"
dest.mkdir(exist_ok=True)
for name in names:
    if (dest / f"{name}-v1.png").exists():
        raise SystemExit(f"Refusing to overwrite {name}-v1.png")

session = new_session("isnet-general-use", providers=["CPUExecutionProvider"])
for name in names:
    original = Image.open(root / "source" / f"{name}-v1.png").convert("RGB")
    cutout = remove(original, session=session).convert("RGBA")
    alpha = cutout.getchannel("A")
    # Eliminate barely visible model noise outside the silhouette.
    alpha = alpha.point(lambda value: 0 if value < 8 else 255 if value > 250 else value)
    histogram = alpha.histogram()
    pixels = cutout.width * cutout.height
    if histogram[0] < pixels * 0.1 or histogram[255] < pixels * 0.1:
        raise SystemExit(f"Invalid foreground mask for {name}; inspect before continuing")
    cutout.putalpha(alpha)
    cutout.save(dest / f"{name}-v1.png")
    print(json.dumps({"asset": name, "size": cutout.size, "bounds": alpha.getbbox(),
                      "transparent_percent": round(histogram[0] / pixels * 100, 1)}), flush=True)
