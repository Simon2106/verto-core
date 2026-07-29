/**
 * Verto effects — vanilla ports of the React prototype's animation system.
 * 1. Section scroll-reveal with page-build stagger + viewport-aware failsafe
 * 2. Headline line reveal (.verto-title-reveal, markup from the widget)
 * 3. Autoplay-safe muted video (.verto-autoplay)
 */
(function () {
  "use strict";
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

  var SELECTOR = "main > section, .elementor-section-wrap > section, [data-reveal], .elementor-top-section";

  document.addEventListener("DOMContentLoaded", function () {
    /* ── 1. Section reveals ── */
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("reveal-in");
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -8% 0px" });

    var visIdx = 0;
    document.querySelectorAll(SELECTOR).forEach(function (el) {
      if (el.classList.contains("reveal-init")) return;
      el.classList.add("reveal-init");
      el.dataset.revealTagged = String(Date.now());
      if (el.getBoundingClientRect().top < window.innerHeight) {
        el.style.setProperty("--reveal-delay", Math.min(visIdx++ * 110, 440) + "ms");
      }
      io.observe(el);
    });

    // Failsafe: force-reveal anything on screen still hidden after 1.6s
    setInterval(function () {
      document.querySelectorAll(".reveal-init:not(.reveal-in)").forEach(function (el) {
        var t = el.dataset.revealTagged;
        if (!t || Date.now() - Number(t) < 1600) return;
        var r = el.getBoundingClientRect();
        if (r.top < window.innerHeight && r.bottom > 0) el.classList.add("reveal-in");
      });
    }, 800);

    /* ── 2. Headline line reveals ── */
    var tio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("is-in");
          tio.unobserve(e.target);
        }
      });
    }, { threshold: 0.3 });
    document.querySelectorAll(".verto-title-reveal").forEach(function (el) {
      tio.observe(el);
      setTimeout(function () { el.classList.add("is-in"); }, 2500); // failsafe
    });

    /* ── 3. Autoplay-safe videos ── */
    document.querySelectorAll("video.verto-autoplay").forEach(function (v) {
      v.muted = true;
      v.defaultMuted = true;
      var tryPlay = function () { v.play().catch(function () {}); };
      tryPlay();
      v.addEventListener("canplay", tryPlay, { once: true });
    });
  });
})();

/* ── 4. Count-up stats ([data-countup="40"][data-suffix="%"]) ── */
(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var els = document.querySelectorAll("[data-countup]");
    if (!els.length) return;
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        var el = e.target;
        var target = parseFloat(el.dataset.countup || "0");
        var suffix = el.dataset.suffix || "";
        if (reduce) { el.textContent = target.toLocaleString() + suffix; return; }
        var t0 = performance.now();
        (function tick(now) {
          var p = Math.min(1, (now - t0) / 1600);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * eased).toLocaleString() + suffix;
          if (p < 1) requestAnimationFrame(tick);
        })(t0);
      });
    }, { threshold: 0.3 });
    els.forEach(function (el) { io.observe(el); });
  });
})();
