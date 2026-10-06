"""Phase C driver: article (Phase A content) + editor patch  ->  final article, with a human-readable report.

usage: python3 art_phase_c.py <scratch dir> <id> [<id> ...]
reads  <scratch>/posts/<id>.html (live content), <scratch>/patches/<id>.json, <scratch>/catalog.json
writes <scratch>/out2/<id>.html and <scratch>/reports/<id>.txt ; prints one summary line per article
"""
import sys, os, json, re
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import art_tools as T, art_patch as P

IMG_FIX = {  # raw src fragments that 404 -> working file names (found through the media library, 2026-10-06)
    22109: [(r'(v4-728px-Use-a-Fire-Extinguisher-Step-[\w-]*?)\.webp-1\.webp', r'\1.jpg-1.webp'),
            (r'(v4-728px-Use-a-Fire-Extinguisher-Step-[\w-]*?)\.webp\.webp', r'\1.jpg.webp')],
}
IMG_DROP = {13397: [13817], 14277: [14299]}  # media files that no longer exist (404 in the library as well)

def fix_images(s, pid, rep):
    for pat, sub in IMG_FIX.get(pid, []):
        s, n = re.subn(pat, sub, s)
        if n:
            rep.append(('img', f'{n} broken src rewritten'))
    for mid in IMG_DROP.get(pid, []):
        m = re.search(rf'<img\b[^>]*wp-image-{mid}\b[^>]*>', s)
        if m:
            a, b = m.start(), m.end()
            # also drop the empty <p> wrapper if the image was its only content
            pm = re.search(r'<p\b[^>]*>\s*$', s[:a]); pe = re.match(r'\s*</p>', s[b:])
            if pm and pe:
                a, b = pm.start(), b + pe.end()
            s = s[:a] + s[b:]
            rep.append(('img', f'dead image wp-image-{mid} removed'))
    return s

def main():
    sp = sys.argv[1]; ids = [int(x) for x in sys.argv[2:]]
    cat = {c['id']: c for c in json.load(open(os.path.join(sp, 'catalog.json')))}
    os.makedirs(os.path.join(sp, 'out2'), exist_ok=True); os.makedirs(os.path.join(sp, 'reports'), exist_ok=True)
    for pid in ids:
        src = open(os.path.join(sp, 'posts', f'{pid}.html'), encoding='utf-8').read()
        pf = os.path.join(sp, 'patches', f'{pid}.json')
        patch = json.load(open(pf, encoding='utf-8')) if os.path.exists(pf) else {'id': pid}
        rep = []
        try:
            s = fix_images(src, pid, rep)
            s, r2 = P.apply_patch(s, patch, cat)
            rep += r2
            s = T.normalize(s, headings=False)
        except Exception as e:
            print(pid, 'FAILED', e); continue
        lost = [x for x in re.findall(r'\b(?:href|src)="([^"]*)"', re.sub(r'<style\b.*?</style>', '', src, flags=re.S)) if x not in s]
        if pid in IMG_FIX or pid in IMG_DROP:
            lost = []
        if lost and patch.get('ad', {}).get('existing') not in ('replace', 'remove'):
            print(pid, 'FAILED lost links', lost[:2]); continue
        open(os.path.join(sp, 'out2', f'{pid}.html'), 'w', encoding='utf-8').write(s)
        flags = patch.get('flags', [])
        lines = [f'# {pid}'] + [f'[{lv}] {msg}' for lv, msg in rep] + [f'[flag] {f}' for f in flags]
        open(os.path.join(sp, 'reports', f'{pid}.txt'), 'w', encoding='utf-8').write('\n'.join(lines) + '\n')
        kinds = {}
        for lv, _ in rep: kinds[lv] = kinds.get(lv, 0) + 1
        print(pid, 'ok', kinds, 'changed' if s != src else 'same')

if __name__ == '__main__':
    main()
