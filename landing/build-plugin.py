#!/usr/bin/env python3
"""Assemble the WordPress plugin zip from the landing page sources.

    python3 build-plugin.py

Output: landing/deploy/bsma-backdraft-story/  (+ .zip)  and landing/deploy/preview.html
(preview.html is a local stand-in for template.php so the production markup can be checked
without WordPress: serve landing/deploy and open /preview.html).
"""
import os, re, shutil, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'backdraft-flashover')
PLG = os.path.join(HERE, 'plugin-src')
OUT = os.path.join(HERE, 'deploy')
NAME = 'bsma-backdraft-story'
DEST = os.path.join(OUT, NAME)

shutil.rmtree(DEST, ignore_errors=True)
os.makedirs(os.path.join(DEST, 'assets'))
for f in ('bsma-backdraft-story.php', 'template.php'):
    shutil.copy(os.path.join(PLG, f), DEST)
for d in ('css', 'js', 'vendor', 'fonts', 'audio', 'img'):
    shutil.copytree(os.path.join(SRC, d), os.path.join(DEST, 'assets', d))

html = open(os.path.join(SRC, 'index.html'), encoding='utf-8').read()
body = re.search(r'<body>(.*?)<script src="vendor/gsap', html, re.S).group(1)
body = body.replace('src="img/', 'src="%BASE%img/')
open(os.path.join(DEST, 'body.html'), 'w', encoding='utf-8').write(body.strip() + '\n')
noscript = re.search(r'<noscript>.*?</noscript>', html, re.S).group(0)
open(os.path.join(DEST, 'head-extra.html'), 'w', encoding='utf-8').write(noscript + '\n')

# local preview that mimics template.php (BASE = plugin assets folder)
scripts = ['vendor/gsap.min.js', 'vendor/ScrollTrigger.min.js', 'vendor/howler.min.js', 'js/main.js', 'js/scene.js']
prev = f'''<!doctype html><html lang="fa-IR" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>preview</title><link rel="preload" href="{NAME}/assets/fonts/Vazirmatn-VF.woff2" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="{NAME}/assets/css/style.css">{noscript}</head><body class="bsma-story">
{body.replace('%BASE%', NAME + '/assets/')}
<script>window.BSMA_BASE = "{NAME}/assets/";</script>
''' + '\n'.join(f'<script src="{NAME}/assets/{s}"' + (' async' if s == 'js/scene.js' else '') + '></script>' for s in scripts) + '\n</body></html>\n'
open(os.path.join(OUT, 'preview.html'), 'w', encoding='utf-8').write(prev)

zp = os.path.join(OUT, NAME + '.zip')
if os.path.exists(zp):
    os.remove(zp)
with zipfile.ZipFile(zp, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, _, files in os.walk(DEST):
        for f in sorted(files):
            full = os.path.join(root, f)
            z.write(full, os.path.join(NAME, os.path.relpath(full, DEST)))
print('zip:', zp, os.path.getsize(zp), 'bytes')
