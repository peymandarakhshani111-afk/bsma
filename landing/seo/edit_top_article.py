import re, sys, difflib, json
src = open('top-post-raw.html', encoding='utf-8').read()
s = src
N3D = 'https://bsma.ir/%d8%a8%da%a9-%d8%af%d8%b1%d9%81%d8%aa-backdraft-%d9%88-%d9%81%d9%84%d8%b4-%d8%a7%d9%88%d8%b1-flashover/'

def rep(old, new, count=1):
    global s
    n = s.count(old)
    if n != count:
        sys.exit(f'!! expected {count} match(es) but found {n} for: {old[:80]!r}')
    s = s.replace(old, new)

# A. intro paragraph: the old one calls backdraft "flashover" and describes backdraft as if it were flashover
a0 = s.index('این پدیده به نام <strong>"فلش‌اور" یا "Backdraft"</strong>')
a1 = s.index('<h2>بک درفت چیست؟</h2>')
intro = ('<strong>بک‌درفت (Backdraft)</strong> و <strong>فلش‌اور (Flashover)</strong> دو پدیده‌ی متفاوت و خطرناک در آتش‌سوزی فضاهای بسته‌اند که اغلب با هم اشتباه گرفته می‌شوند. '
         '<strong>بک‌درفت</strong> زمانی رخ می‌دهد که در محیطی با احتراق ناقص، گازهای داغ و نسوخته انباشته شده‌اند و ورود ناگهانی اکسیژن (مثلاً با باز شدن در) آن‌ها را به‌صورت انفجاری می‌سوزاند. '
         '<strong>فلش‌اور</strong> اما نتیجه‌ی بالا رفتن دماست: وقتی تابش حرارتی، سطوح اتاق را کم‌وبیش هم‌زمان به دمای اشتعال برساند، کل فضا یک‌باره درگیر آتش می‌شود و به ورود هوای تازه نیازی نیست.\n\n'
         '<div style="border-right:4px solid #ff6b1f;background:#fff6ef;padding:14px 18px;border-radius:10px;margin:20px 0;line-height:2">'
         '<strong>به‌روزرسانی علمی (مهر ۱۴۰۵):</strong>'
         '<ul style="margin:8px 0 6px;padding-inline-start:20px">'
         '<li>طبق پژوهش‌های UL FSRI، با اثاث و مواد سینتتیک امروزی زمان رسیدن به فلش‌اور می‌تواند از بیش از ۲۹ دقیقه به ۳ تا ۴ دقیقه برسد.</li>'
         '<li>یک درِ بسته دما را به‌شدت کاهش می‌دهد: حدود ۱۰۰ درجه‌ی فارنهایت در برابر بیش از ۱٬۰۰۰ درجه‌ی فارنهایت در اتاقِ با درِ باز.</li>'
         '<li>نام‌گذاری «بک‌درفت» و «انفجار دود» هنوز در ادبیات علمی یکدست نیست و پژوهش درباره‌ی آن‌ها ادامه دارد.</li>'
         '<li>گازِ باتری‌های لیتیوم‌یون هم می‌تواند با باز شدن در دچار دفلاگریشن شود.</li>'
         '</ul>'
         f'<a href="{N3D}"><strong>متن کامل همراه با منابع، و تجربه‌ی سه‌بعدی و تعاملی ←</strong></a>'
         '</div>\n')
s = s[:a0] + intro + s[a1:]

# second CTA button right after the existing fan button (re-uses the page's own .bsma-cta-btn style)
old_btn_end = '        <span class="badge">ویژه</span>\n    </a>\n</div>\n&nbsp;\n\n\n<h2>ویژگی‌های بک درفت چیست؟</h2>'
new_btn = ('        <span class="badge">ویژه</span>\n    </a>\n</div>\n'
           '<div class="bsma-btn-wrapper">\n'
           f'    <a href="{N3D}" class="bsma-cta-btn" rel="noopener">\n'
           '        <span>تجربه‌ی سه‌بعدی بک‌درفت و فلش‌اور</span>\n        <span class="arrow">←</span>\n        <span class="badge">جدید</span>\n    </a>\n</div>\n'
           '&nbsp;\n\n\n<h2>ویژگی‌های بک درفت چیست؟</h2>')
rep(old_btn_end, new_btn)

