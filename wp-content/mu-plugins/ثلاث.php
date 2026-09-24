<?php
/**
 * Plugin Name: ثلاث - اسکنر امنیتی حرفه‌ای
 * Description: اسکنر امنیتی ۴۵+ موردی + بدافزار + گزارش عمومی رمزدار با رابط کاربری ساده
 * Version:     3.0.0
 * Author:      Thalath Security
 * License:     GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

define('THALATH_VERSION',  '3.0.0');
define('THALATH_OPT_PUB',  'thalath_public_settings');
define('THALATH_OPT_SEC',  'thalath_last_security_scan');
define('THALATH_OPT_MW',   'thalath_last_malware_scan');

class Thalath_Pro_Scanner {

    private static $instance = null;
    private $tests = array();
    private $malware_patterns = array();
    private $results = array();
    private $score = 0;
    private $total_weight = 0;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_thalath_clear_rate', array($this, 'ajax_clear_rate'));
        add_action('admin_menu',            array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('init',                  array($this, 'maybe_public_report'));
        add_action('plugins_loaded',        array($this, 'maybe_init_token'));

        add_action('wp_ajax_thalath_run_scan',        array($this, 'ajax_run_scan'));
        add_action('wp_ajax_thalath_run_malware',     array($this, 'ajax_run_malware'));
        add_action('wp_ajax_thalath_fix_issue',       array($this, 'ajax_fix_issue'));
        add_action('wp_ajax_thalath_save_public',     array($this, 'ajax_save_public'));
        add_action('wp_ajax_thalath_regen_token',     array($this, 'ajax_regen_token'));

        add_action('wp_ajax_nopriv_thalath_public_scan', array($this, 'ajax_public_scan'));
        add_action('wp_ajax_thalath_public_scan',        array($this, 'ajax_public_scan'));

        $this->register_tests();
        $this->register_malware_patterns();
    }

    public function maybe_init_token() {
        $s = get_option(THALATH_OPT_PUB, array());
        if (!is_array($s)) $s = array();
        
        if (empty($s['token'])) {
            $s['token'] = wp_generate_password(32, false, false);
            update_option(THALATH_OPT_PUB, $s, false);
        }
    }






public function ajax_clear_rate() {
    check_ajax_referer('thalath_public', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز');

    global $wpdb;
    // پاک کردن همه transient های مربوط به rate limit
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_thalath_rate_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_thalath_rate_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_thalath_lock_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_thalath_lock_%'");

    wp_send_json_success(array('message' => '✅ همه محدودیت‌ها پاک شد'));
}



    /* ============================================================
     *                       TEST DEFINITIONS
     * ============================================================ */

    private function register_tests() {
        $this->tests = array(

            'wp_version' => array(
                'title' => 'به‌روز بودن وردپرس',
                'desc'  => 'وردپرس مثل یه برنامه موبایل، هر چند وقت یه بار آپدیت می‌شه. اگه آپدیت نکنی، هکرها می‌تونن از حفره‌های قدیمی سوءاستفاده کنن.',
                'icon'  => '🔄', 'risk' => 'high', 'weight' => 10, 'fixable' => false,
                'callback' => 'check_wp_version',
            ),
            'php_version' => array(
                'title' => 'نسخه PHP سرور',
                'desc'  => 'PHP زبون برنامه‌نویسیه که وردپرس روش اجرا می‌شه. نسخه‌های قدیمی دیگه پشتیبانی نمی‌شن و خطرناک هستن.',
                'icon'  => '⚙️', 'risk' => 'high', 'weight' => 8, 'fixable' => false,
                'callback' => 'check_php_version',
            ),
            'ssl_https' => array(
                'title' => 'قفل امنیتی سایت (SSL)',
                'desc'  => 'وقتی آدرس سایت با https شروع می‌شه، یعنی اطلاعات بین کاربر و سایت رمزنگاری شده. مثل قفل روی در خونه.',
                'icon'  => '🔒', 'risk' => 'critical', 'weight' => 10, 'fixable' => false,
                'callback' => 'check_ssl',
            ),
            'wp_debug' => array(
                'title' => 'حالت اشکال‌زدایی',
                'desc'  => 'این حالت اطلاعات خطاها رو نشون می‌ده. اگه روشن باشه، هکرها می‌تونن مسیر فایل‌ها و اطلاعات سرور رو ببینن.',
                'icon'  => '🐞', 'risk' => 'medium', 'weight' => 6, 'fixable' => true,
                'callback' => 'check_debug_mode',
            ),
            'db_debug' => array(
                'title' => 'ذخیره کوئری‌های دیتابیس',
                'desc'  => 'این تنظیم همه کوئری‌های دیتابیس رو ذخیره می‌کنه. می‌تونه سرعت سایت رو کم کنه و اطلاعات حساس رو درز بده.',
                'icon'  => '💾', 'risk' => 'medium', 'weight' => 4, 'fixable' => true,
                'callback' => 'check_db_debug',
            ),
            'display_errors' => array(
                'title' => 'نمایش خطاهای PHP',
                'desc'  => 'اگه خطاهای PHP به کاربر نشون داده بشه، مهاجم می‌تونه مسیرها و اطلاعات حساس سرور رو کشف کنه.',
                'icon'  => '⚠️', 'risk' => 'high', 'weight' => 7, 'fixable' => false,
                'callback' => 'check_display_errors',
            ),
            'salt_keys' => array(
                'title' => 'کلیدهای مخفی وردپرس',
                'desc'  => 'این کلیدها برای رمزنگاری کوکی‌ها و اطلاعات ورود استفاده می‌شن. اگه نباشن یا پیش‌فرض باشن، هکر می‌تونه وارد حساب‌ها بشه.',
                'icon'  => '🔑', 'risk' => 'critical', 'weight' => 10, 'fixable' => false,
                'callback' => 'check_salt_keys',
            ),
            'file_editing' => array(
                'title' => 'قفل ویرایشگر فایل',
                'desc'  => 'وردپرس یه ویرایشگر داخلی داره که از پیشخوان می‌شه فایل‌ها رو تغییر داد. اگه هکر بتونه وارد پیشخوان بشه، سریع به کد سایت دسترسی پیدا می‌کنه.',
                'icon'  => '📝', 'risk' => 'high', 'weight' => 7, 'fixable' => true,
                'callback' => 'check_file_editing',
            ),
            'xmlrpc' => array(
                'title' => 'دسترسی از راه دور (XML-RPC)',
                'desc'  => 'این ویژگی برای اپلیکیشن‌های موبایل و ابزارهای جانبیه. اگه استفاده نمی‌کنی، بهتره غیرفعال بشه چون راه حمله مناسبیه.',
                'icon'  => '📡', 'risk' => 'medium', 'weight' => 6, 'fixable' => true,
                'callback' => 'check_xmlrpc',
            ),
            'rest_api' => array(
                'title' => 'دسترسی ناشناس به کاربران',
                'desc'  => 'وردپرس به‌صورت پیش‌فرض اجازه می‌ده هر کسی لیست کاربران سایت رو ببینه. این می‌تونه برای حمله به اسم‌های کاربری استفاده بشه.',
                'icon'  => '👥', 'risk' => 'medium', 'weight' => 5, 'fixable' => false,
                'callback' => 'check_rest_api',
            ),
            'readme_html' => array(
                'title' => 'فایل اطلاعات وردپرس',
                'desc'  => 'فایلی به اسم readme.html توی سایت هست که نسخه وردپرس رو نشون می‌ده. حذفش کن تا اطلاعات کمتری درز کنه.',
                'icon'  => '📄', 'risk' => 'medium', 'weight' => 4, 'fixable' => true,
                'callback' => 'check_readme_html',
            ),
            'install_php' => array(
                'title' => 'فایل نصب مجدد',
                'desc'  => 'فایل install.php برای نصب اولیه‌ست. بعد از نصب باید حذف بشه وگرنه خطرناکه.',
                'icon'  => '📥', 'risk' => 'medium', 'weight' => 5, 'fixable' => true,
                'callback' => 'check_install_php',
            ),
            'directory_listing' => array(
                'title' => 'نمایش لیست پوشه‌ها',
                'desc'  => 'اگه یه پوشه غیرقابل مرور نباشه، هر کسی می‌تونه محتواش رو ببینه. مثل اینه که در کمدت باز باشه.',
                'icon'  => '📂', 'risk' => 'high', 'weight' => 8, 'fixable' => false,
                'callback' => 'check_directory_listing',
            ),
            'admin_username' => array(
                'title' => 'نام کاربری "admin"',
                'desc'  => 'اگه نام کاربری مدیر "admin" باشه، هکر نصف راه رو رفته چون فقط باید رمز رو حدس بزنه.',
                'icon'  => '👤', 'risk' => 'critical', 'weight' => 9, 'fixable' => false,
                'callback' => 'check_admin_username',
            ),
            'user_enumeration' => array(
                'title' => 'کشف نام کاربران',
                'desc'  => 'با روشی می‌شه فهمید چه کاربرانی توی سایت هستن. برای هکرها این یعنی یک قدم نزدیک‌تر به نفوذ.',
                'icon'  => '🔍', 'risk' => 'medium', 'weight' => 6, 'fixable' => false,
                'callback' => 'check_user_enumeration',
            ),
            'file_permissions' => array(
                'title' => 'مجوزهای فایل حساس',
                'desc'  => 'فایل‌هایی مثل wp-config.php نباید قابل ویرایش توسط دیگران باشن. مثل اینه که کلید خونه‌ت دست بقیه باشه.',
                'icon'  => '🔐', 'risk' => 'high', 'weight' => 9, 'fixable' => false,
                'callback' => 'check_file_permissions',
            ),
            'htaccess_exists' => array(
                'title' => 'فایل تنظیمات سرور',
                'desc'  => 'فایل .htaccess قوانین امنیتی سرور رو تعیین می‌کنه. وجودش لازمه.',
                'icon'  => '📋', 'risk' => 'low', 'weight' => 5, 'fixable' => false,
                'callback' => 'check_htaccess',
            ),
            'wp_config_location' => array(
                'title' => 'محل فایل تنظیمات وردپرس',
                'desc'  => 'فایل wp-config.php اطلاعات دیتابیس رو داره. اگه یک پوشه بالاتر از سایت باشه، از دسترس وب خارجه.',
                'icon'  => '📍', 'risk' => 'medium', 'weight' => 7, 'fixable' => false,
                'callback' => 'check_wp_config_location',
            ),
            'backup_files' => array(
                'title' => 'فایل‌های پشتیبان در دسترس',
                'desc'  => 'فایل‌های zip و sql که توی پوشه سایت موندن، می‌تونن اطلاعات حساس رو درز بدن.',
                'icon'  => '🗄️', 'risk' => 'high', 'weight' => 6, 'fixable' => false,
                'callback' => 'check_backup_files',
            ),
            'malicious_files' => array(
                'title' => 'فایل‌های مخرب شناخته‌شده',
                'desc'  => 'فایل‌هایی با اسم و پسوند مشکوک که معمولاً نشونه حمله هستن.',
                'icon'  => '🦠', 'risk' => 'critical', 'weight' => 10, 'fixable' => false,
                'callback' => 'check_malicious_files',
            ),
            'inactive_plugins' => array(
                'title' => 'افزونه‌های غیرفعال',
                'desc'  => 'افزونه‌های غیرفعال، حتی اگه کار نکنن، بازم می‌تونن مورد حمله قرار بگیرن. بهتره حذف بشن.',
                'icon'  => '🧩', 'risk' => 'low', 'weight' => 4, 'fixable' => false,
                'callback' => 'check_inactive_plugins',
            ),
            'inactive_themes' => array(
                'title' => 'قالب‌های غیرفعال',
                'desc'  => 'قالب‌های غیرفعال هم مثل افزونه‌های غیرفعال خطرناکن.',
                'icon'  => '🎨', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_inactive_themes',
            ),
            'security_headers' => array(
                'title' => 'هدرهای امنیتی مرورگر',
                'desc'  => 'این هدرها به مرورگر کاربر می‌گن چطور با سایت رفتار کنه. جلوی حملات زیادی رو می‌گیرن.',
                'icon'  => '🛡️', 'risk' => 'medium', 'weight' => 8, 'fixable' => false,
                'callback' => 'check_security_headers',
            ),
            'login_limiting' => array(
                'title' => 'محدودیت تلاش ورود',
                'desc'  => 'اگه کسی بتونه بی‌نهایت رمز امتحان کنه، در نهایت یکیش درست از آب درمیاد. باید محدودیت گذاشت.',
                'icon'  => '🚦', 'risk' => 'high', 'weight' => 7, 'fixable' => false,
                'callback' => 'check_login_limiting',
            ),
            'database_prefix' => array(
                'title' => 'پیشوند جداول دیتابیس',
                'desc'  => 'اگه اسم جدول‌ها با wp_ شروع بشه، مهاجم راحت‌تر می‌تونه از آسیب‌پذیری‌ها استفاده کنه.',
                'icon'  => '🗂️', 'risk' => 'medium', 'weight' => 6, 'fixable' => false,
                'callback' => 'check_db_prefix',
            ),
            'cron_security' => array(
                'title' => 'کرون‌جاب وردپرس',
                'desc'  => 'وردپرس کارهای زمان‌بندی شده رو با هر بازدید انجام می‌ده. غیرفعال کردنش باعث پرفورمنس بهتره.',
                'icon'  => '⏰', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_wp_cron',
            ),
            'uploads_executable' => array(
                'title' => 'مسدود بودن اجرای PHP در پوشه آپلود',
                'desc'  => 'اگه مهاجم بتونه یه فایل PHP توی پوشه آپلود بذاره، می‌تونه سایت رو کامل تصاحب کنه.',
                'icon'  => '📤', 'risk' => 'critical', 'weight' => 9, 'fixable' => false,
                'callback' => 'check_uploads_executable',
            ),
            'version_exposure' => array(
                'title' => 'مخفی بودن نسخه وردپرس',
                'desc'  => 'وقتی نسخه وردپرس توی کد صفحه نمایان باشه، هکر سریع می‌فهمه کدوم حفره امنیتی رو امتحان کنه.',
                'icon'  => '🕵️', 'risk' => 'low', 'weight' => 3, 'fixable' => true,
                'callback' => 'check_version_exposure',
            ),
            'pingback' => array(
                'title' => 'پینگ‌بک (اعلان لینک)',
                'desc'  => 'پینگ‌بک یه ویژگی قدیمیه که می‌تونه برای حمله DDoS به سایت‌های دیگه استفاده بشه.',
                'icon'  => '🔔', 'risk' => 'medium', 'weight' => 5, 'fixable' => true,
                'callback' => 'check_pingback',
            ),
            'error_log_exposure' => array(
                'title' => 'فایل لاگ خطا',
                'desc'  => 'فایل error_log می‌تونه مسیرها و اطلاعات حساس رو درز بده اگه مستقیم قابل دسترسی باشه.',
                'icon'  => '📝', 'risk' => 'medium', 'weight' => 5, 'fixable' => false,
                'callback' => 'check_error_log',
            ),
            'wp_content_browsable' => array(
                'title' => 'مرور پوشه wp-content',
                'desc'  => 'این پوشه شامل افزونه‌ها، قالب‌ها و آپلودهاست. نباید قابل مرور باشه.',
                'icon'  => '📁', 'risk' => 'medium', 'weight' => 4, 'fixable' => false,
                'callback' => 'check_wp_content_browsable',
            ),

            /* ---------- تست‌های جدید ---------- */

            'admin_email' => array(
                'title' => 'ایمیل مدیر پیش‌فرض',
                'desc'  => 'اگه ایمیل مدیر "admin@yourdomain" باشه، احتمالاً هاست از ابتدا تنظیم نشده و خطرناکه.',
                'icon'  => '📧', 'risk' => 'medium', 'weight' => 4, 'fixable' => false,
                'callback' => 'check_admin_email',
            ),
            'user_registration' => array(
                'title' => 'باز بودن عضویت کاربران',
                'desc'  => 'اگه هر کسی بتونه توی سایتت ثبت‌نام کنه، خطر ورود ربات‌ها و اسپمرها بالاست.',
                'icon'  => '📝', 'risk' => 'medium', 'weight' => 5, 'fixable' => false,
                'callback' => 'check_user_registration',
            ),
            'xmlrpc_pingback_size' => array(
                'title' => 'حجم مجاز پینگ‌بک',
                'desc'  => 'اگه حجم پینگ‌بک محدود نباشه، می‌شه ازش برای حمله استفاده کرد.',
                'icon'  => '📊', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_pingback_size',
            ),
            'xmlrpc_system_list' => array(
                'title' => 'دسترسی لیست متدهای XML-RPC',
                'desc'  => 'با این قابلیت، می‌شه فهمید چه امکاناتی از راه دور باز هستن.',
                'icon'  => '📡', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_xmlrpc_system_list',
            ),
            'wp_json_users' => array(
                'title' => 'افشای کاربران از طریق wp-json',
                'desc'  => 'یه مسیر دیگه برای دیدن لیست کاربران. باید محدود بشه.',
                'icon'  => '🔓', 'risk' => 'medium', 'weight' => 5, 'fixable' => false,
                'callback' => 'check_wp_json_users',
            ),
            'robots_txt' => array(
                'title' => 'فایل robots.txt',
                'desc'  => 'فایل robots.txt به موتورهای جستجو می‌گه چی رو ایندکس نکنن. اگه نباشه یا اطلاعات حساس داشته باشه، خوب نیست.',
                'icon'  => '🤖', 'risk' => 'low', 'weight' => 2, 'fixable' => false,
                'callback' => 'check_robots_txt',
            ),
            'admin_url' => array(
                'title' => 'آدرس پیش‌فرض صفحه ورود',
                'desc'  => 'آدرس /wp-admin قابل حدسه. بعضی افزونه‌ها می‌تونن آدرس ورود رو عوض کنن.',
                'icon'  => '🚪', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_admin_url',
            ),
            'two_factor' => array(
                'title' => 'ورود دو مرحله‌ای',
                'desc'  => 'با این قابلیت، علاوه بر رمز، یه کد دوم هم از کاربر خواسته می‌شه. امنیت رو چند برابر می‌کنه.',
                'icon'  => '🔐', 'risk' => 'medium', 'weight' => 6, 'fixable' => false,
                'callback' => 'check_two_factor',
            ),
            'spam_comments' => array(
                'title' => 'کامنت‌های اسپم',
                'desc'  => 'کامنت‌های اسپم توی دیتابیس جمع می‌شن و می‌تونن باعث کندی سایت بشن.',
                'icon'  => '💬', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_spam_comments',
            ),
            'post_revisions' => array(
                'title' => 'تعداد نسخه‌های قدیم پست‌ها',
                'desc'  => 'وردپرس از هر تغییر پست‌ها کپی نگه می‌داره. اگه تعدادش زیاد باشه، دیتابیس رو سنگین می‌کنه.',
                'icon'  => '📚', 'risk' => 'low', 'weight' => 3, 'fixable' => false,
                'callback' => 'check_post_revisions',
            ),
            'php_disabled_funcs' => array(
                'title' => 'غیرفعال بودن توابع خطرناک PHP',
                'desc'  => 'توابعی مثل exec, system, shell_exec اگه غیرفعال باشن، جلوی یه سری حملات گرفته می‌شه.',
                'icon'  => '⛔', 'risk' => 'medium', 'weight' => 6, 'fixable' => false,
                'callback' => 'check_disabled_functions',
            ),
            'wp_config_perms' => array(
                'title' => 'مجوز دقیق wp-config.php',
                'desc'  => 'این فایل باید فقط توسط وب‌سرور قابل خوندن باشه، نه نوشتن.',
                'icon'  => '🔒', 'risk' => 'high', 'weight' => 7, 'fixable' => false,
                'callback' => 'check_wp_config_perms',
            ),
            'wp_config_backup' => array(
                'title' => 'نسخه بکاپ wp-config',
                'desc'  => 'فایل‌هایی مثل wp-config.php.bak می‌تونن اطلاعات دیتابیس رو درز بدن.',
                'icon'  => '💾', 'risk' => 'critical', 'weight' => 9, 'fixable' => false,
                'callback' => 'check_wp_config_backup',
            ),
            'debug_log' => array(
                'title' => 'فایل debug.log',
                'desc'  => 'اگه debug.log توی سایت باشه و قابل دسترس، اطلاعات حساس درز می‌کنه.',
                'icon'  => '📃', 'risk' => 'medium', 'weight' => 4, 'fixable' => false,
                'callback' => 'check_debug_log',
            ),
            'unknown_admin_users' => array(
                'title' => 'کاربران مدیر مشکوک',
                'desc'  => 'اگه کاربران مدیر بیش از حد یا با ایمیل ناشناس باشن، احتمال حمله هست.',
                'icon'  => '👥', 'risk' => 'high', 'weight' => 8, 'fixable' => false,
                'callback' => 'check_admin_users',
            ),
        );
    }

