"""Add three product ads to the SCBA article (post 22595) — built only from products that exist in the shop.

  1. «سیلندر هوای تنفسی و کمپرسور پرکن»  (under the cylinder section)  : Spasciani 6.8 L composite cylinder, Bauer PE 100, L&W filling valve
  2. «پیش از ورود به فضای بسته، هوا را بسنجید» (under "applications")  : Honeywell BW Max XT II 4-gas detector
  3. «همه‌ی تجهیزات حفاظت فردی» (before the closing section)          : helmet, safety shoes, gloves, boots, suits, harness, full-face mask + category/phone buttons

Everything else in the article stays byte-for-byte; the script stops if an anchor is missing or not unique.
Input : backups/post-22595-raw-after-2026-10-06.html   Output: scba-ads.html
Product data (names, links, image URLs, specs) were read from the shop's REST API on 2026-10-06; specs quoted in the copy come from the product pages.
"""
import sys
SRC = sys.argv[1] if len(sys.argv) > 1 else '../backups/post-22595-raw-after-2026-10-06.html'
OUT = sys.argv[2] if len(sys.argv) > 2 else 'scba-ads.html'
s = open(SRC, encoding='utf-8').read()

U = 'https://bsma.ir/wp-content/uploads/'
CAT_PPE = 'https://bsma.ir/product-category/%d8%aa%d8%ac%d9%87%db%8c%d8%b2%d8%a7%d8%aa-%d8%a7%db%8c%d9%85%d9%86%db%8c/'
P = {  # key: (link, image, short label)
 'cyl': ('https://bsma.ir/product/%d8%b3%db%8c%d9%84%d9%86%d8%af%d8%b1-%da%a9%d8%a7%d9%85%d9%be%d9%88%d8%b2%db%8c%d8%aa%db%8c-%d8%a7%d8%b3%d9%be%d8%a7%d8%b3%db%8c%d8%a7%d9%86%db%8c-6-8-%d9%84%db%8c%d8%aa%d8%b1%db%8c-%d8%b3%d8%a7%d8%ae/',
         U + '2024/04/47d61fea-95a8-48f7-a263-46d70b4ac7fe-242x300.webp', 'سیلندر کامپوزیتی اسپاسیانی ۶٫۸ لیتری'),
 'pe100': ('https://bsma.ir/product/%da%a9%d9%85%d9%be%d8%b1%d8%b3%d9%88%d8%b1-%d9%82%d8%a7%d8%a8%d9%84-%d8%ad%d9%85%d9%84-%d8%a8%d8%a7%d8%a6%d8%b1-pe-100/',
           U + '2025/11/IMG_1136169_frontview_26.08.2024-300x225.webp', 'کمپرسور قابل حمل بائر PE 100'),
 'lw': ('https://bsma.ir/product/%d8%b4%db%8c%d8%b1-%d9%be%d8%b1%da%a9%d9%86-%da%a9%d9%be%d8%b3%d9%88%d9%84-lw/',
        U + '2025/11/Annotation-2025-11-23-180827-240x300.webp', 'شیر پرکن کپسول L&amp;W با گیج فشار'),
 'gas': ('https://bsma.ir/product/%da%af%d8%a7%d8%b2%d8%b3%d9%86%d8%ac-4-%da%86%d9%87%d8%a7%d8%b1-%d8%b3%d9%86%d8%b3%d9%88%d8%b1-bw-max-xt-ll-%d9%87%d8%a7%d9%86%db%8c%d9%88%d9%84-honeywell/',
         U + '2024/04/l638120296447925038-300x300.webp', 'گازسنج ۴ حسگر هانیول BW Max XT II'),
 'helmet': ('https://bsma.ir/product/%da%a9%d9%84%d8%a7%d9%87-%d8%a7%db%8c%d9%85%d9%86%db%8c-%d8%ac%db%8c-%d8%a7%d8%b3-%d9%be%db%8c-jsp-%d9%85%d8%ad%d8%a7%d9%81%d8%b8%d8%aa-%d8%b3%d8%b1/',
            U + '2024/04/823__20240221T091047_R270343-1-300x181.webp', 'کلاه ایمنی JSP'),
 'shoe': ('https://bsma.ir/product/%da%a9%d9%81%d8%b4-%d8%a7%db%8c%d9%85%d9%86%db%8c-%da%a9%d9%84%d8%a7%d8%b1-%d8%b9%d8%a7%db%8c%d9%82-%d8%a8%d8%b1%d9%82-%da%a9%d8%a7%d9%be-%d8%af%d8%a7%d8%b1-%da%a9%d9%81%d8%b4-%d8%b5%d9%86%d8%b9/',
          U + '2024/05/lw5oa03z-300x300.webp', 'کفش ایمنی کلار'),
 'glove': ('https://bsma.ir/product/%d8%af%d8%b3%d8%aa%da%a9%d8%b4-%d9%85%d9%82%d8%a7%d9%88%d9%85-%d8%b4%db%8c%d9%85%db%8c%d8%a7%db%8c%db%8c-%d8%a7%d9%86%d8%b3%d9%84-ansell-%d9%85%d8%af%d9%84-edge-14-663/',
           U + '2024/04/photo_2024-03-02_15-13-39-225x300.webp', 'دستکش مقاوم شیمیایی انسل'),
 'boot': ('https://bsma.ir/product/%da%86%da%a9%d9%85%d9%87-%d8%a7%db%8c%d9%85%d9%86%db%8c-%d8%b6%d8%af-%d8%a7%d8%b3%db%8c%d8%af-%d9%85%d8%a7%da%af%d9%85%d8%a7/',
          U + '2024/04/photo_2024-03-04_13-00-51-254x300.webp', 'چکمه ایمنی ضد اسید'),
 'acid': ('https://bsma.ir/product/%d9%84%d8%a8%d8%a7%d8%b3-%d8%b6%d8%af-%d8%a7%d8%b3%db%8c%d8%af-%d9%be%d8%b1%d9%88%d9%85%da%a9%d8%b3/',
          U + '2024/03/UntitleDd-1-300x300.webp', 'لباس ضد اسید پرومکس'),
 'gassuit': ('https://bsma.ir/product/%d9%84%d8%a8%d8%a7%d8%b3-%d8%b6%d8%af-%da%af%d8%a7%d8%b2-mkf-06-meikang-%d8%b1%d9%86%da%af-%d9%82%d8%b1%d9%85%d8%b2/',
             U + '2024/08/1-300x300.webp', 'لباس ضد گاز MKF 06'),
 'harness': ('https://bsma.ir/product/%d8%b3%d8%aa-%d9%87%d8%a7%d8%b1%d9%86%d8%b3-%da%a9%d8%a7%db%8c%d8%a7-%d9%84%d9%86%db%8c%d8%a7%d8%b1%d8%af-%d8%b1%d8%a7%da%a9-%d9%87%d8%a7%d8%b1%d9%86%d8%b3-%da%a9%d8%a7%d8%b1-%d8%af%d8%b1-%d8%a7%d8%b1/',
             U + '2024/05/new-rh-1-el-1-1-300x300.webp', 'هارنس ایمنی کایا ترک'),
 'mask': ('https://bsma.ir/product/%d9%85%d8%a7%d8%b3%da%a9-%d8%aa%d9%85%d8%a7%d9%85-%d8%b5%d9%88%d8%b1%d8%aa-%d8%a7%d8%b3%d9%be%d8%a7%d8%b3%db%8c%d8%a7%d9%86%db%8c-%d9%85%d8%a7%d8%b3%da%a9-%d8%a7%d8%b3%d9%be%d8%a7%d8%b3%db%8c%d8%a7/',
          U + '2024/04/Spasciani-mask-200x300.webp', 'ماسک تمام صورت اسپاسیانی'),
}

