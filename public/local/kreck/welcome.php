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
$anon = (!isloggedin() || isguestuser());

// ---- Public, read-only data -------------------------------------------------
$learnercount = (int) $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra JOIN {role} r ON r.id = ra.roleid
      WHERE r.archetype = :a", ['a' => 'student']);

// ---- Course wall: every published course with live stats --------------------
$courses = [];
$landingcoursedbgerror = '';
$landingcoursesdebug = !empty($CFG->local_kreck_landingcoursesdebug);
$landingcourses = get_config('local_kreck', 'landingcourses');
if ($landingcourses === false || (bool) $landingcourses) {
    try {
        $courses = local_kreck_get_landing_courses();
    } catch (Exception $e) {
        $courses = [];
        if ($landingcoursesdebug && isloggedin() && !isguestuser()
            && has_capability('moodle/site:config', context_system::instance())) {
            $landingcoursedbgerror = $e->getMessage() .
                ' in ' . $e->getFile() . ':' . $e->getLine();
        }
    }
}

$coursecount = count($courses);
$categoryids = [];
$activitycount = 0;
foreach ($courses as $course) {
    $categoryids[$course->categoryid] = true;
    $activitycount += $course->stats->activities;
}
$catcount = count($categoryids);

$intro = (string) get_config('local_kreck', 'landingintro');
if ($intro === '') {
    $intro = $SITE->summary ? content_to_text($SITE->summary, FORMAT_HTML)
        : get_string('landing_defaultintro', 'local_kreck');
}
$sitename = format_string($SITE->fullname);

// Interactivity (CSP-safe via Moodle): course search, read-more and cookie banner.
$PAGE->requires->js_init_code(<<<JS
(function(){
  var s=document.getElementById('kl-search');
  if(s){ s.addEventListener('input',function(){
    var q=this.value.trim().toLowerCase();
    var any=false;
    document.querySelectorAll('.kl-course-card').forEach(function(card){
      var matches=card.getAttribute('data-search').indexOf(q)>-1;
      card.hidden=!matches;
      if(matches)any=true;
    });
    var empty=document.getElementById('kl-no-results');
    if(empty)empty.hidden=any;
  }); }
  function set(k,v){try{localStorage.setItem(k,v);}catch(e){}}
  function get(k){try{return localStorage.getItem(k);}catch(e){return '1';}}
  var cb=document.getElementById('kl-cookie');
  if(cb && !get('kreck_cookie')){ cb.hidden=false; }
  ['kl-accept','kl-reject'].forEach(function(id){
    var b=document.getElementById(id);
    if(b){ b.addEventListener('click',function(){ set('kreck_cookie',id==='kl-accept'?'all':'reject'); if(cb)cb.hidden=true; }); }
  });

  // Read more / read less on course descriptions.
  document.querySelectorAll('.kl-card-toggle').forEach(function(b){
    b.addEventListener('click',function(ev){
      var desc=b.closest('.kl-course-card').querySelector('.kl-card-desc');
      var open=!desc.classList.contains('kl-expanded');
      desc.classList.toggle('kl-expanded');
      b.setAttribute('aria-expanded',String(open));
      b.textContent=open?b.getAttribute('data-less'):b.getAttribute('data-more');
    });
  });
})();
JS);

// KRECK Assistant Wall — animated hero visual (config + engine, CSP-safe, DOM-ready).
$awconfig = [
    'messages' => [
        "Welcome to " . $sitename . ".\nLet's pick up where\nyou left off.",
        "Your streak is waiting —\na few minutes is\nall it takes.",
        "New lessons just landed\nin your learning pathway.",
        "One student, one record.\nEverything in one place.",
        "Ready for today's\ncheckpoint quiz?",
        "Short lessons, big progress.\nLet's go.",
    ],
    'placeholder' => get_string('landing_aw_placeholder', 'local_kreck'),
    'badge' => 'kreck',
    'interval' => 9,
    'background' => '#0f1e2e', 'glow' => '#1e8a74', 'haze' => '#14564b',
    'glass' => '#2a4a5e', 'accent' => '#0f2233', 'text' => '#eaf2f0',
];
$PAGE->requires->js_init_code('window.KRECK_AW = ' . json_encode($awconfig) . ';', true);
$awjs = @file_get_contents(__DIR__ . '/assistantwall.js');
if ($awjs !== false && $awjs !== '') {
    $PAGE->requires->js_init_code($awjs, true);
}

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

