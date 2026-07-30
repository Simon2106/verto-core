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

/* ── 5. Jobs board filtering ── */
(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-verto-jobs]").forEach(function (board) {
      var state = { brand: "all", location: "all", level: "all" };
      var rows = board.querySelectorAll(".verto-jobs__row");
      var empty = board.querySelector(".verto-jobs__empty");
      var count = board.querySelector("[data-jobs-count]");
      var clear = board.querySelector("[data-jobs-clear]");

      function apply() {
        var shown = 0;
        rows.forEach(function (r) {
          var ok =
            (state.brand === "all" || r.dataset.brand === state.brand) &&
            (state.location === "all" || r.dataset.location === state.location) &&
            (state.level === "all" || r.dataset.level === state.level);
          r.hidden = !ok;
          if (ok) shown++;
        });
        if (empty) empty.hidden = shown !== 0;
        if (count) count.textContent = shown;
        var active = Object.keys(state).filter(function (k) { return state[k] !== "all"; }).length;
        if (clear) {
          clear.hidden = active === 0;
          clear.textContent = "Clear (" + active + ")";
        }
      }

      board.querySelectorAll("[data-filter-group]").forEach(function (group) {
        var key = group.dataset.filterGroup;
        group.addEventListener("click", function (e) {
          var chip = e.target.closest(".verto-chip");
          if (!chip) return;
          state[key] = chip.dataset.value;
          group.querySelectorAll(".verto-chip").forEach(function (c) { c.classList.remove("is-active"); });
          chip.classList.add("is-active");
          apply();
        });
      });

      if (clear) clear.addEventListener("click", function () {
        state = { brand: "all", location: "all", level: "all" };
        board.querySelectorAll("[data-filter-group]").forEach(function (group) {
          group.querySelectorAll(".verto-chip").forEach(function (c) {
            c.classList.toggle("is-active", c.dataset.value === "all");
          });
        });
        apply();
      });
    });
  });
})();

/* ── 7. Parallax images (.verto-parallax) — vanilla port of the
      prototype's ParallaxImage: moves at `speed`× scroll, optional
      scale/offset, disabled for reduced-motion and coarse pointers ── */
(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var els = document.querySelectorAll(".verto-parallax");
    if (!els.length) return;
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var coarse = window.matchMedia("(pointer: coarse)").matches;
    if (reduced || coarse) return; // prototype leaves the image untransformed

    var items = [];
    els.forEach(function (el) {
      var img = el.querySelector("img");
      if (!img) return;
      items.push({
        el: el,
        img: img,
        speed: parseFloat(el.dataset.parallaxSpeed || "0.25"),
        scale: parseFloat(el.dataset.parallaxScale || "1.18"),
        offsetY: parseFloat(el.dataset.parallaxOffset || "0"),
      });
    });
    if (!items.length) return;

    var raf = 0;
    function update() {
      raf = 0;
      var vh = window.innerHeight;
      items.forEach(function (it) {
        var rect = it.el.getBoundingClientRect();
        // progress from -1 (below screen) to 1 (above screen) — prototype math
        var progress = (rect.top + rect.height / 2 - vh / 2) / (vh + rect.height / 2);
        var offset = -progress * rect.height * it.speed + it.offsetY;
        it.img.style.transform = "translate3d(0, " + offset.toFixed(2) + "px, 0) scale(" + it.scale + ")";
      });
    }
    function onScroll() {
      if (raf) return;
      raf = window.requestAnimationFrame(update);
    }
    update();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);
  });
})();

/* ── 6. Autoplay rescue: if the browser blocked muted autoplay (low power
      mode, data saver), retry on the first user interaction ── */
(function () {
  "use strict";
  function rescue() {
    document.querySelectorAll("video.verto-autoplay").forEach(function (v) {
      if (v.paused) { v.muted = true; v.play().catch(function () {}); }
    });
  }
  ["touchstart", "scroll", "click", "keydown"].forEach(function (evt) {
    window.addEventListener(evt, rescue, { once: true, passive: true });
  });
})();
