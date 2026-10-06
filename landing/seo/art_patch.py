"""Apply a reviewed patch (fixes / callouts / product ad) to an article, with guards.

patch = {
  "id": 123,
  "fixes":   [{"old": "...", "new": "...", "kind": "typo|grammar|punct|wording|fact"}],   # plain text inside one text node
  "callouts":[{"para_starts": "first words of the paragraph", "type": "note|tip|warn|key"}],     # max 2
  "ad": {"existing": "keep|replace|remove",
         "new": {"products":[id,...], "section_heading": "heading text | TOP", "title": "...", "text": "...", "cta": "..."} | null},
  "flags":   ["..."]
}
apply_patch(html, patch, catalog) -> (new_html, report)   report: list of (level, message)
"""
import re, html as H, json
from art_tools import SPLIT, text_of
from art_view import ad_blocks, find_div_end

LABEL = {'note': 'نکته', 'tip': 'پیشنهاد', 'warn': 'هشدار', 'key': 'نکته‌ی کلیدی'}
CTA_OK = ['مشاهده محصول', 'اطلاعات بیشتر', 'خرید محصول', 'دریافت مشاوره']
BTN = 'display:inline-block;color:#fff !important;text-decoration:none !important;padding:8px 18px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;border:1px solid #c0392b;background:#c0392b;'
DIGITS = re.compile(r'[0-9۰-۹٠-٩]+')

def _plain(x):
    return re.sub(r'\s+', ' ', H.unescape(re.sub(r'<[^>]+>', '', x))).strip()

# ───────────────────────── fixes
def apply_fixes(s, fixes, rep):
    parts = SPLIT.split(s)
    for fx in fixes or []:
        old, new, kind = fx.get('old', ''), fx.get('new', ''), fx.get('kind', 'wording')
        if not old or old == new or '<' in old or '>' in old or '<' in new or '>' in new:
            rep.append(('skip', f'bad fix {old[:40]!r}')); continue
        if len(old) > 400 or len(new) > 1.7 * len(old) + 30 or len(new) < 0.35 * len(old) - 5:
            rep.append(('skip', f'fix size guard {old[:40]!r}')); continue
        if kind != 'fact' and DIGITS.findall(old) != DIGITS.findall(new):
            rep.append(('skip', f'digits changed (kind={kind}) {old[:40]!r}')); continue
        hits = [(i, parts[i].count(old)) for i in range(0, len(parts), 2) if old in parts[i]]
        total = sum(c for _, c in hits)
        if total != 1:
            rep.append(('skip', f'{"not found" if total == 0 else "ambiguous x" + str(total)}: {old[:50]!r}')); continue
        i = hits[0][0]
        parts[i] = parts[i].replace(old, new, 1)
        rep.append(('fact' if kind == 'fact' else 'fix', f'{old[:60]!r} -> {new[:60]!r}'))
    return ''.join(parts)

# ───────────────────────── callouts
def apply_callouts(s, callouts, rep):
    done = 0
    for c in (callouts or [])[:2]:
        starts = re.sub(r'\s+', ' ', c.get('para_starts', '')).strip()
        typ = c.get('type', 'note')
        if typ not in LABEL or len(starts) < 12:
            rep.append(('skip', f'bad callout {starts[:30]!r}')); continue
        cands = []
        for m in re.finditer(r'<p\b([^>]*)>(.*?)</p>', s, re.S):
            if _plain(m.group(2)).startswith(starts):
                cands.append(m)
        if len(cands) != 1:
            rep.append(('skip', f'callout paragraph {"not found" if not cands else "ambiguous"}: {starts[:40]!r}')); continue
        m = cands[0]
        plain = _plain(m.group(2))
        if len(plain) > 650 or len(plain) < 25:
            rep.append(('skip', f'callout length {len(plain)}: {starts[:30]!r}')); continue
        # not inside a list / table / existing callout
        pre = s[:m.start()]
        if pre.rfind('<li') > pre.rfind('</li>') or pre.rfind('<td') > pre.rfind('</td>') or pre.rfind('<blockquote') > pre.rfind('</blockquote>'):
            rep.append(('skip', f'callout inside list/table: {starts[:30]!r}')); continue
        inner = m.group(2)
        if not re.match(r'\s*(?:<[^>]+>\s*)*(نکته|توجه|هشدار|مهم|خطر|اخطار|تذکر|نتیجه|جمع)', inner):
            inner = f'<strong>{LABEL[typ]}:</strong> ' + inner.lstrip()
        new = f'<div class="bsma-{typ}"><p{m.group(1)}>{inner}</p></div>'
        s = s[:m.start()] + new + s[m.end():]
        done += 1
        rep.append(('callout', f'{typ}: {plain[:60]!r}'))
    return s

# ───────────────────────── ads
def short_title(t, n=58):
    t = t.strip()
    for sep in (' (', ' | ', ' – ', ' - ', ' ؛ ', ' ، '):
        k = t.find(sep)
        if k >= 14:
            t = t[:k]
    if len(t) > n:
        t = t[:n].rsplit(' ', 1)[0] + '…'
    return t