/* Course wall */
.kl-course-wall{position:relative;isolation:isolate;overflow:hidden;border-radius:28px;
  padding:clamp(1.25rem,3vw,2.3rem);
  color:#f4f8fb;
  background:
    radial-gradient(560px 360px at 92% -5%,rgba(30,138,116,.26),transparent 72%),
    radial-gradient(440px 340px at 4% 100%,rgba(47,109,163,.22),transparent 72%),
    var(--kreck-slate-900,var(--kl-slate));
  box-shadow:0 24px 60px rgba(15,30,46,.18);}
.kl-course-wall::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
  opacity:.22;background-image:radial-gradient(rgba(255,255,255,.55) 1px,transparent 1px);background-size:18px 18px;
  mask-image:linear-gradient(135deg,#000,transparent 70%);}
.kl-wall-content{position:relative;z-index:1;}
.kl-wall-header{display:grid;grid-template-columns:minmax(0,1fr) minmax(230px,340px);
  align-items:end;gap:1.25rem;margin-bottom:1.3rem;}
.kl-wall-eyebrow{display:flex;align-items:center;gap:.5rem;margin:0 0 .5rem;
  color:#b6dfd7;font-size:.7rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;}
.kl-wall-eyebrow::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--kl-amber);box-shadow:0 0 14px rgba(242,164,60,.75);}
.kl-wall-header h2{margin:0;color:#fff;font-size:clamp(1.7rem,3.4vw,2.5rem);font-weight:800;line-height:1.08;}
.kl-wall-header p:not(.kl-wall-eyebrow){margin:.55rem 0 0;max-width:56ch;color:#becbd5;}
.kl-search-wrap{position:relative;}
.kl-search-wrap input{width:100%;min-height:48px;padding:.7rem 1rem .7rem 2.7rem;
  border:1px solid rgba(255,255,255,.2);border-radius:14px;font-size:.95rem;
  background:rgba(255,255,255,.08);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);color:#fff;}
.kl-search-wrap input::placeholder{color:#b9c5cf;}
.kl-search-wrap input:focus{outline:2px solid #8ee0d6;outline-offset:2px;border-color:transparent;}
.kl-search-wrap svg{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#b9c5cf;pointer-events:none;}
.kl-wall-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-bottom:1.3rem;}
.kl-wall-stat{min-height:82px;padding:.85rem 1rem;border:1px solid rgba(255,255,255,.15);border-radius:16px;
  background:linear-gradient(145deg,rgba(255,255,255,.11),rgba(255,255,255,.045));
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);}
.kl-wall-stat .v{display:block;color:#fff;font-size:clamp(1.4rem,3vw,2rem);font-weight:800;line-height:1.05;font-variant-numeric:tabular-nums;}
.kl-wall-stat .l{display:block;margin-top:.35rem;color:#b9c9d4;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;}
.kl-wall-empty{margin:1rem 0 0;padding:1.2rem;border:1px solid rgba(255,255,255,.14);border-radius:16px;
  background:rgba(255,255,255,.06);color:#d8e2e9;}
.kl-wall-empty[hidden]{display:none;}
@media(max-width:760px){.kl-wall-header{grid-template-columns:1fr;}.kl-wall-stats{grid-template-columns:repeat(2,minmax(0,1fr));}}

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

[data-bs-theme="dark"] .kreck-landing .kl-feature{background:#13222f;border-color:rgba(255,255,255,.08);color:#e7edf3;}
[data-bs-theme="dark"] .kreck-landing .kl-cookie{background:#13222f;}

/* Frosted course cards */
.kl-courses{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:clamp(1rem,2vw,1.3rem);margin-top:.25rem;}
@media(max-width:520px){.kl-courses{grid-template-columns:1fr;}}
.kl-course-card{min-width:0;height:100%;animation:kl-card-arrive .55s both;}
.kl-course-card[hidden]{display:none;}
.kl-course-card:nth-child(4n+2){animation-delay:.06s;}
.kl-course-card:nth-child(4n+3){animation-delay:.12s;}
.kl-course-card:nth-child(4n+4){animation-delay:.18s;}
@keyframes kl-card-arrive{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
.kl-card-glass{position:relative;display:flex;flex-direction:column;height:100%;min-height:100%;
  overflow:hidden;border:1px solid rgba(255,255,255,.2);border-radius:22px;
  background:
    radial-gradient(280px 170px at 0% 0%,rgba(30,138,116,.19),transparent 74%),
    linear-gradient(145deg,rgba(255,255,255,.13),rgba(255,255,255,.045));
  box-shadow:0 14px 36px rgba(2,10,20,.22),inset 0 1px 0 rgba(255,255,255,.16);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  transition:box-shadow .22s ease,transform .22s ease,border-color .22s ease;}
.kl-card-glass::before{content:"";position:absolute;inset:0 0 auto;height:1px;pointer-events:none;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.8),transparent);}
.kl-course-card:hover .kl-card-glass{transform:translateY(-5px);
  border-color:rgba(142,224,214,.55);box-shadow:0 24px 52px rgba(2,10,20,.35),0 0 30px rgba(30,138,116,.12);}
.kl-card-avatar{position:relative;width:68px;height:68px;border-radius:50%;
  margin:1.1rem 1rem .75rem;display:grid;place-items:center;border:1px solid rgba(255,255,255,.35);
  background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.52),transparent 33%),
    linear-gradient(145deg,var(--kl-card-accent),rgba(15,30,46,.9));
  box-shadow:inset -3px 4px 12px rgba(255,255,255,.1),0 8px 24px rgba(0,0,0,.24);}
.kl-card-badge{font-weight:800;font-size:1rem;letter-spacing:.05em;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.3);}
.kl-card-content{padding:0 1rem 1rem;flex:1;display:flex;flex-direction:column;}
.kl-card-kicker{font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  color:#b5c8d3;margin:0 0 .35rem;}
.kl-card-title{font-size:1.1rem;font-weight:800;margin:0 0 .4rem;line-height:1.25;color:#fff;
  display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;}
.kl-card-cat{display:inline-flex;align-self:flex-start;margin:0 0 .8rem;padding:.25rem .65rem;border-radius:999px;
  border:1px solid rgba(142,224,214,.28);background:rgba(30,138,116,.16);
  color:#aee7dc;font-size:.72rem;font-weight:700;}
.kl-card-stats{display:flex;gap:.5rem;margin-bottom:.8rem;}
.kl-card-stat{flex:1;min-width:0;padding:.6rem .35rem;border:1px solid rgba(255,255,255,.12);border-radius:12px;
  background:rgba(255,255,255,.055);text-align:center;}
.kl-card-stat-value{display:block;color:#fff;font-size:1.12rem;font-weight:800;}
.kl-card-stat-label{color:#b4c3ce;font-size:.62rem;letter-spacing:.045em;text-transform:uppercase;}
.kl-card-desc{font-size:.88rem;color:#d1dce4;line-height:1.55;margin-bottom:.7rem;
  display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:var(--kl-clamp,3);overflow:hidden;}
.kl-card-desc p{margin:0 0 .5rem;}
.kl-card-desc.kl-expanded{display:block;-webkit-line-clamp:unset;overflow:visible;}
.kl-card-toggle{margin-top:auto;background:none;border:0;color:#8ee0d6;font-weight:800;font-size:.82rem;
  padding:0;cursor:pointer;text-decoration:underline;text-decoration-style:dotted;width:fit-content;align-self:flex-start;}
.kl-card-toggle:hover{color:#fff;}
.kl-card-toggle:focus-visible,.kl-card-foot:focus-visible{outline:3px solid var(--kl-amber);outline-offset:3px;}
.kl-card-foot{margin-top:auto;padding:.85rem 1rem;border-top:1px solid rgba(255,255,255,.12);
  display:flex;align-items:center;justify-content:space-between;gap:.5rem;
  font-size:.82rem;font-weight:800;color:#8ee0d6;text-decoration:none;}
.kl-card-foot:hover{color:#fff;text-decoration:none;background:rgba(30,138,116,.1);}
@media(prefers-reduced-motion:reduce){.kl-course-card{animation:none;}.kl-card-glass{transition:none;}}

/* Debug notice shown to admins when course-wall queries fail. */
.kl-debug{background:rgba(255,80,80,.1);border:1px solid rgba(255,80,80,.45);color:#ffb3b3;
  border-radius:10px;padding:.75rem 1rem;margin:1rem 0;font-size:.86rem;line-height:1.5;}
.kl-debug strong{color:#ff6b6b;display:block;margin-bottom:.25rem;}
[data-bs-theme="dark"] .kreck-landing .kl-debug{background:rgba(120,40,40,.2);border-color:rgba(255,80,80,.35);color:#ffb3b3;}
[data-bs-theme="dark"] .kreck-landing .kl-debug strong{color:#ff8a8a;}

/* ===== KRECK Assistant Wall (embedded hero visual) ===== */
.kl-aw-embed{position:relative;min-height:300px;height:100%;border-radius:22px;overflow:hidden;
  background:#0f1e2e;box-shadow:inset 0 1px 0 rgba(255,255,255,.06), 0 18px 46px rgba(2,10,20,.3);}
@media(min-width:861px){.kl-aw-embed{min-height:360px;}}
.kl-aw-embed .aw-root{position:absolute;inset:0;width:100%;height:100%;min-height:0;border-radius:22px;}
.aw-root {
      position: relative;
      overflow: hidden;
      width: 100%;
      height: 100%;
      min-height: 420px;
      background: #0d062d;
      isolation: isolate;
      user-select: none;
      -webkit-font-smoothing: antialiased;
    }

    /* Scaled 864px Reference Stage */
    .aw-stage {
      position: absolute;
      left: 0;
      top: 0;
      transform-origin: 0 0;
      will-change: transform;
    }

    /* Ambient Backdrop Layer (Bloom + Haze) */
    .aw-backdrop {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }

    /* Cards Container */
    .aw-cards-layer {
      position: absolute;
      inset: 0;
    }

    /* Individual Frosted Glass Card */
    .aw-card {
      position: absolute;
      left: 0;
      top: 0;
      width: 379px;
      height: 433px;
      will-change: transform;
      cursor: pointer;
      outline: none;
      transform-origin: 50% 50%;
      --aw-hover: 0;
    }

    .aw-card:focus-visible .aw-card-glass {
      box-shadow: 0 0 0 2px rgba(241, 235, 246, 0.75), 0 22px 46px -18px rgba(8, 0, 28, 0.6);
    }

    .aw-card-inner {
      position: absolute;
      inset: 0;
      will-change: filter;
    }

    /* Stage-Space Frosted Glass Body */
    .aw-card-glass {
      position: absolute;
      inset: 0;
      border-radius: 22px;
      background-repeat: no-repeat;
      box-shadow: 0 22px 46px -18px rgba(8, 0, 28, calc(0.35 + var(--aw-hover, 0) * 0.25));
      transition: box-shadow 0.2s ease;
    }

    /* Surface Sheen & Warm Mauve Accent */
    .aw-card-sheen {
      position: absolute;
      inset: 0;
      border-radius: 22px;
      pointer-events: none;
      background:
        radial-gradient(ellipse 200px 110px at 92px 405px, rgba(240, 196, 232, 0.04), rgba(240, 196, 232, 0) 100%),
        linear-gradient(
          140deg,
          rgba(255, 228, 250, 0) 46%,
          rgba(255, 228, 250, 0.03) 55%,
          rgba(255, 228, 250, 0) 63%,
          rgba(255, 228, 250, 0) 71%,
          rgba(255, 228, 250, 0.025) 79%,
          rgba(255, 228, 250, 0) 87%
        );
    }

    /* Edge Rim Light SVG */
    .aw-card-rim {
      position: absolute;
      inset: 0;
      width: 379px;
      height: 433px;
      overflow: visible;
      pointer-events: none;
    }

    /* Glossy 3D Avatar Sphere */
    .aw-avatar {
      position: absolute;
      left: 23.5px;
      top: 24.5px;
      width: 80px;
      height: 80px;
      border-radius: 50%;
      box-shadow:
        inset -2px 3px 5px rgba(90, 210, 185, 0.34),
        inset 2px 2px 3px rgba(255, 220, 255, 0.12),
        0 5px 9px rgba(12, 0, 36, 0.38);
    }

    .aw-avatar-ring {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      padding: 1px;
      background: conic-gradient(
        from 0deg,
        rgba(255, 236, 255, 0.3) 0deg,
        rgba(255, 236, 255, 0.75) 32deg,
        rgba(255, 236, 255, 0.6) 52deg,
        rgba(255, 236, 255, 0) 90deg,
        rgba(255, 236, 255, 0) 250deg,
        rgba(255, 236, 255, 0.22) 290deg,
        rgba(255, 236, 255, 0.55) 320deg,
        rgba(255, 236, 255, 0.3) 360deg
      );
      -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
      mask: linear-gradient(#000 0 0) content-box exclude, linear-gradient(#000 0 0);
    }

    .aw-avatar-badge {
      position: absolute;
      left: 0;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      text-align: center;
      font-family: "SF Pro Rounded", ui-rounded, "Arial Rounded MT Bold", "Varela Round", -apple-system, sans-serif;
      line-height: 1;
      letter-spacing: 0.25px;
      font-weight: 300;
      color: rgba(150, 230, 214, 0.95);
      white-space: nowrap;
      pointer-events: none;
    }

    /* Card Message Text */
    .aw-message {
      position: absolute;
      left: 26.5px;
      right: 22px;
      top: 139px;
      font-size: 25.6px;
      line-height: 28.6px;
      font-weight: 350;
      letter-spacing: -0.05px;
      white-space: pre-line;
      pointer-events: none;
    }

    /* Hairline Divider & Motion Blur Filter SVG */
    .aw-hairline-svg {
      position: absolute;
      inset: 0;
      width: 379px;
      height: 433px;
      overflow: visible;
      pointer-events: none;
    }

    /* Input Placeholder Text */
    .aw-placeholder {
      position: absolute;
      left: 26.5px;
      top: 347.5px;
      font-size: 14.6px;
      line-height: 17px;
      letter-spacing: -0.1px;
      white-space: nowrap;
      pointer-events: none;
    }

    /* Round Microphone Button */
    .aw-mic-btn {
      position: absolute;
      left: 299.5px;
      top: 311.5px;
      width: 43px;
      height: 43px;
      border-radius: 50%;
      box-shadow:
        0 0 0 1.6px rgba(20, 86, 75, 0.95),
        0 0 5px 1.5px rgba(47, 208, 181, 0.40);
      pointer-events: none;
    }

    .aw-mic-ring {
      position: absolute;
      inset: -1.5px;
      border-radius: 50%;
      padding: 1.1px;
      background: conic-gradient(
        from 0deg,
        rgba(255, 240, 255, 0.85) 0deg,
        rgba(255, 240, 255, 0) 14deg,
        rgba(255, 240, 255, 0) 60deg,
        rgba(255, 240, 255, 0.9) 82deg,
        rgba(255, 240, 255, 0.95) 92deg,
        rgba(255, 240, 255, 0.35) 108deg,
        rgba(255, 240, 255, 0) 122deg,
        rgba(255, 240, 255, 0) 232deg,
        rgba(255, 240, 255, 0.8) 252deg,
        rgba(255, 240, 255, 0.95) 268deg,
        rgba(255, 240, 255, 0.9) 280deg,
        rgba(255, 240, 255, 0.3) 298deg,
        rgba(255, 240, 255, 0) 312deg,
        rgba(255, 240, 255, 0) 346deg,
        rgba(255, 240, 255, 0.85) 360deg
      );
      -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
      mask: linear-gradient(#000 0 0) content-box exclude, linear-gradient(#000 0 0);
    }

    /* Subtle Film Noise Overlay */
    .aw-noise {
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-size: 128px 128px;
      opacity: 0.022;
      mix-blend-mode: overlay;
      z-index: 10;
    }

    

</style>

<div class="kreck-landing">

  <!-- HERO -->
  <section class="kl-hero">
    <div class="kl-aw-embed">
      <div id="assistantWall" class="aw-root" role="img" aria-label="KRECK assistant preview">
        <div class="aw-stage" id="awStage">
          <div class="aw-backdrop" id="awBackdrop"></div>
          <div class="aw-cards-layer" id="awCardsLayer"></div>
        </div>
        <div class="aw-noise" id="awNoise" aria-hidden="true"></div>
      </div>
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
        <a class="kl-btn kl-btn--ghost" href="#courses"><?php echo get_string('landing_browse', 'local_kreck'); ?></a>
      </div>
    </div>
  </section>

  <?php if ($landingcoursedbgerror !== ''): ?>
  <div class="kl-debug">
    <strong>KRECK course wall debug:</strong>
    <?php echo s($landingcoursedbgerror); ?>
  </div>
  <?php endif; ?>

  <?php if ($landingcourses === false || (bool) $landingcourses) { ?>
  <section class="kl-course-wall" id="courses" aria-labelledby="kl-wall-title">
    <div class="kl-wall-content">
      <header class="kl-wall-header">
        <div>
          <p class="kl-wall-eyebrow"><?php echo get_string('landing_wall_eyebrow', 'local_kreck'); ?></p>
          <h2 id="kl-wall-title"><?php echo get_string('landing_featured_title', 'local_kreck'); ?></h2>
          <p><?php echo get_string('landing_featured_sub', 'local_kreck'); ?></p>
        </div>
        <div class="kl-search-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input id="kl-search" type="search" aria-label="<?php echo s(get_string('landing_searchph', 'local_kreck')); ?>"
            placeholder="<?php echo s(get_string('landing_searchph', 'local_kreck')); ?>" autocomplete="off">
        </div>
      </header>

      <div class="kl-wall-stats" aria-label="<?php echo s(get_string('landing_stat_summary', 'local_kreck')); ?>">
        <div class="kl-wall-stat"><span class="v"><?php echo (int) $coursecount; ?></span><span class="l"><?php echo get_string('landing_stat_courses', 'local_kreck'); ?></span></div>
        <div class="kl-wall-stat"><span class="v"><?php echo (int) $catcount; ?></span><span class="l"><?php echo get_string('landing_stat_categories', 'local_kreck'); ?></span></div>
        <div class="kl-wall-stat"><span class="v"><?php echo (int) $activitycount; ?></span><span class="l"><?php echo get_string('landing_stat_activities', 'local_kreck'); ?></span></div>
        <div class="kl-wall-stat"><span class="v"><?php echo (int) $learnercount; ?></span><span class="l"><?php echo get_string('landing_stat_learners', 'local_kreck'); ?></span></div>
      </div>

      <?php if (empty($courses)) { ?>
        <p class="kl-wall-empty"><?php echo get_string('landing_nocourses', 'local_kreck'); ?></p>
      <?php } else { ?>
        <div class="kl-courses">
          <?php $accentcycle = ['#1e8a74', '#2f6da3', '#f2a43c', '#0f2233']; $k = 0; ?>
          <?php foreach ($courses as $course) { $accent = $accentcycle[$k++ % count($accentcycle)]; ?>
            <article class="kl-course-card" data-search="<?php echo s($course->searchtext); ?>"
              aria-labelledby="kl-course-title-<?php echo (int) $course->id; ?>"
              style="--kl-card-accent:<?php echo s($accent); ?>">
              <div class="kl-card-glass">
                <div class="kl-card-avatar" aria-hidden="true"><span class="kl-card-badge"><?php echo s($course->monogram); ?></span></div>
                <div class="kl-card-content">
                  <span class="kl-card-kicker"><?php echo s(get_string('landing_card_course', 'local_kreck', $course->shortname)); ?></span>
                  <h3 class="kl-card-title" id="kl-course-title-<?php echo (int) $course->id; ?>"><?php echo $course->fullname; ?></h3>
                  <span class="kl-card-cat"><?php echo $course->categoryname; ?></span>
                  <div class="kl-card-stats">
                    <span class="kl-card-stat"><span class="kl-card-stat-value"><?php echo (int) $course->stats->activities; ?></span><span class="kl-card-stat-label"><?php echo get_string('landing_card_activities', 'local_kreck'); ?></span></span>
                    <span class="kl-card-stat"><span class="kl-card-stat-value"><?php echo (int) $course->stats->lessons; ?></span><span class="kl-card-stat-label"><?php echo get_string('landing_card_lessons', 'local_kreck'); ?></span></span>
                    <span class="kl-card-stat"><span class="kl-card-stat-value"><?php echo (int) $course->stats->discussions; ?></span><span class="kl-card-stat-label"><?php echo get_string('landing_card_discussions', 'local_kreck'); ?></span></span>
                  </div>
                  <div class="kl-card-desc" id="kl-course-desc-<?php echo (int) $course->id; ?>" style="--kl-clamp:3"><?php echo $course->summary; ?></div>
                  <?php if ($course->hasdescription) { ?>
                    <button class="kl-card-toggle" type="button" aria-controls="kl-course-desc-<?php echo (int) $course->id; ?>" aria-expanded="false"
                      data-more="<?php echo s(get_string('landing_card_readmore', 'local_kreck')); ?>"
                      data-less="<?php echo s(get_string('landing_card_readless', 'local_kreck')); ?>">
                      <?php echo get_string('landing_card_readmore', 'local_kreck'); ?>
                    </button>
                  <?php } ?>
                </div>
                <a class="kl-card-foot" href="<?php echo $course->url; ?>">
                  <span><?php echo get_string('landing_card_viewcourse', 'local_kreck'); ?></span><span aria-hidden="true">&rarr;</span>
                </a>
              </div>
            </article>
          <?php } ?>
        </div>
        <p id="kl-no-results" class="kl-wall-empty" aria-live="polite" hidden><?php echo get_string('landing_noresults', 'local_kreck'); ?></p>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

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
