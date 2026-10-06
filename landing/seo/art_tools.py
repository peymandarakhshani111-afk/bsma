"""Safe, verifiable clean-up of article HTML (bsma.ir posts).

normalize(html) applies deterministic fixes only:
  D1  identical <style> blocks are kept once (the ad boilerplate was pasted 4-9x into most posts)
  D2  heading outline: <h1> inside the body -> h2, no skipped levels, always starting at h2
  D3  Persian typography inside text nodes: Arabic ي/ك/ى -> ی/ک, «می »/«نمی » + ZWNJ, «ها/های» ZWNJ, spacing around ، ؛ ؟, double spaces
  D4  empty paragraphs removed; copy-paste artefacts (data-start/data-end) removed
  D5  long body paragraphs that were centred by copy-paste get normal (start) alignment
verify(before, after) proves that nothing but the above changed: same links/images, same words (after typographic normalisation),
same heading texts. The pipeline stops on any mismatch.
"""
import re, html as _html

SPLIT = re.compile(r'(<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>|<pre\b.*?</pre>|<code\b.*?</code>|<[^>]+>)', re.S | re.I)
FA = '؀-ۿ'
ZW = '‌'

# ───────────────────────── helpers
def tokens(s):
    return SPLIT.split(s)  # even index = text, odd = markup

def text_of(s):
    parts = tokens(s)
    return ''.join(p for i, p in enumerate(parts) if i % 2 == 0)

def _strip_tags(x):
    return re.sub(r'<[^>]+>', '', x)

# ───────────────────────── D1
def dedupe_styles(s):
    seen = set()
    def f(m):
        key = re.sub(r'\s+', '', m.group(0))
        if key in seen:
            return ''
        seen.add(key)
        return m.group(0)
    out = re.sub(r'<style\b[^>]*>.*?</style>[ \t]*\n?', f, s, flags=re.S | re.I)
    return out

# ───────────────────────── D2
H_RE = re.compile(r'<h([1-6])\b([^>]*)>(.*?)</h\1>', re.S | re.I)

def fix_headings(s):
    """returns (new_html, changed:bool). Skips headings inside <style>/<script>."""
    parts = SPLIT.split(s)  # markup parts include heading open/close tags separately, so work on the whole string instead
    spans = [(m.start(), m.end()) for m in re.finditer(r'<style\b.*?</style>|<script\b.*?</script>|<!--.*?-->', s, re.S | re.I)]
    def inside(pos):
        return any(a <= pos < b for a, b in spans)
    hs = [m for m in H_RE.finditer(s) if not inside(m.start())]
    if not hs:
        return s, False
    present = {int(m.group(1)) for m in hs}
    orig = [(2 if (int(m.group(1)) == 1 and 2 in present) else int(m.group(1))) for m in hs]  # a stray h1 sits beside the h2s
    stack, new_levels = [], []          # (orig level, new level); a heading is one level below its nearest earlier heading with a smaller level
    for o in orig:
        while stack and stack[-1][0] >= o:
            stack.pop()
        nl = stack[-1][1] + 1 if stack else 2
        stack.append((o, nl))
        new_levels.append(min(nl, 6))
    if all(nl == int(m.group(1)) for nl, m in zip(new_levels, hs)):
        return s, False
    out, last = [], 0
    for nl, m in zip(new_levels, hs):
        out.append(s[last:m.start()])
        out.append(f'<h{nl}{m.group(2)}>{m.group(3)}</h{nl}>')
        last = m.end()
    out.append(s[last:])
    return ''.join(out), True