def render_ad(prods, title, text, cta, catalog, label='محصول مرتبط'):
    items = [catalog[p] for p in prods if p in catalog and catalog[p].get('img')]
    if not items:
        return None
    esc = lambda x: H.escape(x, quote=True)
    def tile(it):
        return (f'<a class="bsma-x-tile" href="{it["link"]}" target="_blank" rel="noopener">'
                f'<img src="{it["img"]}" alt="{esc(it["t"])}" loading="lazy" decoding="async"><span>{esc(short_title(it["t"]))}</span></a>')
    btn = lambda it, c: f'<a class="bsma-x-btn" href="{it["link"]}" target="_blank" rel="noopener" style="{BTN}">{esc(c)}</a>'
    lab = f'<div class="bsma-related-label"><span class="bsma-related-icon">📌 {"محصولات مرتبط" if len(items) > 1 else label}</span></div>'
    cta = cta if cta in CTA_OK else 'مشاهده محصول'
    if len(items) == 1:
        body = (f'<div class="bsma-x-head"><div class="bsma-x-ttl">{esc(title)}</div></div>'
                f'<div class="bsma-x-row">{tile(items[0])}<p class="bsma-x-txt">{esc(text)}</p></div>')
    else:
        body = (f'<div class="bsma-x-head"><div class="bsma-x-ttl">{esc(title)}</div><div class="bsma-x-sub">{esc(text)}</div></div>'
                f'<div class="bsma-x-grid g{len(items)}">{"".join(tile(i) for i in items)}</div>')
    return lab + '<div class="bsma-x">' + body + f'<div class="bsma-x-foot"><div class="bsma-x-btns">{btn(items[0], cta)}</div></div></div>'

def _headings(s):
    spans = [(m.start(), m.end()) for m in re.finditer(r'<style\b.*?</style>|<script\b.*?</script>', s, re.S | re.I)]
    return [(m.start(), m.end(), int(m.group(1)), _plain(m.group(3))) for m in re.finditer(r'<h([1-6])\b([^>]*)>(.*?)</h\1>', s, re.S)
            if not any(a <= m.start() < b for a, b in spans)]

def apply_ad(s, adspec, catalog, rep):
    if not adspec:
        return s
    blocks = ad_blocks(s)
    old = adspec.get('existing', 'keep')
    new = adspec.get('new')
    html_new = None
    if new:
        prods = [int(p) for p in new.get('products', [])][:3]
        html_new = render_ad(prods, new.get('title', '').strip(), new.get('text', '').strip(), new.get('cta', ''), catalog)
        if not html_new or len(new.get('title', '')) < 4 or len(new.get('text', '')) < 25:
            rep.append(('skip', 'ad: incomplete spec / unknown products')); html_new = None
    if old in ('replace', 'remove') and blocks:
        a, e, _ = blocks[0]
        if old == 'replace' and html_new:
            s = s[:a] + html_new + s[e:]; rep.append(('ad', 'existing ad replaced')); html_new = None
        elif old == 'remove':
            s = s[:a] + s[e:]; rep.append(('ad', 'existing ad removed'))
    if html_new:
        sec = new.get('section_heading', 'TOP')
        pos = None
        if sec and sec != 'TOP':
            hs = _headings(s)
            want = _plain(sec)
            idx = [k for k, h in enumerate(hs) if h[3] == want]
            if len(idx) == 1:
                k = idx[0]; lvl = hs[k][2]
                nxt = [h for h in hs[k + 1:] if h[2] <= lvl]
                pos = nxt[0][0] if nxt else None
                if pos is None:
                    # last section: before the closing heading/CTA block if the last heading is of lower rank, else the end
                    pos = len(s.rstrip())
            else:
                rep.append(('skip', f'ad: section heading {"not found" if not idx else "ambiguous"}: {sec[:40]!r}'))
        if pos is None:
            ps = [m for m in re.finditer(r'</p>', s) if len(_plain(s[max(0, m.start() - 900):m.start()])) > 120]
            first_h = _headings(s)
            limit = first_h[0][0] if first_h else len(s)
            ps = [m for m in ps if m.end() <= limit] or ps
            pos = ps[0].end() if ps else len(s.rstrip())
        s = s[:pos] + '\n' + html_new + '\n' + s[pos:]
        rep.append(('ad', f'new ad inserted ({sec[:30]})'))
    return s

# ───────────────────────── driver
def apply_patch(s, patch, catalog):
    rep = []
    out = apply_fixes(s, patch.get('fixes'), rep)
    out = apply_callouts(out, patch.get('callouts'), rep)
    out = apply_ad(out, patch.get('ad'), catalog, rep)
    # guards: nothing the reader relies on may disappear
    for kind in ('href', 'src'):
        a = re.findall(rf'\b{kind}="([^"]*)"', re.sub(r'<style\b.*?</style>', '', s, flags=re.S))
        b = re.findall(rf'\b{kind}="([^"]*)"', re.sub(r'<style\b.*?</style>', '', out, flags=re.S))
        removed = [x for x in a if x not in b]
        if removed and patch.get('ad', {}) and patch['ad'].get('existing') in ('replace', 'remove'):
            removed = [x for x in removed if 'bsma.ir/product' not in x and 'wp-content/uploads' not in x]
        if removed:
            raise ValueError(f'{kind} removed: {removed[:2]}')
    if len(re.findall(r'<h[1-6]\b', s)) != len(re.findall(r'<h[1-6]\b', out)):
        raise ValueError('heading count changed')
    return out, rep
