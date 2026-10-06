#!/usr/bin/env python3
"""Zip the article-UI plugin: landing/deploy/bsma-article-ui.zip (upload it in wp-admin → Plugins → Add New → Upload)."""
import os, zipfile
HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, '..', 'deploy'); NAME = 'bsma-article-ui'
os.makedirs(OUT, exist_ok=True); zp = os.path.join(OUT, NAME + '.zip')
with zipfile.ZipFile(zp, 'w', zipfile.ZIP_DEFLATED) as z:
    z.write(os.path.join(HERE, 'bsma-article-ui.php'), NAME + '/bsma-article-ui.php')
    z.write(os.path.join(HERE, 'assets', 'article.css'), NAME + '/assets/article.css')
print(zp, os.path.getsize(zp))
