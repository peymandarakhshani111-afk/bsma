(function () {
  'use strict';
  var d = document;

  // fire-box tabs + "show more"
  var grid = d.getElementById('bhm-grid'), more = d.getElementById('bhm-more');
  if (grid) {
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.bhm-pcard'));
    var tabs = Array.prototype.slice.call(d.querySelectorAll('.bhm-tab'));
    var filter = 'all', expanded = false, LIMIT = 8;
    var fa = function (n) { return String(n).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); };
    var apply = function () {
      var shown = 0, total = 0;
      cards.forEach(function (c) {
        var match = (' ' + (c.getAttribute('data-k') || '') + ' ').indexOf(' ' + filter + ' ') > -1;
        if (match) total++;
        var visible = match && (expanded || shown < LIMIT);
        if (visible) shown++;
        c.hidden = !visible;
      });
      if (more) {
        more.hidden = expanded || total <= LIMIT;
        more.textContent = 'نمایش ' + fa(total - LIMIT) + ' مدل دیگر';
      }
    };
    grid.classList.remove('bhm-collapsed');
    tabs.forEach(function (t) {
      t.addEventListener('click', function () {
        filter = t.getAttribute('data-f'); expanded = false;
        tabs.forEach(function (x) { x.setAttribute('aria-selected', String(x === t)); });
        apply();
      });
    });
    if (more) more.addEventListener('click', function () { expanded = true; apply(); });
    apply();
  }

  // quote form -> REST -> Eitaa
  var form = d.getElementById('bhm-qform');
  if (!form || !window.BSMA_HOME) return;
  var status = d.getElementById('bhm-status');
  var btn = form.querySelector('.bhm-submit');
  var toEn = function (s) { return String(s).replace(/[۰-۹]/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(x); }).replace(/[٠-٩]/g, function (x) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(x); }); };
  var show = function (ok, msg) { status.className = 'bhm-status ' + (ok ? 'ok' : 'bad'); status.textContent = msg; };
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var name = form.elements.name.value.trim();
    var phone = toEn(form.elements.phone.value).replace(/\D/g, '');
    var okName = name.length >= 2, okPhone = /^(0|98)?9\d{9}$/.test(phone) || /^0\d{10}$/.test(phone);
    d.getElementById('bhm-e-name').textContent = okName ? '' : 'نام را بنویسید.';
    d.getElementById('bhm-e-phone').textContent = okPhone ? '' : 'شماره را کامل بنویسید؛ مثلاً ۰۹۱۲۰۰۰۰۰۰۰';
    if (!okName) { form.elements.name.focus(); return; }
    if (!okPhone) { form.elements.phone.focus(); return; }
    btn.disabled = true; var label = btn.textContent; btn.textContent = 'در حال ارسال…';
    var body = new FormData(form);
    fetch(window.BSMA_HOME.quote, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j && j.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok) { show(true, res.j.message || 'درخواست شما رسید.'); form.reset(); }
        else show(false, (res.j && res.j.message) || 'ارسال انجام نشد؛ لطفاً تماس بگیرید.');
      })
      .catch(function () { show(false, 'اتصال برقرار نشد؛ لطفاً دوباره امتحان کنید یا با ۰۳۱-۳۶۲۴۲۵۳۲ تماس بگیرید.'); })
      .then(function () { btn.disabled = false; btn.textContent = label; });
  });
})();
