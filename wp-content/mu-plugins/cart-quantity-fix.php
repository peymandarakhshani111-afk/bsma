<?php
/*
Plugin Name: Cart Quantity Fix
Description: Fixes cart quantity inputs by converting hidden fields to visible styled numbers
Version: 1.0
Author: محمد قربانی
*/

if (!defined('ABSPATH')) exit;

add_action('wp_footer', 'fix_all_cart_quantities', 999);
function fix_all_cart_quantities() {
    if (!is_cart()) return;
    ?>
    <script>
    (function() {
        function processQuantities() {
            // انتخاب همه فیلدهای تعداد (چه hidden چه number)
            document.querySelectorAll('input[name*="[qty]"]').forEach(function(input) {
                // اگر قبلاً پردازش شده (کلاس qty-fixed دارد)، رد کن
                if (input.classList.contains('qty-fixed')) return;

                // اگر hidden بود، تبدیل به number کن
                if (input.type === 'hidden') {
                    input.type = 'number';
                }

                // استایل‌دهی مشترک
                input.style.cssText = 'width:auto;min-width:15px;border:none;background:transparent;padding:0;margin:0;-moz-appearance:textfield;pointer-events:none;display:inline-block;font-weight:bold';

                // اگر parent قبلاً wrapper نیست، wrapper بساز
                if (!input.parentElement.classList.contains('qty-wrapper')) {
                    var wrapper = document.createElement('span');
                    wrapper.className = 'qty-wrapper';
                    wrapper.style.cssText = 'display:inline-flex;align-items:center;gap:3px;font-weight:bold';

                    // ساخت label "عدد"
                    var label = document.createElement('span');
                    label.textContent = 'عدد';
                    label.style.fontSize = 'inherit';

                    // چیدمان
                    input.parentNode.insertBefore(wrapper, input);
                    wrapper.appendChild(input);
                    wrapper.appendChild(label);
                }

                // علامت‌گذاری as processed
                input.classList.add('qty-fixed');
            });
        }

        // اجرای فوری و با تأخیر
        processQuantities();
        setTimeout(processQuantities, 300);
        setTimeout(processQuantities, 600);
        setTimeout(processQuantities, 1000);

        // نظارت روی تغییرات DOM، حداکثر یک بار در هر ۱۵۰ میلی‌ثانیه (نه با هر تغییر جزئی).
        // body دیده می‌شود چون ووکامرس بعد از به‌روزرسانی سبد، کل فرم را با فرم جدید جایگزین می‌کند.
        // از debounce استفاده نشده چون المانی که مدام تغییر می‌کند (تایمر و ...) اجرای آن را برای همیشه عقب می‌اندازد.
        var scheduled = false;
        var observer = new MutationObserver(function() {
            if (scheduled) return;
            scheduled = true;
            setTimeout(function() {
                scheduled = false;
                processQuantities();
            }, 150);
        });

        observer.observe(document.body, { childList: true, subtree: true });

    })();
    </script>
    <style>
        input[name*="[qty]"]::-webkit-outer-spin-button,
        input[name*="[qty]"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
    <?php
}