def tile(k, alt=None):
    link, img, label = P[k]
    return (f'<a class="bsma-x-tile" href="{link}" target="_blank" rel="noopener">'
            f'<img src="{img}" alt="{(alt or label)}" loading="lazy" decoding="async"><span>{label}</span></a>')

BTN = 'display:inline-block;color:#fff !important;text-decoration:none !important;padding:8px 18px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;border:1px solid '

def btn(href, text, alt=False, blank=True):
    st = BTN + ('rgba(255,255,255,.5);background:transparent;' if alt else '#c0392b;background:#c0392b;')
    tgt = ' target="_blank" rel="noopener"' if blank else ''
    return f'<a class="bsma-x-btn{" alt" if alt else ""}" href="{href}"{tgt} style="{st}">{text}</a>'

def label(txt):
    return f'<div class="bsma-related-label"><span class="bsma-related-icon">📌 {txt}</span></div>'

CSS = ('<style>'
 '.bsma-x{max-width:720px;margin:12px auto 22px;padding:14px 14px 16px;box-sizing:border-box;border-radius:12px;background:#0f172a;box-shadow:0 6px 24px rgba(0,0,0,.3);font-family:Tahoma,sans-serif;direction:rtl;line-height:1.5;color:#fff;}'
 '.bsma-x *{box-sizing:border-box;}'
 '.bsma-x-head{margin-bottom:12px;}'
 '.bsma-x-ttl{color:#fff !important;font-size:17px;font-weight:800;margin:0;line-height:1.5;}'
 '.bsma-x-sub{color:#aebcd6 !important;font-size:12.5px;margin:4px 0 0;line-height:1.9;}'
 '.bsma-x-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}'
 '.bsma-x-grid.g4{grid-template-columns:repeat(4,1fr);}'
 '.bsma-x-tile{display:block;text-decoration:none !important;border-radius:8px;overflow:hidden;border:1px solid rgba(255,255,255,.14);background:#fff;}'
 '.bsma-x-tile img{display:block;width:100%;height:auto;aspect-ratio:1/1;object-fit:contain;background:#fff;margin:0;padding:6px;}'
 '.bsma-x-tile span{display:block;color:#fff !important;background:rgba(15,23,42,.94);font-size:11.5px;padding:7px 8px;text-align:center;line-height:1.7;min-height:3.9em;}'
 '.bsma-x-row{display:flex;gap:14px;align-items:center;margin-bottom:12px;}'
 '.bsma-x-row .bsma-x-tile{width:170px;flex:0 0 170px;}'
 '.bsma-x-txt{flex:1;min-width:0;color:#dbe4f3 !important;font-size:13px;line-height:2;margin:0;}'
 '.bsma-x-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}'
 '.bsma-x-tags{display:flex;gap:6px;flex-wrap:wrap;}'
 '.bsma-x-tags span{background:rgba(241,196,15,.12);color:#f1c40f !important;padding:3px 10px;border-radius:12px;font-size:11px;border:1px solid rgba(241,196,15,.4);white-space:nowrap;}'
 '.bsma-x-btns{display:flex;gap:8px;flex-wrap:wrap;}'
 '.bsma-x-btn{display:inline-block;background:#c0392b;color:#fff !important;text-decoration:none !important;padding:8px 18px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;border:1px solid #c0392b;}'
 '.bsma-x-btn.alt{background:transparent;border-color:rgba(255,255,255,.5);}'
 '@media (max-width:600px){'
 '.bsma-x{padding:10px 10px 14px;}'
 '.bsma-x-ttl{font-size:15.5px;}'
 '.bsma-x-grid,.bsma-x-grid.g4{grid-template-columns:repeat(2,1fr);}'
 '.bsma-x-row{flex-direction:column;align-items:stretch;}'
 '.bsma-x-row .bsma-x-tile{width:62%;flex:none;margin:0 auto;}'
 '.bsma-x-foot{flex-direction:column;align-items:stretch;}'
 '.bsma-x-tags,.bsma-x-btns{justify-content:center;}'
 '}'
 '</style>')