# B. "تفاوت فلش اور و بک درفت چیست؟": the two paragraphs mixed the phenomena up
b0 = s.index('اگرچه بر اساس باور عموم مردم، فلش اور و بک درفت یکسان هستند')
b1 = s.index('<img class="aligncenter size-full wp-image-22860"')
sys_link = '<a href="https://bsma.ir/product-category/%d8%b3%db%8c%d8%b3%d8%aa%d9%85-%d8%a7%d8%b7%d9%81%d8%a7-%d8%ad%d8%b1%db%8c%d9%82/"><strong>سیستم اطفا حریق</strong></a>'
assert sys_link in s[b0:b1]
diff_txt = ('اگرچه بسیاری فلش اور و بک درفت را یکی می‌دانند، این دو از نظر محرک و سازوکار تفاوت اساسی دارند. <strong>فلش اور</strong> یک گذار حرارتی است: لایه‌ی داغ دود زیر سقف جمع می‌شود و با تابش گرما، سطوح و اشیای اتاق کم‌وبیش هم‌زمان به دمای اشتعال می‌رسند و آتش در کل فضا پخش می‌شود. برای این اتفاق به ورود هوای تازه نیازی نیست؛ دما محرک آن است (در ادبیات، دمای آستانه‌ی آن حدود ۶۰۰ درجه‌ی سانتی‌گراد گزارش می‌شود).\n\n'
            '<strong>بک درفت</strong> اما پدیده‌ای اکسیژن‌محور است: در مرحله‌ی افولِ آتش، شعله‌ی دیدنی ناپدید شده ولی گازهای داغ و نسوخته در فضای بسته مانده‌اند. با باز شدن در یا شکستن شیشه، هوای تازه وارد می‌شود و این گازها انفجاری می‌سوزند و گوی آتش و موج فشار می‌سازند. در <strong>انفجار دود</strong> هم گازهای نسوخته به حفره یا اتاق مجاور نفوذ می‌کنند و آن‌جا با هوا مخلوط و مشتعل می‌شوند. '
            f'در هر سه حالت، کنترل جریان هوا، خنک‌سازی و تهویه‌ی هماهنگ کلید کاهش خطر است و تشخیص زودهنگام و {sys_link} می‌توانند به مهار آتش پیش از رسیدن به این مراحل کمک کنند.\n\n')
s = s[:b0] + diff_txt + s[b1:]

# C. process / features headings: the old text described backdraft under the "flashover" heading
rep('<h3>فرآیند رخداد فلش‌اور</h3>\nدر محیطی که میزان اکسیژن کاهش یافته',
    '<h3>فرآیند رخداد فلش‌اور</h3>\nدر ابتدا آتش در اتاق رشد می‌کند و دود داغ زیر سقف جمع می‌شود. لایه‌ی داغ هر دم ضخیم‌تر می‌شود و گرما را به همه‌ی سطوح اتاق می‌تاباند. وقتی سطوح در معرض تابش کم‌وبیش هم‌زمان به دمای اشتعال برسند، آتش در چند ثانیه به کل فضا می‌رسد؛ بدون آن‌که ورود ناگهانی هوا لازم باشد. پژوهش‌های UL FSRI نشان می‌دهد با اثاث مدرن این مسیر می‌تواند فقط ۳ تا ۴ دقیقه طول بکشد.\n'
    '<h3>ویژگی‌های فلش‌اور (Flashover)</h3>\n<ul>\n <li><strong>محرک حرارتی:</strong> افزایش دما و تابش گرما از لایه‌ی دود داغ، نه ورود هوای تازه.</li>\n <li><strong>اشتعال هم‌زمان:</strong> اشیا و سطوح اتاق تقریباً با هم شعله‌ور می‌شوند.</li>\n <li><strong>زمان وقوع:</strong> بین مرحله‌ی رشد و مرحله‌ی توسعه‌یافته‌ی آتش.</li>\n <li><strong>زمان کوتاه:</strong> در اتاق با اثاث سینتتیک مدرن گاهی فقط ۳ تا ۴ دقیقه (UL FSRI).</li>\n</ul>\n'
    '<h3>فرآیند رخداد بک‌درفت</h3>\nدر محیطی که میزان اکسیژن کاهش یافته')