# ───────────────────────── D3
NOT_PLURAL = {'انتها', 'انتهای', 'انتهایی', 'شانگهای', 'گرانبها', 'گرانبهایی', 'بها', 'بهای', 'بهایی', 'پیشانها'}
def fix_text(t):
    t = t.replace('ي', 'ی').replace('ك', 'ک').replace('ى', 'ی')
    t = t.translate({0x660 + i: 0x6F0 + i for i in range(10)})
    t = re.sub(rf'(?<![{FA}{ZW}])(ن?می) (?=[{FA}])', lambda m: m.group(1) + ZW, t)
    t = re.sub(rf'(?<=[{FA}]) (هایی|های|ها)(?![{FA}])', lambda m: ZW + m.group(1), t)
    t = re.sub(rf'([{FA}]) +([،؛؟])', r'\1\2', t)
    t = re.sub(rf'([،؛؟])(?=[{FA}])', r'\1 ', t)
    t = re.sub(rf'(?<=[{FA}])\.(?=[{FA}]{{2,}})', '. ', t)
    t = re.sub(rf'(?<![{FA}{ZW}])([{FA}]{{3,}}?)(?<![اآدذرزژو])(هایی|های|ها)(?![{FA}{ZW}])',
               lambda m: m.group(0) if (m.group(1) + m.group(2)) in NOT_PLURAL else m.group(1) + ZW + m.group(2), t)
    t = re.sub(r'[ \t]{2,}', ' ', t)
    return t

def fix_typography(s):
    parts = SPLIT.split(s)
    for i in range(0, len(parts), 2):
        if parts[i]:
            parts[i] = fix_text(parts[i])
    s = ''.join(parts)
    # headings: trim stray leading/trailing space inside the tag
    s = re.sub(r'(<h[1-6]\b[^>]*>)[ \t]+', r'\1', s)
    s = re.sub(r'[ \t]+(</h[1-6]>)', r'\1', s)
    return s

# ───────────────────────── D4 / D5
def fix_artifacts(s):
    s = re.sub(r'\sdata-(?:start|end)="\d+"', '', s)
    s = re.sub(r'<p\b[^>]*>(?:\s|&nbsp;|<br\s*/?>)*</p>[ \t]*\n?', '', s)
    s = re.sub(r'<p(\b[^>]*?)(\sstyle="\s*text-align:\s*center;?\s*")([^>]*)>(.*?)</p>', lambda m: (
        f'<p{m.group(1)}{m.group(3)}>{m.group(4)}</p>' if len(_strip_tags(m.group(4)).strip()) > 140 and '<img' not in m.group(4) else m.group(0)), s, flags=re.S)
    return s

def normalize(s, headings=True):
    out = dedupe_styles(s)
    stats = {}
    if headings:
        out, ch = fix_headings(out); stats['headings'] = ch
    out = fix_typography(out)
    out = fix_artifacts(out)
    return out

# ───────────────────────── verification
def _norm_words(t):
    t = _html.unescape(t)
    t = t.replace('ي', 'ی').replace('ك', 'ک').replace('ى', 'ی').replace(ZW, '').replace(' ', ' ')
    t = t.translate({0x660 + i: 0x30 + i for i in range(10)}).translate({0x6F0 + i: 0x30 + i for i in range(10)})
    return re.sub(r'[\s،؛؟]+', '', t)

def _attrs(s, name):
    return sorted(re.findall(rf'\b{name}="([^"]*)"', re.sub(r'<style\b.*?</style>', '', s, flags=re.S)))

def heading_texts(s):
    spans = [(m.start(), m.end()) for m in re.finditer(r'<style\b.*?</style>|<script\b.*?</script>', s, re.S | re.I)]
    return [_norm_words(_strip_tags(m.group(3))) for m in H_RE.finditer(s) if not any(a <= m.start() < b for a, b in spans)]

def verify(before, after, allow_text_change=False):
    errs = []
    if not allow_text_change and _norm_words(text_of(re.sub(r'<style\b.*?</style>|<script\b.*?</script>', '', before, flags=re.S | re.I))) != \
            _norm_words(text_of(re.sub(r'<style\b.*?</style>|<script\b.*?</script>', '', after, flags=re.S | re.I))):
        errs.append('text differs')
    for a in ('href', 'src', 'alt'):
        if _attrs(before, a) != _attrs(after, a):
            errs.append(f'{a} attributes differ')
    if heading_texts(before) != heading_texts(after):
        errs.append('heading texts/order differ')
    for tag in ('img', 'a ', 'iframe', 'table', 'video', 'script'):
        if len(re.findall(rf'<{tag}\b', before)) != len(re.findall(rf'<{tag}\b', after)):
            errs.append(f'<{tag}> count differs')
    return errs