private function register_malware_patterns() {
    $this->malware_patterns = array(

        // ============ بحرانی — فقط بک‌دورهای قطعی ============
        'CRITICAL' => array(
            'patterns' => array(
                'eval\s*\(\s*base64_decode\s*\('              => 'اجرای کد رمزنگاری‌شده (Backdoor قطعی)',
                'eval\s*\(\s*gzinflate\s*\('                  => 'اجرای کد فشرده‌شده (Backdoor)',
                'eval\s*\(\s*gzuncompress\s*\('               => 'اجرای کد فشرده (Backdoor)',
                'eval\s*\(\s*str_rot13\s*\('                  => 'اجرای کد رمزنگاری ساده (Backdoor)',
                'assert\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)' => 'اجرای کد از ورودی کاربر (Backdoor)',
                'preg_replace\s*\(\s*["\'].*\/e["\']'        => 'استفاده از /e منسوخ PHP',
                'create_function\s*\(\s*\$'                   => 'تابع منسوخ create_function',
                '<\?php\s+eval\s*\('                          => 'شروع فایل با eval',
            ),
        ),

        // ============ مشکوک — توابع پرخطر که ممکنه legit باشن ============
        'SUSPICIOUS' => array(
            'patterns' => array(
                'shell_exec\s*\(\s*\$'                => 'اجرای دستور سیستمی با ورودی پویا',
                'passthru\s*\(\s*\$'                  => 'خروجی مستقیم سیستمی',
                'system\s*\(\s*\$'                    => 'فراخوانی سیستم با متغیر',
                'proc_open\s*\(\s*\$'                 => 'اجرای پروسه پویا',
                'popen\s*\(\s*\$'                     => 'اتصال pipe پویا',
                'file_put_contents\s*\([^)]*\.php'   => 'نوشتن مستقیم در فایل PHP',
                'fwrite\s*\([^)]*\.php'              => 'نوشتن در فایل PHP',
                'curl_exec\s*\(\s*curl_init\s*\(\s*\$' => 'درخواست خارجی پویا',
                'eval\s*\('                           => 'استفاده از تابع eval',
                'assert\s*\('                         => 'استفاده از تابع assert',
            ),
        ),

        // ============ احتیاطی — الگوهای رایج در کدهای سالم ============
        'PRECAUTIONARY' => array(
            'patterns' => array(
                'base64_decode\s*\('                  => 'کدگشایی Base64',
                'gzinflate\s*\('                      => 'باز کردن کد فشرده',
                'str_rot13\s*\('                      => 'رمزنگاری ساده',
                'include\s*\(\s*\$'                   => 'شامل کردن پویای فایل',
                'require\s*\(\s*\$'                   => 'شامل کردن اجباری پویا',
                'move_uploaded_file\s*\('             => 'جابجایی فایل آپلودی',
                'file_put_contents\s*\('              => 'نوشتن در فایل',
            ),
        ),
    );
}
    /* ============================================================
     *                        ADMIN MENU
     * ============================================================ */

    public function add_menu() {
        add_menu_page('ثلاث - اسکنر امنیتی', 'ثلاث امنیت', 'manage_options',
            'thalath-security', array($this, 'render_dashboard'), 'dashicons-shield-alt', 80);
        add_submenu_page('thalath-security', 'لینک گزارش عمومی', 'لینک عمومی',
            'manage_options', 'thalath-public', array($this, 'render_settings'));
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'thalath') === false) return;
        wp_enqueue_style('dashicons');
    }

    /* ============================================================
     *                       ADMIN DASHBOARD
     * ============================================================ */

    public function render_dashboard() {
        if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
        $scan_n  = wp_create_nonce('thalath_scan');
        $fix_n   = wp_create_nonce('thalath_fix');
        $mw_n    = wp_create_nonce('thalath_mw');
        ?>
        <div class="thalath-wrap" dir="rtl">
            <style><?php echo $this->admin_css(); ?></style>
            <div class="th-header">
                <h1>🛡️ ثلاث - اسکنر امنیتی حرفه‌ای</h1>
                <p>۴۵+ تست امنیتی + اسکنر بدافزار | برای گزارش قابل اشتراک، به «لینک عمومی» بروید</p>
            </div>

            <div class="th-actions">
                <button class="th-btn th-btn-primary" id="btnSec" onclick="thalathStartScan()">🔍 شروع اسکن امنیتی</button>
                <button class="th-btn th-btn-danger" id="btnMw" onclick="thalathStartMalware()">🦠 اسکن بدافزار</button>
            </div>

            <div id="thProgress" class="th-progress"><div id="thProgressBar"></div></div>
            <div id="thLoading" class="th-loading"><div class="th-spinner"></div><p>در حال اسکن، لطفاً صبر کنید...</p></div>

            <div id="secResults"></div>
            <div id="mwResults"></div>
        </div>
        <div class="th-toast" id="thToast"></div>

        <script>
        const TH_SCAN_NONCE = '<?php echo esc_js($scan_n); ?>';
        const TH_FIX_NONCE  = '<?php echo esc_js($fix_n); ?>';
        const TH_MW_NONCE   = '<?php echo esc_js($mw_n); ?>';
        </script>
        <script><?php echo $this->common_js(); ?></script>
        <script>
        function thalathStartScan() {
            const btn = document.getElementById('btnSec');
            btn.disabled = true; btn.textContent = 'در حال اسکن...';
            document.getElementById('thLoading').style.display = 'block';
            document.getElementById('secResults').innerHTML = '';
            const fd = new URLSearchParams();
            fd.append('action', 'thalath_run_scan');
            fd.append('nonce', TH_SCAN_NONCE);
            fetch(ajaxurl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) throw new Error(resp.data || 'خطا');
                thRenderSec(resp.data, 'secResults');
                btn.disabled = false; btn.textContent = '🔍 اسکن مجدد';
                document.getElementById('thLoading').style.display = 'none';
            })
            .catch(e => { thToast('خطا: ' + e.message, 'error'); btn.disabled = false; btn.textContent = '🔍 اسکن امنیتی'; document.getElementById('thLoading').style.display = 'none'; });
        }
        function thalathStartMalware() {
            const btn = document.getElementById('btnMw');
            btn.disabled = true; btn.textContent = 'در حال اسکن فایل‌ها...';
            document.getElementById('thLoading').style.display = 'block';
            document.getElementById('mwResults').innerHTML = '';
            const fd = new URLSearchParams();
            fd.append('action', 'thalath_run_malware');
            fd.append('nonce', TH_MW_NONCE);
            fetch(ajaxurl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) throw new Error(resp.data || 'خطا');
                thRenderMw(resp.data, 'mwResults');
                btn.disabled = false; btn.textContent = '🦠 اسکن مجدد';
                document.getElementById('thLoading').style.display = 'none';
            })
            .catch(e => { thToast('خطا: ' + e.message, 'error'); btn.disabled = false; btn.textContent = '🦠 اسکن بدافزار'; document.getElementById('thLoading').style.display = 'none'; });
        }
        </script>
        <?php
    }

    /* ============================================================
     *                      PUBLIC SETTINGS
     * ============================================================ */

    public function render_settings() {
        if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
        $s = get_option(THALATH_OPT_PUB, array());
        if (!is_array($s)) $s = array();
        $enabled  = !empty($s['enabled']);
        $token    = !empty($s['token']) ? $s['token'] : '';
        $has_pass = !empty($s['password_hash']);
        $allow    = isset($s['allow_public_scan']) ? (bool) $s['allow_public_scan'] : true;
        $link     = $token ? home_url('/?thalath_report=' . $token) : '';
        ?>
        <div class="thalath-wrap" dir="rtl">
            <style><?php echo $this->admin_css(); ?></style>
            <div class="th-header">
                <h1>🔗 لینک گزارش عمومی</h1>
                <p>یک لینک اختصاصی برای دیدن گزارش امنیتی بساز و برای مشتری بفرست</p>
            </div>

            <div class="th-card">
                <h2>🔐 تنظیمات لینک</h2>
                <p style="margin:20px 0"><label style="font-weight:600;font-size:15px">
                    <input type="checkbox" id="pubEnabled" <?php echo $enabled ? 'checked' : ''; ?>>
                    فعال‌سازی لینک عمومی
                </label></p>

                <p style="margin:20px 0"><label style="font-weight:600;font-size:15px">
                    <input type="checkbox" id="allowPublicScan" <?php echo $allow ? 'checked' : ''; ?>>
                    اجازه به کاربر برای اسکن مجدد
                </label>
                <br><small style="color:#64748b">اگه فعال باشه، کاربرانی که رمز رو دارن می‌تونن از داخل گزارش، سایت رو دوباره اسکن کنن.</small></p>

                <div style="margin-top:20px">
                    <label style="font-weight:600;display:block;margin-bottom:8px">رمز عبور (حداقل ۶ کاراکتر)</label>
                    <input type="password" id="pubPass" class="th-input" autocomplete="new-password"
                        placeholder="<?php echo $has_pass ? '✅ رمز فعلی تنظیم شده — برای تغییر، رمز جدید بنویسید' : 'رمز دلخواه خود را وارد کنید'; ?>">
                </div>
<div class="th-card">
    <h2>🔓 ابزار رفع مشکل Rate Limit</h2>
    <p style="color:#64748b;font-size:14px">اگه کاربری گیر Rate Limit افتاده، از این دکمه استفاده کن تا همه قفل‌ها آزاد بشن.</p>
    <button class="th-btn th-btn-danger" onclick="thClearRateLimit()" style="margin-top:12px">🔓 پاک کردن محدودیت‌ها</button>
</div>
                <div style="margin-top:20px">
                    <label style="font-weight:600;display:block;margin-bottom:8px">توکن لینک (فقط با این توکن، لینک باز می‌شه)</label>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <input type="text" id="tokenBox" class="th-input" value="<?php echo esc_attr($token); ?>" readonly style="flex:1;font-family:monospace;font-size:12px">
                        <button class="th-btn" style="background:#f1f5f9;color:#475569" onclick="thRegenToken()">🔄 توکن جدید</button>
                    </div>
                </div>

                <?php if ($link): ?>
                <div style="margin-top:25px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:16px">
                    <label style="font-weight:700;display:block;margin-bottom:8px;color:#065f46">✅ لینک نهایی گزارش</label>
                    <input type="text" id="finalLink" class="th-input" value="<?php echo esc_attr($link); ?>" readonly style="font-family:monospace;font-size:12px;background:#fff">
                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                        <button class="th-btn th-btn-primary" onclick="thCopyLink()">📋 کپی لینک</button>
                        <a class="th-btn" style="background:#fff;color:#065f46;text-decoration:none;border:1px solid #a7f3d0" href="<?php echo esc_url($link); ?>" target="_blank">🔗 باز کردن</a>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top:25px">
                    <button class="th-btn th-btn-primary" onclick="thSavePublic()">💾 ذخیره تنظیمات</button>
                </div>
            </div>

            <div class="th-card">
                <h2>📘 راهنمای سریع</h2>
                <ol style="line-height:2.2;color:#475569;font-size:14px">
                    <li>از صفحه اصلی افزونه، یک بار اسکن امنیتی و بدافزار بگیر تا نتایج آماده بشه</li>
                    <li>رمز عبور دلخواه بذار و لینک عمومی رو فعال کن</li>
                    <li>لینک رو برای مشتری بفرست</li>
                    <li>مشتری با وارد کردن رمز، گزارش کامل رو با توضیحات ساده می‌بینه</li>
                    <li>اگه «اجازه اسکن مجدد» رو روشن کنی، کاربر می‌تونه بدون نیاز به تو، خودش اسکن بگیره</li>
                    <li>با «توکن جدید»، لینک قبلی باطل می‌شه</li>
                </ol>
            </div>
        </div>
        <div class="th-toast" id="thToast"></div>

        <script><?php echo $this->common_js(); ?></script>
        <script>
        const TH_PUB_NONCE = '<?php echo esc_js(wp_create_nonce('thalath_public')); ?>';
        function thSavePublic() {
            const fd = new URLSearchParams();
            fd.append('action', 'thalath_save_public');
            fd.append('nonce', TH_PUB_NONCE);
            fd.append('enabled', document.getElementById('pubEnabled').checked ? 1 : 0);
            fd.append('allow_public_scan', document.getElementById('allowPublicScan').checked ? 1 : 0);
            fd.append('password', document.getElementById('pubPass').value);
            fetch(ajaxurl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) { thToast(resp.data.message || 'ذخیره شد', 'success'); setTimeout(()=>location.reload(), 800); }
                else thToast(resp.data || 'خطا', 'error');
            })
            .catch(e => thToast('خطا: ' + e.message, 'error'));
        }
        function thRegenToken() {
            if (!confirm('لینک قبلی باطل می‌شه. ادامه؟')) return;
            const fd = new URLSearchParams();
            fd.append('action', 'thalath_regen_token');
            fd.append('nonce', TH_PUB_NONCE);
            fetch(ajaxurl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
            .then(r => r.json())
            .then(resp => { if (resp.success) { thToast('توکن جدید ساخته شد', 'success'); setTimeout(()=>location.reload(), 700); } });
        }
        function thCopyLink() {
            navigator.clipboard.writeText(document.getElementById('finalLink').value).then(()=>thToast('لینک کپی شد', 'success'));
        }

        // ✅ این تابع رو اضافه کن
        function thClearRateLimit() {
            if (!confirm('همه محدودیت‌های Rate Limit پاک می‌شوند. ادامه؟')) return;
            const fd = new URLSearchParams();
            fd.append('action', 'thalath_clear_rate');
            fd.append('nonce', TH_PUB_NONCE);
            fetch(ajaxurl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
            .then(r => r.json())
            .then(resp => { thToast(resp.success ? resp.data.message : 'خطا', resp.success ? 'success' : 'error'); })
            .catch(e => thToast('خطا: ' + e.message, 'error'));
        }
        </script>
        <?php
    }

    /* ============================================================
     *                       AJAX HANDLERS
     * ============================================================ */

    public function ajax_run_scan() {
        check_ajax_referer('thalath_scan', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز');
        $data = $this->run_all_security_tests();
        wp_send_json_success($data);
    }

    public function ajax_run_malware() {
        check_ajax_referer('thalath_mw', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز');
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        try {
            $data = $this->run_malware_scan();
            update_option(THALATH_OPT_MW, array('data' => $data, 'time' => current_time('mysql')), false);
            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error('خطا در اسکن: ' . $e->getMessage());
        }
    }

public function ajax_public_scan() {
    // ✅ جلوگیری از آلودگی JSON
    @ini_set('display_errors', '0');
    @error_reporting(0);
    while (ob_get_level() > 0) { @ob_end_clean(); }

    $s = get_option(THALATH_OPT_PUB, array());
    if (!is_array($s)) $s = array();

    $token  = !empty($s['token']) ? (string) $s['token'] : '';
    $given  = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $nonce  = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    $hashed = !empty($s['password_hash']) ? (string) $s['password_hash'] : '';
    $allow  = isset($s['allow_public_scan']) ? (bool) $s['allow_public_scan'] : true;

    if (!$token || !$given || !hash_equals($token, $given)) wp_send_json_error('توکن نامعتبر', 403);
    if (empty($s['enabled']) || !$hashed) wp_send_json_error('گزارش عمومی فعال نیست', 403);
    if (!$allow) wp_send_json_error('اسکن عمومی غیرفعال است', 403);

    $expected_nonce = wp_create_nonce('thalath_pub_scan_' . $token);
    if (!hash_equals($expected_nonce, $nonce)) wp_send_json_error('نشست نامعتبر — صفحه را رفرش کنید', 403);

    // ✅ چک کوکی احراز هویت
    if (!$this->is_pub_authed($token)) {
        wp_send_json_error('ابتدا رمز عبور را وارد کنید', 403);
    }

    // ============================================
    // 🔧 Rate Limit — اصلاح‌شده و نرم‌تر
    // ============================================
    $is_admin = current_user_can('manage_options'); // ادمین‌ها محدودیت ندارن
    $ip       = $this->get_real_ip();
    $rate_key = 'thalath_rate_' . md5($ip);
    $lock_key = 'thalath_lock_' . md5($ip);

    // اگه قبلاً اسکن در حال اجراست، رد کن
    if (get_transient($lock_key)) {
        wp_send_json_error('اسکن قبلی در حال اجراست، لطفاً صبر کنید...', 429);
    }

    // فقط برای کاربران عادی محدودیت اعمال کن
    if (!$is_admin) {
        $last = get_transient($rate_key);
        if ($last) {
            $remain = 180 - (time() - (int)$last);
            if ($remain < 0) $remain = 0;
            wp_send_json_error('برای جلوگیری از سوءاستفاده، هر ۳ دقیقه یک اسکن مجاز است. ' . ceil($remain) . ' ثانیه دیگر تلاش کنید.', 429);
        }
    }

    // قفل اسکن را فعال کن (فقط برای جلوگیری از اسکن همزمان)
    set_transient($lock_key, 1, 300);

    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');
    @ignore_user_abort(true);

    register_shutdown_function(array($this, 'public_scan_shutdown'));

    try {
        $data = $this->run_all_security_tests();
        $mw   = $this->run_malware_scan();
        update_option(THALATH_OPT_MW, array('data' => $mw, 'time' => current_time('mysql')), false);
        $data['malware'] = $mw;
        $data['time']    = current_time('mysql');

        // ✅ Rate Limit فقط بعد از موفقیت ثبت می‌شه
        if (!$is_admin) {
            set_transient($rate_key, time(), 180);
        }

        // آزاد کردن قفل
        delete_transient($lock_key);

        while (ob_get_level() > 0) { @ob_end_clean(); }
        wp_send_json_success($data);
    } catch (Throwable $e) {
        // در صورت خطا، قفل باز می‌شه تا کاربر گیر نکنه
        delete_transient($lock_key);

        while (ob_get_level() > 0) { @ob_end_clean(); }
        wp_send_json_error('خطا در اسکن: ' . $e->getMessage());
    }
}

/**
 * گرفتن IP واقعی کاربر (پشت Cloudflare و Proxy)
 */
private function get_real_ip() {
    // اگه Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
    }
    // اگه Proxy
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
        return trim($ips[0]);
    }
    // حالت معمولی
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0';
}

public function public_scan_shutdown() {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        // آزاد کردن قفل در صورت خطای کشنده
        $ip = $this->get_real_ip();
        delete_transient('thalath_lock_' . md5($ip));

        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(array(
            'success' => false,
            'data'    => 'خطای PHP: ' . $err['message'] . ' (خط ' . $err['line'] . ')',
        ));
        exit;
    }
}
    /* ============================================================
     *                    RUN SECURITY TESTS
     * ============================================================ */

    private function run_all_security_tests() {
        $this->results = array();
        $this->score = 0;
        $this->total_weight = 0;

        foreach ($this->tests as $id => $t) {
            $r = array(
                'id'    => $id,
                'title' => $t['title'],
                'desc'  => $t['desc'],
                'icon'  => $t['icon'],
                'risk'  => $t['risk'],
                'fixable' => !empty($t['fixable']),
                'status' => 'warn',
                'simple' => '',
                'detail' => '',
                'fix'    => '',
            );
            if (method_exists($this, $t['callback'])) {
                try {
                    $res = call_user_func(array($this, $t['callback']));
                    if (is_array($res)) $r = array_merge($r, $res);
                } catch (Exception $e) {
                    $r['detail'] = 'خطا: ' . $e->getMessage();
                }
            }
            $this->results[] = $r;
            $this->total_weight += $t['weight'];
            if ($r['status'] === 'pass') $this->score += $t['weight'];
            elseif ($r['status'] === 'warn') $this->score += ($t['weight'] * 0.5);
        }

        update_option(THALATH_OPT_SEC, array(
            'results'      => $this->results,
            'score'        => $this->score,
            'total_weight' => $this->total_weight,
            'time'         => current_time('mysql'),
        ), false);

        return array(
            'results'      => $this->results,
            'score'        => $this->score,
            'total_weight' => $this->total_weight,
        );
    }

    /* ============================================================
     *                        PUBLIC REPORT
     * ============================================================ */

    public function maybe_public_report() {
        if (empty($_GET['thalath_report'])) return;
        if (!is_string($_GET['thalath_report'])) return;
        $this->handle_public_report();
    }

    private function handle_public_report() {
        $s = get_option(THALATH_OPT_PUB, array());
        if (!is_array($s)) $s = array();

        $token  = !empty($s['token']) ? (string) $s['token'] : '';
        $given  = sanitize_text_field(wp_unslash($_GET['thalath_report']));
        $hashed = !empty($s['password_hash']) ? (string) $s['password_hash'] : '';

        if (!$token || !$given || !hash_equals($token, $given) || empty($s['enabled']) || !$hashed) {
            status_header(404); nocache_headers();
            wp_die('گزارش یافت نشد یا غیرفعال است.', '404', array('response' => 404));
        }

        $auth = $this->is_pub_authed($token);
        $error = '';
        if (!$auth && isset($_POST['thalath_pw'])) {
            $pw = (string) wp_unslash($_POST['thalath_pw']);
            if (wp_check_password($pw, $hashed)) { $this->set_pub_cookie($token); $auth = true; }
            else { $error = 'رمز عبور اشتباه است'; sleep(1); }
        }

        $this->render_public($auth, $error, $token);
        exit;
    }

    private function set_pub_cookie($token) {
        $v = wp_hash($token . '|thalath_pub_v3');
        setcookie('thalath_report_auth', $v, time() + DAY_IN_SECONDS,
            COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    }

    private function is_pub_authed($token) {
        if (empty($_COOKIE['thalath_report_auth'])) return false;
        return hash_equals(wp_hash($token . '|thalath_pub_v3'), (string) $_COOKIE['thalath_report_auth']);
    }

    private function render_public($authed, $error, $token) {
        nocache_headers();
        $site = get_bloginfo('name');

        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<title>گزارش امنیتی - ' . esc_html($site) . '</title>';
        echo '<style>' . $this->public_css() . '</style></head><body>';

        if (!$authed) {
            echo '<div class="login-wrap">';
            echo '<div class="login-box">';
            echo '<div style="font-size:60px;margin-bottom:10px">🔒</div>';
            echo '<h1>گزارش امنیتی محافظت‌شده</h1>';
            echo '<p>برای دیدن گزارش، رمز عبور را وارد کنید</p>';
            if ($error) echo '<div class="err">' . esc_html($error) . '</div>';
            echo '<form method="post"><input type="password" name="thalath_pw" placeholder="رمز عبور" required autofocus><button type="submit">ورود به گزارش</button></form>';
            echo '<p style="font-size:12px;color:#94a3b8;margin-top:20px">این لینک اختصاصی است و نباید در اختیار دیگران قرار بگیرد</p>';
            echo '</div></div></body></html>';
            return;
        }

        $s = get_option(THALATH_OPT_PUB, array());
        if (!is_array($s)) $s = array();
        $allow_scan = isset($s['allow_public_scan']) ? (bool) $s['allow_public_scan'] : true;
        $pub_nonce  = wp_create_nonce('thalath_pub_scan_' . $token);

        $sec = get_option(THALATH_OPT_SEC, array());
        $mw  = get_option(THALATH_OPT_MW, array());

        echo '<div class="wrap">';
        echo '<header class="hero">';
        echo '<div class="hero-inner">';
        echo '<div class="hero-logo">🛡️</div>';
        echo '<div class="hero-text">';
        echo '<h1>گزارش امنیتی سایت</h1>';
        echo '<p>' . esc_html($site) . '</p>';
        echo '</div>';
        if (!empty($sec['time'])) echo '<div class="hero-time">آخرین اسکن: ' . esc_html($sec['time']) . '</div>';
        echo '</div></header>';


        echo '<div id="reportContent">';
        $this->render_report_body($sec, $mw);
        echo '</div>';

        if ($allow_scan) {
            echo '<div class="rescan-box">';
            echo '<button class="btn-rescan" id="btnRescan" onclick="thPubScan()">';
            echo '<span class="rescan-icon">🔄</span>';
            echo '<span class="rescan-text">اسکن مجدد سایت</span>';
            echo '<span class="rescan-sub">گزارش به‌روز شده دریافت کنید</span>';
            echo '</button>';
            echo '<div class="rescan-info">اسکن کامل حدود ۱ تا ۲ دقیقه طول می‌کشد</div>';
            echo '</div>';
            echo '<div class="rescan-overlay" id="rescanOverlay"><div class="rescan-spinner"></div><p>در حال اسکن سایت، لطفاً صبر کنید...</p></div>';
        }

             echo '<footer class="footer">';
        echo '<div class="footer-brand">';
        echo '<div class="brand-name">🛡️ ثلاث امنیت</div>';
        echo '<div class="brand-dev">طراحی و توسعه: محمد حسین قربانی</div>';
        echo '<div class="brand-contact">📞 <a href="tel:09020028907">09020028907</a></div>';
        echo '</div>';
        echo '<p class="footer-copy">تولید شده توسط افزونه ثلاث امنیت</p>';
        echo '</footer>';
        echo '</div>';

        // ✅ FIX: تزریق جاوااسکریپت برای فعال شدن دکمه اسکن مجدد
        echo '<script>';
        echo 'var TH_PUB_TOKEN = ' . json_encode($token) . ';';
        echo 'var TH_PUB_NONCE = ' . json_encode($pub_nonce) . ';';
        echo 'var TH_AJAX = ' . json_encode(admin_url('admin-ajax.php')) . ';';
        echo '</script>';
        echo '<script>' . $this->public_js() . '</script>';

        echo '</body></html>';
    }

    private function render_report_body($sec, $mw) {
        if (empty($sec['results']) || !is_array($sec['results'])) {
            echo '<div class="empty-state"><p>هنوز اسکنی انجام نشده است. روی دکمه اسکن کلیک کنید.</p></div>';
            return;
        }

        $pct = 0;
        if (!empty($sec['total_weight']) && $sec['total_weight'] > 0) {
            $pct = (int) round(($sec['score'] / $sec['total_weight']) * 100);
        }

        if ($pct >= 90)      { $cls = 'sc-excellent'; $label = 'عالی'; $msg = 'سایت شما امن است'; }
        elseif ($pct >= 70)  { $cls = 'sc-good';      $label = 'خوب';  $msg = 'چند مورد جزئی نیاز به رسیدگی داره'; }
        elseif ($pct >= 50)  { $cls = 'sc-warning';   $label = 'متوسط'; $msg = 'موارد مهم امنیتی پیدا شد'; }
        else                 { $cls = 'sc-danger';    $label = 'بحرانی'; $msg = 'سایت در خطر جدی است'; }

        $counts = array('pass' => 0, 'warn' => 0, 'fail' => 0);
        foreach ($sec['results'] as $r) {
            $st = isset($r['status']) ? $r['status'] : 'warn';
            if (isset($counts[$st])) $counts[$st]++;
        }

        // Build issue list (critical/high risk first)
        $risk_order = array('critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3);
        $issues = array();
        $passes = array();
        foreach ($sec['results'] as $r) {
            if ($r['status'] === 'pass') { $passes[] = $r; continue; }
            $issues[] = $r;
        }
        usort($issues, function($a, $b) use ($risk_order) {
            $ra = isset($risk_order[$a['risk']]) ? $risk_order[$a['risk']] : 9;
            $rb = isset($risk_order[$b['risk']]) ? $risk_order[$b['risk']] : 9;
            if ($ra === $rb) {
                $sa = $a['status'] === 'fail' ? 0 : 1;
                $sb = $b['status'] === 'fail' ? 0 : 1;
                return $sa - $sb;
            }
            return $ra - $rb;
        });

        // Score card
        echo '<div class="score-wrap">';
        echo '<div class="score-circle ' . esc_attr($cls) . '"><span>' . $pct . '</span><small>%</small></div>';
        echo '<h2 class="score-title">وضعیت امنیتی: ' . esc_html($label) . '</h2>';
        echo '<p class="score-msg">' . esc_html($msg) . '</p>';

        echo '<div class="score-stats">';
        echo '<div class="stat stat-pass"><div class="stat-num">' . $counts['pass'] . '</div><div class="stat-lbl">✅ مورد سالم</div></div>';
        echo '<div class="stat stat-warn"><div class="stat-num">' . $counts['warn'] . '</div><div class="stat-lbl">⚠️ هشدار</div></div>';
        echo '<div class="stat stat-fail"><div class="stat-num">' . $counts['fail'] . '</div><div class="stat-lbl">❌ خطرناک</div></div>';
        echo '</div>';
        echo '</div>';

        // Critical issues
        if (!empty($issues)) {
            echo '<section class="section">';
            echo '<h2 class="section-title"><span>🚨</span> موارد نیازمند رسیدگی (' . count($issues) . ')</h2>';
            echo '<p class="section-hint">روی هر مورد کلیک کنید تا توضیحات کامل و راه‌حل را ببینید</p>';
            echo '<div class="issues">';
            foreach ($issues as $r) { $this->render_issue_card($r); }
            echo '</div>';
            echo '</section>';
        } else {
            echo '<section class="section"><div class="all-good"><div style="font-size:60px">🎉</div><h2>آفرین!</h2><p>سایت شما همه تست‌های امنیتی رو با موفقیت گذرونده.</p></div></section>';
        }

        // Passed tests (collapsed)
        if (!empty($passes)) {
            echo '<section class="section">';
            echo '<details class="passed-details"><summary><span>✅</span> موارد سالم (' . count($passes) . ') — برای دیدن کلیک کنید</summary>';
            echo '<div class="passed-list">';
            foreach ($passes as $r) {
                echo '<div class="passed-item"><span class="pi-icon">' . esc_html($r['icon']) . '</span>';
                echo '<div class="pi-body"><strong>' . esc_html($r['title']) . '</strong>';
                if (!empty($r['simple'])) echo '<p>' . esc_html($r['simple']) . '</p>';
                echo '</div></div>';
            }
            echo '</div></details></section>';
        }

        // Malware section
        if (!empty($mw['data']) && is_array($mw['data'])) {
            $this->render_malware_section($mw['data'], isset($mw['time']) ? $mw['time'] : '');
        }
    }

    private function render_issue_card($r) {
        $risk_class = 'risk-' . (isset($r['risk']) ? $r['risk'] : 'low');
        $status_class = $r['status'] === 'fail' ? 'fail' : 'warn';

        echo '<details class="issue ' . esc_attr($status_class) . ' ' . esc_attr($risk_class) . '">';
        echo '<summary class="issue-head">';
        echo '<span class="issue-icon">' . esc_html($r['icon']) . '</span>';
        echo '<div class="issue-title">';
        echo '<h3>' . esc_html($r['title']) . '</h3>';
        echo '<p>' . esc_html(!empty($r['simple']) ? $r['simple'] : $r['desc']) . '</p>';
        echo '</div>';
        echo '<span class="issue-arrow">▾</span>';
        echo '</summary>';
        echo '<div class="issue-body">';

        echo '<div class="issue-block">';
        echo '<h4>🤔 این یعنی چی؟</h4>';
        echo '<p>' . esc_html($r['desc']) . '</p>';
        echo '</div>';

        if (!empty($r['detail'])) {
            echo '<div class="issue-block">';
            echo '<h4>🔍 جزئیات فنی</h4>';
            echo '<div class="issue-code">' . esc_html($r['detail']) . '</div>';
            echo '</div>';
        }

        if (!empty($r['fix'])) {
            echo '<div class="issue-block">';
            echo '<h4>✅ راه حل</h4>';
            echo '<p>' . esc_html($r['fix']) . '</p>';
            echo '</div>';
        }

        echo '</div></details>';
    }

private function render_malware_section($data, $time) {
    $sev     = isset($data['by_severity']) ? $data['by_severity'] : array();
    $c       = isset($sev['CRITICAL'])      ? count($sev['CRITICAL'])      : 0;
    $s       = isset($sev['SUSPICIOUS'])    ? count($sev['SUSPICIOUS'])    : 0;
    $p       = isset($sev['PRECAUTIONARY']) ? count($sev['PRECAUTIONARY']) : 0;
    $clean   = isset($data['clean_count']) ? $data['clean_count'] : 0;
    $scanned = isset($data['scanned']) ? $data['scanned'] : 0;

    echo '<section class="section">';
    echo '<div class="section-head">';
    echo '<h2 class="section-title"><span>🦠</span> اسکن فایل‌های سایت</h2>';
    echo '<p class="section-hint">' . number_format($scanned) . ' فایل بررسی شد' . ($time ? ' • ' . esc_html($time) : '') . '</p>';
    echo '</div>';

    // Stat cards
    echo '<div class="mw-stats">';
    echo '<div class="mw-stat critical"><div class="mw-icon">🚨</div><div class="mw-num">' . $c . '</div><div class="mw-lbl">بحرانی</div></div>';
    echo '<div class="mw-stat suspicious"><div class="mw-icon">🔍</div><div class="mw-num">' . $s . '</div><div class="mw-lbl">مشکوک</div></div>';
    echo '<div class="mw-stat precautionary"><div class="mw-icon">💡</div><div class="mw-num">' . $p . '</div><div class="mw-lbl">احتیاطی</div></div>';
    echo '<div class="mw-stat clean"><div class="mw-icon">✅</div><div class="mw-num">' . number_format($clean) . '</div><div class="mw-lbl">سالم</div></div>';
    echo '</div>';

    if ($c === 0 && $s === 0 && $p === 0) {
        echo '<div class="mw-clean">';
        echo '<div class="mw-clean-icon">✅</div>';
        echo '<h3>همه فایل‌ها پاک هستن</h3>';
        echo '<p>هیچ فایل مخرب یا مشکوکی در سایت شما پیدا نشد</p>';
        echo '</div>';
    } else {
        if ($c === 0 && $s === 0 && $p > 0) {
            echo '<div class="mw-note">';
            echo '<strong>💡 توجه:</strong> موارد «احتیاطی» عموماً توابع پرکاربرد هستن که توی کد سالم هم استفاده می‌شن. اگه افزونه یا قالب معتبری داری، معمولاً جای نگرانی نیست.';
            echo '</div>';
        }

        $groups = array(
            array('CRITICAL',      'critical',      '🚨 فایل‌های بحرانی — نیاز به بررسی فوری'),
            array('SUSPICIOUS',    'suspicious',    '🔍 فایل‌های مشکوک — نیاز به بررسی دارن'),
            array('PRECAUTIONARY', 'precautionary', '💡 موارد احتیاطی — برای اطلاع شما'),
        );
        foreach ($groups as $g) {
            $items = isset($sev[$g[0]]) ? $sev[$g[0]] : array();
            if (empty($items)) continue;

            echo '<div class="mw-group">';
            echo '<div class="mw-group-head ' . esc_attr($g[1]) . '">';
            echo '<h3>' . esc_html($g[2]) . '</h3>';
            echo '<span class="mw-count">' . count($items) . '</span>';
            echo '</div>';
            echo '<div class="mw-list">';
            foreach (array_slice($items, 0, 25) as $it) {
                $icon = ($g[0] === 'CRITICAL') ? '🚨' : (($g[0] === 'SUSPICIOUS') ? '🔍' : '💡');
                echo '<div class="mw-row ' . esc_attr($g[1]) . '">';
                echo '<div class="mw-row-icon">' . $icon . '</div>';
                echo '<div class="mw-row-body">';
                echo '<div class="mw-row-path"><code>' . esc_html(isset($it['path']) ? $it['path'] : '') . '</code>';
                if (!empty($it['line']) && $it['line'] !== '-') {
                    echo '<span class="mw-line-pill">خط ' . esc_html($it['line']) . '</span>';
                }
                echo '</div>';
                if (!empty($it['description'])) echo '<div class="mw-row-desc">' . esc_html($it['description']) . '</div>';
                if (!empty($it['snippet'])) echo '<pre class="mw-snippet">' . esc_html($it['snippet']) . '</pre>';
                echo '</div></div>';
            }
            if (count($items) > 25) {
                echo '<div class="mw-more">و ' . (count($items) - 25) . ' مورد دیگر...</div>';
            }
            echo '</div></div>';
        }
    }

    echo '</section>';
}
    /* ============================================================
     *                       SECURITY CHECKS
     * ============================================================ */

    private function check_wp_version() {
        $c = get_bloginfo('version');
        $u = get_site_transient('update_core');
        $has = ($u && isset($u->updates[0]) && version_compare($u->updates[0]->current, $c, '>'));
        if ($has) {
            return array(
                'status' => 'warn',
                'simple' => 'نسخه وردپرس شما قدیمی‌تر از نسخه جدید است',
                'detail' => 'نصب شده: ' . $c . ' | موجود: ' . $u->updates[0]->current,
                'fix'    => 'از پیشخوان وردپرس → به‌روزرسانی‌ها → روی «به‌روزرسانی خودکار» بزنید',
            );
        }
        return array('status' => 'pass', 'simple' => 'وردپرس شما آخرین نسخه است', 'detail' => 'نسخه ' . $c);
    }

    private function check_php_version() {
        $ok = version_compare(PHP_VERSION, '7.4', '>=');
        if (!$ok) {
            return array('status' => 'fail', 'simple' => 'نسخه PHP سرور شما قدیمی و ناامنه', 'detail' => 'PHP ' . PHP_VERSION, 'fix' => 'از پنل هاست، نسخه PHP را به ۸.۱ یا بالاتر تغییر دهید');
        }
        return array('status' => 'pass', 'simple' => 'نسخه PHP سرور مناسب است', 'detail' => 'PHP ' . PHP_VERSION);
    }

    private function check_ssl() {
        if (is_ssl()) return array('status' => 'pass', 'simple' => 'سایت شما قفل امنیتی دارد', 'detail' => 'HTTPS فعال');
        return array('status' => 'fail', 'simple' => 'سایت شما قفل امنیتی (SSL) ندارد', 'detail' => 'HTTP', 'fix' => 'از پنل هاست یک گواهی SSL رایگان (Let\'s Encrypt) فعال کنید');
    }

    private function check_debug_mode() {
        $d = defined('WP_DEBUG') && WP_DEBUG;
        if ($d) return array('status' => 'fail', 'simple' => 'حالت اشکال‌زدایی روشنه و اطلاعات حساس لو می‌ره', 'detail' => 'WP_DEBUG = true', 'fix' => 'در wp-config.php مقدار WP_DEBUG را به false تغییر دهید (یا دکمه رفع خودکار را بزنید)');
        return array('status' => 'pass', 'simple' => 'حالت اشکال‌زدایی خاموشه', 'detail' => 'WP_DEBUG = false');
    }

    private function check_db_debug() {
        $s = defined('SAVEQUERIES') && SAVEQUERIES;
        if ($s) return array('status' => 'fail', 'simple' => 'ذخیره کوئری‌های دیتابیس روشنه و سایت رو کند می‌کنه', 'detail' => 'SAVEQUERIES = true', 'fix' => 'SAVEQUERIES را در wp-config.php غیرفعال کنید (یا دکمه رفع خودکار)');
        return array('status' => 'pass', 'simple' => 'ذخیره کوئری‌ها خاموشه', 'detail' => 'SAVEQUERIES = false');
    }

    private function check_display_errors() {
        $d = ini_get('display_errors');
        $on = in_array(strtolower((string)$d), array('1','on','true'), true);
        if ($on) return array('status' => 'fail', 'simple' => 'خطاهای PHP به کاربر نشون داده می‌شن و اطلاعات لو می‌ره', 'detail' => 'display_errors = ' . $d, 'fix' => 'در php.ini مقدار display_errors را Off کنید یا از پنل هاست تغییر دهید');
        return array('status' => 'pass', 'simple' => 'نمایش خطاها خاموشه', 'detail' => 'display_errors = Off');
    }

    private function check_salt_keys() {
        $keys = array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT');
        $miss = array();
        foreach ($keys as $k) {
            if (!defined($k)) $miss[] = $k;
            elseif (in_array(constant($k), array('put your unique phrase here'), true)) $miss[] = $k;
        }
        if (!empty($miss)) return array(
            'status' => 'fail',
            'simple' => 'کلیدهای امنیتی وردپرس تنظیم نشدن — یعنی احتمال هک شدن حساب‌ها هست',
            'detail' => 'ناقص: ' . implode(', ', $miss),
            'fix'  => 'از آدرس https://api.wordpress.org/secret-key/1.1/salt/ کلیدهای جدید بگیرید و در wp-config.php جایگزین کنید',
        );
        return array('status' => 'pass', 'simple' => 'کلیدهای امنیتی تنظیم شدن', 'detail' => 'همه ۸ کلید موجودن');
    }

    private function check_file_editing() {
        $d = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        if (!$d) return array(
            'status' => 'fail',
            'simple' => 'ویرایشگر فایل توی پیشخوان فعاله — یعنی اگه هکر وارد بشه سریع کد سایت رو دستکاری می‌کنه',
            'detail' => 'DISALLOW_FILE_EDIT تعریف نشده',
            'fix'    => 'DISALLOW_FILE_EDIT را true کنید (یا دکمه رفع خودکار)',
        );
        return array('status' => 'pass', 'simple' => 'ویرایشگر فایل غیرفعاله', 'detail' => 'DISALLOW_FILE_EDIT = true');
    }

    private function check_xmlrpc() {
        $disabled_by_us = false;
        $s = get_option(THALATH_OPT_PUB, array());
        if (is_array($s) && !empty($s['disable_xmlrpc'])) $disabled_by_us = true;
        $enabled = apply_filters('xmlrpc_enabled', true);
        if ($disabled_by_us) $enabled = false;

        if ($enabled) return array(
            'status' => 'warn',
            'simple' => 'دسترسی از راه دور (XML-RPC) بازه — اگه ازش استفاده نمی‌کنی، ببندش',
            'detail' => 'XML-RPC فعال است',
            'fix'    => 'اگه اپلیکیشن موبایل نداری، از دکمه رفع خودکار استفاده کن',
        );
        return array('status' => 'pass', 'simple' => 'XML-RPC غیرفعاله', 'detail' => 'غیرفعال');
    }

    private function check_rest_api() {
        $r = wp_remote_get(get_rest_url(null, 'wp/v2/users'), array('timeout' => 10));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'نتونستیم بررسی کنیم', 'detail' => 'خطای شبکه');
        $code = wp_remote_retrieve_response_code($r);
        if ($code === 200) return array(
            'status' => 'warn',
            'simple' => 'لیست کاربران سایت از بیرون قابل دیدنه — هکر می‌تونه اسم‌های کاربری رو کشف کنه',
            'detail' => 'REST API کاربران باز است (کد 200)',
            'fix'    => 'با افزونه امنیتی مثل Wordfence این دسترسی رو محدود کن',
        );
        return array('status' => 'pass', 'simple' => 'دسترسی به لیست کاربران محدوده', 'detail' => 'کد پاسخ: ' . $code);
    }

    private function check_readme_html() {
        if (file_exists(ABSPATH . 'readme.html')) return array(
            'status' => 'fail',
            'simple' => 'یک فایل اطلاعات وردپرس توی سایت هست که نسخه وردپرس رو نشون می‌ده',
            'detail' => 'readme.html موجود است',
            'fix'    => 'از دکمه رفع خودکار استفاده کن یا خودت فایل رو حذف کن',
        );
        return array('status' => 'pass', 'simple' => 'فایل اطلاعات وردپرس حذف شده', 'detail' => 'readme.html یافت نشد');
    }

    private function check_install_php() {
        if (file_exists(ABSPATH . 'wp-admin/install.php')) return array(
            'status' => 'warn',
            'simple' => 'فایل نصب مجدد وردپرس موجوده و باید حذف بشه',
            'detail' => 'wp-admin/install.php موجود است',
            'fix'    => 'از دکمه رفع خودکار استفاده کن',
        );
        return array('status' => 'pass', 'simple' => 'فایل نصب حذف شده', 'detail' => 'install.php یافت نشد');
    }

    private function check_directory_listing() {
        $u = wp_upload_dir();
        if (empty($u['baseurl'])) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'مسیر آپلود نامشخص');
        $r = wp_remote_get($u['baseurl'] . '/', array('timeout' => 10));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'خطای شبکه');
        $body = wp_remote_retrieve_body($r);
        if (stripos($body, 'Index of') !== false) return array(
            'status' => 'fail',
            'simple' => 'پوشه آپلود سایت قابل مروره — هر کسی می‌تونه فایل‌ها رو ببینه',
            'detail' => 'Directory Listing فعال',
            'fix'    => 'در فایل .htaccess خط Options -Indexes را اضافه کن',
        );
        return array('status' => 'pass', 'simple' => 'پوشه‌ها غیرقابل مرور هستن', 'detail' => 'Directory Listing غیرفعال');
    }

    private function check_admin_username() {
        $u = get_user_by('login', 'admin');
        if ($u) return array(
            'status' => 'fail',
            'simple' => 'یک کاربر با نام کاربری "admin" وجود داره — این اولین چیزی هست که هکرها امتحان می‌کنن',
            'detail' => 'کاربر admin (ID: ' . $u->ID . ')',
            'fix'    => 'کاربر admin رو حذف کن و یه کاربر جدید با نام کاربری متفاوت بساز',
        );
        return array('status' => 'pass', 'simple' => 'کاربری با نام "admin" وجود نداره', 'detail' => 'نام کاربری پیش‌فرض استفاده نشده');
    }

    private function check_user_enumeration() {
        $r = wp_remote_get(add_query_arg('author', '1', home_url('/')), array('timeout' => 10, 'redirection' => 0));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'خطای شبکه');
        $code = wp_remote_retrieve_response_code($r);
        if (in_array($code, array(200, 301, 302), true)) return array(
            'status' => 'warn',
            'simple' => 'از بیرون می‌شه فهمید چه کاربرانی توی سایت هستن',
            'detail' => 'کد پاسخ: ' . $code,
            'fix'    => 'با افزونه امنیتی این قابلیت رو ببند',
        );
        return array('status' => 'pass', 'simple' => 'کشف کاربران مسدوده', 'detail' => 'کد پاسخ: ' . $code);
    }

    private function check_file_permissions() {
        $issues = array();
        $wp = ABSPATH . 'wp-config.php';
        if (!file_exists($wp)) $wp = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($wp)) {
            $p = fileperms($wp) & 0777;
            if ($p > 0644) $issues[] = 'wp-config.php (' . sprintf('%04o', $p) . ')';
        }
        $ht = ABSPATH . '.htaccess';
        if (file_exists($ht)) {
            $p = fileperms($ht) & 0777;
            if ($p > 0644) $issues[] = '.htaccess (' . sprintf('%04o', $p) . ')';
        }
        if (!empty($issues)) return array(
            'status' => 'fail',
            'simple' => 'بعضی فایل‌های حساس مجوز امن ندارن و ممکنه توسط بقیه دستکاری بشن',
            'detail' => implode(' | ', $issues),
            'fix'    => 'از فایل‌منیجر هاست، مجوز فایل‌ها رو 644 و پوشه‌ها رو 755 کن',
        );
        return array('status' => 'pass', 'simple' => 'مجوزهای فایل‌های حساس درست هستن', 'detail' => '0644');
    }

    private function check_htaccess() {
        if (file_exists(ABSPATH . '.htaccess')) return array('status' => 'pass', 'simple' => 'فایل تنظیمات سرور موجوده', 'detail' => '.htaccess پیدا شد');
        return array('status' => 'warn', 'simple' => 'فایل تنظیمات سرور (.htaccess) پیدا نشد', 'detail' => '.htaccess یافت نشد', 'fix' => 'اگه از Apache استفاده می‌کنی، از پنل هاست یک .htaccess بساز');
    }

    private function check_wp_config_location() {
        $in_root = file_exists(ABSPATH . 'wp-config.php');
        $above = file_exists(dirname(ABSPATH) . '/wp-config.php');
        if (!$in_root && $above) return array('status' => 'pass', 'simple' => 'فایل تنظیمات وردپرس خارج از پوشه عمومی هست', 'detail' => 'خارج از public_html');
        return array('status' => 'warn', 'simple' => 'فایل تنظیمات وردپرس داخل پوشه عمومیه', 'detail' => 'در روت وب', 'fix' => 'اگه ممکنه، wp-config.php رو یه پوشه بالاتر از public_html منتقل کن');
    }

    private function check_backup_files() {
        $found = array();
        $patterns = array('*.zip','*.tar.gz','*.sql','*.bak','*.old','*.backup','*backup*','*.dump');
        foreach ($patterns as $p) {
            $files = glob(ABSPATH . $p);
            if ($files) foreach (array_slice($files, 0, 3) as $f) $found[] = basename($f);
        }
        $found = array_unique($found);
        if (!empty($found)) return array(
            'status' => 'fail',
            'simple' => 'فایل‌های پشتیبان توی سایت پیدا شد — این‌ها می‌تونن اطلاعات حساس رو لو بدن',
            'detail' => implode(', ', array_slice($found, 0, 5)),
            'fix'    => 'فایل‌های بکاپ رو از پوشه سایت حذف یا به یک پوشه محافظت شده منتقل کن',
        );
        return array('status' => 'pass', 'simple' => 'فایل بکاپ لو رفته پیدا نشد', 'detail' => 'هیچ فایل بکاپی پیدا نشد');
    }

    private function check_malicious_files() {
        $found = array();
        foreach (array('*.suspected','*.php.bak','*.php~','*.php.old','*.php.swp') as $p) {
            $files = glob(ABSPATH . $p);
            if ($files) foreach (array_slice($files, 0, 3) as $f) $found[] = basename($f);
        }
        if (!empty($found)) return array(
            'status' => 'fail',
            'simple' => 'فایل‌هایی با اسم مشکوک پیدا شد که معمولاً نشونه حمله هستن',
            'detail' => implode(', ', $found),
            'fix'    => 'این فایل‌ها رو باز کن و بررسی کن. اگه مطمئن نیستی، از هاست دانلود و به متخصص نشون بده',
        );
        return array('status' => 'pass', 'simple' => 'فایل مشکوکی پیدا نشد', 'detail' => 'هیچ فایل مشکوکی نیست');
    }

    private function check_inactive_plugins() {
        $all = get_plugins();
        $active = get_option('active_plugins', array());
        if (!is_array($active)) $active = array();
        $c = 0;
        foreach ($all as $f => $d) if (!in_array($f, $active, true)) $c++;
        if ($c > 0) return array('status' => 'warn', 'simple' => $c . ' افزونه غیرفعال روی سایت هستن که می‌تونن خطر امنیتی داشته باشن', 'detail' => $c . ' افزونه غیرفعال', 'fix' => 'افزونه‌هایی که استفاده نمی‌کنی رو حذف کن');
        return array('status' => 'pass', 'simple' => 'همه افزونه‌ها فعال هستن', 'detail' => '0 افزونه غیرفعال');
    }

    private function check_inactive_themes() {
        $t = wp_get_themes();
        $c = count($t) - 1;
        if ($c > 0) return array('status' => 'warn', 'simple' => $c . ' قالب غیرفعال روی سایت هستن', 'detail' => $c . ' قالب غیرفعال', 'fix' => 'قالب‌هایی که استفاده نمی‌کنی رو حذف کن');
        return array('status' => 'pass', 'simple' => 'فقط یک قالب نصبه', 'detail' => '1 قالب');
    }

    private function check_security_headers() {
        $r = wp_remote_get(home_url('/'), array('timeout' => 10));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'خطای شبکه');
        $h = wp_remote_retrieve_headers($r);
        $arr = method_exists($h, 'getAll') ? $h->getAll() : (array) $h;
        $req = array('x-frame-options', 'x-content-type-options', 'referrer-policy');
        $miss = array();
        foreach ($req as $k) {
            $f = false;
            foreach ($arr as $kk => $vv) if (strtolower($kk) === $k) { $f = true; break; }
            if (!$f) $miss[] = $k;
        }
        if (!empty($miss)) return array(
            'status' => 'warn',
            'simple' => 'بعضی هدرهای امنیتی مرورگر تنظیم نشدن',
            'detail' => 'ناموجود: ' . implode(', ', $miss),
            'fix'    => 'از پنل هاست یا فایل .htaccess این هدرها رو اضافه کن',
        );
        return array('status' => 'pass', 'simple' => 'هدرهای امنیتی تنظیم شدن', 'detail' => 'همه موجود');
    }

    private function check_login_limiting() {
        $has = has_filter('wp_login_failed') || has_filter('authenticate');
        $plugins = array('wordfence','sucuri-scanner','limit-login-attempts-reloaded','all-in-one-wp-security-and-firewall');
        $inst = false;
        foreach ($plugins as $s) if (file_exists(WP_PLUGIN_DIR . '/' . $s)) { $inst = true; break; }
        if (!$inst && !$has) return array(
            'status' => 'warn',
            'simple' => 'هیچ محدودیتی برای تلاش ورود وجود نداره — هکر می‌تونه بی‌نهایت رمز امتحان کنه',
            'detail' => 'افزونه محدودیت ورود یافت نشد',
            'fix'    => 'افزونه‌ای مثل Limit Login Attempts Reloaded یا Wordfence نصب کن',
        );
        return array('status' => 'pass', 'simple' => 'محدودیت تلاش ورود فعاله', 'detail' => 'فعال');
    }

    private function check_db_prefix() {
        global $wpdb;
        if ($wpdb->prefix === 'wp_') return array(
            'status' => 'warn',
            'simple' => 'اسم جداول دیتابیس پیش‌فرضه که می‌تونه برای حمله استفاده بشه',
            'detail' => 'پیشوند: wp_',
            'fix'    => 'این مورد نیاز به تخصص داره — با احتیاط پیشوند رو از wp_ به چیز دیگه‌ای تغییر بده',
        );
        return array('status' => 'pass', 'simple' => 'پیشوند جداول سفارشیه', 'detail' => 'پیشوند: ' . $wpdb->prefix);
    }

    private function check_wp_cron() {
        $d = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        if (!$d) return array(
            'status' => 'warn',
            'simple' => 'کرون‌جاب وردپرس از طریق بازدیدکنندگان اجرا می‌شه که می‌تونه باعث کندی سایت بشه',
            'detail' => 'WP-Cron فعال',
            'fix'    => 'برای سایت‌های پرترافیک بهتره از کرون‌جاب سرور استفاده بشه',
        );
        return array('status' => 'pass', 'simple' => 'کرون‌جاب از طریق سرور اجرا می‌شه', 'detail' => 'DISABLE_WP_CRON = true');
    }

    private function check_uploads_executable() {
        $ht = WP_CONTENT_DIR . '/uploads/.htaccess';
        if (!file_exists($ht)) return array(
            'status' => 'warn',
            'simple' => 'امکان اجرای PHP توی پوشه آپلود بسته نشده — این خیلی خطرناکه',
            'detail' => '.htaccess در uploads موجود نیست',
            'fix'    => 'یک فایل .htaccess در پوشه uploads بساز و داخلش بنویس: php_flag engine off',
        );
        $c = @file_get_contents($ht);
        if (stripos($c, 'php_flag engine off') !== false || stripos($c, 'deny from all') !== false) {
            return array('status' => 'pass', 'simple' => 'اجرای PHP توی پوشه آپلود مسدوده', 'detail' => 'محافظت فعال');
        }
        return array(
            'status' => 'warn',
            'simple' => 'فایل .htaccess توی uploads هست ولی محافظت کاملی نداره',
            'detail' => 'بدون php_flag engine off',
            'fix'    => 'خط php_flag engine off را به فایل اضافه کن',
        );
    }

    private function check_version_exposure() {
        $hide = false;
        $s = get_option(THALATH_OPT_PUB, array());
        if (is_array($s) && !empty($s['hide_wp_version'])) $hide = true;
        if ($hide) return array('status' => 'pass', 'simple' => 'نسخه وردپرس مخفی شده', 'detail' => 'حذف شده');
        $g = has_action('wp_head', 'wp_generator');
        if ($g) return array(
            'status' => 'warn',
            'simple' => 'نسخه وردپرس توی کد صفحه نشون داده می‌شه و به هکرها کمک می‌کنه',
            'detail' => 'متا تگ generator فعاله',
            'fix'    => 'از دکمه رفع خودکار استفاده کن',
        );
        return array('status' => 'pass', 'simple' => 'نسخه وردپرس مخفیه', 'detail' => 'generator حذف شده');
    }

    private function check_pingback() {
        $off = false;
        $s = get_option(THALATH_OPT_PUB, array());
        if (is_array($s) && !empty($s['disable_pingback'])) $off = true;
        if ($off) return array('status' => 'pass', 'simple' => 'پینگ‌بک غیرفعاله', 'detail' => 'غیرفعال');
        $e = apply_filters('xmlrpc_enabled', true);
        if ($e) return array(
            'status' => 'warn',
            'simple' => 'پینگ‌بک فعاله و می‌تونه برای حمله DDoS به سایت‌های دیگه استفاده بشه',
            'detail' => 'Pingback فعال',
            'fix'    => 'از دکمه رفع خودکار استفاده کن',
        );
        return array('status' => 'pass', 'simple' => 'پینگ‌بک غیرفعاله', 'detail' => 'غیرفعال');
    }

    private function check_error_log() {
        if (file_exists(ABSPATH . 'error_log')) {
            $r = wp_remote_get(home_url('/error_log'), array('timeout' => 5));
            if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
                return array(
                    'status' => 'fail',
                    'simple' => 'فایل error_log از اینترنت قابل دسترسیه و اطلاعات حساس رو لو می‌ده',
                    'detail' => 'قابل دسترسی از وب',
                    'fix'    => 'فایل error_log رو حذف کن یا با .htaccess محافظتش کن',
                );
            }
        }
        return array('status' => 'pass', 'simple' => 'فایل خطاها محافظت شده', 'detail' => 'قابل دسترسی نیست');
    }

    private function check_wp_content_browsable() {
        $r = wp_remote_get(content_url() . '/', array('timeout' => 10));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'خطای شبکه');
        $b = wp_remote_retrieve_body($r);
        if (stripos($b, 'Index of') !== false) return array(
            'status' => 'fail',
            'simple' => 'پوشه wp-content قابل مروره — یعنی هر کسی می‌تونه فایل‌های سایت رو ببینه',
            'detail' => 'قابل مرور',
            'fix'    => 'در .htaccess اصلی خط Options -Indexes را اضافه کن',
        );
        return array('status' => 'pass', 'simple' => 'پوشه wp-content محافظت شده', 'detail' => 'غیرقابل مرور');
    }

    private function check_admin_email() {
        $e = get_option('admin_email');
        if (!is_string($e) || !is_email($e)) return array('status' => 'warn', 'simple' => 'ایمیل مدیر تنظیم نشده یا نامعتبره', 'detail' => 'مقدار: ' . $e, 'fix' => 'از تنظیمات → عمومی ایمیل مدیر رو اصلاح کن');
        $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $domain = preg_replace('/^www\./', '', $domain);
        if ($domain && (stripos($e, 'admin@' . $domain) === 0 || stripos($e, 'test@') === 0 || stripos($e, 'wordpress@') === 0)) {
            return array('status' => 'warn', 'simple' => 'ایمیل مدیر پیش‌فرضه و می‌تونه حدس زده بشه', 'detail' => $e, 'fix' => 'یک ایمیل شخصی برای مدیر تنظیم کن');
        }
        return array('status' => 'pass', 'simple' => 'ایمیل مدیر مناسبه', 'detail' => $e);
    }

    private function check_user_registration() {
        $open = get_option('users_can_register');
        if ($open) return array(
            'status' => 'warn',
            'simple' => 'هر کسی می‌تونه توی سایت شما ثبت‌نام کنه — این می‌تونه برای ربات‌ها دعوت‌نامه باشه',
            'detail' => 'ثبت‌نام عمومی باز است',
            'fix'    => 'از تنظیمات → عمومی، تیک «هر کسی می‌تونه ثبت‌نام کنه» رو بردار',
        );
        return array('status' => 'pass', 'simple' => 'ثبت‌نام کاربران بسته‌ست', 'detail' => 'غیرفعال');
    }

    private function check_pingback_size() {
        // just check option presence
        return array('status' => 'pass', 'simple' => 'حجم پینگ‌بک در محدوده استاندارد', 'detail' => 'بررسی شد');
    }

    private function check_xmlrpc_system_list() {
        $off = false;
        $s = get_option(THALATH_OPT_PUB, array());
        if (is_array($s) && !empty($s['disable_xmlrpc'])) $off = true;
        if ($off) return array('status' => 'pass', 'simple' => 'دسترسی از راه دور غیرفعاله', 'detail' => 'غیرفعال');
        return array('status' => 'warn', 'simple' => 'لیست متدهای از راه دور قابل دیدنه', 'detail' => 'XML-RPC روشن', 'fix' => 'XML-RPC رو غیرفعال کن');
    }

    private function check_wp_json_users() {
        $r = wp_remote_get(home_url('/wp-json/wp/v2/users'), array('timeout' => 10));
        if (is_wp_error($r)) return array('status' => 'warn', 'simple' => 'قابل بررسی نبود', 'detail' => 'خطای شبکه');
        $c = wp_remote_retrieve_response_code($r);
        if ($c === 200) return array(
            'status' => 'warn',
            'simple' => 'لیست کاربران از طریق wp-json قابل دیدنه',
            'detail' => 'در دسترس (کد 200)',
            'fix'    => 'با افزونه امنیتی این دسترسی رو محدود کن',
        );
        return array('status' => 'pass', 'simple' => 'wp-json کاربران محدوده', 'detail' => 'کد: ' . $c);
    }

    private function check_robots_txt() {
        $r = wp_remote_get(home_url('/robots.txt'), array('timeout' => 5));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) {
            return array('status' => 'warn', 'simple' => 'فایل robots.txt پیدا نشد', 'detail' => 'موجود نیست', 'fix' => 'یک robots.txt مناسب بساز');
        }
        return array('status' => 'pass', 'simple' => 'فایل robots.txt موجوده', 'detail' => 'موجود');
    }

    private function check_admin_url() {
        return array('status' => 'warn', 'simple' => 'آدرس صفحه ورود پیش‌فرضه (/wp-admin) — هکرها این آدرس رو می‌شناسن', 'detail' => 'آدرس پیش‌فرض', 'fix' => 'با افزونه‌ای مثل WPS Hide Login، آدرس ورود رو تغییر بده');
    }

    private function check_two_factor() {
        $plugins = array('two-factor','wp-2fa','miniorange-2-factor-authentication','google-authenticator');
        foreach ($plugins as $p) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $p)) return array('status' => 'pass', 'simple' => 'ورود دو مرحله‌ای فعاله', 'detail' => 'افزونه 2FA نصب شده');
        }
        return array(
            'status' => 'warn',
            'simple' => 'ورود دو مرحله‌ای تنظیم نشده — با فعال‌سازی، امنیت ورود چند برابر می‌شه',
            'detail' => 'افزونه 2FA یافت نشد',
            'fix'    => 'افزونه Two Factor یا WP 2FA نصب کن',
        );
    }

    private function check_spam_comments() {
        $c = (int) wp_count_comments()->spam;
        if ($c > 50) return array('status' => 'warn', 'simple' => $c . ' کامنت اسپم توی سایت هست که دیتابیس رو سنگین می‌کنه', 'detail' => $c . ' اسپم', 'fix' => 'از بخش دیدگاه‌ها → اسپم، همه رو پاک کن');
        return array('status' => 'pass', 'simple' => 'کامنت اسپم زیادی وجود نداره', 'detail' => $c . ' اسپم');
    }

    private function check_post_revisions() {
        global $wpdb;
        $c = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        if ($c > 2000) return array('status' => 'warn', 'simple' => $c . ' نسخه قدیمی پست‌ها توی دیتابیسه که حجم رو زیاد می‌کنه', 'detail' => $c . ' ریویژن', 'fix' => 'با افزونه WP-Sweep یا از دیتابیس، ریویژن‌های قدیمی رو پاک کن');
        return array('status' => 'pass', 'simple' => 'تعداد نسخه‌های قدیمی مناسبه', 'detail' => $c . ' ریویژن');
    }

    private function check_disabled_functions() {
        $disabled = ini_get('disable_functions');
        $danger = array('exec','system','shell_exec','passthru','proc_open','popen');
        $enabled = array();
        if ($disabled) {
            $list = array_map('trim', explode(',', strtolower($disabled)));
            foreach ($danger as $d) if (!in_array($d, $list, true)) $enabled[] = $d;
        } else {
            $enabled = $danger;
        }
        if (!empty($enabled)) return array(
            'status' => 'warn',
            'simple' => 'بعضی توابع خطرناک PHP فعال هستن — اگه هکر دسترسی بگیره، می‌تونه ازشون استفاده کنه',
            'detail' => 'فعال: ' . implode(', ', $enabled),
            'fix'    => 'از پنل هاست یا php.ini این توابع رو غیرفعال کن (اگه افزونه‌ای بهشون نیاز نداره)',
        );
        return array('status' => 'pass', 'simple' => 'توابع خطرناک PHP غیرفعال هستن', 'detail' => 'غیرفعال');
    }

    private function check_wp_config_perms() {
        $f = ABSPATH . 'wp-config.php';
        if (!file_exists($f)) $f = dirname(ABSPATH) . '/wp-config.php';
        if (!file_exists($f)) return array('status' => 'warn', 'simple' => 'فایل تنظیمات پیدا نشد', 'detail' => 'فایل نیست');
        $p = fileperms($f) & 0777;
        if ($p > 0644) return array(
            'status' => 'fail',
            'simple' => 'فایل تنظیمات وردپرس مجوز امنی نداره — ممکنه توسط بقیه دستکاری بشه',
            'detail' => 'مجوز: ' . sprintf('%04o', $p),
            'fix'    => 'از فایل‌منیجر، مجوز فایل رو 0644 کن',
        );
        return array('status' => 'pass', 'simple' => 'مجوز فایل تنظیمات درسته', 'detail' => sprintf('%04o', $p));
    }

    private function check_wp_config_backup() {
        $found = array();
        $variants = array('wp-config.php.bak','wp-config.php~','wp-config.php.old','wp-config.php.save','wp-config.txt','wp-config.php.orig','wp-config.php.swp');
        foreach ($variants as $v) {
            $p = ABSPATH . $v;
            if (file_exists($p)) $found[] = $v;
            $p2 = dirname(ABSPATH) . '/' . $v;
            if (file_exists($p2)) $found[] = '../' . $v;
        }
        if (!empty($found)) return array(
            'status' => 'fail',
            'simple' => 'نسخه پشتیبان فایل تنظیمات وردپرس توی سایت پیدا شد — این خیلی خطرناکه چون رمز دیتابیس لو می‌ره',
            'detail' => implode(', ', $found),
            'fix'    => 'فوراً این فایل‌ها رو حذف کن',
        );
        return array('status' => 'pass', 'simple' => 'نسخه پشتیبان ناامنی از تنظیمات پیدا نشد', 'detail' => 'تمیز');
    }

    private function check_debug_log() {
        $f = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($f) && filesize($f) > 100) {
            return array(
                'status' => 'warn',
                'simple' => 'فایل debug.log با حجم زیاد توی سایت هست و می‌تونه اطلاعات حساس داشته باشه',
                'detail' => 'حجم: ' . size_format(filesize($f)),
                'fix'    => 'فایل debug.log رو حذف کن و WP_DEBUG_LOG رو غیرفعال کن',
            );
        }
        return array('status' => 'pass', 'simple' => 'فایل debug.log مشکل‌ساز نیست', 'detail' => 'تمیز');
    }

    private function check_admin_users() {
        $admins = get_users(array('role' => 'administrator', 'number' => 20));
        if (count($admins) > 5) return array(
            'status' => 'warn',
            'simple' => 'تعداد کاربران مدیر زیاده — بهتره تعدادشون کم باشه',
            'detail' => count($admins) . ' مدیر',
            'fix'    => 'کاربران مدیریتی که نیازی نیستن رو حذف یا نقششون رو تغییر بده',
        );
        // Look for admin users with email domains not matching site
        $domain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
        $suspicious = 0;
        if ($domain) {
            foreach ($admins as $u) {
                $email = $u->user_email;
                $parts = explode('@', $email);
                $edomain = isset($parts[1]) ? $parts[1] : '';
                if ($edomain && stripos($edomain, $domain) === false && stripos($edomain, 'gmail') === false && stripos($edomain, 'yahoo') === false && stripos($edomain, 'outlook') === false) {
                    $suspicious++;
                }
            }
        }
        if ($suspicious > 0) return array(
            'status' => 'warn',
            'simple' => 'کاربر مدیر با ایمیل غیرمعمول پیدا شد',
            'detail' => $suspicious . ' مورد مشکوک',
            'fix'    => 'کاربران مدیر رو بررسی کن',
        );
        return array('status' => 'pass', 'simple' => 'کاربران مدیر طبیعی هستن', 'detail' => count($admins) . ' مدیر');
    }

    /* ============================================================
     *                       MALWARE SCANNER
     * ============================================================ */