rep('<h3>ویژگی‌های Backdraft یا فلش‌اور</h3>', '<h3>ویژگی‌های بک‌درفت (Backdraft)</h3>')
rep('<h3>نکات ایمنی برای جلوگیری از فلش‌اور</h3>', '<h3>نکات ایمنی برای جلوگیری از فلش‌اور و بک‌درفت</h3>')
rep('<li><strong>تشخیص نشانه‌های فلش‌اور</strong>: مانند دود غلیظ، بوی خاص، و دمای بالای در و پنجره‌ها، که نشان از احتمال وقوع انفجار دارد.</li>',
    '<li><strong>تشخیص نشانه‌های خطر</strong>: مانند دود غلیظ زرد یا قهوه‌ای، دود پالسی، شیشه‌ی دوده‌گرفته و داغ و گرمای زیاد در و دیوار؛ این نشانه‌ها برای آموزش‌اند و هیچ‌کدام به‌تنهایی قطعی نیستند.</li>')
rep(' \t<li><strong>ایجاد تهویه کنترل‌شده</strong>: در هنگام ورود به محیط بسته، باید اکسیژن به‌تدریج وارد محیط شود و فضای بسته تهویه گردد.</li>\n</ul>',
    ' \t<li><strong>ایجاد تهویه کنترل‌شده</strong>: در هنگام ورود به محیط بسته، باید اکسیژن به‌تدریج وارد محیط شود و فضای بسته تهویه گردد.</li>\n <li><strong>درها را ببندید</strong>: طبق پژوهش UL (کمپین Close Your Door) دمای اتاقِ با درِ بسته حدود ۱۰۰°F ماند، در حالی که اتاقِ با درِ باز به بیش از ۱٬۰۰۰°F رسید.</li>\n</ul>')

# D. smoke explosion is sometimes treated as a separate phenomenon
rep('<strong>بک‌درافت</strong> یا <strong>انفجار دود</strong> یک پدیده خطرناک در آتش‌سوزی است',
    '<strong>بک‌درافت</strong> (که گاهی «<strong>انفجار دود</strong>» هم نامیده می‌شود؛ هرچند بعضی منابع این دو را از هم جدا می‌دانند) یک پدیده خطرناک در آتش‌سوزی است')

# E. the Arabic-letters block: fix a few statements that contradict current guidance (letters normalised globally below)
rep('<h2>يا به بياني ديگر:</h2>', '<h2>به بیان دیگر:</h2>')
rep('در اين هنگام قبل از انجام عمل تهويه امكان خاموش كردن حريق وجود ندارد.',
    'در اين وضعيت، نخست بايد جريان هوا كنترل و محيط خنک شود و تهويه به‌صورت هماهنگ انجام گيرد.')
rep('ايجاد تهويه زود هنگام كاري بعنوان «كنترل كننده انفجار» محسوس مي‌شود و احتمال روبرو شدن با بك درافت (Backdraft) را كاهش مي‌دهد.',
    'تهويه هماهنگ و هدفمند از بالاترين نقطه مي‌تواند خطر را كاهش دهد، اما تهويه زودهنگام يا ناهماهنگ خودش ممكن است محرک بک‌درفت شود.')
rep('همچنين وجود منو اكسيد توليد شده در محل حريق به علت انفجاري بودن باعث توليد و افزايش بك درفت مي‌شود.',
    'منواكسيد كربن و ديگر گازهاي قابل اشتعال ناشي از احتراق ناقص، سوخت لازم براي انفجار را فراهم مي‌كنند.')
rep('كه بك درفت در حالت اول رشد و فروكش كردن بوجود مي‌آيد', 'كه بك درفت معمولاً در مرحله فروكش (افول) رخ مي‌دهد')

# F. unsourced / wrong temperature and stray hyphens
rep('<li>درجه حرارت باید به ۶۰۹درجه سانتیگراد رسیده باشد.</li>',
    '<li>گازهای داغ و غنی از سوخت باید در فضا انباشته شده باشند (برای بک‌درفت «دمای ثابت» تعریف نمی‌شود؛ عدد حدود ۶۰۰ درجه‌ی سانتی‌گراد به آستانه‌ی فلش‌اور مربوط است).</li>')
rep('<li>- فضای ساختمان با گاز', '<li>فضای ساختمان با گاز')
rep('<li>- ورود ناگهانی هوا', '<li>ورود ناگهانی هوا')
rep('<li>حمله به آتش با هماهنگی کامل و احتیاط از انفجار</li>\n</ul>', '<li>حمله به آتش با هماهنگی کامل و احتیاط از انفجار</li>\n <li>خنک‌سازی محیط و کنترل مسیر جریان هوا پیش از تهویه</li>\n</ul>')

# I. unrelated, garbled hydrogen paragraph
h0 = s.index('<p data-sourcepos="7:1-7:307"><strong>انفجار احتراقی هیدروژن:</strong>')
h1 = s.index('</p>', h0) + 4
s = s[:h0] + s[h1:].lstrip('\n')

