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
 * Public KRECK landing page: an interactive, read-only shopfront for the
 * institution — hero, quick actions, learning pathway, searchable subjects,
 * feature modules and a cookie banner. No uploaded media; all visuals are CSS.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $SITE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/kreck/welcome.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($SITE->fullname));
$PAGE->set_heading('');

$loginurl = (new moodle_url('/login/index.php'))->out(false);
$courseurl = (new moodle_url('/course/'))->out(false);
$anon = (!isloggedin() || isguestuser());

// ---- Public, read-only data -------------------------------------------------
$catcount = $DB->count_records('course_categories', ['visible' => 1]);
$coursecount = $DB->count_records_select('course', 'visible = 1 AND id <> :site', ['site' => SITEID]);
$activitycount = $DB->count_records_select('course_modules', 'visible = 1 AND deletioninprogress = 0');
$learnercount = (int) $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
      WHERE r.archetype = :a", ['a' => 'student']);

// Top-level categories as "pathway" stages (with subtree subject counts/samples).
$topcats = $DB->get_records('course_categories', ['parent' => 0, 'visible' => 1], 'sortorder ASC', 'id, name, path');
$pathway = [];
foreach ($topcats as $top) {
    $subids = $DB->get_fieldset_select('course_categories', 'id',
        'visible = 1 AND (id = :id OR ' . $DB->sql_like('path', ':p') . ')',
        ['id' => $top->id, 'p' => '/' . $top->id . '/%']);
    if (empty($subids)) {
        $subids = [$top->id];
    }
    [$csql, $cparams] = $DB->get_in_or_equal($subids, SQL_PARAMS_NAMED);
    $cparams['site'] = SITEID;
    $n = (int) $DB->count_records_select('course', "category $csql AND visible = 1 AND id <> :site", $cparams);
    if ($n === 0) {
        continue;
    }
    $samples = $DB->get_records_select('course', "category $csql AND visible = 1 AND id <> :site",
        $cparams, 'fullname ASC', 'id, fullname', 0, 4);
    $pathway[] = (object) [
        'name' => format_string($top->name),
        'count' => $n,
        'samples' => array_map(static fn($c) => format_string($c->fullname), $samples),
        'anchor' => 'cat-' . $top->id,
    ];
}

// All visible categories holding courses, for the searchable explorer.
$cats = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC', 'id, name');
$explorer = [];
foreach ($cats as $cat) {
    $courses = $DB->get_records_select('course', 'category = :c AND visible = 1 AND id <> :site',
        ['c' => $cat->id, 'site' => SITEID], 'fullname ASC', 'id, fullname');
    if ($courses) {
        $explorer[] = (object) [
            'id' => $cat->id,
            'name' => format_string($cat->name),
            'courses' => array_map(static fn($c) => format_string($c->fullname), $courses),
        ];
    }
}

$intro = (string) get_config('local_kreck', 'landingintro');
if ($intro === '') {
    $intro = $SITE->summary ? content_to_text($SITE->summary, FORMAT_HTML)
        : get_string('landing_defaultintro', 'local_kreck');
}
$sitename = format_string($SITE->fullname);

// Palette cycle for pathway stage cards.
$stagecolors = ['#1e8a74', '#2f6da3', '#f2a43c', '#8a3b52', '#16293b'];

// Interactivity (CSP-safe via Moodle) : live subject search + cookie banner.
$PAGE->requires->js_init_code(<<<JS
(function(){
  var s=document.getElementById('kl-search');
  if(s){ s.addEventListener('input',function(){
    var q=this.value.trim().toLowerCase();
    document.querySelectorAll('.kl-cat').forEach(function(cat){
      var any=false;
      cat.querySelectorAll('.kl-course').forEach(function(li){
        var m=li.getAttribute('data-name').indexOf(q)>-1; li.style.display=m?'':'none'; if(m)any=true;
      });
      cat.style.display=(any||q==='')?'':'none';
    });
  }); }
  function set(k,v){try{localStorage.setItem(k,v);}catch(e){}}
  function get(k){try{return localStorage.getItem(k);}catch(e){return '1';}}
  var cb=document.getElementById('kl-cookie');
  if(cb && !get('kreck_cookie')){ cb.hidden=false; }
  ['kl-accept','kl-reject'].forEach(function(id){
    var b=document.getElementById(id);
    if(b){ b.addEventListener('click',function(){ set('kreck_cookie',id==='kl-accept'?'all':'reject'); if(cb)cb.hidden=true; }); }
  });
})();
JS);