AD_GAS = (label('محصول مرتبط') +
 '<div class="bsma-x">'
 '<div class="bsma-x-head"><div class="bsma-x-ttl">پیش از ورود به فضای بسته، هوا را بسنجید</div>'
 '<div class="bsma-x-sub">دستگاه تنفسی فقط وقتی کار را تمام می‌کند که بدانید داخل محل چه خبر است؛ با گازسنج، پیش از ورود از وضعیت هوا مطمئن شوید.</div></div>'
 '<div class="bsma-x-row">' + tile('gas') +
 '<p class="bsma-x-txt">گازسنج چهار حسگر <strong style="color:#fff !important;">Honeywell BW Max XT II</strong> همزمان اکسیژن، گازهای قابل اشتعال، مونوکسیدکربن و سولفید هیدروژن را پایش می‌کند و با آلارم دیداری، لرزشی و صوتی (۹۵ دسی‌بل) خطر را اعلام می‌کند. این دستگاه قابل حمل است و در صنایع نفت و گاز، پتروشیمی، معادن و عملیات امداد و آتش‌نشانی کاربرد دارد.</p></div>'
 '<div class="bsma-x-foot"><div class="bsma-x-tags"><span>۴ حسگر همزمان</span><span>قابل حمل</span></div>'
 f'<div class="bsma-x-btns">{btn(P["gas"][0], "مشاهده‌ی گازسنج")}</div></div>'
 '</div>')

