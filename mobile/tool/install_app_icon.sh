#!/usr/bin/env bash
# Cuts the rendered mark into every icon slot the three platforms ask for.
#
# Run tool/generate_app_icon.dart first; this only resizes what that produced,
# so the icon on a phone is always the mark the app draws on startup.
#
#   flutter test tool/generate_app_icon.dart && bash tool/install_app_icon.sh
set -euo pipefail

cd "$(dirname "$0")/.."

square="tool/app_icon.png"
maskable="tool/app_icon_maskable.png"

for file in "$square" "$maskable"; do
  [ -f "$file" ] || { echo "Missing $file — run the generator first."; exit 1; }
done

cut() { # cut <source> <side> <destination>
  sips -z "$2" "$2" "$1" --out "$3" >/dev/null
}

echo "==> iOS"
ios="ios/Runner/Assets.xcassets/AppIcon.appiconset"
# Driven by the catalogue rather than a list typed out here: a slot added by
# Xcode that nothing filled shows up as a blank icon on exactly one device.
python3 - "$ios" <<'PY'
import json, subprocess, sys
from pathlib import Path

catalogue = Path(sys.argv[1])
images = json.loads((catalogue / 'Contents.json').read_text())['images']

for image in images:
    name = image.get('filename')
    if name is None:
        continue
    side = round(float(image['size'].split('x')[0]) * float(image['scale'].rstrip('x')))
    subprocess.run(
        ['sips', '-z', str(side), str(side), 'tool/app_icon.png',
         '--out', str(catalogue / name)],
        check=True, stdout=subprocess.DEVNULL,
    )
    print(f'    {name} ({side}px)')
PY

# Apple rejects an icon that carries an alpha channel, opaque or not, and it
# does so at submission - long after the icon looked right on every device it
# was tested on. Flattened here rather than discovered there.
python3 - "$ios" <<'FLATTEN'
import sys
from pathlib import Path
from PIL import Image

for icon in sorted(Path(sys.argv[1]).glob('*.png')):
    with Image.open(icon) as image:
        if image.mode == 'RGB':
            continue

        flat = Image.new('RGB', image.size, (247, 248, 245))
        flat.paste(image, mask=image.convert('RGBA').split()[3])
        flat.save(icon)

print('    flattened: no alpha channel')
FLATTEN

echo "==> Android"
for pair in "mdpi 48" "hdpi 72" "xhdpi 96" "xxhdpi 144" "xxxhdpi 192"; do
  set -- $pair
  cut "$square" "$2" "android/app/src/main/res/mipmap-$1/ic_launcher.png"
  echo "    mipmap-$1 ($2px)"
done

echo "==> Web"
cut "$square" 192 web/icons/Icon-192.png
cut "$square" 512 web/icons/Icon-512.png
cut "$maskable" 192 web/icons/Icon-maskable-192.png
cut "$maskable" 512 web/icons/Icon-maskable-512.png
cut "$square" 16 web/favicon.png
echo "    icons and favicon"

echo "==> Done."