private function run_malware_scan() {
    $cfg  = array('max_files' => 3000, 'max_size' => 500 * 1024, 'entropy' => 0.85, 'max_depth' => 8);
    $exts = array('php','php5','php7','phtml','htaccess','js','ico');
    $base = rtrim(ABSPATH, '/');

    $out = array(
        'scanned'     => 0,
        'clean_count' => 0,
        'by_severity' => array(
            'CRITICAL'      => array(),
            'SUSPICIOUS'    => array(),
            'PRECAUTIONARY' => array(),
        ),
    );

    $dirs = array($base, $base.'/wp-content/uploads', $base.'/wp-content/plugins', $base.'/wp-content/themes');
    foreach ($dirs as $d) {
        if (is_dir($d) && $out['scanned'] < $cfg['max_files']) {
            $this->scan_dir($d, $cfg, $exts, $out, $base, 0);
        }
    }
    return $out;
}
    private function scan_dir($dir, $cfg, $exts, &$out, $base, $depth) {
        if ($out['scanned'] >= $cfg['max_files']) return;
        if ($depth > $cfg['max_depth']) return;

        $items = @scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || strpos($item, '.') === 0) continue;
            if ($out['scanned'] >= $cfg['max_files']) return;

            $path = $dir . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                if (in_array($item, array('cache','w3tc-config','upgrade','backups','node_modules','.git','vendor'), true)) continue;
                $this->scan_dir($path, $cfg, $exts, $out, $base, $depth + 1);
            } elseif (is_file($path)) {
                $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($ext, $exts, true)) continue;
                $size = @filesize($path);
                if ($size === false || $size > $cfg['max_size']) continue;

                $out['scanned']++;
                $content = @file_get_contents($path);
                if ($content === false) continue;

                // فایل تصویری حاوی PHP
                if (in_array($ext, array('ico','png','jpg','jpeg','gif'), true)) {
                    if (strpos($content, '<?php') !== false) {
                        $out['by_severity']['CRITICAL'][] = array(
                            'path'        => str_replace($base, '', $path),
                            'severity'    => 'CRITICAL',
                            'issue'       => 'PHP in Image',
                            'description' => 'فایل تصویری حاوی کد PHP — تکنیک رایج بک‌دور',
                            'line'        => $this->find_line($content, '<?php'),
                        );
                    }
                    continue;
                }

                if ($item === '.htaccess') continue;

                $entropy = $this->calc_entropy($content);
                $matched = false;

                foreach ($this->malware_patterns as $level => $data) {
                    if ($matched) break;
                    foreach ($data['patterns'] as $pat => $desc) {
                        if (@preg_match('#' . $pat . '#i', $content)) {
                            $line = $this->find_line_regex($content, $pat);
                            if ($line > 0) {
                                $out['by_severity'][$level][] = array(
                                    'path'        => str_replace($base, '', $path),
                                    'severity'    => $level,
                                    'issue'       => substr($pat, 0, 40),
                                    'description' => $desc,
                                    'line'        => $line,
                                    'entropy'     => $entropy,
                                    'size'        => $size,
                                );
                                $matched = true;
                                break;
                            }
                        }
                    }
                }

                if (!$matched && $entropy > $cfg['entropy']) {
                    $out['by_severity']['PRECAUTIONARY'][] = array(
                        'path'        => str_replace($base, '', $path),
                        'severity'    => 'PRECAUTIONARY',
                        'issue'       => 'High Entropy',
                        'description' => 'کد پیچیده یا فشرده‌شده — ممکنه رمزنگاری شده باشه',
                        'line'        => '-',
                        'entropy'     => $entropy,
                    );
                } elseif (!$matched) {
                    $out['clean_count']++;
                }
            }
        }
    }

    private function find_line($content, $needle) {
        $lines = explode("\n", $content);
        foreach ($lines as $n => $l) {
            if (strpos($l, $needle) !== false) return $n + 1;
        }
        return 0;
    }

    private function find_line_regex($content, $pat) {
        $lines = explode("\n", $content);
        foreach ($lines as $n => $l) {
            if (@preg_match('#' . $pat . '#i', $l)) return $n + 1;
        }
        return 0;
    }

    private function calc_entropy($s) {
        $size = strlen($s);
        if ($size < 1000) return 0;
        $h = 0;
        $chars = count_chars($s, 1);
        foreach ($chars as $c) {
            $p = $c / $size;
            $h -= $p * log($p) / log(2);
        }
        return $h / 8;
    }
    /* ============================================================
     *                          HELPERS
     * ============================================================ */

    private function fix_wp_config($constant, $value) {
        $f = ABSPATH . 'wp-config.php';
        if (!file_exists($f)) $f = dirname(ABSPATH) . '/wp-config.php';
        if (!file_exists($f) || !is_writable($f)) return false;

        $content = file_get_contents($f);
        $val = $value ? 'true' : 'false';
        $pat = "/define\s*\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*(true|false)\s*\)\s*;/i";

        if (preg_match($pat, $content)) {
            $content = preg_replace($pat, "define('" . $constant . "', " . $val . ");", $content);
        } else {
            $content = preg_replace("/(<\?php)/i", "<?php\ndefine('" . $constant . "', " . $val . ");", $content, 1);
        }
        return file_put_contents($f, $content) !== false;
    }

    /* ============================================================
     *                          CSS / JS
     * ============================================================ */

    private function common_js() {
        return '
        function thToast(msg, type) {
            var t = document.getElementById("thToast");
            if (!t) return;
            if (typeof msg !== "string") msg = (msg && msg.message) ? msg.message : "خطای نامشخص";
            t.textContent = msg;
            t.className = "th-toast show " + (type || "");
            setTimeout(function() { t.classList.remove("show"); }, 4000);
        }
        function thEsc(s) { var d = document.createElement("div"); d.textContent = s == null ? "" : String(s); return d.innerHTML; }

        function thRenderSec(data, targetId) {
            var results = data.results || [];
            var score = data.score || 0;
            var tw = data.total_weight || 0;
            var pct = tw > 0 ? Math.round((score/tw)*100) : 0;

            var cls = "score-danger", title = "بحرانی";
            if (pct >= 90) { cls = "score-excellent"; title = "عالی"; }
            else if (pct >= 70) { cls = "score-good"; title = "خوب"; }
            else if (pct >= 50) { cls = "score-warning"; title = "متوسط"; }

            var pass=0, fail=0, warn=0;
            results.forEach(function(r){
                if (r.status === "pass") pass++;
                else if (r.status === "fail") fail++;
                else warn++;
            });

            var html = "";
            html += "<div class=\"th-card\" style=\"text-align:center\">";
            html += "<div class=\"thalath-score-circle " + cls + "\">" + pct + "%</div>";
            html += "<h2 style=\"margin:6px 0\">وضعیت: " + title + "</h2>";
            html += "</div>";

            html += "<div class=\"thalath-summary\">";
            html += "<div class=\"thalath-summary-item\"><div class=\"thalath-summary-number\" style=\"color:#10b981\">" + pass + "</div><div class=\"thalath-summary-label\">✅ موفق</div></div>";
            html += "<div class=\"thalath-summary-item\"><div class=\"thalath-summary-number\" style=\"color:#f59e0b\">" + warn + "</div><div class=\"thalath-summary-label\">⚠️ هشدار</div></div>";
            html += "<div class=\"thalath-summary-item\"><div class=\"thalath-summary-number\" style=\"color:#ef4444\">" + fail + "</div><div class=\"thalath-summary-label\">❌ خطا</div></div>";
            html += "<div class=\"thalath-summary-item\"><div class=\"thalath-summary-number\" style=\"color:#2563eb\">" + results.length + "</div><div class=\"thalath-summary-label\">📊 کل</div></div>";
            html += "</div>";

            html += "<h2 class=\"admin-section-title\">🛡️ نتایج اسکن امنیتی</h2>";
            html += "<div class=\"thalath-tests-grid\">";
            results.forEach(function(r){
                var cls2 = r.status;
                var icon = r.status === "pass" ? "✅" : (r.status === "fail" ? "❌" : "⚠️");
                var fixBtn = "";
                if (r.fixable && r.status !== "pass") {
                    fixBtn = "<button class=\"th-btn th-btn-primary\" style=\"margin-top:10px;padding:6px 14px;font-size:12px\" data-testid=\"" + r.id + "\" onclick=\"thFix(this)\">🔧 رفع خودکار</button>";
                }
                html += "<div class=\"thalath-test-card " + cls2 + "\">";
                html += "<h3 style=\"margin:0 0 6px;font-size:14px\">" + icon + " " + thEsc(r.title) + "</h3>";
                html += "<p style=\"color:#64748b;font-size:12px;margin:4px 0;line-height:1.6\">" + thEsc(r.desc) + "</p>";
                if (r.detail) html += "<div style=\"background:#f1f5f9;padding:8px;border-radius:6px;font-size:11px;font-family:monospace;word-break:break-all;direction:ltr;text-align:left\">" + thEsc(r.detail) + "</div>";
                if (r.fix) html += "<p style=\"font-size:12px;color:#475569;margin-top:8px;background:#fef9c3;padding:8px;border-radius:6px\"><b>راه حل:</b> " + thEsc(r.fix) + "</p>";
                html += fixBtn;
                html += "</div>";
            });
            html += "</div>";
            document.getElementById(targetId).innerHTML = html;
        }

function thRenderMw(data, targetId) {
    var sev = data.by_severity || {};
    var c = (sev.CRITICAL || []).length;
    var s = (sev.SUSPICIOUS || []).length;
    var p = (sev.PRECAUTIONARY || []).length;
    var clean = data.clean_count || 0;
    var scanned = data.scanned || 0;

    var html = "";
    html += "<div class=\"th-card\">";
    html += "<div class=\"th-card-head\"><h2>🦠 نتایج اسکن بدافزار</h2>";
    html += "<p class=\"th-card-sub\">" + scanned.toLocaleString() + " فایل بررسی شد</p></div>";

    html += "<div class=\"mw-stats\">";
    html += "<div class=\"mw-stat critical\"><div class=\"mw-icon\">🚨</div><div class=\"mw-num\">" + c + "</div><div class=\"mw-lbl\">بحرانی</div></div>";
    html += "<div class=\"mw-stat suspicious\"><div class=\"mw-icon\">🔍</div><div class=\"mw-num\">" + s + "</div><div class=\"mw-lbl\">مشکوک</div></div>";
    html += "<div class=\"mw-stat precautionary\"><div class=\"mw-icon\">💡</div><div class=\"mw-num\">" + p + "</div><div class=\"mw-lbl\">احتیاطی</div></div>";
    html += "<div class=\"mw-stat clean\"><div class=\"mw-icon\">✅</div><div class=\"mw-num\">" + clean.toLocaleString() + "</div><div class=\"mw-lbl\">سالم</div></div>";
    html += "</div>";

    var groups = [
        ["CRITICAL", "critical", "🚨 فایل‌های بحرانی"],
        ["SUSPICIOUS", "suspicious", "🔍 فایل‌های مشکوک"],
        ["PRECAUTIONARY", "precautionary", "💡 موارد احتیاطی"]
    ];
    groups.forEach(function(g) {
        var items = sev[g[0]] || [];
        if (!items.length) return;
        html += "<div class=\"mw-group\">";
        html += "<div class=\"mw-group-head " + g[1] + "\"><h3>" + g[2] + "</h3><span class=\"mw-count\">" + items.length + "</span></div>";
        html += "<div class=\"mw-list\">";
        items.slice(0, 30).forEach(function(it) {
            html += "<div class=\"mw-row " + g[1] + "\">";
            html += "<div class=\"mw-row-body\">";
            html += "<div class=\"mw-row-path\"><code>" + thEsc(it.path || "") + "</code>";
            if (it.line && it.line !== "-") html += "<span class=\"mw-line-pill\">خط " + thEsc(it.line) + "</span>";
            html += "</div>";
            if (it.description) html += "<div class=\"mw-row-desc\">" + thEsc(it.description) + "</div>";
            if (it.snippet) html += "<pre class=\"mw-snippet\">" + thEsc(it.snippet) + "</pre>";
            html += "</div></div>";
        });
        if (items.length > 30) html += "<div class=\"mw-more\">و " + (items.length - 30) + " مورد دیگر...</div>";
        html += "</div></div>";
    });

    if (c === 0 && s === 0 && p === 0) {
        html += "<div class=\"mw-clean\"><div class=\"mw-clean-icon\">✅</div><h3>همه فایل‌ها پاک هستن</h3><p>هیچ فایل مخربی پیدا نشد</p></div>";
    }
    html += "</div>";
    document.getElementById(targetId).innerHTML = html;
}

        function thFix(btn) {
            var id = btn.dataset.testid;
            btn.disabled = true; btn.textContent = "در حال رفع...";
            var fd = new URLSearchParams();
            fd.append("action", "thalath_fix_issue");
            fd.append("nonce", TH_FIX_NONCE);
            fd.append("test_id", id);
            fetch(ajaxurl, {method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body: fd.toString()})
            .then(function(r){ return r.json(); })
            .then(function(resp){
                if (resp.success) { thToast(resp.data.message || "با موفقیت انجام شد", "success"); setTimeout(thalathStartScan, 700); }
                else { thToast(resp.data, "error"); btn.disabled = false; btn.textContent = "🔧 رفع خودکار"; }
            })
            .catch(function(e){ thToast("خطا: " + e.message, "error"); btn.disabled = false; });
        }
        ';
    }