AD_CYL = (label('محصولات مرتبط') +
 '<div class="bsma-x">'
 '<div class="bsma-x-head"><div class="bsma-x-ttl">سیلندر هوای تنفسی و کمپرسور پرکن</div>'
 '<div class="bsma-x-sub">سیلندر کامپوزیتی اسپاسیانی ۶٫۸ لیتری با فشار کاری ۳۰۰ بار و وزن ۴٫۷ کیلوگرم، زمان کارکرد ۴۵ دقیقه (طبق مشخصات سازنده). برای پُر کردن سیلندر هم کمپرسور قابل حمل بائر PE 100 و شیر پرکن L&amp;W را می‌توانید از به‌سازان تهیه کنید.</div></div>'
 '<div class="bsma-x-grid">' + tile('cyl') + tile('pe100') + tile('lw') + '</div>'
 '<div class="bsma-x-foot"><div class="bsma-x-tags"><span>عمر مفید تا ۱۵ سال</span><span>آزمون مجدد هر ۵ سال</span></div>'
 f'<div class="bsma-x-btns">{btn(P["cyl"][0], "مشاهده‌ی سیلندر")}{btn(P["pe100"][0], "کمپرسور بائر", alt=True)}</div></div>'
 '</div>')

AD_PPE = (label('تجهیزات حفاظت فردی') +
 '<div class="bsma-x">'
 '<div class="bsma-x-head"><div class="bsma-x-ttl">همه‌ی تجهیزات حفاظت فردی (PPE) را از ما بخواهید</div>'
 '<div class="bsma-x-sub">از کلاه و کفش ایمنی تا ماسک، دستکش، چکمه و لباس مقاوم در برابر مواد شیمیایی؛ همه‌ی تجهیزات حفاظت فردی مورد نیاز آتش‌نشانان، پیمانکاران و صنایع را یک‌جا و از یک مجموعه‌ی معتبر تأمین می‌کنیم.</div></div>'
 '<div class="bsma-x-grid g4">' + ''.join(tile(k) for k in ('helmet', 'shoe', 'glove', 'boot', 'acid', 'gassuit', 'harness', 'mask')) + '</div>'
 '<div class="bsma-x-foot"><div class="bsma-x-tags"><span>حفاظت تنفسی</span><span>حفاظت شیمیایی</span><span>سر و پا</span><span>کار در ارتفاع</span></div>'
 f'<div class="bsma-x-btns">{btn(CAT_PPE, "مشاهده‌ی تجهیزات ایمنی")}{btn("tel:+983136242532", "مشاوره: ۰۳۱-۳۶۲۴۲۵۳۲", alt=True, blank=False)}</div></div>'
 '</div>')

def put_before(text, anchor, ins):
    assert text.count(anchor) == 1, f'anchor found {text.count(anchor)}x: {anchor[:60]!r}'
    i = text.index(anchor)
    return text[:i] + ins + '\n' + text[i:]

assert 'bsma-x' not in s, 'ads already present'
n = s
n = put_before(n, '<img class="aligncenter size-full wp-image-22598"', CSS + AD_GAS)
n = put_before(n, '<h3> تنظیم کننده‌های فشار</h3>', AD_CYL)
n = put_before(n, '<h4>بهترین تجهیزات آتش نشانی و اطفای حریق', AD_PPE)

# the article text itself must be untouched: removing the inserted blocks must give the input back
back = n
for blk in (CSS + AD_GAS + '\n', AD_CYL + '\n', AD_PPE + '\n'):
    assert blk in back
    back = back.replace(blk, '', 1)
assert back == s
assert n.count('<h2>') == s.count('<h2>') and n.count('<h3>') + n.count('<h3> ') == s.count('<h3>') + s.count('<h3> ')
open(OUT, 'w', encoding='utf-8').write(n)
print('ok', len(s), '->', len(n))