# J. copy-paste debris: ChatGPT wrapper divs and browser-extension divs (the inner paragraphs stay)
for junk in ['<div class="flex max-w-full flex-col flex-grow">\n', '<div class="min-h-8 text-message flex w-full flex-col items-end gap-2 whitespace-normal break-words [.text-message+&amp;]:mt-5" dir="auto" data-message-author-role="assistant" data-message-id="242bc02f-7590-44b4-b0a3-ef57e1b4cf33" data-message-model-slug="gpt-4o">\n',
             '<div class="flex w-full flex-col gap-1 empty:hidden first:pt-[3px]">\n', '<div class="markdown prose w-full break-words dark:prose-invert light">\n']:
    rep(junk, '')
rep('</div>\n</div>\n</div>\n</div>\n<div class="mb-2 flex gap-3 empty:hidden -ml-2">\n<div class="items-center justify-start rounded-xl p-1 flex">\n<div class="flex items-center">\n<div class="flex items-center pb-0"></div>\n</div>\n</div>\n</div>\n', '')
s = s.replace('<div class="host-bincmiainjofjnhchmcalkanjebghoen" style="position: relative; z-index: 2147483647;"></div>\n', '')

# K. sources before the company paragraph
k = '<h3>شرکت بهسازان سرای مهرآهنگ پیشتاز در عرضه انواع تجهیزات ایمنی آتش نشانی</h3>'
srcs = ('<h2>منابع علمی و بازنگری</h2>\n<p>این مقاله در مهر ۱۴۰۵ بازنگری علمی شد. منابع اصلی:</p>\n<ul>\n'
        ' <li><a href="https://fsri.org/research/firefighter-health-safety" target="_blank" rel="noopener">UL FSRI — زمان رسیدن به فلش‌اور در اثاث مدرن و قدیمی</a></li>\n'
        ' <li><a href="https://fsri.org/research/backdraft-and-smoke-explosions" target="_blank" rel="noopener">UL FSRI — پروژه‌ی Backdrafts and Smoke Explosions</a></li>\n'
        ' <li><a href="https://doi.org/10.1007/s10694-024-01553-5" target="_blank" rel="noopener">Fleischmann, Madrzykowski, Dow (2024) — Exploring Overpressure Events in Compartment Fires، Fire Technology</a></li>\n'
        ' <li><a href="https://www.ul.com/news/ul-fire-safety-research-institute-introduces-close-your-door-help-save-lives" target="_blank" rel="noopener">UL — کمپین Close Your Door (۲۰۱۷)</a></li>\n'
        ' <li><a href="https://www.fireengineering.com/?p=485680" target="_blank" rel="noopener">Fire Engineering — Differentiating the “Fireground Phenomena” (تعریف‌های NFPA 921)</a></li>\n'
        ' <li><a href="https://fsri.org/research-update/report-four-firefighters-injured-lithium-ion-battery-energy-storage-system" target="_blank" rel="noopener">UL FSRI — گزارش حادثه‌ی Surprise آریزونا (باتری لیتیوم‌یون)</a></li>\n</ul>\n'
        f'<p>برای روایت تصویری و تعاملی همین مطلب: <a href="{N3D}"><strong>تجربه‌ی سه‌بعدی بک‌درفت و فلش‌اور</strong></a>.</p>\n')
rep(k, srcs + k)

# L. Arabic yeh/kaf → Persian, in text nodes only (never inside tags/attributes or <style>)
parts = re.split(r'(<style>.*?</style>|<[^>]+>)', s, flags=re.S)
parts = [p if p.startswith('<') else p.replace('ي', 'ی').replace('ك', 'ک').replace('ى', 'ی') for p in parts]
s = ''.join(parts)

open('top-post-new.html', 'w', encoding='utf-8').write(s)
print('old', len(src), 'new', len(s))
# headings parity
H = lambda t: re.findall(r'<h([1-3])[^>]*>(.*?)</h\1>', t, re.S)
ho, hn = [x[1] for x in H(src)], [x[1] for x in H(s)]
print('\nHEADINGS removed:', [h for h in ho if h not in hn])
print('HEADINGS added  :', [h for h in hn if h not in ho])
print('arabic letters left in text:', len(re.findall(r'[يك]', re.sub(r'<[^>]+>|<style>.*?</style>', '', s, flags=re.S))))