echo $OUTPUT->header();

$flame = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M12 2c.6 3-1.5 4.4-2.8 5.9C8 9.2 7 10.6 7 12.5A5 5 0 0 0 17 13c0-1.7-.7-3-1.6-4.2.3 1-.2 2-1 2.6.3-2-.7-4-2.4-5.3C10.8 5 12.5 3.6 12 2z"/></svg>';
?>
<style>
.kreck-landing{--kl-slate:#0f1e2e;--kl-slate2:#16293b;--kl-teal:#1e8a74;--kl-amber:#f2a43c;--kl-steel:#2f6da3;--kl-ink:#13212e;--kl-muted:#5a6b7b;--kl-surface:#fff;--kl-subtle:#f5f7fa;--kl-border:rgba(15,30,46,.08);--kl-shadow:0 1px 2px rgba(15,30,46,.05),0 10px 30px rgba(15,30,46,.07);}
.kreck-landing{color:var(--kreck-ink,var(--kl-ink));}
.kreck-landing h1,.kreck-landing h2,.kreck-landing h3{letter-spacing:-.02em;}
.kreck-landing section{margin-bottom:clamp(2rem,5vw,3.5rem);}
.kreck-landing a.kl-btn,.kreck-landing button.kl-btn{display:inline-flex;align-items:center;gap:.5rem;font-weight:700;border-radius:999px;padding:.7rem 1.3rem;text-decoration:none;border:0;cursor:pointer;transition:transform .12s ease,background .12s ease;}
.kreck-landing a.kl-btn:hover,.kreck-landing button.kl-btn:hover{transform:translateY(-1px);}
.kreck-landing .kl-btn--primary{background:var(--kreck-teal,var(--kl-teal));color:#fff;}
.kreck-landing .kl-btn--primary:hover{background:#166a59;color:#fff;}
.kreck-landing .kl-btn--ghost{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.4);}
.kreck-landing .kl-btn--dark{background:var(--kreck-slate-900,var(--kl-slate));color:#fff;}

/* Hero (asymmetric) */
.kl-hero{display:grid;grid-template-columns:1fr 1fr;gap:clamp(1.5rem,4vw,3rem);align-items:center;
  background:radial-gradient(900px 500px at 100% 0,rgba(30,138,116,.18),transparent 60%),var(--kreck-slate-900,var(--kl-slate));
  color:#fff;border-radius:24px;padding:clamp(1.6rem,4vw,3rem);overflow:hidden;}
@media(max-width:860px){.kl-hero{grid-template-columns:1fr;}}
.kl-hero-eyebrow{font-size:.74rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#aeb9c4;margin:0;display:flex;align-items:center;gap:.5rem;}
.kl-hero-eyebrow::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--kl-amber);}
.kl-hero h1{font-size:clamp(2rem,5.5vw,3.4rem);font-weight:800;color:#fff;margin:.6rem 0 0;line-height:1.05;text-wrap:balance;}
.kl-hero p.lede{max-width:52ch;color:#c7d1da;margin:1rem 0 0;font-size:clamp(1rem,2.3vw,1.18rem);}
.kl-hero-cta{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.6rem;}
/* geometric framing (no media) */
.kl-frame{position:relative;min-height:260px;}
.kl-frame .sh{position:absolute;border-radius:22px;}
.kl-frame .sh1{inset:14% 10% 10% 16%;background:linear-gradient(145deg,#1f7a6b,#14564b);}
.kl-frame .sh2{width:46%;aspect-ratio:1;right:6%;top:4%;background:var(--kl-amber);border-radius:50%;opacity:.92;}
.kl-frame .sh3{width:38%;aspect-ratio:1;left:4%;bottom:6%;background:var(--kl-steel);transform:rotate(12deg);border-radius:18px;opacity:.9;}
.kl-frame .shield{position:absolute;left:30%;top:24%;width:40%;color:#fff;opacity:.95;}
.kl-frame .dots{position:absolute;right:2%;bottom:2%;width:34%;height:34%;background-image:radial-gradient(rgba(255,255,255,.5) 1.5px,transparent 1.5px);background-size:12px 12px;}

/* Quick actions */
.kl-quick{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;}
@media(max-width:860px){.kl-quick{grid-template-columns:repeat(2,1fr);}}
.kl-qcard{display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem;background:var(--kl-surface);border:1px solid var(--kl-border);border-radius:16px;box-shadow:var(--kl-shadow);text-decoration:none;color:var(--kl-ink);transition:transform .12s ease,box-shadow .12s ease;}
.kl-qcard:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(15,30,46,.12);color:var(--kl-ink);}
.kl-qicon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:var(--kreck-teal-050,#e8f4f1);color:var(--kreck-teal,var(--kl-teal));}
.kl-qcard h3{font-size:1.02rem;font-weight:700;margin:0;}
.kl-qcard p{margin:0;color:var(--kl-muted);font-size:.86rem;}

/* Stats strip */
.kl-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;}
@media(max-width:600px){.kl-stats{grid-template-columns:repeat(2,1fr);}}
.kl-stat{background:var(--kl-surface);border:1px solid var(--kl-border);border-radius:16px;padding:1rem 1.15rem;box-shadow:var(--kl-shadow);}
.kl-stat .v{font-size:2rem;font-weight:800;font-variant-numeric:tabular-nums;}
.kl-stat .l{font-size:.76rem;letter-spacing:.04em;text-transform:uppercase;color:var(--kl-muted);}

/* Section heading */
.kl-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.1rem;}
.kl-head h2{font-size:clamp(1.5rem,3.4vw,2.1rem);font-weight:800;margin:0;}
.kl-head p{margin:.3rem 0 0;color:var(--kl-muted);}

/* Pathway stage cards */
.kl-path{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
.kl-stage{border:1px solid var(--kl-border);border-radius:16px;overflow:hidden;background:var(--kl-surface);box-shadow:var(--kl-shadow);display:flex;flex-direction:column;transition:transform .12s ease;}
.kl-stage:hover{transform:translateY(-3px);}
.kl-stage .top{color:#fff;padding:.9rem 1.1rem;font-weight:800;}
.kl-stage .tag{display:inline-block;margin:.9rem 1.1rem 0;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--kl-muted);}
.kl-stage ul{list-style:none;margin:.5rem 0 0;padding:0 1.1rem;display:flex;flex-direction:column;gap:.35rem;flex:1;}
.kl-stage ul li{padding-left:1rem;position:relative;font-size:.9rem;}
.kl-stage ul li::before{content:"";position:absolute;left:0;top:.5em;width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.6;}
.kl-stage .foot{padding:.9rem 1.1rem;}
.kl-stage .foot a{font-weight:700;text-decoration:none;color:var(--kreck-teal-700,#166a59);}

/* Subject explorer */
.kl-search-wrap{position:relative;max-width:420px;}
.kl-search-wrap input{width:100%;padding:.75rem 1rem .75rem 2.5rem;border-radius:999px;border:1px solid var(--kl-border);font-size:1rem;background:var(--kl-surface);color:var(--kl-ink);}
.kl-search-wrap svg{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--kl-muted);}
.kl-cats{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-top:1.1rem;}
.kl-cat{background:var(--kl-surface);border:1px solid var(--kl-border);border-radius:16px;padding:1.1rem 1.2rem;box-shadow:var(--kl-shadow);}
.kl-cat h3{font-size:1.05rem;font-weight:700;margin:0 0 .6rem;}
.kl-cat ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.4rem;}
.kl-cat .kl-course{padding-left:1.1rem;position:relative;}
.kl-cat .kl-course::before{content:"";position:absolute;left:0;top:.55em;width:7px;height:7px;border-radius:50%;background:var(--kreck-teal,var(--kl-teal));}

/* Feature modules (alternating) */
.kl-feature{display:grid;grid-template-columns:1.1fr 1fr;gap:clamp(1.2rem,3vw,2.4rem);align-items:center;background:var(--kl-surface);border:1px solid var(--kl-border);border-radius:20px;padding:clamp(1.3rem,3vw,2.2rem);box-shadow:var(--kl-shadow);margin-bottom:1rem;}
.kl-feature:nth-child(even){grid-template-columns:1fr 1.1fr;}
.kl-feature:nth-child(even) .kl-fviz{order:-1;}
@media(max-width:800px){.kl-feature,.kl-feature:nth-child(even){grid-template-columns:1fr;}.kl-feature:nth-child(even) .kl-fviz{order:0;}}
.kl-feature h3{font-size:clamp(1.3rem,2.6vw,1.7rem);font-weight:800;margin:0;}
.kl-feature p{color:var(--kl-muted);margin:.6rem 0 0;max-width:52ch;}
.kl-fviz{min-height:160px;border-radius:16px;position:relative;overflow:hidden;}
.kl-fviz.v1{background:radial-gradient(300px 160px at 20% 10%,rgba(242,164,60,.35),transparent 60%),var(--kreck-slate-900,var(--kl-slate));}
.kl-fviz.v2{background:radial-gradient(300px 160px at 80% 20%,rgba(47,109,163,.4),transparent 60%),linear-gradient(135deg,#1f7a6b,#14564b);}
.kl-fviz.v3{background:radial-gradient(260px 160px at 50% 0,rgba(30,138,116,.4),transparent 60%),var(--kreck-slate-800,var(--kl-slate2));}
.kl-fviz .chip{position:absolute;left:1.1rem;bottom:1.1rem;display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.14);color:#fff;border-radius:999px;padding:.35rem .8rem;font-weight:700;font-size:.82rem;}

/* CTA band + cookie banner */
.kl-cta-band{background:linear-gradient(135deg,var(--kreck-teal,var(--kl-teal)),#14564b);color:#fff;border-radius:20px;padding:clamp(1.6rem,4vw,2.6rem);display:flex;align-items:center;justify-content:space-between;gap:1.2rem;flex-wrap:wrap;}
.kl-cta-band h2{margin:0;font-size:clamp(1.4rem,3vw,2rem);font-weight:800;}
.kl-cta-band p{margin:.3rem 0 0;color:#d6f0e8;}
.kl-cookie{position:fixed;left:0;right:0;bottom:0;z-index:1040;background:#fff;border-top:1px solid var(--kl-border);box-shadow:0 -6px 24px rgba(15,30,46,.12);padding:1rem clamp(1rem,4vw,2rem);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
.kl-cookie .txt{max-width:70ch;}
.kl-cookie .txt strong{display:block;margin-bottom:.2rem;}
.kl-cookie .txt p{margin:0;color:var(--kl-muted);font-size:.86rem;}
.kl-cookie .acts{display:flex;gap:.6rem;flex-wrap:wrap;}
.kl-cookie .btn-sm{border-radius:999px;padding:.55rem 1.1rem;font-weight:700;border:1.5px solid var(--kl-border);background:#fff;color:var(--kl-ink);cursor:pointer;}
.kl-cookie .btn-sm.accept{background:var(--kreck-teal,var(--kl-teal));color:#fff;border-color:var(--kreck-teal,var(--kl-teal));}

[data-bs-theme="dark"] .kreck-landing .kl-qcard,[data-bs-theme="dark"] .kreck-landing .kl-stat,[data-bs-theme="dark"] .kreck-landing .kl-cat,[data-bs-theme="dark"] .kreck-landing .kl-stage,[data-bs-theme="dark"] .kreck-landing .kl-feature{background:#13222f;border-color:rgba(255,255,255,.08);color:#e7edf3;}
[data-bs-theme="dark"] .kreck-landing .kl-cookie{background:#13222f;}
</style>

<div class="kreck-landing">

  <!-- HERO -->
  <section class="kl-hero">
    <div class="kl-frame" aria-hidden="true">
      <div class="sh sh1"></div><div class="sh sh2"></div><div class="sh sh3"></div>
      <svg class="shield" viewBox="0 0 100 120" fill="none"><path d="M50 4 92 20v44c0 30-20 46-42 52C28 110 8 94 8 64V20L50 4Z" fill="rgba(255,255,255,.12)" stroke="#fff" stroke-width="3"/></svg>
      <div class="dots"></div>
    </div>
    <div class="kl-hero-copy">
      <p class="kl-hero-eyebrow"><?php echo get_string('landing_eyebrow', 'local_kreck'); ?></p>
      <h1><?php echo $sitename; ?></h1>
      <p class="lede"><?php echo s($intro); ?></p>
      <div class="kl-hero-cta">
        <?php if ($anon) { ?>
          <a class="kl-btn kl-btn--primary" href="<?php echo $loginurl; ?>"><?php echo get_string('login'); ?></a>
        <?php } else { ?>
          <a class="kl-btn kl-btn--primary" href="<?php echo (new moodle_url('/my/'))->out(false); ?>"><?php echo get_string('landing_dashboard', 'local_kreck'); ?></a>
        <?php } ?>
        <a class="kl-btn kl-btn--ghost" href="#explorer"><?php echo get_string('landing_browse', 'local_kreck'); ?></a>
      </div>
    </div>
  </section>

  <!-- QUICK ACTIONS -->
  <section class="kl-quick">
    <a class="kl-qcard" href="#explorer">
      <span class="kl-qicon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg></span>
      <h3><?php echo get_string('landing_qa_subjects', 'local_kreck'); ?></h3>
      <p><?php echo get_string('landing_qa_subjects_d', 'local_kreck'); ?></p>
    </a>
    <a class="kl-qcard" href="#pathway">
      <span class="kl-qicon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 18V8m5 10V5m5 13v-7m5 7V9"/></svg></span>
      <h3><?php echo get_string('landing_qa_pathway', 'local_kreck'); ?></h3>
      <p><?php echo get_string('landing_qa_pathway_d', 'local_kreck'); ?></p>
    </a>
    <a class="kl-qcard" href="<?php echo $loginurl; ?>">
      <span class="kl-qicon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg></span>
      <h3><?php echo get_string('landing_qa_login', 'local_kreck'); ?></h3>
      <p><?php echo get_string('landing_qa_login_d', 'local_kreck'); ?></p>
    </a>
    <a class="kl-qcard" href="#contact">
      <span class="kl-qicon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H5.2L4 17.2V4Z"/></svg></span>
      <h3><?php echo get_string('landing_qa_enquire', 'local_kreck'); ?></h3>
      <p><?php echo get_string('landing_qa_enquire_d', 'local_kreck'); ?></p>
    </a>
  </section>

  <!-- STATS -->
  <section class="kl-stats">
    <div class="kl-stat"><div class="v"><?php echo (int) $coursecount; ?></div><div class="l"><?php echo get_string('landing_stat_subjects', 'local_kreck'); ?></div></div>
    <div class="kl-stat"><div class="v"><?php echo (int) $catcount; ?></div><div class="l"><?php echo get_string('landing_stat_categories', 'local_kreck'); ?></div></div>
    <div class="kl-stat"><div class="v"><?php echo (int) $activitycount; ?></div><div class="l"><?php echo get_string('landing_stat_activities', 'local_kreck'); ?></div></div>
    <div class="kl-stat"><div class="v"><?php echo (int) $learnercount; ?></div><div class="l"><?php echo get_string('landing_stat_learners', 'local_kreck'); ?></div></div>
  </section>

  <!-- PATHWAY -->
  <?php if (!empty($pathway)) { ?>
  <section id="pathway">
    <div class="kl-head"><div><h2><?php echo get_string('landing_pathway_title', 'local_kreck'); ?></h2>
      <p><?php echo get_string('landing_pathway_sub', 'local_kreck'); ?></p></div></div>
    <div class="kl-path">
      <?php foreach ($pathway as $k => $st) { $c = $stagecolors[$k % count($stagecolors)]; ?>
        <div class="kl-stage" style="color:<?php echo $c; ?>">
          <div class="top" style="background:<?php echo $c; ?>"><?php echo $st->name; ?></div>
          <span class="tag"><?php echo get_string('landing_subjectcount', 'local_kreck', $st->count); ?></span>
          <ul>
            <?php foreach ($st->samples as $sname) { echo html_writer::tag('li', s($sname)); } ?>
          </ul>
          <div class="foot"><a href="#<?php echo $st->anchor; ?>"><?php echo get_string('landing_viewsubjects', 'local_kreck'); ?> &rarr;</a></div>
        </div>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

  <!-- SUBJECT EXPLORER -->
  <section id="explorer">
    <div class="kl-head">
      <div><h2><?php echo get_string('landing_courses', 'local_kreck'); ?></h2>
        <p><?php echo get_string('landing_explorer_sub', 'local_kreck'); ?></p></div>
      <div class="kl-search-wrap">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="kl-search" type="search" placeholder="<?php echo s(get_string('landing_searchph', 'local_kreck')); ?>" autocomplete="off">
      </div>
    </div>
    <?php if (empty($explorer)) { ?>
      <p class="text-muted"><?php echo get_string('landing_nocourses', 'local_kreck'); ?></p>
    <?php } else { ?>
      <div class="kl-cats">
        <?php foreach ($explorer as $b) { ?>
          <div class="kl-cat" id="cat-<?php echo (int) $b->id; ?>">
            <h3><?php echo $b->name; ?></h3>
            <ul>
              <?php foreach ($b->courses as $cn) {
                  echo html_writer::tag('li', s($cn), ['class' => 'kl-course', 'data-name' => core_text::strtolower($cn)]);
              } ?>
            </ul>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </section>

  <!-- FEATURE MODULES -->
  <section>
    <div class="kl-feature">
      <div><h3><?php echo get_string('landing_feat1_t', 'local_kreck'); ?></h3>
        <p><?php echo get_string('landing_feat1_d', 'local_kreck'); ?></p></div>
      <div class="kl-fviz v1"><span class="chip"><?php echo $flame; ?> <?php echo get_string('landing_feat1_chip', 'local_kreck'); ?></span></div>
    </div>
    <div class="kl-feature">
      <div><h3><?php echo get_string('landing_feat2_t', 'local_kreck'); ?></h3>
        <p><?php echo get_string('landing_feat2_d', 'local_kreck'); ?></p></div>
      <div class="kl-fviz v2"><span class="chip"><?php echo get_string('landing_feat2_chip', 'local_kreck'); ?></span></div>
    </div>
    <div class="kl-feature">
      <div><h3><?php echo get_string('landing_feat3_t', 'local_kreck'); ?></h3>
        <p><?php echo get_string('landing_feat3_d', 'local_kreck'); ?></p></div>
      <div class="kl-fviz v3"><span class="chip"><?php echo get_string('landing_feat3_chip', 'local_kreck'); ?></span></div>
    </div>
  </section>

  <!-- CTA BAND -->
  <section id="contact">
    <div class="kl-cta-band">
      <div><h2><?php echo get_string('landing_cta_title', 'local_kreck'); ?></h2>
        <p><?php echo get_string('landing_cta_sub', 'local_kreck'); ?></p></div>
      <div style="display:flex;gap:.7rem;flex-wrap:wrap">
        <a class="kl-btn kl-btn--dark" href="<?php echo $loginurl; ?>"><?php echo get_string('login'); ?></a>
        <a class="kl-btn kl-btn--ghost" style="border-color:rgba(255,255,255,.6)" href="mailto:<?php echo s($CFG->supportemail ?: 'admin@example.com'); ?>"><?php echo get_string('landing_cta_contact', 'local_kreck'); ?></a>
      </div>
    </div>
  </section>

</div>

<!-- COOKIE BANNER -->
<div id="kl-cookie" class="kl-cookie" hidden>
  <div class="txt"><strong><?php echo get_string('landing_cookie_title', 'local_kreck'); ?></strong>
    <p><?php echo get_string('landing_cookie_body', 'local_kreck'); ?></p></div>
  <div class="acts">
    <button id="kl-reject" class="btn-sm" type="button"><?php echo get_string('landing_cookie_reject', 'local_kreck'); ?></button>
    <button id="kl-accept" class="btn-sm accept" type="button"><?php echo get_string('landing_cookie_accept', 'local_kreck'); ?></button>
  </div>
</div>
<?php
echo $OUTPUT->footer();
