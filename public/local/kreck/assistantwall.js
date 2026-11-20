(function () {
  "use strict";
  var el = document.getElementById("assistantWall");
  if (!el) { return; }
  var USER = window.KRECK_AW || {};
  const CONFIG = Object.assign({
    messages: [
      "Welcome to KRECK.\nLet's pick up\nwhere you left off.",
      "Your streak is waiting —\na few minutes is\nall it takes.",
      "New lessons just landed\nin your pathway.",
      "One student, one record.\nEverything in one place.",
      "Ready for today's\ncheckpoint quiz?",
      "Short lessons, big progress.\nLet's go."
    ],
    placeholder: "Ask KRECK anything...",
    interval: 9, speed: 1,
    background: "#10150d", glow: "#52701f", haze: "#2f4214",
    glass: "#2a4a5e", accent: "#10150d", text: "#edf1e6",
    font: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    badge: "kreck",
    onSelect: function () {}
  }, USER);
      const STAGE_H = 864;
      const CW = 379;          // Card width
      const CH = 433;          // Card height
      const RAD = 22;          // Card corner radius
      const COLP = 454;        // Column pitch
      const ROWP = 515;        // Row pitch
      const ODD = -ROWP / 2;   // Vertical offset of odd columns (-257.5)
      const BADGE_PX = 13;     // Default badge font size
      const BADGE_MAX_W = 58;  // Max width for badge wordmark
      const GPAD = 600;        // Stage-space glass field padding
      const CY = 1;            // Card centre sits 1px below stage centre

      // -----------------------------------------------------------------------
      // Measured Hand-off Motion Curves (30 fps reference frames)
      // -----------------------------------------------------------------------
      const A_T = [
        0, 0.1, 0.2, 0.2, 0.3, 0.5, 0.8, 1, 1.2, 1.5, 1.8, 2, 2.3, 2.7, 3.1, 3.5,
        3.9, 4.4, 4.9, 5.4, 5.9, 6.6, 7.3, 8.1, 8.9, 9.9, 10.9, 11.9, 12.8, 13.9,
        15, 16.4, 17.7, 19.4, 21, 22.4, 23.8, 24.6, 25.5, 25.8, 26
      ];

      const F_T = [
        0, -3.8, -8.7, -16.1, -27, -41, -59, -80.6, -106.2, -134.4, -163.8, -193.8,
        -223, -251.8, -278.2, -302.3, -324, -343.9, -362.5, -379.5, -395, -409.4,
        -422.5, -434.3, -444.4, -453.2, -460.8, -467.5, -473.2, -478, -482, -485.2,
        -487.8, -489.7, -490.8, -491.3
      ];

      const S_T = [
        0, -0.3, -0.5, -0.8, -1, -1.4, -1.8, -2.3, -2.8, -3.4, -3.9, -4.6, -5.2,
        -5.9, -6.6, -7.4, -8.1, -8.9, -9.7, -10.5, -11.3, -12.1, -12.9, -13.7,
        -14.5, -15.3, -16.1, -16.8, -17.5, -18.3, -19.1, -19.8, -20.5, -21.3,
        -22, -22.6, -23.3, -24, -24.6, -25.3, -25.9, -26.5, -27.1, -27.7, -28.3,
        -28.8, -29.4, -29.9, -30.5, -30.9, -31.5, -31.9, -32.4, -32.8, -33.3,
        -33.6, -34, -34.4, -34.7, -35.1, -35.4, -35.7, -36.1, -36.4, -36.7, -37,
        -37.3, -37.6, -37.9, -38.1, -38.4, -38.7, -39, -39.2, -39.5, -39.7, -40,
        -40.2, -40.4, -40.7, -40.9, -41.1, -41.3, -41.5, -41.8, -42, -42.2, -42.4,
        -42.5, -42.7, -42.9, -43.1, -43.3, -43.5, -43.6, -43.8, -44, -44.1, -44.3,
        -44.4, -44.6, -44.8, -44.9, -45, -45.2, -45.3, -45.5, -45.6, -45.7, -45.9,
        -46, -46.1, -46.2, -46.4, -46.5, -46.6, -46.7, -46.8, -46.9, -47, -47.1,
        -47.2, -47.3, -47.4, -47.5, -47.6, -47.7, -47.8, -47.9, -48, -48.1, -48.1,
        -48.2, -48.3, -48.4, -48.5, -48.5, -48.6, -48.7, -48.8, -48.8, -48.9, -49,
        -49, -49.1, -49.2, -49.2, -49.3, -49.3, -49.4, -49.4, -49.5, -49.5, -49.6,
        -49.7, -49.7, -49.8, -49.8, -49.8, -49.9, -49.9, -50, -50, -50.1, -50.1,
        -50.1, -50.2, -50.2, -50.3, -50.3, -50.3, -50.4, -50.4, -50.4, -50.5,
        -50.5, -50.5, -50.5, -50.6, -50.6, -50.6, -50.6, -50.7, -50.7
      ];

      const F_START = 25;
      const S_START = 60;
      const TOTAL = -516;
      const T_END = S_START + S_T.length - 1;
      const LAG = 12.8;
      const BLUR_K = 0.17;

      const H_OFF = [0, 0.03, 0.08, 0.16, 0.26, 0.36, 0.46, 0.56, 0.64, 0.72, 0.8, 0.88, 0.94, 0.97, 1];
      const V_OFF = [0, 0.1, 0.2, 0.3, 0.38, 0.46, 0.54, 0.62, 0.7, 0.78, 0.86, 0.93, 1];

      // -----------------------------------------------------------------------
      // Math & Interpolation Helpers
      // -----------------------------------------------------------------------
      function lookup(tab, x) {
        if (x <= 0) return tab[0];
        const n = tab.length - 1;
        if (x >= n) return tab[n];
        const i = Math.floor(x);
        const f = x - i;
        return tab[i] + (tab[i + 1] - tab[i]) * f;
      }

      function prog(t, d) {
        if (t <= 0) return 0;
        if (t >= T_END && t - F_START - d >= F_T.length) return 1;
        return (lookup(A_T, t) + lookup(F_T, t - F_START - d) + lookup(S_T, t - S_START)) / TOTAL;
      }

      const ss = (a, b, v) => {
        const t = Math.max(0, Math.min(1, (v - a) / (b - a)));
        return t * t * (3 - 2 * t);
      };

      const rimL = (y) => 0.08 + 0.92 * ss(-40, 25, y);
      const rimR = (y) => 0.1 + 0.85 * ss(-5, 30, y) * (1 - ss(115, 150, y));
      const rimT = (x, y) => {
        const d = x - 140;
        const bump = Math.exp(-((d / (d < 0 ? 45 : 18)) ** 2) / 2);
        return 0.13 + 0.8 * bump * ss(-60, -160, y) + 0.12 * ss(150, 300, y);
      };
      const rimB = (x, y) => 0.07 + 0.16 * ss(40, -170, x) * ss(80, 180, y);

      const isOdd = (c) => Math.abs(c) % 2 === 1;
      const cellX = (c) => c * COLP;
      const cellY = (c, r) => r * ROWP + (isOdd(c) ? ODD : 0);
      const mod = (a, n) => ((a % n) + n) % n;

      function pickMessage(n, c, r) {
        if (!n) return -1;
        const g = Math.ceil(n / 3);
        const centre = [0, g];
        const right = n > g ? [g, Math.min(n, 2 * g)] : centre;
        const left = n > 2 * g ? [2 * g, n] : right;
        const [a, b] = !isOdd(c) ? centre : c > 0 ? right : left;
        return a + mod(r + (isOdd(c) ? 0 : Math.trunc(c / 2)), b - a);
      }

      // -----------------------------------------------------------------------
      // Procedural Film Noise Generator
      // -----------------------------------------------------------------------
      function generateNoiseDataURL() {
        try {
          const cv = document.createElement("canvas");
          cv.width = cv.height = 128;
          const g = cv.getContext("2d");
          if (!g) return "";
          const im = g.createImageData(128, 128);
          for (let i = 0; i < im.data.length; i += 4) {
            const v = Math.random() * 255;
            im.data[i] = im.data[i + 1] = im.data[i + 2] = v;
            im.data[i + 3] = 255;
          }
          g.putImageData(im, 0, 0);
          return cv.toDataURL();
        } catch (e) {
          return "";
        }
      }

      // -----------------------------------------------------------------------
      // Measure Badge Wordmark Width
      // -----------------------------------------------------------------------
      function computeBadgeFontSize(badge, font) {
        if (!badge) return BADGE_PX;
        try {
          const g = document.createElement("canvas").getContext("2d");
          if (!g) return BADGE_PX;
          g.font = `300 ${BADGE_PX}px "SF Pro Rounded", ui-rounded, "Arial Rounded MT Bold", "Varela Round", ${font}`;
          const w = g.measureText(badge).width + badge.length * 0.25;
          return w > BADGE_MAX_W ? (BADGE_PX * BADGE_MAX_W) / w : BADGE_PX;
        } catch (e) {
          return BADGE_PX;
        }
      }

      // -----------------------------------------------------------------------
      // DOM & State Initialization
      // -----------------------------------------------------------------------
      const rootEl = document.getElementById("assistantWall");
      const stageEl = document.getElementById("awStage");
      const backdropEl = document.getElementById("awBackdrop");
      const cardsLayerEl = document.getElementById("awCardsLayer");
      const noiseEl = document.getElementById("awNoise");
      const autoBtn = document.getElementById("awAutoBtn");
      const nextBtn = document.getElementById("awNextBtn");

      const noiseUrl = generateNoiseDataURL();
      if (noiseUrl) noiseEl.style.backgroundImage = `url(${noiseUrl})`;

      const badgePx = computeBadgeFontSize(CONFIG.badge, CONFIG.font);
      const uid = "aw" + Math.random().toString(36).slice(2, 7);

      const reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
      let reducedMotion = reducedMotionQuery.matches;
      reducedMotionQuery.addEventListener("change", (e) => {
        reducedMotion = e.matches;
      });

      const state = {
        size: { w: rootEl.clientWidth || 1024, h: rootEl.clientHeight || 864 },
        clock: 0,
        lastTime: -1,
        moves: [],
        base: { x: 0, y: 0 },
        target: { x: 0, y: 0 },
        focus: { c: 0, r: 0 },
        autoEnabled: CONFIG.interval > 0,
        nextAuto: CONFIG.interval > 0 ? CONFIG.interval : Infinity,
        hoverKey: "",
        hoverAmt: new Map(),
        activeCards: new Map(), // key -> CardRecord
        glassFieldCSS: "",
        glassFieldSize: ""
      };

      // Color mixing helpers matching CSS color-mix(in srgb, ...)
      const tx = (p) => `color-mix(in srgb, ${CONFIG.text} ${p}%, transparent)`;
      const rimColor = `color-mix(in srgb, ${CONFIG.text} 80%, #f0d8ff)`;
      const lineColor = `color-mix(in srgb, ${CONFIG.text} 72%, #c9a2ff)`;
      const ac = (p, w) => `color-mix(in srgb, ${CONFIG.accent} ${p}%, ${w})`;

      // Prebuild static sphere background string
      const sphereBackground = [
        "radial-gradient(ellipse 13% 9% at 73% 25%, rgba(240, 255, 250, 0.95), rgba(220, 248, 240, 0.45) 50%, rgba(220, 248, 240, 0) 100%)",
        "radial-gradient(ellipse 24% 20% at 74% 31%, rgba(214, 255, 244, 0.42), rgba(214, 255, 244, 0.16) 55%, rgba(214, 255, 244, 0) 100%)",
        "radial-gradient(ellipse 40.6% 72.1% at 2% -20.8%, rgba(150, 240, 214, 1), rgba(150, 240, 214, 0.5) 55%, rgba(150, 240, 214, 0) 100%)",
        "radial-gradient(ellipse 26.3% 27.9% at 100.4% 42.7%, rgba(242, 164, 60, 1), rgba(242, 164, 60, 0.5) 55%, rgba(242, 164, 60, 0) 100%)",
        "radial-gradient(ellipse 28.2% 25.2% at 58.2% 38.4%, rgba(82, 112, 31, 1), rgba(82, 112, 31, 0.5) 55%, rgba(82, 112, 31, 0) 100%)",
        "radial-gradient(ellipse 37.3% 18.4% at 72.3% 30.9%, rgba(174, 231, 220, 1), rgba(174, 231, 220, 0.5) 55%, rgba(174, 231, 220, 0) 100%)",
        "radial-gradient(ellipse 67.2% 14.1% at 34.8% 18.4%, rgba(6, 20, 30, 1), rgba(6, 20, 30, 0.5) 55%, rgba(6, 20, 30, 0) 100%)",
        "radial-gradient(ellipse 23.3% 10% at 72.6% 20.1%, rgba(47, 208, 181, 1), rgba(47, 208, 181, 0.5) 55%, rgba(47, 208, 181, 0) 100%)",
        "radial-gradient(ellipse 46% 40% at 30% 78%, rgba(4, 14, 24, 0.7), rgba(4, 14, 24, 0) 100%)",
        `radial-gradient(circle 56px at 39.7% 104.4%, ${ac(35, "#060e18")} 0%, ${ac(55, "#060e18")} 40%, ${CONFIG.accent} 75%, ${ac(60, "#52701f")} 100%)`
      ].join(", ");

      // -----------------------------------------------------------------------
      // Stage Backdrop & Stage-Space Glass Field Gradients
      // -----------------------------------------------------------------------
      function updateStageGradients(wr) {
        // 1. Backdrop (Bloom + Linear Haze)
        const PHI = 197.7;
        const a = (PHI * Math.PI) / 180;
        const dirX = Math.sin(a);
        const Lref = Math.abs(720 * Math.sin(a)) + Math.abs(864 * Math.cos(a));
        const Lact = Math.abs(wr * Math.sin(a)) + Math.abs(864 * Math.cos(a));
        const shift = (wr - 360 - wr / 2) * dirX;
        const at = (t) => (Lact / 2 + (t - 0.5) * Lref + shift).toFixed(1) + "px";
        const hz = (p) => `color-mix(in srgb, ${CONFIG.haze} ${p}%, transparent)`;
        const gl = (p) => `color-mix(in srgb, ${CONFIG.glow} ${p}%, transparent)`;
        const bloomX = wr - 720 + 755.5;

        backdropEl.style.background = [
          `radial-gradient(circle 824px at ${bloomX.toFixed(1)}px 47.6px, ${gl(97.1)} 0px, ${gl(56.9)} 164.8px, ${gl(37)} 329.6px, ${gl(7.8)} 494.3px, ${gl(0.4)} 659px, ${gl(0)} 824px)`,
          `linear-gradient(${PHI}deg, ${hz(75.2)} ${at(0)}, ${hz(78)} ${at(0.15)}, ${hz(56.8)} ${at(0.3)}, ${hz(12.6)} ${at(0.45)}, ${hz(5.9)} ${at(0.6)}, ${hz(4.4)} ${at(0.8)}, ${hz(0)} ${at(1)})`,
          CONFIG.background
        ].join(", ");

        // 2. Stage-Space Glass Field (Shared across all cards, offset per card)
        const GPHI = 227.4;
        const ga = (GPHI * Math.PI) / 180;
        const gdx = Math.sin(ga);
        const gL = Math.abs(720 * Math.sin(ga)) + Math.abs(864 * Math.cos(ga));
        const gLp = Math.abs((wr + 2 * GPAD) * Math.sin(ga)) + Math.abs((STAGE_H + 2 * GPAD) * Math.cos(ga));
        const gat = (t) => ((t - 0.5) * gL + (wr / 2 - 360) * gdx + gLp / 2).toFixed(1) + "px";

        const gBase = `color-mix(in srgb, ${CONFIG.glass} 40%, ${CONFIG.background})`;
        const gBand = `color-mix(in srgb, ${CONFIG.glass} 50%, ${CONFIG.haze})`;
        const gLight = `color-mix(in srgb, ${CONFIG.glow} 8%, #f2e8f8)`;
        const gFloor = `color-mix(in srgb, ${CONFIG.glass} 72%, ${CONFIG.glow})`;
        const cm = (c, p) => `color-mix(in srgb, ${c} ${p}%, transparent)`;
        const gx = (x) => (wr / 2 - 360 + x + GPAD).toFixed(1) + "px";
        const gy = (y) => (y + GPAD).toFixed(1) + "px";

        state.glassFieldCSS = [
          `radial-gradient(ellipse 230px 250px at ${gx(300)} ${gy(540)}, rgba(14, 0, 40, 0.075), rgba(14, 0, 40, 0.05) 60%, rgba(14, 0, 40, 0) 100%)`,
          `radial-gradient(ellipse 130px 92px at ${gx(260)} ${gy(380)}, rgba(14, 0, 40, 0.2), rgba(14, 0, 40, 0.09) 55%, rgba(14, 0, 40, 0) 100%)`,
          `radial-gradient(ellipse 112px 232px at ${gx(515)} ${gy(455)}, rgba(240, 214, 255, 0.1), rgba(240, 214, 255, 0.05) 55%, rgba(240, 214, 255, 0) 100%)`,
          `radial-gradient(circle 482.3px at ${(165.1 + GPAD).toFixed(1)}px ${(740.4 + GPAD).toFixed(1)}px, ${cm(gFloor, 100)} 0px, ${cm(gFloor, 72.6)} 120.6px, ${cm(gFloor, 24.9)} 241.2px, ${cm(gFloor, 19.4)} 361.7px, ${cm(gFloor, 0)} 482.3px)`,
          `radial-gradient(ellipse 212px 305px at ${(730 + wr - 720 + GPAD).toFixed(1)}px ${(62 + GPAD).toFixed(1)}px, ${cm(gLight, 96)} 0%, ${cm(gLight, 72)} 35%, ${cm(gLight, 33)} 66%, ${cm(gLight, 0)} 100%)`,
          `linear-gradient(${GPHI}deg, ${cm(gBand, 100)} ${gat(0)}, ${cm(gBand, 94.6)} ${gat(0.2)}, ${cm(gBand, 45.9)} ${gat(0.4)}, ${cm(gBand, 0)} ${gat(0.6)}, ${cm(gBand, 0)} ${gat(0.8)}, ${cm(gBand, 33)} ${gat(1)})`,
          gBase
        ].join(", ");

        state.glassFieldSize = `${(wr + 2 * GPAD).toFixed(1)}px ${STAGE_H + 2 * GPAD}px`;

        // Apply updated glass background to existing cards
        for (const card of state.activeCards.values()) {
          card.glass.style.backgroundImage = state.glassFieldCSS;
          card.glass.style.backgroundSize = state.glassFieldSize;
          card.lastSig = ""; // Force position update
        }
      }

      // -----------------------------------------------------------------------
      // Card Creation & Lifecycle
      // -----------------------------------------------------------------------
      function selectCell(c, r) {
        const dx = -(cellX(c) + state.target.x);
        const dy = -(cellY(c, r) + state.target.y);
        state.focus = { c, r };

        if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
          const t0 = reducedMotion ? state.clock - 1e5 : state.clock;
          state.moves.push({
            t0,
            dx,
            dy,
            bx: state.target.x,
            by: state.target.y
          });
          state.target = {
            x: state.target.x + dx,
            y: state.target.y + dy
          };
        }

        if (state.autoEnabled && CONFIG.interval > 0) {
          state.nextAuto = state.clock + CONFIG.interval;
        }

        const mi = pickMessage(CONFIG.messages.length, c, r);
        if (mi >= 0 && CONFIG.onSelect) {
          CONFIG.onSelect(mi, CONFIG.messages[mi]);
        }
      }

      function createCardElement(c, r, key) {
        const mi = pickMessage(CONFIG.messages.length, c, r);
        const msg = mi >= 0 ? CONFIG.messages[mi] : "";
        const fid = `${uid}-${key.replace(":", "_").replace(/-/g, "m")}`;

        const root = document.createElement("div");
        root.className = "aw-card";
        root.setAttribute("role", "button");
        root.setAttribute("tabindex", "0");
        root.setAttribute("aria-label", msg ? msg.replace(/\n/g, " ") : "Assistant card");

        // Rim stops generator helper
        const makeStops = (arr, id) => {
          const stops = arr.map((o) => `<stop offset="${o}" style="stop-color: ${rimColor};" stop-opacity="0.2" />`).join("");
          return stops;
        };

        root.innerHTML = `
          <div class="aw-card-inner">
            <div class="aw-card-glass" style="background-image: ${state.glassFieldCSS}; background-size: ${state.glassFieldSize};">
              <div class="aw-card-sheen"></div>
              <svg class="aw-card-rim" width="${CW}" height="${CH}" viewBox="0 0 ${CW} ${CH}" aria-hidden="true">
                <defs>
                  <linearGradient id="${fid}-rt" data-rim="t" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="${CW}" y2="0">
                    ${makeStops(H_OFF)}
                  </linearGradient>
                  <linearGradient id="${fid}-rb" data-rim="b" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="${CW}" y2="0">
                    ${makeStops(H_OFF)}
                  </linearGradient>
                  <linearGradient id="${fid}-rl" data-rim="l" gradientUnits="userSpaceOnUse" x1="0" y1="${RAD}" x2="0" y2="${CH - RAD}">
                    ${makeStops(V_OFF)}
                  </linearGradient>
                  <linearGradient id="${fid}-rr" data-rim="r" gradientUnits="userSpaceOnUse" x1="0" y1="${RAD}" x2="0" y2="${CH - RAD}">
                    ${makeStops(V_OFF)}
                  </linearGradient>
                </defs>
                <g fill="none" stroke-width="2" transform="translate(-0.5 -0.5) scale(1.00264 1.00231)">
                  <path d="M1 ${RAD} A${RAD - 1} ${RAD - 1} 0 0 1 ${RAD} 1 H${CW - RAD} A${RAD - 1} ${RAD - 1} 0 0 1 ${CW - 1} ${RAD}" stroke="url(#${fid}-rt)" />
                  <path d="M1 ${CH - RAD} A${RAD - 1} ${RAD - 1} 0 0 0 ${RAD} ${CH - 1} H${CW - RAD} A${RAD - 1} ${RAD - 1} 0 0 0 ${CW - 1} ${CH - RAD}" stroke="url(#${fid}-rb)" />
                  <path d="M1 ${RAD} V${CH - RAD}" stroke="url(#${fid}-rl)" />
                  <path d="M${CW - 1} ${RAD} V${CH - RAD}" stroke="url(#${fid}-rr)" />
                </g>
              </svg>

              <!-- Glossy 3D Avatar Sphere -->
              <div class="aw-avatar" style="background: ${sphereBackground};">
                <div class="aw-avatar-ring"></div>
                ${CONFIG.badge ? `<div class="aw-avatar-badge" style="font-size: ${badgePx}px;">${CONFIG.badge}</div>` : ""}
              </div>

              <!-- Message Text -->
              <div class="aw-message" style="color: ${CONFIG.text};">${msg}</div>

              <!-- Hairline Input Divider & Motion Blur Filter -->
              <svg class="aw-hairline-svg" width="${CW}" height="${CH}" viewBox="0 0 ${CW} ${CH}" aria-hidden="true">
                <defs>
                  <linearGradient id="${fid}-l" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="${CW}" y2="0">
                    <stop offset="0" style="stop-color: ${lineColor}; stop-opacity: 0.2;" />
                    <stop offset="0.45" style="stop-color: ${lineColor}; stop-opacity: 0.17;" />
                    <stop offset="0.68" style="stop-color: ${lineColor}; stop-opacity: 0.45;" />
                    <stop offset="0.8" style="stop-color: ${lineColor}; stop-opacity: 1;" />
                    <stop offset="0.9" style="stop-color: ${lineColor}; stop-opacity: 1;" />
                    <stop offset="1" style="stop-color: ${lineColor}; stop-opacity: 0.65;" />
                  </linearGradient>
                  <filter id="${fid}" x="-20%" y="-25%" width="140%" height="150%" color-interpolation-filters="sRGB">
                    <feGaussianBlur stdDeviation="0 0" />
                  </filter>
                </defs>
                <path
                  d="M0.6 ${328.5 + 22} A22 22 0 0 1 22.6 328.5 H299.6 M342.4 328.5 H${CW - 22.6} A22 22 0 0 1 ${CW - 0.6} ${328.5 + 22}"
                  fill="none"
                  stroke="url(#${fid}-l)"
                  stroke-width="1.3"
                />
              </svg>

              <!-- Placeholder Text -->
              <div class="aw-placeholder" style="color: ${tx(86)};">${CONFIG.placeholder}</div>

              <!-- Round Microphone Button -->
              <div class="aw-mic-btn" style="background: ${ac(22, "#14031f")};">
                <div class="aw-mic-ring"></div>
                <svg width="43" height="43" viewBox="0 0 43 43" style="position: absolute; inset: 0; overflow: visible;" aria-hidden="true">
                  <rect x="17.8" y="10.4" width="7.8" height="15.6" rx="3.9" fill="none" stroke="${tx(92)}" stroke-width="1.3" />
                  <path d="M14 19.4 V21.4 A7.7 7.7 0 0 0 29.4 21.4 V19.4" fill="none" stroke="${tx(92)}" stroke-width="1.3" stroke-linecap="round" />
                  <path d="M21.7 29.2 V35 M17 35.1 H26.4" fill="none" stroke="${tx(92)}" stroke-width="1.3" stroke-linecap="round" />
                </svg>
              </div>
            </div>
          </div>
        `;

        // Pointer & keyboard interaction handlers
        root.addEventListener("pointerenter", () => {
          state.hoverKey = key;
        });

        root.addEventListener("pointerleave", () => {
          if (state.hoverKey === key) state.hoverKey = "";
        });

        root.addEventListener("click", () => {
          selectCell(c, r);
        });

        root.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            selectCell(c, r);
          }
        });

        cardsLayerEl.appendChild(root);

        const inner = root.firstElementChild;
        const glass = root.querySelector(".aw-card-glass");
        const blur = root.querySelector("feGaussianBlur");
        const rimStops = ["t", "b", "l", "r"].map((side) =>
          Array.from(root.querySelectorAll(`[data-rim="${side}"] stop`))
        );

        return {
          c,
          r,
          key,
          fid,
          root,
          inner,
          glass,
          blur,
          rimStops,
          lastSig: ""
        };
      }

      // -----------------------------------------------------------------------
      // Viewport Cell Culling & Layout Computation
      // -----------------------------------------------------------------------
      function getNeededCells(wr) {
        const halfW = wr / 2 + CW;
        const halfH = STAGE_H / 2 + CH;
        const offs = [state.base, state.target, ...state.moves.map((m) => ({ x: m.bx, y: m.by }))];
        const xs = offs.map((o) => o.x);
        const ys = offs.map((o) => o.y);
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);

        const out = [];
        const c0 = Math.floor((-halfW - maxX) / COLP);
        const c1 = Math.ceil((halfW - minX) / COLP);

        for (let c = c0; c <= c1; c++) {
          const x = cellX(c);
          if (x + maxX < -halfW || x + minX > halfW) continue;
          const oy = isOdd(c) ? ODD : 0;
          const r0 = Math.floor((-halfH - maxY - oy) / ROWP) - 1;
          const r1 = Math.ceil((halfH - minY - oy) / ROWP) + 1;
          for (let r = r0; r <= r1; r++) {
            out.push({ c, r, key: `${c}:${r}` });
          }
        }
        return out;
      }

      function computeCardPosition(c, r, clock, fps) {
        let x = cellX(c) + state.base.x;
        let y = cellY(c, r) + state.base.y;

        for (let i = 0; i < state.moves.length; i++) {
          const m = state.moves[i];
          const len = Math.hypot(m.dx, m.dy) || 1;
          const px = cellX(c) + m.bx;
          const py = cellY(c, r) + m.by;
          const ahead = -(px * m.dx + py * m.dy) / len;
          const d = Math.max(-24, Math.min(80, (LAG * ahead) / ROWP));
          const g = prog((clock - m.t0) * fps, d);
          x += m.dx * g;
          y += m.dy * g;
        }
        return [x, y];
      }

      // -----------------------------------------------------------------------
      // Per-Frame Physics & Rendering Loop
      // -----------------------------------------------------------------------
      function renderFrame() {
        const s = state.size.h / STAGE_H;
        const wr = state.size.w / s;

        // 1. Auto-advance scheduler
        if (!reducedMotion && state.autoEnabled) {
          let guard = 0;
          while (state.clock >= state.nextAuto && guard++ < 50) {
            const t0 = state.nextAuto;
            state.focus = { c: state.focus.c, r: state.focus.r + 1 };
            const dy = -(cellY(state.focus.c, state.focus.r) + state.target.y);
            const dx = -(cellX(state.focus.c) + state.target.x);
            state.moves.push({
              t0,
              dx,
              dy,
              bx: state.target.x,
              by: state.target.y
            });
            state.target = {
              x: state.target.x + dx,
              y: state.target.y + dy
            };
            state.nextAuto += CONFIG.interval > 0 ? CONFIG.interval : Infinity;
          }
        }

        const fps = 30 * Math.max(0.05, CONFIG.speed);

        // 2. Fold completed moves into base offset
        while (state.moves.length) {
          const m = state.moves[0];
          const tf = (state.clock - m.t0) * fps;
          if (tf > T_END + 80 || reducedMotion) {
            state.base = {
              x: state.base.x + m.dx,
              y: state.base.y + m.dy
            };
            state.moves.shift();
          } else {
            break;
          }
        }

        // 3. Reconcile visible card DOM nodes
        const needed = getNeededCells(wr);
        const neededKeys = new Set(needed.map((n) => n.key));

        for (const [key, card] of state.activeCards.entries()) {
          if (!neededKeys.has(key)) {
            card.root.remove();
            state.activeCards.delete(key);
          }
        }

        for (const cell of needed) {
          if (!state.activeCards.has(cell.key)) {
            state.activeCards.set(cell.key, createCardElement(cell.c, cell.r, cell.key));
          }
        }

        // 4. Update transforms, motion blur, glass background offset, and edge rim lighting
        const dt = 0.5 / fps;

        for (const cell of needed) {
          const card = state.activeCards.get(cell.key);
          if (!card) continue;

          const [x, y] = computeCardPosition(cell.c, cell.r, state.clock, fps);
          const [x0, y0] = computeCardPosition(cell.c, cell.r, state.clock - dt, fps);

          const vx = Math.abs(x - x0) * 2;
          const vy = Math.abs(y - y0) * 2;

          // Smooth hover interpolation
          const h0 = state.hoverAmt.get(cell.key) || 0;
          const h = h0 + ((state.hoverKey === cell.key ? 1 : 0) - h0) * 0.18;
          if (Math.abs(h) > 0.001) {
            state.hoverAmt.set(cell.key, h);
          } else {
            state.hoverAmt.delete(cell.key);
          }

          const sharp = 1 - 0.85 * h;
          const bx = vx * BLUR_K * Math.pow(vx / 15, 0.35) * sharp;
          const by = vy * BLUR_K * Math.pow(vy / 15, 0.35) * sharp;
          const lift = h * 6;

          const txPos = (wr / 2 + x - CW / 2).toFixed(2);
          const tyPos = (STAGE_H / 2 + CY + y - CH / 2 - lift).toFixed(2);
          const sc = (1 + h * 0.018).toFixed(4);

          card.root.style.transform = `translate(${txPos}px, ${tyPos}px) scale(${sc})`;
          card.root.style.zIndex = h > 0.01 ? "2" : "1";
          card.root.style.setProperty("--aw-hover", h.toFixed(3));

          // Velocity-proportional SVG motion blur
          if (card.blur && (bx > 0.05 || by > 0.05)) {
            card.blur.setAttribute("stdDeviation", `${bx.toFixed(2)} ${by.toFixed(2)}`);
            card.inner.style.filter = `url(#${card.fid})`;
          } else {
            card.inner.style.filter = "none";
          }

          // Stage-space glass texture alignment & screen-position rim lighting
          const sig = `${x.toFixed(1)},${y.toFixed(1)},${h.toFixed(2)},${wr.toFixed(1)}`;
          if (sig !== card.lastSig) {
            card.lastSig = sig;
            const XO = x - CW / 2;
            const YO = y + CY - CH / 2 - lift;

            card.glass.style.backgroundPosition = `${(-(wr / 2 + XO) - GPAD).toFixed(2)}px ${(-(STAGE_H / 2 + YO) - GPAD).toFixed(2)}px`;

            const hb = 0.25 * h;
            const setStops = (arr, vals) => {
              for (let i = 0; i < arr.length; i++) {
                arr[i].setAttribute("stop-opacity", Math.min(1, vals[i] + hb).toFixed(3));
              }
            };

            // Top rim
            setStops(
              card.rimStops[0],
              H_OFF.map((o, i) => {
                const v = rimT(XO + o * CW, YO);
                return i <= 1 ? v + 0.05 * ss(-60, -160, YO) : v;
              })
            );

            // Bottom rim
            setStops(
              card.rimStops[1],
              H_OFF.map((o, i) => {
                const v = rimB(XO + o * CW, YO + CH);
                if (i <= 1) return Math.max(v, rimL(YO + CH - 8) * (i ? 0.55 : 0.85));
                if (i >= H_OFF.length - 2) return Math.max(v, rimR(YO + CH - 8) * 0.5);
                return v;
              })
            );

            // Left rim
            setStops(
              card.rimStops[2],
              V_OFF.map((o) => rimL(YO + RAD + o * (CH - 2 * RAD)))
            );

            // Right rim
            setStops(
              card.rimStops[3],
              V_OFF.map((o) => rimR(YO + RAD + o * (CH - 2 * RAD)))
            );
          }
        }
      }

      // -----------------------------------------------------------------------
      // Resize & Stage Scaling Handler
      // -----------------------------------------------------------------------
      function handleResize() {
        const rect = rootEl.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        state.size = { w: rect.width, h: rect.height };
        const s = state.size.h / STAGE_H;
        const wr = state.size.w / s;

        stageEl.style.width = `${wr}px`;
        stageEl.style.height = `${STAGE_H}px`;
        stageEl.style.transform = `scale(${s})`;

        updateStageGradients(wr);
        renderFrame();
      }

      const resizeObserver = new ResizeObserver(handleResize);
      resizeObserver.observe(rootEl);

      // -----------------------------------------------------------------------
      // Animation Loop with Visibility & Viewport Pause
      // -----------------------------------------------------------------------
      let rafId = 0;
      let isTabVisible = true;
      let isInView = true;

      function tick(now) {
        if (state.lastTime >= 0) {
          state.clock += Math.max(0, now - state.lastTime) / 1000;
        }
        state.lastTime = now;
        renderFrame();
        rafId = requestAnimationFrame(tick);
      }

      function startLoop() {
        if (rafId || !isTabVisible || !isInView) return;
        state.lastTime = -1;
        rafId = requestAnimationFrame(tick);
      }

      function stopLoop() {
        if (rafId) {
          cancelAnimationFrame(rafId);
          rafId = 0;
        }
      }

      document.addEventListener("visibilitychange", () => {
        isTabVisible = document.visibilityState !== "hidden";
        if (isTabVisible && !reducedMotion) startLoop();
        else stopLoop();
      });

      if (typeof IntersectionObserver !== "undefined") {
        const io = new IntersectionObserver((entries) => {
          isInView = entries[0]?.isIntersecting ?? true;
          if (isInView && !reducedMotion) startLoop();
          else stopLoop();
        });
        io.observe(rootEl);
      }

      // -----------------------------------------------------------------------
      // Interactive Controls Wiring
      // -----------------------------------------------------------------------
      if (nextBtn) nextBtn.addEventListener("click", () => {
        selectCell(state.focus.c, state.focus.r + 1);
      });

      if (autoBtn) autoBtn.addEventListener("click", () => {
        state.autoEnabled = !state.autoEnabled;
        autoBtn.setAttribute("data-active", String(state.autoEnabled));
        autoBtn.textContent = state.autoEnabled ? "Auto: On" : "Auto: Off";
        if (state.autoEnabled && CONFIG.interval > 0) {
          state.nextAuto = state.clock + CONFIG.interval;
        } else {
          state.nextAuto = Infinity;
        }
      });

      // Initial layout & start
      handleResize();
      handleResize();
      if (!reducedMotion) { startLoop(); }
})();

