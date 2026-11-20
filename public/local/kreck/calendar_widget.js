// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later
// version. Distributed WITHOUT ANY WARRANTY. See <http://www.gnu.org/licenses/>.

/**
 * KRECK calendar widget.
 *
 * A self-contained month / week / day / agenda calendar that renders the host
 * LMS's events (lessons) in the KRECK style and navigates ENTIRELY IN PLACE via
 * Moodle's own AJAX calendar web services. It replaces the embedded core-
 * calendar snapshot whose prev/next/view links navigated away from the KRECK
 * page (so the whole list vanished until a reload). Nothing here leaves the page.
 *
 * @package   local_kreck
 */
(function () {
    'use strict';

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December'];
    var DOW = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    function el(tag, cls, attrs) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (attrs) { Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); }); }
        return n;
    }
    function typeClass(ev) {
        var t = (ev && (ev.normalisedeventtype || ev.eventtype)) || 'event';
        return 'klc-t-' + String(t).replace(/[^a-z]/gi, '').toLowerCase();
    }
    function startOfWeek(d) { // Monday-based.
        var x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        var day = (x.getDay() + 6) % 7;
        x.setDate(x.getDate() - day);
        return x;
    }

    function KreckCalendar(root, Ajax) {
        this.root = root;
        this.Ajax = Ajax;
        var cfg = {};
        try { cfg = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }
        this.courseid = parseInt(cfg.courseid, 10) || 1;
        this.view = (cfg.view === 'week' || cfg.view === 'day' || cfg.view === 'agenda') ? cfg.view : 'month';
        var now = new Date();
        this.cursor = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        this.build();
        this.load();
    }

    KreckCalendar.prototype.callWS = function (methodname, args) {
        return this.Ajax.call([{ methodname: methodname, args: args }])[0];
    };

    KreckCalendar.prototype.build = function () {
        var self = this;
        this.root.innerHTML = '';
        this.root.classList.add('klc');

        var bar = el('div', 'klc-bar');
        var nav = el('div', 'klc-nav');
        this.prevBtn = el('button', 'klc-ico', { type: 'button', 'aria-label': 'Previous' });
        this.prevBtn.innerHTML = '&#8249;';
        this.titleEl = el('strong', 'klc-title');
        this.nextBtn = el('button', 'klc-ico', { type: 'button', 'aria-label': 'Next' });
        this.nextBtn.innerHTML = '&#8250;';
        this.todayBtn = el('button', 'klc-today', { type: 'button' });
        this.todayBtn.textContent = 'Today';
        nav.appendChild(this.prevBtn);
        nav.appendChild(this.titleEl);
        nav.appendChild(this.nextBtn);
        nav.appendChild(this.todayBtn);

        var views = el('div', 'klc-views', { role: 'tablist' });
        this.viewBtns = {};
        ['month', 'week', 'day', 'agenda'].forEach(function (v) {
            var b = el('button', 'klc-view', { type: 'button', 'data-view': v, role: 'tab' });
            b.textContent = v.charAt(0).toUpperCase() + v.slice(1);
            b.addEventListener('click', function () { self.setView(v); });
            views.appendChild(b);
            self.viewBtns[v] = b;
        });

        bar.appendChild(nav);
        bar.appendChild(views);
        this.body = el('div', 'klc-body');
        this.root.appendChild(bar);
        this.root.appendChild(this.body);

        this.prevBtn.addEventListener('click', function () { self.go(-1); });
        this.nextBtn.addEventListener('click', function () { self.go(1); });
        this.todayBtn.addEventListener('click', function () {
            var n = new Date(); self.cursor = new Date(n.getFullYear(), n.getMonth(), n.getDate()); self.load();
        });
    };

    KreckCalendar.prototype.setView = function (v) {
        if (this.view === v) { return; }
        this.view = v;
        this.load();
    };

    KreckCalendar.prototype.go = function (delta) {
        var c = this.cursor;
        if (this.view === 'month') { this.cursor = new Date(c.getFullYear(), c.getMonth() + delta, 1); }
        else if (this.view === 'week') { this.cursor = new Date(c.getFullYear(), c.getMonth(), c.getDate() + 7 * delta); }
        else { this.cursor = new Date(c.getFullYear(), c.getMonth(), c.getDate() + delta); } // day/agenda
        this.load();
    };

    KreckCalendar.prototype.load = function () {
        var self = this;
        Object.keys(this.viewBtns).forEach(function (v) {
            self.viewBtns[v].classList.toggle('is-active', v === self.view);
        });
        this.prevBtn.style.visibility = (this.view === 'agenda') ? 'hidden' : '';
        this.nextBtn.style.visibility = (this.view === 'agenda') ? 'hidden' : '';
        this.body.setAttribute('aria-busy', 'true');
        this.body.innerHTML = '<div class="klc-loading">Loading…</div>';

        if (this.view === 'month') { return this.loadMonth(); }
        if (this.view === 'day') { return this.loadDay(this.cursor); }
        if (this.view === 'week') { return this.loadWeek(); }
        return this.loadAgenda();
    };

    KreckCalendar.prototype.fail = function () {
        this.body.innerHTML = '<div class="klc-empty">The calendar could not be loaded. '
            + '<a href="/calendar/view.php?view=month">Open the full calendar</a>.</div>';
        this.body.removeAttribute('aria-busy');
    };

    KreckCalendar.prototype.loadMonth = function () {
        var self = this;
        this.titleEl.textContent = MONTHS[this.cursor.getMonth()] + ' ' + this.cursor.getFullYear();
        this.callWS('core_calendar_get_calendar_monthly_view', {
            year: this.cursor.getFullYear(),
            month: this.cursor.getMonth() + 1,
            courseid: this.courseid,
            includenavigation: true,
            mini: false
        }).then(function (data) { self.renderMonth(data); self.body.removeAttribute('aria-busy'); })
            .catch(function () { self.fail(); });
    };

    KreckCalendar.prototype.renderMonth = function (data) {
        var self = this;
        var table = el('table', 'klc-month');
        var thead = el('thead');
        var htr = el('tr');
        (data.daynames && data.daynames.length ? data.daynames.map(function (d) { return d.shortname; }) : DOW)
            .forEach(function (nm) { var th = el('th'); th.textContent = nm; htr.appendChild(th); });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        var total = 0;
        (data.weeks || []).forEach(function (w) {
            var tr = el('tr');
            var pre = Array.isArray(w.prepadding) ? w.prepadding.length : (parseInt(w.prepadding, 10) || 0);
            var post = Array.isArray(w.postpadding) ? w.postpadding.length : (parseInt(w.postpadding, 10) || 0);
            for (var i = 0; i < pre; i++) { tr.appendChild(el('td', 'klc-pad')); }
            (w.days || []).forEach(function (day) {
                var ts = (day.timestamp || 0) * 1000;
                var dte = new Date(ts);
                var td = el('td', 'klc-day' + (day.istoday ? ' is-today' : '') + (day.isweekend ? ' is-weekend' : ''));
                var num = el('button', 'klc-daynum', { type: 'button' });
                num.textContent = dte.getDate() || '';
                num.addEventListener('click', function () {
                    self.cursor = new Date(dte.getFullYear(), dte.getMonth(), dte.getDate());
                    self.setView('day');
                });
                td.appendChild(num);
                var evs = day.events || [];
                total += evs.length;
                if (evs.length) {
                    var chips = el('ul', 'klc-chips');
                    evs.slice(0, 3).forEach(function (ev) {
                        var li = el('li', 'klc-chip ' + typeClass(ev));
                        var a = el('a', null, { href: ev.viewurl || '#' });
                        a.textContent = ev.name || '';
                        a.title = ev.name || '';
                        li.appendChild(a);
                        chips.appendChild(li);
                    });
                    if (evs.length > 3) {
                        var more = el('li', 'klc-more');
                        more.textContent = '+' + (evs.length - 3) + ' more';
                        more.addEventListener('click', function () {
                            self.cursor = new Date(dte.getFullYear(), dte.getMonth(), dte.getDate());
                            self.setView('day');
                        });
                        chips.appendChild(more);
                    }
                    td.appendChild(chips);
                }
                tr.appendChild(td);
            });
            for (var j = 0; j < post; j++) { tr.appendChild(el('td', 'klc-pad')); }
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        this.body.innerHTML = '';
        this.body.appendChild(table);
        if (!total) { this.body.appendChild(this.emptyNote('No lessons or events this month.')); }
    };

    KreckCalendar.prototype.loadDay = function (date) {
        var self = this;
        this.titleEl.textContent = DOW[(date.getDay() + 6) % 7] + ', ' + date.getDate() + ' '
            + MONTHS[date.getMonth()] + ' ' + date.getFullYear();
        this.callWS('core_calendar_get_calendar_day_view', {
            year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate(), courseid: this.courseid
        }).then(function (data) {
            self.renderList((data && data.events) || [], 'No lessons or events on this day.');
            self.body.removeAttribute('aria-busy');
        }).catch(function () { self.fail(); });
    };

    KreckCalendar.prototype.loadWeek = function () {
        var self = this;
        var start = startOfWeek(this.cursor);
        var end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
        this.titleEl.textContent = start.getDate() + ' ' + MONTHS[start.getMonth()]
            + ' – ' + end.getDate() + ' ' + MONTHS[end.getMonth()] + ' ' + end.getFullYear();
        var reqs = [];
        for (var i = 0; i < 7; i++) {
            var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            reqs.push({ methodname: 'core_calendar_get_calendar_day_view',
                args: { year: d.getFullYear(), month: d.getMonth() + 1, day: d.getDate(), courseid: this.courseid } });
        }
        Promise.all(this.Ajax.call(reqs)).then(function (results) {
            self.body.innerHTML = '';
            var any = 0;
            results.forEach(function (res, idx) {
                var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + idx);
                var evs = (res && res.events) || [];
                any += evs.length;
                var h = el('div', 'klc-weekday');
                h.textContent = DOW[idx] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
                self.body.appendChild(h);
                if (evs.length) { self.body.appendChild(self.listOf(evs)); }
                else { self.body.appendChild(self.emptyNote('—', true)); }
            });
            if (!any) { self.body.innerHTML = ''; self.body.appendChild(self.emptyNote('No lessons or events this week.')); }
            self.body.removeAttribute('aria-busy');
        }).catch(function () { self.fail(); });
    };

    KreckCalendar.prototype.loadAgenda = function () {
        var self = this;
        this.titleEl.textContent = 'Upcoming';
        this.callWS('core_calendar_get_calendar_upcoming_view', { courseid: this.courseid })
            .then(function (data) {
                self.renderList((data && data.events) || [], 'Nothing coming up.');
                self.body.removeAttribute('aria-busy');
            }).catch(function () { self.fail(); });
    };

    KreckCalendar.prototype.renderList = function (events, emptyMsg) {
        this.body.innerHTML = '';
        if (!events.length) { this.body.appendChild(this.emptyNote(emptyMsg)); return; }
        this.body.appendChild(this.listOf(events));
    };

    KreckCalendar.prototype.listOf = function (events) {
        var ul = el('ul', 'klc-list');
        events.forEach(function (ev) {
            var li = el('li', 'klc-item ' + typeClass(ev));
            var time = el('span', 'klc-time');
            time.innerHTML = ev.formattedtime || ''; // Moodle-generated markup.
            var a = el('a', 'klc-name', { href: ev.viewurl || '#' });
            a.textContent = ev.name || '';
            li.appendChild(time);
            li.appendChild(a);
            if (ev.course && ev.course.fullname) {
                var c = el('span', 'klc-course');
                c.textContent = ev.course.fullname;
                li.appendChild(c);
            }
            ul.appendChild(li);
        });
        return ul;
    };

    KreckCalendar.prototype.emptyNote = function (msg, subtle) {
        var d = el('div', subtle ? 'klc-empty klc-empty--subtle' : 'klc-empty');
        d.textContent = msg;
        return d;
    };

    function injectStyle() {
        if (document.getElementById('klc-style')) { return; }
        var css = ''
            + '.klc{color:#edf1e6;}'
            + '.klc-bar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between;margin-bottom:.9rem;}'
            + '.klc-nav{display:flex;align-items:center;gap:.4rem;}'
            + '.klc-title{font-family:Georgia,"Times New Roman",Times,serif;font-size:1.1rem;color:#fff;min-width:9.5rem;text-align:center;}'
            + '.klc-ico,.klc-today,.klc-view{cursor:pointer;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.06);'
            + 'color:#edf1e6;border-radius:999px;font:inherit;line-height:1;padding:.4rem .7rem;transition:background .12s,color .12s,border-color .12s;}'
            + '.klc-ico{width:2rem;height:2rem;font-size:1.1rem;padding:0;}'
            + '.klc-today{font-size:.8rem;font-weight:700;}'
            + '.klc-ico:hover,.klc-today:hover,.klc-view:hover{background:rgba(196,220,82,.2);border-color:#c4dc52;color:#fff;}'
            + '.klc-views{display:flex;gap:.3rem;flex-wrap:wrap;}'
            + '.klc-view{font-size:.8rem;font-weight:700;}'
            + '.klc-view.is-active{background:#52701f;border-color:#52701f;color:#fff;}'
            + '.klc-loading{padding:1.5rem;text-align:center;color:#c7d1da;}'
            + '.klc-month{width:100%;border-collapse:separate;border-spacing:3px;table-layout:fixed;}'
            + '.klc-month th{font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:#aeb9c4;font-weight:700;padding:.2rem 0;text-align:center;}'
            + '.klc-month td{vertical-align:top;height:4.2rem;border-radius:10px;background:rgba(255,255,255,.05);'
            + 'border:1px solid rgba(255,255,255,.08);padding:.2rem;overflow:hidden;}'
            + '.klc-month td.klc-pad{background:transparent;border-color:transparent;}'
            + '.klc-month td.is-weekend{background:rgba(255,255,255,.03);}'
            + '.klc-month td.is-today{border-color:#e6b765;box-shadow:inset 0 0 0 1px #e6b765;}'
            + '.klc-daynum{display:inline-flex;align-items:center;justify-content:center;min-width:1.5rem;height:1.5rem;'
            + 'border:0;background:transparent;color:#edf1e6;font:inherit;font-weight:700;font-size:.8rem;cursor:pointer;border-radius:50%;}'
            + '.klc-day.is-today .klc-daynum{background:#e6b765;color:#10150d;}'
            + '.klc-daynum:hover{background:rgba(196,220,82,.3);color:#fff;}'
            + '.klc-chips{list-style:none;margin:.15rem 0 0;padding:0;display:flex;flex-direction:column;gap:2px;}'
            + '.klc-chip{font-size:.68rem;line-height:1.2;border-radius:5px;padding:1px 5px;background:rgba(82,112,31,.55);'
            + 'overflow:hidden;white-space:nowrap;text-overflow:ellipsis;border-left:3px solid #c4dc52;}'
            + '.klc-chip a{color:#f1f5e6;text-decoration:none;}'
            + '.klc-chip.klc-t-course{border-left-color:#c4dc52;background:rgba(82,112,31,.55);}'
            + '.klc-chip.klc-t-activity{border-left-color:#e6b765;background:rgba(230,183,101,.22);}'
            + '.klc-chip.klc-t-user{border-left-color:#2f6da3;background:rgba(47,109,163,.3);}'
            + '.klc-chip.klc-t-site,.klc-chip.klc-t-category{border-left-color:#9aa7b2;background:rgba(154,167,178,.25);}'
            + '.klc-more{font-size:.64rem;color:#cfe06a;cursor:pointer;padding:1px 5px;}'
            + '.klc-more:hover{color:#fff;text-decoration:underline;}'
            + '.klc-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem;}'
            + '.klc-item{display:grid;grid-template-columns:auto 1fr;gap:.2rem .7rem;align-items:baseline;'
            + 'padding:.6rem .75rem;border:1px solid rgba(255,255,255,.12);border-left-width:4px;border-radius:12px;'
            + 'background:rgba(255,255,255,.06);}'
            + '.klc-item.klc-t-course{border-left-color:#c4dc52;}'
            + '.klc-item.klc-t-activity{border-left-color:#e6b765;}'
            + '.klc-item.klc-t-user{border-left-color:#2f6da3;}'
            + '.klc-item.klc-t-site,.klc-item.klc-t-category{border-left-color:#9aa7b2;}'
            + '.klc-time{font-size:.74rem;white-space:nowrap;}'
            + '.klc-time,.klc-time *{color:#d6dee6 !important;opacity:1 !important;}'
            + '.klc-name{grid-column:2;color:#fff;font-weight:700;text-decoration:none;}'
            + '.klc-name:hover{color:#cfe06a;text-decoration:underline;}'
            + '.klc-course{grid-column:2;font-size:.7rem;color:#aeb9c4;}'
            + '.klc-weekday{font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:#e6b765;font-weight:700;margin:.8rem 0 .3rem;}'
            + '.klc-weekday:first-child{margin-top:0;}'
            + '.klc-empty{padding:1.3rem;text-align:center;color:#c7d1da;border:1px dashed rgba(255,255,255,.18);border-radius:12px;}'
            + '.klc-empty a{color:#cfe06a;}'
            + '.klc-empty--subtle{padding:.3rem .5rem;border:0;text-align:left;color:#8795a1;}';
        var s = document.createElement('style');
        s.id = 'klc-style';
        s.textContent = css;
        document.head.appendChild(s);
    }

    function boot() {
        var mounts = document.querySelectorAll('[data-kreck-calendar]');
        if (!mounts.length || typeof require === 'undefined') { return; }
        injectStyle();
        require(['core/ajax'], function (Ajax) {
            Array.prototype.forEach.call(mounts, function (m) {
                if (m.getAttribute('data-klc-ready')) { return; }
                m.setAttribute('data-klc-ready', '1');
                try { new KreckCalendar(m, Ajax); } catch (e) { /* leave fallback content */ }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
