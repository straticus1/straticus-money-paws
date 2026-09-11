"""Check actual alpha, export sizes, and produce an edge-inspection sheet."""
import json
from pathlib import Path
from PIL import Image, ImageDraw

root = Path(__file__).resolve().parent
web = root.parent / "packages/web/src/assets/home"
names = ["dog", "cat", "fox", "rabbit", "squeaky-moon", "rolling-acorn", "clover-crunch"]
backgrounds = ["#fffdf5", "#243c31", "#bc8657"]
sheet = Image.new("RGB", (3 * 260, len(names) * 280), "#fffdf5")
draw = ImageDraw.Draw(sheet)
report = []
for row, name in enumerate(names):
    source = Image.open(root / "cutouts" / f"{name}-v1.png")
    export = Image.open(web / f"{name}-cutout-v1.webp")
    assert source.mode == "RGBA" and export.mode == "RGBA", f"{name}: missing alpha"
    for image in [source, export]:
        alpha = image.getchannel("A")
        assert alpha.getextrema() == (0, 255), f"{name}: invalid alpha range"
        for point in [(0, 0), (image.width - 1, 0), (0, image.height - 1), (image.width - 1, image.height - 1)]:
            assert alpha.getpixel(point) == 0, f"{name}: opaque corner"
        counts = alpha.histogram()
        area = image.width * image.height
        assert 0.1 < counts[0] / area < 0.9, f"{name}: unexpected foreground coverage"
        assert counts[255] / area > 0.1, f"{name}: missing opaque foreground"
    expected_size = 512 if name in names[:4] else 384
    assert export.size == (expected_size, expected_size), f"{name}: export size"
    report.append({"asset": name, "source_dimensions": source.size,
                   "export_dimensions": export.size, "alpha_range": [0, 255],
                   "transparent_percent": round(counts[0] / area * 100, 1),
                   "export_bytes": (web / f"{name}-cutout-v1.webp").stat().st_size})
    thumb = export.copy()
    thumb.thumbnail((240, 240), Image.Resampling.LANCZOS)
    for column, color in enumerate(backgrounds):
        tile = Image.new("RGBA", (260, 260), color)
        tile.alpha_composite(thumb, ((260 - thumb.width) // 2, (260 - thumb.height) // 2))
        sheet.paste(tile.convert("RGB"), (column * 260, row * 280))
    draw.text((8, row * 280 + 263), name, fill="#142318")

(root / "cutouts/validation.json").write_text(json.dumps(report, indent=2) + "\n")
sheet.save(root / "cutouts/edge-review.png")
print(json.dumps(report, indent=2))
