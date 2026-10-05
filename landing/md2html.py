"""Tiny Markdown → HTML converter for article-fa.md (headings, lists, tables, quotes, links, bold/italic)."""
import html, re, sys

def inline(t):
    t = html.escape(t, quote=False)
    t = re.sub(r'`([^`]+)`', r'<code>\1</code>', t)
    t = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', t)
    t = re.sub(r'(?<![\w*])\*([^*\n]+)\*(?![\w*])', r'<em>\1</em>', t)
    t = re.sub(r'\[([^\]]+)\]\((https?://[^)\s]+)\)', lambda m: f'<a href="{m.group(2)}" target="_blank" rel="noopener">{m.group(1)}</a>', t)
    # bare URLs in the sources list
    t = re.sub(r'(?<![">])(https?://[^\s<)]+)', lambda m: f'<a href="{m.group(1)}" target="_blank" rel="noopener">{html.unescape(m.group(1))[:60]}…</a>' if len(m.group(1)) > 60 else f'<a href="{m.group(1)}" target="_blank" rel="noopener">{m.group(1)}</a>', t)
    return t

def convert(md, skip_h1=True, h_shift=0):
    lines = md.split('\n'); out = []; i = 0
    while i < len(lines):
        ln = lines[i]
        if not ln.strip(): i += 1; continue
        m = re.match(r'^(#{1,4})\s+(.*)$', ln)
        if m:
            lvl = len(m.group(1))
            if lvl == 1 and skip_h1: i += 1; continue
            lvl = min(6, lvl + h_shift)
            out.append(f'<h{lvl}>{inline(m.group(2))}</h{lvl}>'); i += 1; continue
        if ln.startswith('>'):
            buf = []
            while i < len(lines) and lines[i].startswith('>'):
                buf.append(lines[i].lstrip('> ').rstrip()); i += 1
            out.append('<blockquote><p>' + inline(' '.join(buf)) + '</p></blockquote>'); continue
        if ln.startswith('|'):
            rows = []
            while i < len(lines) and lines[i].startswith('|'):
                rows.append([c.strip() for c in lines[i].strip().strip('|').split('|')]); i += 1
            head, body = rows[0], [r for r in rows[2:]]
            t = '<div class="table-wrap"><table><thead><tr>' + ''.join(f'<th>{inline(c)}</th>' for c in head) + '</tr></thead><tbody>'
            for r in body:
                t += '<tr>' + f'<th>{inline(r[0])}</th>' + ''.join(f'<td>{inline(c)}</td>' for c in r[1:]) + '</tr>'
            out.append(t + '</tbody></table></div>'); continue
        if re.match(r'^\s*[-*]\s+', ln) or re.match(r'^\s*\d+\.\s+', ln):
            ordered = bool(re.match(r'^\s*\d+\.\s+', ln)); tag = 'ol' if ordered else 'ul'; items = []
            while i < len(lines) and (re.match(r'^\s*[-*]\s+', lines[i]) or re.match(r'^\s*\d+\.\s+', lines[i])):
                items.append(re.sub(r'^\s*(?:[-*]|\d+\.)\s+', '', lines[i])); i += 1
            out.append(f'<{tag}>' + ''.join(f'<li>{inline(x)}</li>' for x in items) + f'</{tag}>'); continue
        buf = []
        while i < len(lines) and lines[i].strip() and not re.match(r'^(#{1,4}\s|>|\||\s*[-*]\s+|\s*\d+\.\s+)', lines[i]):
            buf.append(lines[i].strip()); i += 1
        out.append('<p>' + inline(' '.join(buf)) + '</p>')
    return '\n'.join(out)

if __name__ == '__main__':
    print(convert(open(sys.argv[1], encoding='utf-8').read()))