(function () {
  "use strict";
  const config = window.KRECK_COURSE_WALL;
  const viewport = document.getElementById("kl-course-wall");
  const grid = document.getElementById("kl-courses");
  if (!config || !viewport || !grid) return;

  const search = document.getElementById("kl-search");
  const navigation = document.querySelector(".kl-course-navigation");
  const previousButton = document.getElementById("kl-course-previous");
  const nextButton = document.getElementById("kl-course-next");
  const position = document.getElementById("kl-course-position");
  const noResults = document.getElementById("kl-no-results");
  const labels = config.labels || {};
  const accents = ["#52701f", "#2f6da3", "#e6b765", "#10150d"];
  const courses = Array.isArray(config.courses) ? config.courses : [];
  const motionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
  const allMotionClasses = [
    "is-panning-right", "is-panning-down", "is-panning-left", "is-panning-up",
    "is-arriving-from-right", "is-arriving-from-bottom",
    "is-arriving-from-left", "is-arriving-from-top"
  ];
  const entrySide = {
    right: "right",
    down: "bottom",
    left: "left",
    up: "top"
  };
  const state = {
    filtered: courses.slice(),
    cursor: 0,
    animating: false,
    reducedMotion: motionQuery.matches,
    inView: true,
    autoTimer: 0,
    pointer: null,
    cancelPan: null
  };

  function wrap(index, length) {
    return length ? ((index % length) + length) % length : 0;
  }

  function localizedCourseLabel(code) {
    return String(labels.course || "Course {code}").replace("{code}", code);
  }

  function createStat(value, label) {
    const stat = document.createElement("span");
    stat.className = "kl-card-stat";
    const count = document.createElement("span");
    count.className = "kl-card-stat-value";
    count.textContent = String(Number(value) || 0);
    const name = document.createElement("span");
    name.className = "kl-card-stat-label";
    name.textContent = label;
    stat.append(count, name);
    return stat;
  }

  function createCourseCard(course, filteredIndex) {
    const card = document.createElement("article");
    card.className = "kl-course-card";
    card.setAttribute("role", "listitem");
    card.setAttribute("aria-labelledby", `kl-course-title-${course.id}`);
    card.style.setProperty("--kl-card-accent", accents[filteredIndex % accents.length]);

    const glass = document.createElement("div");
    glass.className = "kl-card-glass";
    const avatar = document.createElement("div");
    avatar.className = "kl-card-avatar";
    avatar.setAttribute("aria-hidden", "true");
    const badge = document.createElement("span");
    badge.className = "kl-card-badge";
    badge.textContent = course.monogram || "";
    avatar.appendChild(badge);

    const content = document.createElement("div");
    content.className = "kl-card-content";
    const kicker = document.createElement("span");
    kicker.className = "kl-card-kicker";
    kicker.textContent = localizedCourseLabel(course.shortname || "");
    const title = document.createElement("h3");
    title.className = "kl-card-title";
    title.id = `kl-course-title-${course.id}`;
    title.textContent = course.fullname || "";
    const category = document.createElement("span");
    category.className = "kl-card-cat";
    category.textContent = course.categoryname || "";

    const stats = document.createElement("div");
    stats.className = "kl-card-stats";
    const courseStats = course.stats || {};
    stats.append(
      createStat(courseStats.activities, labels.activities || "Activities"),
      createStat(courseStats.lessons, labels.lessons || "Lessons"),
      createStat(courseStats.discussions, labels.discussions || "Discussions")
    );

    const description = document.createElement("div");
    description.className = "kl-card-desc";
    description.id = `kl-course-desc-${course.id}`;
    description.style.setProperty("--kl-clamp", "3");
    description.innerHTML = course.summary || "";

    content.append(kicker, title, category, stats, description);
    if (course.hasdescription) {
      const toggle = document.createElement("button");
      toggle.className = "kl-card-toggle";
      toggle.type = "button";
      toggle.setAttribute("aria-controls", description.id);
      toggle.setAttribute("aria-expanded", "false");
      toggle.dataset.more = labels.readmore || "Read more";
      toggle.dataset.less = labels.readless || "Read less";
      toggle.textContent = toggle.dataset.more;
      content.appendChild(toggle);
    }

    const footer = document.createElement("a");
    footer.className = "kl-card-foot";
    footer.href = course.url || "#";
    const viewLabel = document.createElement("span");
    viewLabel.textContent = labels.viewcourse || "View course";
    const arrow = document.createElement("span");
    arrow.setAttribute("aria-hidden", "true");
    arrow.textContent = "\u2192";
    footer.append(viewLabel, arrow);

    glass.append(avatar, content, footer);
    card.appendChild(glass);
    return card;
  }

  function updatePosition() {
    if (!position) return;
    if (!state.filtered.length) {
      position.textContent = "";
      return;
    }
    position.textContent = String(labels.position || "Course {index} of {total}")
      .replace("{index}", String(state.cursor + 1))
      .replace("{total}", String(state.filtered.length));
  }

  function render() {
    const count = state.filtered.length;
    const visibleCount = Math.min(6, count);
    const fragment = document.createDocumentFragment();
    for (let offset = 0; offset < visibleCount; offset++) {
      const index = wrap(state.cursor + offset, count);
      fragment.appendChild(createCourseCard(state.filtered[index], index));
    }
    grid.replaceChildren(fragment);
    grid.hidden = count === 0;
    if (noResults) noResults.hidden = count !== 0;
    if (navigation) navigation.hidden = count < 2;
    updatePosition();
  }

  function pan(direction, step) {
    const count = state.filtered.length;
    if (count < 2 || state.animating) return;

    state.animating = true;
    grid.classList.remove(...allMotionClasses);
    let outTimer = 0;
    let arrivalTimer = 0;
    let arrivalHandler = null;
    const outClass = `is-panning-${direction}`;
    const arrivalClass = `is-arriving-from-${entrySide[direction]}`;
    const cancelPan = () => {
      window.clearTimeout(outTimer);
      window.clearTimeout(arrivalTimer);
      grid.removeEventListener("animationend", onPanOut);
      if (arrivalHandler) grid.removeEventListener("animationend", arrivalHandler);
      grid.classList.remove(outClass, arrivalClass);
      state.animating = false;
      state.cancelPan = null;
    };
    state.cancelPan = cancelPan;

    const finishPan = () => {
      if (!state.animating) return;
      window.clearTimeout(outTimer);
      grid.removeEventListener("animationend", onPanOut);
      grid.classList.remove(outClass);
      state.cursor = wrap(state.cursor + step, count);
      render();

      if (state.reducedMotion) {
        state.animating = false;
        state.cancelPan = null;
        return;
      }

      const finishArrival = (event) => {
        if (event && (event.target !== grid || !event.animationName.startsWith("kl-pan-in-"))) return;
        window.clearTimeout(arrivalTimer);
        grid.removeEventListener("animationend", finishArrival);
        grid.classList.remove(arrivalClass);
        state.animating = false;
        state.cancelPan = null;
      };
      arrivalHandler = finishArrival;
      grid.addEventListener("animationend", finishArrival);
      grid.classList.add(arrivalClass);
      arrivalTimer = window.setTimeout(() => finishArrival(), 340);
    };
    const onPanOut = (event) => {
      if (event.target === grid && event.animationName.startsWith("kl-pan-out-")) finishPan();
    };

    if (state.reducedMotion) {
      state.animating = false;
      state.cursor = wrap(state.cursor + step, count);
      render();
      return;
    }

    grid.addEventListener("animationend", onPanOut);
    grid.classList.add(outClass);
    outTimer = window.setTimeout(finishPan, 240);
  }

  function goNext(direction) {
    pan(direction, 1);
  }

  function goPrevious(direction) {
    pan(direction, -1);
  }

  if (search) {
    search.addEventListener("input", () => {
      const query = search.value.trim().toLocaleLowerCase();
      state.filtered = courses.filter((course) =>
        String(course.searchtext || "").toLocaleLowerCase().includes(query)
      );
      state.cursor = 0;
      if (state.cancelPan) state.cancelPan();
      render();
    });
  }

  grid.addEventListener("click", (event) => {
    const toggle = event.target.closest(".kl-card-toggle");
    if (!toggle) return;
    const description = document.getElementById(toggle.getAttribute("aria-controls"));
    if (!description) return;
    const expanded = !description.classList.contains("kl-expanded");
    description.classList.toggle("kl-expanded", expanded);
    toggle.setAttribute("aria-expanded", String(expanded));
    toggle.textContent = expanded ? toggle.dataset.less : toggle.dataset.more;
  });

  if (previousButton) previousButton.addEventListener("click", () => goPrevious("left"));
  if (nextButton) nextButton.addEventListener("click", () => goNext("right"));
  viewport.addEventListener("keydown", (event) => {
    if (event.target !== viewport) return;
    const directions = {
      ArrowRight: ["right", 1],
      ArrowDown: ["down", 1],
      ArrowLeft: ["left", -1],
      ArrowUp: ["up", -1]
    };
    const selection = directions[event.key];
    if (!selection) return;
    event.preventDefault();
    pan(selection[0], selection[1]);
  });

  // Minimum travel (px) before a gesture counts as a swipe at all.
  const SWIPE_MIN = 36;
  // The dominant axis must beat the other by this factor, otherwise the
  // gesture is treated as an ambiguous ~45° diagonal and ignored. This is the
  // diagonal dead-zone that stops up/down and left/right fighting each other.
  const AXIS_RATIO = 1.35;

  // Resolve a 2-D delta to a single-axis pan [direction, step], or null when
  // the gesture is too small or too diagonal to be unambiguous.
  function resolveAxis(dx, dy) {
    const absX = Math.abs(dx);
    const absY = Math.abs(dy);
    if (Math.max(absX, absY) < SWIPE_MIN) return null;
    if (absX >= absY) {
      if (absX < absY * AXIS_RATIO) return null; // ambiguous diagonal
      return dx > 0 ? ["right", 1] : ["left", -1];
    }
    if (absY < absX * AXIS_RATIO) return null;   // ambiguous diagonal
    return dy > 0 ? ["down", 1] : ["up", -1];
  }

  function finishPointer(event) {
    const pointer = state.pointer;
    if (!pointer || pointer.id !== event.pointerId) return;
    const x = event.type !== "pointercancel" && Number.isFinite(event.clientX)
      ? event.clientX : pointer.lastX;
    const y = event.type !== "pointercancel" && Number.isFinite(event.clientY)
      ? event.clientY : pointer.lastY;
    state.pointer = null;
    // Lock out new pans while one is in flight (pan() also guards on this, but
    // bail early so an ambiguous release can never queue behind the animation).
    if (state.animating) return;
    const selection = resolveAxis(x - pointer.x, y - pointer.y);
    if (!selection) return;
    pan(selection[0], selection[1]);
  }

  viewport.addEventListener("pointerdown", (event) => {
    if (!event.isPrimary || (event.pointerType === "mouse" && event.button !== 0)) return;
    if (event.target.closest("a, button, input, textarea, select")) return;
    state.pointer = {
      id: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      lastX: event.clientX,
      lastY: event.clientY
    };
    if (viewport.setPointerCapture) viewport.setPointerCapture(event.pointerId);
  });
  viewport.addEventListener("pointermove", (event) => {
    if (!state.pointer || state.pointer.id !== event.pointerId) return;
    state.pointer.lastX = event.clientX;
    state.pointer.lastY = event.clientY;
  });
  viewport.addEventListener("pointerup", finishPointer);
  viewport.addEventListener("pointercancel", finishPointer);

  // Trackpad / wheel path. There is no native scroll inside the wall, so a
  // two-finger trackpad swipe arrives as wheel events. We only hijack clearly
  // HORIZONTAL gestures (deltaX dominant) so vertical page scrolling is never
  // trapped on this public landing page. Deltas are accumulated and debounced,
  // run through the same resolveAxis dead-zone, and a short cooldown plus the
  // state.animating guard stop a single flick from double-firing. ---------- */
  const wheel = { x: 0, y: 0, timer: 0, cooldown: false };
  const WHEEL_TRIGGER = 48;
  viewport.addEventListener("wheel", (event) => {
    if (state.reducedMotion || state.filtered.length < 2) return;
    // Ignore vertical-dominant scrolls entirely — let the page scroll.
    if (Math.abs(event.deltaX) <= Math.abs(event.deltaY)) return;
    event.preventDefault();
    if (state.animating || wheel.cooldown) return;
    wheel.x += event.deltaX;
    wheel.y += event.deltaY;
    window.clearTimeout(wheel.timer);
    wheel.timer = window.setTimeout(() => { wheel.x = 0; wheel.y = 0; }, 180);
    if (Math.abs(wheel.x) < WHEEL_TRIGGER) return;
    const selection = resolveAxis(wheel.x, wheel.y);
    wheel.x = 0;
    wheel.y = 0;
    if (!selection) return;
    pan(selection[0], selection[1]);
    wheel.cooldown = true;
    window.setTimeout(() => { wheel.cooldown = false; }, 420);
  }, { passive: false });

  function stopAutoPan() {
    if (!state.autoTimer) return;
    window.clearInterval(state.autoTimer);
    state.autoTimer = 0;
  }

  function startAutoPan() {
    if (state.autoTimer || state.reducedMotion || !state.inView ||
        document.visibilityState === "hidden" || Number(config.interval) <= 0 ||
        state.filtered.length < 2) return;
    state.autoTimer = window.setInterval(() => goNext("down"), Number(config.interval) * 1000);
  }

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") stopAutoPan();
    else startAutoPan();
  });
  if (typeof IntersectionObserver !== "undefined") {
    const observer = new IntersectionObserver((entries) => {
      state.inView = entries[0]?.isIntersecting ?? true;
      if (state.inView) startAutoPan();
      else stopAutoPan();
    });
    observer.observe(viewport);
  }
  const handleMotionChange = (event) => {
    state.reducedMotion = event.matches;
    if (state.reducedMotion) {
      stopAutoPan();
      if (state.cancelPan) state.cancelPan();
    }
    else startAutoPan();
  };
  if (motionQuery.addEventListener) motionQuery.addEventListener("change", handleMotionChange);
  else motionQuery.addListener(handleMotionChange);

  render();
  startAutoPan();
})();
