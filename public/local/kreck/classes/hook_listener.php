<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * KRECK hook listeners.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

/**
 * Injects "View in KRECK SMS" deep-links on course and user-profile pages.
 */
class hook_listener {

    /**
     * Keep light dropdown surfaces readable when the active theme supplies
     * light menus but inherits white link colors from the dark navbar.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function add_theme_contrast_styles(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        $hook->add_html(<<<'HTML'
<style id="local-kreck-theme-contrast">
.dropdown-menu,
#usernavigation .dropdown-menu,
.navbar .dropdown-menu {
    --bs-dropdown-color: #10150d;
    --bs-dropdown-bg: #ffffff;
    --bs-dropdown-link-color: #10150d;
    --bs-dropdown-link-hover-color: #10150d;
    --bs-dropdown-link-hover-bg: #edf1e6;
    --bs-dropdown-link-active-color: #ffffff;
    --bs-dropdown-link-active-bg: #42571d;
    --bs-dropdown-link-disabled-color: #667783;
    --bs-dropdown-header-color: #536574;
    color: #10150d !important;
    background-color: #ffffff !important;
    border-color: #d5dfe6 !important;
}
.dropdown-menu .dropdown-item,
.dropdown-menu a:not(.btn) {
    color: #10150d !important;
}
.dropdown-menu .dropdown-item:hover,
.dropdown-menu .dropdown-item:focus,
.dropdown-menu a:not(.btn):hover,
.dropdown-menu a:not(.btn):focus {
    color: #10150d !important;
    background-color: #edf1e6 !important;
}
.dropdown-menu .dropdown-item.active,
.dropdown-menu .dropdown-item:active {
    color: #ffffff !important;
    background-color: #42571d !important;
}
.dropdown-menu .dropdown-item.disabled,
.dropdown-menu .dropdown-item:disabled {
    color: #667783 !important;
}
.dropdown-menu .dropdown-header {
    color: #536574 !important;
}
.dropdown-menu .dropdown-divider {
    border-top-color: #d5dfe6;
    opacity: 1;
}
[data-bs-theme="dark"] .dropdown-menu,
[data-bs-theme="dark"] #usernavigation .dropdown-menu,
[data-bs-theme="dark"] .navbar .dropdown-menu {
    --bs-dropdown-color: #eef1e8;
    --bs-dropdown-bg: #10150d;
    --bs-dropdown-link-color: #eef1e8;
    --bs-dropdown-link-hover-color: #ffffff;
    --bs-dropdown-link-hover-bg: #1c3e4f;
    --bs-dropdown-link-active-color: #ffffff;
    --bs-dropdown-link-active-bg: #42571d;
    --bs-dropdown-header-color: #b8c5ce;
    color: #eef1e8 !important;
    background-color: #10150d !important;
    border-color: #365166 !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-item,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn) {
    color: #eef1e8 !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-menu .dropdown-item:focus,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn):hover,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn):focus {
    color: #ffffff !important;
    background-color: #1c3e4f !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-header {
    color: #b8c5ce !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-divider {
    border-top-color: #365166;
}
/* KRECK Overstory accent on Moodle core controls (buttons, links) so the LMS
   chrome matches the KRECK SMS: leaf-green primary, lime/gold accents. */
.btn-primary {
    --bs-btn-bg: #52701f; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #42571d; --bs-btn-hover-border-color: #42571d;
    --bs-btn-active-bg: #3c5618; --bs-btn-active-border-color: #3c5618;
    --bs-btn-disabled-bg: #6f8a3e; --bs-btn-disabled-border-color: #6f8a3e;
}
.btn-success {
    --bs-btn-bg: #52701f; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #42571d; --bs-btn-hover-border-color: #42571d;
}
.btn-outline-primary {
    --bs-btn-color: #42571d; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #52701f; --bs-btn-hover-border-color: #52701f;
}
a { color: #42571d; }
a:hover { color: #3c5618; }
.text-primary { color: #42571d !important; }
.bg-primary { background-color: #52701f !important; }
</style>
HTML
        );
    }

    /**
     * Render a KRECK SMS deep-link near the top of the body on course /
     * user-profile pages, when a stored link exists and a base URL is set.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function add_sms_link(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $DB;

        $base = trim((string) get_config('local_kreck', 'gibbonbaseurl'));
        if ($base === '') {
            return;
        }
        $context = $PAGE->context ?? null;
        if (!$context) {
            return;
        }

        $link = null;
        if ($context instanceof \context_course && (int) $context->instanceid !== (int) SITEID) {
            $shortname = $DB->get_field('course', 'shortname', ['id' => $context->instanceid]);
            if ($shortname) {
                $link = $DB->get_record('local_kreck_link',
                    ['entitytype' => 'course', 'entitykey' => $shortname, 'direction' => 'togibbon']);
            }
        } else {
            $path = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';
            if (strpos($path, '/user/profile.php') !== false || strpos($path, '/user/view.php') !== false) {
                $userid = optional_param('id', 0, PARAM_INT);
                if ($userid) {
                    $studentid = $DB->get_field('local_kreck_student', 'studentid', ['userid' => $userid]);
                    if ($studentid) {
                        $link = $DB->get_record('local_kreck_link',
                            ['entitytype' => 'user', 'entitykey' => $studentid, 'direction' => 'togibbon']);
                    }
                }
            }
        }
        if (!$link) {
            return;
        }

        $href = s(rtrim($base, '/') . $link->relurl);
        $label = s(get_string('viewinsms', 'local_kreck'));
        $html = '<div class="local-kreck-smslink" style="text-align:right;margin:4px 0;padding:0 16px;">'
            . '<a href="' . $href . '" target="_blank" rel="noopener" '
            . 'style="display:inline-block;background:#52701f;color:#fff;text-decoration:none;'
            . 'border-radius:6px;padding:5px 12px;font-weight:600;font-size:13px;">'
            . $label . ' &#8599;</a></div>';
        $hook->add_html($html);
    }

    /**
     * Point the navbar logo/brand at the KRECK welcome page.
     *
     * The brand is a plain static anchor whose href is the site "home" URL —
     * which, when the site's default home page is the Dashboard, is /my/, not
     * the KRECK landing page. Rewrite it to welcome.php. (The primary navigation
     * is a React component, so the "Home" item is added server-side in
     * extend_primary_navigation(); only the static brand is handled here.)
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function retarget_logo(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        if (!get_config('local_kreck', 'landingenabled')) {
            return;
        }
        $url = (new \moodle_url('/local/kreck/welcome.php'))->out(false);
        $js = '<script>(function(){var w=' . json_encode($url) . ';'
            . 'function r(){var b=document.querySelector("a.navbar-brand");if(b&&b.getAttribute("href")!==w){b.setAttribute("href",w);}}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",r);}else{r();}})();</script>';
        $hook->add_html($js);
    }

    /**
     * Add a "Home" item to the primary navigation that opens the KRECK welcome
     * page, placed before the Dashboard node. Runs server-side so it is part of
     * the (React-rendered) primary-nav data and survives client re-render.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER;
        $primary = $hook->get_primaryview();

        // Parent mirror: a "My children" entry for any user with linked children,
        // independent of the public landing page. Shown only when the current
        // user actually has at least one mirrored child link.
        if (isloggedin() && !isguestuser()
                && !$primary->get('kreckchildren', \navigation_node::TYPE_CUSTOM)
                && \local_kreck\persistent\parentlink::count_records(['parentuserid' => (int) $USER->id]) > 0) {
            $childrennode = \navigation_node::create(
                get_string('mychildren', 'local_kreck'),
                new \moodle_url('/local/kreck/children.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'kreckchildren',
                new \pix_icon('i/users', '')
            );
            $childrennode->showinflatnavigation = true;
            $primary->add_node($childrennode, 'myhome');
        }

        if (!get_config('local_kreck', 'landingenabled')) {
            return;
        }
        if ($primary->get('kreckhome', \navigation_node::TYPE_CUSTOM)) {
            return;
        }
        $node = \navigation_node::create(
            get_string('home'),
            new \moodle_url('/local/kreck/welcome.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'kreckhome',
            new \pix_icon('i/home', '')
        );
        $node->showinflatnavigation = true;
        // Insert before Dashboard ('myhome'); append if that node is absent.
        $primary->add_node($node, 'myhome');
    }

    /**
     * Send visitors from the site front page to the KRECK landing (welcome) page,
     * so the navbar logo and the "Home" nav item — which both point at the site
     * home ('/') — open the KRECK welcome page. Applies to anonymous AND
     * logged-in users.
     *
     * Runs early (before the front page's force-login check), so it works even
     * when the site forces login. Anyone can still reach Moodle's own front page
     * with '/?redirect=0'. Gated by the 'landingenabled' setting; disabling it
     * restores the stock front page for everyone.
     *
     * @param \core\hook\after_config $hook
     * @return void
     */
    /**
     * Restyle the LMS login page to match the KRECK welcome page: the stock
     * "Welcome to Moodle" split screen becomes a glass sign-in panel on the
     * KRECK gradient, with the institution wordmark + tagline on the hero side.
     * Pure CSS + a tiny vanilla script (same house pattern as retarget_logo);
     * no template/theme override, so it ships with the plugin and toggles off
     * via the 'loginstyleenabled' setting.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function style_login_page(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $SITE;

        if (!get_config('local_kreck', 'loginstyleenabled')) {
            return;
        }
        if (($PAGE->pagelayout ?? '') !== 'login') {
            return;
        }

        $css = <<<'CSS'
<style id="local-kreck-login">
/* ---- KRECK branded sign-in (glass on the Overstory gradient) ---- */
body.pagelayout-login{
  --k-slate:#10150d;--k-teal:#52701f;--k-amber:#e6b765;--k-steel:#2f6da3;--k-lime:#c4dc52;
  background:
    radial-gradient(760px 520px at 88% -8%,rgba(82,112,31,.30),transparent 70%),
    radial-gradient(620px 460px at 6% 108%,rgba(47,109,163,.26),transparent 72%),
    radial-gradient(520px 420px at 50% 40%,rgba(230,183,101,.06),transparent 70%),
    #10150d !important;
  background-attachment:fixed !important;
  color:#eef1e6;
}
body.pagelayout-login #page,
body.pagelayout-login #page-content,
body.pagelayout-login #region-main,
body.pagelayout-login #region-main-box,
body.pagelayout-login .login-container{background:transparent !important;border:0 !important;box-shadow:none !important;}

/* Hero (left) side */
body.pagelayout-login .login-layout-left{
  background:
    radial-gradient(500px 360px at 85% -5%,rgba(82,112,31,.38),transparent 68%),
    radial-gradient(420px 340px at 10% 105%,rgba(47,109,163,.30),transparent 70%),
    linear-gradient(160deg,#133420,#0b140d) !important;
  position:relative;overflow:hidden;color:#fff;
}
body.pagelayout-login .login-layout-left::after{
  content:"";position:absolute;right:-4%;bottom:-4%;width:46%;height:46%;
  background-image:radial-gradient(rgba(255,255,255,.5) 1.5px,transparent 1.5px);
  background-size:16px 16px;opacity:.25;
  -webkit-mask-image:linear-gradient(135deg,#000,transparent 70%);mask-image:linear-gradient(135deg,#000,transparent 70%);
  pointer-events:none;
}
body.pagelayout-login .login-layout-left-content{position:relative;z-index:1;max-width:30rem;}
body.pagelayout-login .login-layout-left-content h1{font-weight:800;letter-spacing:-.01em;font-size:clamp(2rem,3.4vw,3rem);color:#fff;}
body.pagelayout-login .kreck-eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#f0d49a;margin-bottom:.6rem;}
body.pagelayout-login .kreck-eyebrow::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--k-amber);box-shadow:0 0 14px rgba(230,183,101,.8);}
body.pagelayout-login .login-layout-stats{display:none !important;}

/* Sign-in (right) side: the glass panel */
body.pagelayout-login .loginform{
  max-width:440px;margin:auto;padding:clamp(1.6rem,4vw,2.6rem);
  background:linear-gradient(145deg,rgba(255,255,255,.14),rgba(255,255,255,.05)) !important;
  border:1px solid rgba(255,255,255,.22) !important;border-radius:22px !important;
  box-shadow:0 24px 60px rgba(2,10,20,.40),inset 0 1px 0 rgba(255,255,255,.16) !important;
  -webkit-backdrop-filter:blur(20px);backdrop-filter:blur(20px);
  color:#eef1e6;
}
body.pagelayout-login .loginform h1,body.pagelayout-login .loginform .h2{color:#fff;font-weight:800;}
body.pagelayout-login .loginform .text-muted{color:#bcd0b1 !important;}
body.pagelayout-login .loginform label,body.pagelayout-login .loginform .form-label{color:#dce7d2 !important;font-weight:600;}
body.pagelayout-login .login-input-wrapper .form-control{
  background:rgba(255,255,255,.08) !important;border:1px solid rgba(255,255,255,.22) !important;
  color:#fff !important;border-radius:12px !important;
}
body.pagelayout-login .login-input-wrapper .form-control::placeholder{color:rgba(255,255,255,.5) !important;}
body.pagelayout-login .login-input-wrapper .form-control:focus{
  background:rgba(255,255,255,.13) !important;border-color:rgba(196,220,82,.7) !important;
  box-shadow:0 0 0 .2rem rgba(82,112,31,.35) !important;color:#fff !important;
}
body.pagelayout-login .login-input-icon .icon,body.pagelayout-login .login-input-icon i{color:rgba(255,255,255,.6) !important;}
body.pagelayout-login .loginform a{color:var(--k-amber);}
body.pagelayout-login .loginform a:hover{color:#f0c983;}
body.pagelayout-login #loginbtn,body.pagelayout-login .loginform .btn-primary{
  background:linear-gradient(135deg,#6f8a3e,#42571d) !important;border:0 !important;border-radius:12px !important;
  font-weight:700;box-shadow:0 10px 24px rgba(82,112,31,.38) !important;
}
body.pagelayout-login #loginbtn:hover,body.pagelayout-login .loginform .btn-primary:hover{background:linear-gradient(135deg,#7d9a46,#3c5618) !important;}
body.pagelayout-login .loginform .btn:not(.btn-primary):not(.btn-link){
  background:rgba(255,255,255,.1) !important;border:1px solid rgba(255,255,255,.22) !important;color:#fff !important;border-radius:12px !important;
}
body.pagelayout-login .loginform .btn-link{color:var(--k-amber) !important;}
body.pagelayout-login .login-cookiesnotice,body.pagelayout-login .logininfo,body.pagelayout-login .login-cookiesnotice *{color:#c6d3bc !important;}
body.pagelayout-login .divider,body.pagelayout-login hr{border-color:rgba(255,255,255,.18) !important;}
</style>
CSS;

        $brand = format_string($SITE->fullname);
        $payload = json_encode([
            'brand'   => $brand !== '' ? $brand : 'KRECK MicroLearn',
            'eyebrow' => get_string('landing_eyebrow', 'local_kreck'),
            'tagline' => get_string('login_tagline', 'local_kreck'),
            'hero'    => get_string('login_hero', 'local_kreck'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $js = <<<JS
<script>(function(){
  var K=$payload;
  function go(){
    var left=document.querySelector('.login-layout-left-content');
    if(!left){return;}
    var h1=left.querySelector('h1');
    if(h1 && !left.querySelector('.kreck-eyebrow')){
      var eb=document.createElement('span');
      eb.className='kreck-eyebrow';
      eb.textContent=K.eyebrow;
      h1.parentNode.insertBefore(eb,h1);
      h1.textContent=K.brand;
    }
    var sub=left.querySelector('div.mx-auto');
    if(sub){sub.textContent=K.tagline;}
    var p=left.querySelector('p');
    if(p){p.textContent=K.hero;}
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',go);}else{go();}
})();</script>
JS;

        $hook->add_html($css . $js);
    }

    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
            return;
        }
        if (during_initial_install()) {
            return;
        }
        if (!get_config('local_kreck', 'landingenabled')) {
            return;
        }

        $me = qualified_me();
        if (!$me) {
            return;
        }
        $path = strtok($me, '?');
        $isfrontpage = in_array($path, [
            $CFG->wwwroot,
            $CFG->wwwroot . '/',
            $CFG->wwwroot . '/index.php',
        ], true);
        if (!$isfrontpage) {
            return;
        }
        // Never get in the way of an upgrade.
        if (moodle_needs_upgrading()) {
            return;
        }
        // Standard escape hatch: '/?redirect=0' reaches Moodle's own front page.
        if (optional_param('redirect', 1, PARAM_BOOL) == 0) {
            return;
        }

        redirect(new \moodle_url('/local/kreck/welcome.php'));
    }
}
