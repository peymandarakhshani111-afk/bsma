# تست چنددستگاهی (Playwright)

صفحه را روی ۲۰ پروفایل دستگاه (آیفون SE تا Pro Max، پیکسل، گلکسی، آیپد ۱۲٫۹، موبایل افقی، لپ‌تاپ ۱۰۲۴ تا ۲۵۶۰) باز می‌کند و در ۱۶ نقطه‌ی داستان بررسی می‌کند:
سرریز افقی، رفتن کارت زیر نوار بالا یا نوار پخش، هم‌پوشانی کارت/HUD/پلیر، کنترل‌های خیلی کوچک و نبودنِ دکمه‌های اصلی بدون اسکرول داخل کارت.

    python3 ../build-plugin.py && (cd ../deploy && python3 -m http.server 8766) &
    PLAYWRIGHT_PATH=/path/to/playwright PAGE='http://localhost:8766/preview.html?q=min&nosmooth&msaa=0' SHOTS=1 node devices.js
    ONLY="iPhone SE,landscape" node devices.js      # فقط چند دستگاه
    node player.js                                   # کنترل‌های پلیر: قبلی/بعدی، سرعت، کشیدن نوار، پخش/توقف، پنهان‌شدن در تماس