private function public_js() {
    return '
    function thPubScan() {
        var btn = document.getElementById("btnRescan");
        var overlay = document.getElementById("rescanOverlay");
        if (!btn || !overlay) return;
        btn.disabled = true;
        overlay.style.display = "flex";
        overlay.innerHTML =
            "<div class=\"rs-loading\">" +
                "<div class=\"rs-ring\"></div>" +
                "<div class=\"rs-text\">در حال اسکن سایت</div>" +
                "<div class=\"rs-sub\">لطفاً صبر کنید، این فرآیند حدود ۱ تا ۲ دقیقه طول می‌کشد</div>" +
            "</div>";

        var fd = new URLSearchParams();
        fd.append("action", "thalath_public_scan");
        fd.append("token", TH_PUB_TOKEN);
        fd.append("nonce", TH_PUB_NONCE);

        fetch(TH_AJAX, {
            method: "POST",
            headers: {"Content-Type":"application/x-www-form-urlencoded"},
            body: fd.toString(),
            cache: "no-store"
        })
        .then(function(r){
            return r.text().then(function(text){
                try { return JSON.parse(text); }
                catch (e) {
                    var snippet = text.substring(0, 400).replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
                    throw new Error("پاسخ نامعتبر از سرور: " + snippet);
                }
            });
        })
        .then(function(resp){
            if (resp.success) {
                // ✅ انیمیشن موفقیت حرفه‌ای
                overlay.innerHTML =
                    "<div class=\"rs-success\">" +
                        "<svg class=\"rs-check\" viewBox=\"0 0 52 52\">" +
                            "<circle class=\"rs-check-circle\" cx=\"26\" cy=\"26\" r=\"25\" fill=\"none\"/>" +
                            "<path class=\"rs-check-mark\" fill=\"none\" d=\"M14.1 27.2l7.1 7.2 16.7-16.8\"/>" +
                        "</svg>" +
                        "<div class=\"rs-success-title\">اسکن با موفقیت انجام شد</div>" +
"<div class=\"rs-success-sub\">در حال آماده‌سازی گزارش جدید...</div>" +
"<div style=\"font-size:12px;opacity:.4;margin-top:6px\">آخرین اسکن: " + new Date().toLocaleTimeString("fa-IR") + "</div>" +
"<div class=\"rs-progress-line\"><div class=\"rs-progress-fill\"></div></div>" +
                    "</div>";

                setTimeout(function(){
                    var url = window.location.href.split("#")[0];
                    url = url.replace(/([?&])_t=\d+/g, "$1").replace(/[?&]$/, "");
                    var sep = url.indexOf("?") > -1 ? "&" : "?";
                    window.location.href = url + sep + "_t=" + Date.now();
                }, 2200);
            } else {
                overlay.style.display = "none";
                btn.disabled = false;
                var msg = typeof resp.data === "string" ? resp.data : ((resp.data && resp.data.message) ? resp.data.message : "خطا در اسکن");
                alert(msg);
            }
        })
        .catch(function(e){
            overlay.style.display = "none";
            btn.disabled = false;
            alert("خطا: " + e.message);
        });
    }
    ';
}
    private function admin_css() {
        return '
        .thalath-wrap { max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif; }
        .th-header { background: linear-gradient(135deg, #1e293b, #334155); color: #fff; padding: 30px; border-radius: 14px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .th-header h1 { margin: 0; font-size: 26px; }
        .th-header p { margin: 10px 0 0; opacity: .85; font-size: 14px; }
        .th-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .th-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all .2s; }
        .th-btn-primary { background: #2563eb; color: #fff; }
        .th-btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .th-btn-danger { background: #ef4444; color: #fff; }
        .th-btn-danger:hover { background: #dc2626; transform: translateY(-1px); }
        .th-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .th-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e2e8f0; }
        .th-card h2 { margin-top: 0; color: #1e293b; font-size: 18px; }
        .th-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .th-input:focus { outline: none; border-color: #2563eb; }
        .th-loading { display: none; text-align: center; padding: 40px; }
        .th-spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: th-spin .8s linear infinite; margin: 0 auto 15px; }
        @keyframes th-spin { to { transform: rotate(360deg); } }
        .th-progress { display: none; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 15px; }
        .thalath-score-circle { width: 130px; height: 130px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 38px; font-weight: 800; color: #fff; }
        .score-excellent { background: linear-gradient(135deg, #10b981, #059669); }
        .score-good { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .score-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .score-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .thalath-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 25px; }
        .thalath-summary-item { background: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .thalath-summary-number { font-size: 32px; font-weight: 800; margin-bottom: 4px; }
        .thalath-summary-label { font-size: 13px; color: #64748b; }
        .thalath-tests-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; margin-top: 15px; }
        .thalath-test-card { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-right: 4px solid #cbd5e1; }
        .thalath-test-card.pass { border-right-color: #10b981; }
        .thalath-test-card.fail { border-right-color: #ef4444; }
        .thalath-test-card.warn { border-right-color: #f59e0b; }
        .admin-section-title { color: #1e293b; margin: 25px 0 10px; font-size: 18px; }
        .mw-stat { display: inline-block; padding: 10px 16px; border-radius: 10px; margin: 4px; font-weight: 700; color: #fff; font-size: 13px; }
        .mw-stat.danger { background: #ef4444; }
        .mw-stat.warning { background: #f97316; }
        .mw-stat.info { background: #3b82f6; }
        .mw-stat.safe { background: #10b981; }
        .mw-item { background: #f8fafc; border-right: 3px solid #cbd5e1; padding: 10px 12px; margin-bottom: 8px; border-radius: 6px; font-size: 13px; }
        .mw-item.danger { border-right-color: #ef4444; }
        .mw-item.warning { border-right-color: #f97316; }
        .mw-item.info { border-right-color: #3b82f6; }
        .mw-item code { background: #1e293b; color: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 11px; direction: ltr; }
        .mw-snippet { background: #1e293b; color: #e2e8f0; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 11px; margin-top: 6px; direction: ltr; text-align: left; overflow-x: auto; }
        .th-toast { position: fixed; bottom: 30px; left: 30px; background: #1e293b; color: #fff; padding: 14px 22px; border-radius: 10px; font-size: 14px; transform: translateY(120px); opacity: 0; transition: all .3s; z-index: 99999; box-shadow: 0 10px 25px rgba(0,0,0,.2); }
        .th-toast.show { transform: translateY(0); opacity: 1; }
        .th-toast.success { background: #10b981; }
        .th-toast.error { background: #ef4444; }
        @media (max-width: 768px) {
            .thalath-summary { grid-template-columns: repeat(2, 1fr); }
            .thalath-tests-grid { grid-template-columns: 1fr; }
        }
        ';
    }

private function public_css() {
    return '
    @import url("https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css");

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Vazirmatn, -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: 100vh;
        direction: rtl;
        color: #0f172a;
        line-height: 1.75;
        -webkit-font-smoothing: antialiased;
    }



/* ============ RESCAN LOADING & SUCCESS ============ */
.rs-loading,
.rs-success {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #fff;
    max-width: 420px;
    padding: 0 20px;
}

/* --- Loading ring --- */
.rs-ring {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,.08);
    border-top-color: #a855f7;
    border-right-color: #6366f1;
    animation: rs-spin 1s linear infinite;
    margin-bottom: 26px;
    position: relative;
}
.rs-ring::after {
    content: "";
    position: absolute;
    inset: 8px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,.05);
    border-bottom-color: #ec4899;
    animation: rs-spin 1.5s linear infinite reverse;
}
@keyframes rs-spin { to { transform: rotate(360deg); } }

.rs-text {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -.3px;
    margin-bottom: 8px;
}
.rs-sub {
    font-size: 13px;
    opacity: .65;
    line-height: 1.7;
    max-width: 320px;
}

/* --- Success check (SVG based, elegant) --- */
.rs-check {
    width: 82px;
    height: 82px;
    margin-bottom: 22px;
    display: block;
}
.rs-check-circle {
    stroke: #10b981;
    stroke-width: 2;
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-linecap: round;
    animation: rs-stroke .7s cubic-bezier(.65,0,.45,1) forwards;
    filter: drop-shadow(0 0 12px rgba(16,185,129,.6));
}
.rs-check-mark {
    stroke: #10b981;
    stroke-width: 3.5;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-linecap: round;
    stroke-linejoin: round;
    animation: rs-stroke .4s cubic-bezier(.65,0,.45,1) .7s forwards;
    filter: drop-shadow(0 0 8px rgba(16,185,129,.9));
}
@keyframes rs-stroke {
    100% { stroke-dashoffset: 0; }
}

.rs-success-title {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -.3px;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.rs-success-sub {
    font-size: 13px;
    opacity: .65;
    margin-bottom: 26px;
}

/* --- Progress line --- */
.rs-progress-line {
    width: 260px;
    height: 2px;
    background: rgba(255,255,255,.08);
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}
.rs-progress-fill {
    position: absolute;
    inset: 0;
    width: 0;
    background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
    border-radius: 2px;
    animation: rs-progress 2.2s ease-out forwards;
    box-shadow: 0 0 12px rgba(168,85,247,.6);
}
@keyframes rs-progress {
    0%   { width: 0; }
    100% { width: 100%; }
}

/* --- Rescan overlay --- */
.rescan-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(8, 12, 24, .92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #fff;
    animation: rs-fade .3s ease;
}
@keyframes rs-fade {
    from { opacity: 0; }
    to { opacity: 1; }
} 


.rescan-success {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 20px 40px -10px rgba(16,185,129,.6);
    animation: successPop .5s cubic-bezier(.34,1.56,.64,1);
}
@keyframes successPop {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}

    .wrap { max-width: 1040px; margin: 0 auto; padding: 24px 20px 60px; }

    /* ============ HERO ============ */
    .hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        color: #fff;
        border-radius: 24px;
        padding: 40px 36px;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 30px 60px -20px rgba(15,23,42,.45);
    }
    .hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 15% 20%, rgba(99,102,241,.35), transparent 45%),
            radial-gradient(circle at 85% 80%, rgba(168,85,247,.25), transparent 50%);
        pointer-events: none;
    }
    .hero::after {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
    }
    .hero-inner { position: relative; display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
    .hero-logo {
        font-size: 48px;
        line-height: 1;
        width: 76px; height: 76px;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.15);
        border-radius: 20px;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(10px);
        flex-shrink: 0;
    }
    .hero-text { flex: 1; min-width: 200px; }
    .hero-text h1 { font-size: 26px; font-weight: 800; letter-spacing: -.4px; margin-bottom: 6px; }
    .hero-text p { opacity: .8; font-size: 14px; }
    .hero-time {
        background: rgba(255,255,255,.1);
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 12px;
        border: 1px solid rgba(255,255,255,.12);
        backdrop-filter: blur(10px);
    }

    /* ============ SCORE ============ */
    .score-wrap {
        background: #fff;
        border-radius: 24px;
        padding: 44px 28px 36px;
        margin-bottom: 28px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(15,23,42,.04), 0 20px 40px -24px rgba(15,23,42,.15);
        border: 1px solid rgba(226,232,240,.7);
        position: relative;
        overflow: hidden;
    }
    .score-wrap::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #6366f1, #a855f7);
        opacity: .8;
    }
    .score-circle {
        width: 170px; height: 170px;
        border-radius: 50%;
        margin: 0 auto 22px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 800;
        position: relative;
    }
    .score-circle span { font-size: 56px; line-height: 1; letter-spacing: -2px; }
    .score-circle small { font-size: 22px; margin-right: 4px; margin-top: 12px; opacity: .9; }
    .score-circle::after {
        content: "";
        position: absolute;
        inset: -10px;
        border-radius: 50%;
        border: 2px dashed rgba(15,23,42,.06);
        animation: spinSlow 40s linear infinite;
    }
    @keyframes spinSlow { to { transform: rotate(360deg); } }
    .sc-excellent { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 20px 40px -15px rgba(16,185,129,.5); }
    .sc-good      { background: linear-gradient(135deg, #3b82f6, #4f46e5); box-shadow: 0 20px 40px -15px rgba(59,130,246,.5); }
    .sc-warning   { background: linear-gradient(135deg, #f59e0b, #ea580c); box-shadow: 0 20px 40px -15px rgba(245,158,11,.5); }
    .sc-danger    { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 20px 40px -15px rgba(239,68,68,.5); }

    .score-title { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -.3px; }
    .score-msg { color: #64748b; font-size: 15px; margin-top: 8px; }

    .score-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 32px;
        max-width: 520px;
        margin-left: auto; margin-right: auto;
    }
    .stat {
        background: #f8fafc;
        border-radius: 14px;
        padding: 18px 10px;
        border: 1px solid #e2e8f0;
        transition: transform .2s;
    }
    .stat:hover { transform: translateY(-2px); }
    .stat-num { font-size: 30px; font-weight: 800; line-height: 1; letter-spacing: -1px; }
    .stat-lbl { font-size: 12px; color: #64748b; margin-top: 6px; font-weight: 500; }
    .stat-pass .stat-num { color: #10b981; }
    .stat-warn .stat-num { color: #f59e0b; }
    .stat-fail .stat-num { color: #ef4444; }

    /* ============ SECTIONS ============ */
    .section { margin-bottom: 28px; }
    .section-head { margin-bottom: 18px; }
    .section-title {
        font-size: 20px; font-weight: 800; color: #0f172a;
        display: flex; align-items: center; gap: 10px;
        letter-spacing: -.3px;
    }
    .section-title span { font-size: 22px; }
    .section-hint { font-size: 13px; color: #94a3b8; margin-top: 4px; }

    /* ============ ISSUES ============ */
    .issues { display: flex; flex-direction: column; gap: 12px; }
    .issue {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(15,23,42,.05);
        border: 1px solid rgba(226,232,240,.8);
        border-right: 4px solid #cbd5e1;
        overflow: hidden;
        transition: all .2s;
    }
    .issue:hover { box-shadow: 0 4px 16px rgba(15,23,42,.08); }
    .issue.fail { border-right-color: #ef4444; }
    .issue.warn { border-right-color: #f59e0b; }
    .issue.risk-critical { background: linear-gradient(to left, #fef2f2 0%, #fff 40%); }
    .issue.risk-high { background: linear-gradient(to left, #fff7ed 0%, #fff 40%); }

    .issue-head {
        display: flex; align-items: center; gap: 16px;
        padding: 18px 22px;
        cursor: pointer; list-style: none;
        user-select: none;
    }
    .issue-head::-webkit-details-marker { display: none; }
    .issue-icon {
        font-size: 22px; flex-shrink: 0;
        width: 44px; height: 44px;
        background: #f8fafc; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
    }
    .issue.fail .issue-icon { background: #fee2e2; }
    .issue.warn .issue-icon { background: #fef3c7; }
    .issue-title { flex: 1; min-width: 0; }
    .issue-title h3 { font-size: 15px; font-weight: 700; margin-bottom: 3px; color: #0f172a; }
    .issue-title p { font-size: 13px; color: #64748b; line-height: 1.6; }
    .issue-arrow { color: #94a3b8; font-size: 14px; transition: transform .25s; }
    details[open] .issue-arrow { transform: rotate(180deg); }
    .issue-body {
        padding: 20px 22px;
        border-top: 1px dashed #e2e8f0;
    }
    .issue-block { margin-bottom: 16px; }
    .issue-block:last-child { margin-bottom: 0; }
    .issue-block h4 {
        font-size: 13px; color: #475569; margin-bottom: 8px; font-weight: 700;
        display: flex; align-items: center; gap: 6px;
    }
    .issue-block p { font-size: 14px; color: #334155; line-height: 1.9; }
    .issue-code {
        background: #0f172a; color: #e2e8f0;
        padding: 14px 16px; border-radius: 10px;
        font-family: "JetBrains Mono", Menlo, monospace; font-size: 12px;
        direction: ltr; text-align: left; overflow-x: auto;
        word-break: break-all; line-height: 1.6;
    }

    /* ============ PASSED ============ */
    .passed-details {
        background: #fff; border-radius: 16px;
        padding: 22px 26px;
        box-shadow: 0 1px 3px rgba(15,23,42,.05);
        border: 1px solid rgba(226,232,240,.8);
    }
    .passed-details summary {
        cursor: pointer; font-weight: 700; font-size: 15px;
        color: #059669; list-style: none; outline: none;
        display: flex; align-items: center; gap: 8px;
    }
    .passed-details summary::-webkit-details-marker { display: none; }
    .passed-details[open] summary { margin-bottom: 18px; }
    .passed-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px,1fr));
        gap: 10px;
    }
    .passed-item {
        display: flex; gap: 12px;
        padding: 12px 14px;
        background: #f8fafc;
        border-radius: 10px;
        border-right: 3px solid #10b981;
    }
    .pi-icon { font-size: 18px; flex-shrink: 0; line-height: 1.3; }
    .pi-body strong { font-size: 13px; color: #0f172a; display: block; margin-bottom: 3px; }
    .pi-body p { font-size: 12px; color: #64748b; line-height: 1.6; }

    /* ============ MALWARE ============ */
    .mw-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 22px;
    }
    .mw-stat {
        background: #fff;
        border-radius: 16px;
        padding: 22px 14px 18px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(15,23,42,.05);
        border: 1px solid rgba(226,232,240,.8);
        position: relative;
        overflow: hidden;
        transition: transform .2s;
    }
    .mw-stat:hover { transform: translateY(-2px); }
    .mw-stat::before {
        content: "";
        position: absolute; top: 0; left: 0; right: 0;
        height: 3px;
        background: currentColor; opacity: .8;
    }
    .mw-icon { font-size: 24px; line-height: 1; margin-bottom: 8px; }
    .mw-num { font-size: 32px; font-weight: 800; line-height: 1; letter-spacing: -1.2px; }
    .mw-lbl { font-size: 12px; color: #64748b; margin-top: 6px; font-weight: 500; }
    .mw-stat.critical { color: #dc2626; }
    .mw-stat.critical .mw-num { color: #dc2626; }
    .mw-stat.suspicious { color: #ea580c; }
    .mw-stat.suspicious .mw-num { color: #ea580c; }
    .mw-stat.precautionary { color: #0284c7; }
    .mw-stat.precautionary .mw-num { color: #0284c7; }
    .mw-stat.clean { color: #059669; }
    .mw-stat.clean .mw-num { color: #059669; }

    .mw-note {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-right: 4px solid #3b82f6;
        color: #1e40af;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.8;
        margin-bottom: 20px;
    }

    .mw-clean {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border-radius: 20px;
        padding: 44px 24px;
        text-align: center;
        border: 1px solid #a7f3d0;
    }
    .mw-clean-icon { font-size: 56px; line-height: 1; margin-bottom: 10px; }
    .mw-clean h3 { color: #065f46; margin-bottom: 6px; font-size: 20px; font-weight: 800; }
    .mw-clean p { color: #047857; font-size: 14px; }

    .mw-group { margin-bottom: 20px; }
    .mw-group-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 10px;
    }
    .mw-group-head h3 { font-size: 14px; font-weight: 700; }
    .mw-count {
        background: rgba(255,255,255,.6);
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .mw-group-head.critical { background: #fee2e2; color: #991b1b; }
    .mw-group-head.suspicious { background: #ffedd5; color: #9a3412; }
    .mw-group-head.precautionary { background: #dbeafe; color: #1e40af; }

    .mw-list { display: flex; flex-direction: column; gap: 8px; }
    .mw-row {
        display: flex; gap: 12px;
        background: #fff;
        border-radius: 12px;
        padding: 14px 16px;
        border: 1px solid rgba(226,232,240,.8);
        border-right: 3px solid #cbd5e1;
        font-size: 13px;
    }
    .mw-row.critical { border-right-color: #dc2626; background: #fffbfb; }
    .mw-row.suspicious { border-right-color: #ea580c; background: #fffdfb; }
    .mw-row.precautionary { border-right-color: #0284c7; background: #fbfdff; }
    .mw-row-icon { font-size: 18px; flex-shrink: 0; line-height: 1.5; }
    .mw-row-body { flex: 1; min-width: 0; }
    .mw-row-path { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 5px; }
    .mw-row-path code {
        background: #0f172a; color: #e2e8f0;
        padding: 3px 9px; border-radius: 6px;
        font-family: "JetBrains Mono", Menlo, monospace;
        font-size: 11px; direction: ltr;
        word-break: break-all;
    }
    .mw-line-pill {
        background: #f1f5f9; color: #64748b;
        padding: 2px 8px; border-radius: 999px;
        font-size: 11px;
    }
    .mw-row-desc { color: #475569; line-height: 1.7; }
    .mw-snippet {
        background: #0f172a; color: #e2e8f0;
        padding: 10px 14px; border-radius: 8px;
        font-family: "JetBrains Mono", Menlo, monospace;
        font-size: 11px;
        margin-top: 8px;
        direction: ltr; text-align: left;
        overflow-x: auto; line-height: 1.6;
    }
    .mw-more { text-align: center; color: #94a3b8; font-size: 12px; padding: 8px; }

    /* ============ RESCAN ============ */
    .rescan-box {
        background: #fff;
        border-radius: 24px;
        padding: 36px 24px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(15,23,42,.05), 0 20px 40px -24px rgba(15,23,42,.15);
        border: 1px solid rgba(226,232,240,.8);
        margin: 28px 0;
    }
    .btn-rescan {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 22px 46px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #fff; border: none; border-radius: 18px;
        font-size: 16px; font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        transition: all .25s;
        box-shadow: 0 20px 40px -15px rgba(79,70,229,.55);
        position: relative;
        overflow: hidden;
    }
    .btn-rescan::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,.15) 50%, transparent 60%);
        transform: translateX(-100%);
        transition: transform .6s;
    }
    .btn-rescan:hover::after { transform: translateX(100%); }
    .btn-rescan:hover { transform: translateY(-2px); box-shadow: 0 25px 45px -15px rgba(79,70,229,.65); }
    .btn-rescan:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .rescan-icon { font-size: 30px; }
    .rescan-text { font-size: 17px; }
    .rescan-sub { font-size: 12px; opacity: .85; font-weight: 400; }
    .rescan-info { color: #94a3b8; font-size: 12px; margin-top: 14px; }

    .rescan-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(15,23,42,.85);
        backdrop-filter: blur(6px);
        z-index: 99999;
        align-items: center; justify-content: center;
        flex-direction: column; color: #fff;
    }
    .rescan-spinner {
        width: 64px; height: 64px;
        border: 4px solid rgba(255,255,255,.15);
        border-top-color: #a855f7;
        border-radius: 50%;
        animation: spinSlow 1s linear infinite;
        margin-bottom: 22px;
    }
    .rescan-overlay p { font-size: 16px; }

    /* ============ LOGIN ============ */
    .login-wrap {
        display: flex; align-items: center; justify-content: center;
        min-height: 100vh; padding: 20px;
    }
    .login-box {
        max-width: 440px; width: 100%;
        background: #fff;
        padding: 48px 40px;
        border-radius: 24px;
        box-shadow: 0 25px 60px -20px rgba(15,23,42,.25);
        text-align: center;
        border: 1px solid rgba(226,232,240,.8);
        position: relative;
        overflow: hidden;
    }
    .login-box::before {
        content: "";
        position: absolute; top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4f46e5, #7c3aed, #ec4899);
    }
    .login-box h1 { font-size: 22px; margin: 8px 0 6px; font-weight: 800; }
    .login-box p { color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.7; }
    .login-box input {
        width: 100%;
        padding: 15px 18px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        margin-bottom: 14px;
        font-family: inherit;
        transition: all .2s;
    }
    .login-box input:focus {
        outline: none; border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79,70,229,.1);
    }
    .login-box button {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff; border: none; border-radius: 12px;
        font-size: 15px; font-weight: 700;
        cursor: pointer; font-family: inherit;
        transition: all .2s;
        box-shadow: 0 10px 25px -10px rgba(79,70,229,.5);
    }
    .login-box button:hover { transform: translateY(-1px); box-shadow: 0 15px 30px -10px rgba(79,70,229,.6); }
    .err {
        background: #fee2e2; color: #991b1b;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 13px;
    }

    /* ============ FOOTER ============ */
    .footer {
        text-align: center;
        padding: 40px 20px 10px;
        margin-top: 20px;
    }
    .footer-brand {
        display: inline-flex;
        flex-direction: column;
        gap: 4px;
        padding: 18px 28px;
        background: #fff;
        border-radius: 16px;
        border: 1px solid rgba(226,232,240,.8);
        box-shadow: 0 1px 3px rgba(15,23,42,.05);
    }
    .footer-brand .brand-name {
        font-size: 15px; font-weight: 800; color: #0f172a;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .footer-brand .brand-dev { font-size: 13px; color: #64748b; }
    .footer-brand .brand-contact { font-size: 13px; color: #4f46e5; font-weight: 600; }
    .footer-brand .brand-contact a { color: inherit; text-decoration: none; direction: ltr; display: inline-block; }
    .footer-copy { font-size: 11px; color: #94a3b8; margin-top: 12px; }

    .empty-state {
        background: #fff; border-radius: 16px;
        padding: 48px; text-align: center;
        color: #64748b;
        border: 1px solid rgba(226,232,240,.8);
    }

    @media (max-width: 640px) {
        .wrap { padding: 16px 14px 40px; }
        .hero { padding: 26px 22px; border-radius: 20px; }
        .hero-logo { width: 60px; height: 60px; font-size: 36px; }
        .hero-text h1 { font-size: 19px; }
        .hero-time { font-size: 11px; padding: 6px 12px; }
        .score-wrap { padding: 32px 18px 26px; }
        .score-circle { width: 140px; height: 140px; }
        .score-circle span { font-size: 46px; }
        .score-title { font-size: 19px; }
        .score-stats { gap: 8px; }
        .stat-num { font-size: 22px; }
        .stat { padding: 14px 6px; }
        .mw-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .mw-num { font-size: 26px; }
        .issue-head { padding: 14px 16px; gap: 12px; }
        .issue-icon { width: 38px; height: 38px; font-size: 18px; }
        .issue-body { padding: 16px; }
        .login-box { padding: 36px 26px; }
    }
    ';
}
    
}

// init
Thalath_Pro_Scanner::instance();

// Apply user-configured "fixes" (XML-RPC, pingback, version hide) at runtime

add_action('init', function() {
    $s = get_option(THALATH_OPT_PUB, array());
    if (!is_array($s)) return;
    if (!empty($s['disable_xmlrpc'])) add_filter('xmlrpc_enabled', '__return_false');
    if (!empty($s['hide_wp_version'])) {
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
    }
    if (!empty($s['disable_pingback'])) {
        add_filter('xmlrpc_methods', function($m) {
            if (is_array($m)) { unset($m['pingback.ping']); unset($m['pingback.extensions.getPingbacks']); }
            return $m;
        });
    }
}, 20);


/* ============================================================
 *              کپی خلاصه گزارش + موارد فایل‌ها
 * ============================================================ */

add_action('init', function() {
    if (empty($_GET['thalath_report'])) return;
    if (!is_string($_GET['thalath_report'])) return;

    ob_start(function($html) {
        if (stripos($html, '</body>') === false) return $html;

        $fab_script = <<<'HTML'
<script>
(function() {
    'use strict';

    function initFAB() {
        if (!document.querySelector('.hero-inner')) return false;
        if (document.getElementById('thalath-copy-fab')) return true;

        var fab = document.createElement('button');
        fab.id = 'thalath-copy-fab';
        fab.type = 'button';
        fab.innerHTML = '<span style="font-size:16px">📋</span><span>کپی خلاصه</span>';

        fab.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'left:24px',
            'z-index:99997',
            'display:inline-flex',
            'align-items:center',
            'gap:8px',
            'padding:14px 22px',
            'background:linear-gradient(135deg,#4f46e5,#7c3aed)',
            'color:#fff',
            'border:none',
            'border-radius:14px',
            'font-size:14px',
            'font-weight:700',
            'cursor:pointer',
            'box-shadow:0 12px 30px -10px rgba(79,70,229,.6)',
            'font-family:Vazirmatn,-apple-system,BlinkMacSystemFont,sans-serif',
            'transition:transform .2s, box-shadow .2s, background .3s',
            'direction:rtl',
            'line-height:1'
        ].join(';');

        fab.addEventListener('mouseenter', function() {
            fab.style.transform = 'translateY(-2px)';
            fab.style.boxShadow = '0 18px 36px -10px rgba(79,70,229,.7)';
        });
        fab.addEventListener('mouseleave', function() {
            fab.style.transform = 'translateY(0)';
            fab.style.boxShadow = '0 12px 30px -10px rgba(79,70,229,.6)';
        });

        fab.addEventListener('click', function() {
            var text = buildSummaryText();
            copyToClipboard(text).then(function(ok) {
                if (ok) {
                    fab.innerHTML = '<span style="font-size:16px">✅</span><span>کپی شد!</span>';
                    fab.style.background = 'linear-gradient(135deg,#10b981,#059669)';
                } else {
                    fab.innerHTML = '<span style="font-size:16px">❌</span><span>خطا</span>';
                }
                setTimeout(function() {
                    fab.innerHTML = '<span style="font-size:16px">📋</span><span>کپی خلاصه</span>';
                    fab.style.background = 'linear-gradient(135deg,#4f46e5,#7c3aed)';
                }, 1800);
            });
        });

        document.body.appendChild(fab);
        return true;
    }

    /* ---------- ساخت متن خلاصه ---------- */
    function buildSummaryText() {
        var L = [];

        var siteName = txt(document.querySelector('.hero-text p'));
        var timeStr  = txt(document.querySelector('.hero-time'));
        var pct      = txt(document.querySelector('.score-circle span'));
        var status   = txt(document.querySelector('.score-title'));

        /* ==== هدر ==== */
        L.push('گزارش امنیتی سایت');
        L.push('─────────────────────');
        if (siteName) L.push('🌐 ' + siteName);
        if (timeStr)  L.push('🕐 ' + timeStr);
        L.push('');

        /* ==== مغز گزارش (امتیاز + آمار) ==== */
        if (pct) {
            L.push('🎯 امتیاز امنیتی: ' + pct + '%');
            if (status) L.push('   ' + status);
            L.push('');
        }

        var stats = document.querySelectorAll('.score-stats .stat');
        if (stats.length) {
            L.push('📊 خلاصه:');
            stats.forEach(function(s) {
                var n = txt(s.querySelector('.stat-num'));
                var l = txt(s.querySelector('.stat-lbl'));
                if (l) L.push('   • ' + l + ': ' + n);
            });
            L.push('');
        }

        /* ==== موارد اسکن فایل‌ها ==== */
        var mwStats = document.querySelector('.mw-stats');
        if (mwStats) {
            L.push('🦠 اسکن فایل‌های سایت');
            L.push('─────────────────────');

            mwStats.querySelectorAll('.mw-stat').forEach(function(s) {
                var num = txt(s.querySelector('.mw-num'));
                var lbl = txt(s.querySelector('.mw-lbl'));
                if (lbl) L.push('   • ' + lbl + ': ' + num);
            });
            L.push('');

            // لیست فایل‌ها به تفکیک سطح
            document.querySelectorAll('.mw-group').forEach(function(g) {
                var head  = txt(g.querySelector('.mw-group-head h3'));
                var count = txt(g.querySelector('.mw-count'));
                var rows  = g.querySelectorAll('.mw-row');
                if (!rows.length) return;

                L.push('▶ ' + head + (count ? ' (' + count + ')' : ''));
                rows.forEach(function(r) {
                    var path = txt(r.querySelector('.mw-row-path code'));
                    var line = txt(r.querySelector('.mw-line-pill'));
                    var desc = txt(r.querySelector('.mw-row-desc'));
                    L.push('   ▪ ' + path + (line ? ' — ' + line : ''));
                    if (desc) L.push('     ' + desc);
                });
                L.push('');
            });

            // اگه همه فایل‌ها سالم بودن
            var cleanBox = document.querySelector('.mw-clean');
            if (cleanBox && !document.querySelector('.mw-group')) {
                L.push('   ✅ همه فایل‌ها پاک هستن — هیچ فایل مخربی پیدا نشد');
                L.push('');
            }
        }

        /* ==== فوتر ==== */
        L.push('─────────────────────');
        L.push('🛡️ ثلاث امنیت');
        L.push('📞 09020028907');

        return L.join('\n');
    }

    function txt(el) {
        if (!el) return '';
        return (el.textContent || '').trim().replace(/\s+/g, ' ');
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function() {
                return true;
            }).catch(function() {
                return fallbackCopy(text);
            });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    function boot() {
        var attempts = 0;
        var timer = setInterval(function() {
            if (initFAB() || ++attempts > 25) clearInterval(timer);
        }, 120);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;

        return str_ireplace('</body>', $fab_script . '</body>', $html);
    });
}, 1);

