"""Build the compact, attribute-free view of an article that a proof-reader reads (see art_patch.py for how edits are applied back).

view(html, title) -> text:
  - <style>/<script> removed, the standard «محصول مرتبط» ad block replaced by one [[EXISTING-AD …]] line
  - <img …> -> [[IMG alt="…" w=…]], <a …> -> <a>, every other tag loses its attributes
  - text between tags is left byte-for-byte as in the post, so a phrase copied from the view can be located in the real HTML
"""
import re, html as _html

AD_START = re.compile(r'<div class="bsma-related-label">.*?</div>\s*<div class="bsma-ad-box"', re.S)

def find_div_end(s, start):
    """index just after the </div> that closes the <div …> opening at s[start]"""
    depth = 0
    for m in re.finditer(r'<div\b|</div>', s[start:]):
        depth += 1 if m.group(0).startswith('<div') else -1
        if depth == 0:
            return start + m.end()
    return None

def ad_blocks(s):
    """[(start, end, info)] for the long-standing «محصول مرتبط» blocks (label + .bsma-ad-box), and for new .bsma-x blocks"""
    out = []
    for m in re.finditer(r'<div class="bsma-related-label">', s):
        a = m.start()
        lab_end = find_div_end(s, a)
        rest = s[lab_end:lab_end + 400]
        m2 = re.match(r'\s*(<!--.*?-->\s*)?<div class="(bsma-ad-box|bsma-x)"', rest, re.S)
        if not m2:
            continue
        box_start = lab_end + rest.index('<div class="' + m2.group(2) + '"')
        e = find_div_end(s, box_start)
        out.append((a, e, s[a:e]))
    return out

def describe_ad(block):
    title = re.search(r'class="(?:bsma-ad-title|bsma-x-ttl)"[^>]*>([^<]*)<', block)
    sub = re.search(r'class="(?:bsma-ad-subtitle|bsma-x-sub)"[^>]*>([^<]*)<', block)
    prods = re.findall(r'<(?:div|span)[^>]*>([^<]{4,80})</(?:div|span)></a>', block)
    prods = [p for p in prods if p.strip()]
    imgs = re.findall(r'alt="([^"]{3,80})"', block)
    return (title.group(1).strip() if title else '', sub.group(1).strip() if sub else '', imgs or prods)

def view(s, title=''):
    blocks = ad_blocks(s)
    out, last = [], 0
    for a, e, blk in blocks:
        out.append(s[last:a])
        t, sub, p = describe_ad(blk)
        out.append(f'[[EXISTING-AD title="{t}" sub="{sub}" products="{" | ".join(p)}"]]')
        last = e
    out.append(s[last:])
    v = ''.join(out)
    v = re.sub(r'<style\b.*?</style>|<script\b.*?</script>', '', v, flags=re.S | re.I)
    v = re.sub(r'<!--.*?-->', '', v, flags=re.S)
    def img(m):
        tag = m.group(0)
        alt = re.search(r'alt="([^"]*)"', tag)
        w = re.search(r'\bwidth="(\d+)"', tag)
        return f'[[IMG alt="{alt.group(1) if alt else ""}" w={w.group(1) if w else "?"}]]'
    v = re.sub(r'<img\b[^>]*>', img, v)
    v = re.sub(r'<a\b[^>]*>', '<a>', v)
    v = re.sub(r'<(/?)(h[1-6]|p|ul|ol|li|table|thead|tbody|tr|td|th|strong|b|em|i|blockquote|div|span|figure|figcaption|br)\b[^>]*?(/?)>', lambda m: f'<{m.group(1)}{m.group(2)}{m.group(3)}>', v)
    v = re.sub(r'</?(span|div)>', '', v)
    v = re.sub(r'[ \t]+\n', '\n', v)
    v = re.sub(r'\n{3,}', '\n\n', v)
    return v.strip() + '\n'
