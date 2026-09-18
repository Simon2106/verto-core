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

/* ── 8. Timeline auto-carousel (.verto-timeline) — mirror of the
      prototype's TimelineCarousel (about.tsx): auto-advance every 3.5s,
      pause on hover/touch/focus, resume after 6s idle, sync to manual
      swipes (native overflow scroll stays the mechanism) and fill a gold
      progress line between visited milestones. Round 4, item 15: the native
      scrollbar is hidden in CSS and prev/next chevron buttons are injected
      here, wired to the same carousel index. Reduced motion → no autoplay or
      progress line, but the chevrons still page the track. ── */
(function () {
  "use strict";
  var ADVANCE_MS = 3500;
  var RESUME_MS = 6000;
  var CHEVRON = {
    prev: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>',
    next: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>',
  };

  document.addEventListener("DOMContentLoaded", function () {
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    document.querySelectorAll(".verto-timeline").forEach(function (scroller) {
      var track = scroller.querySelector(".verto-timeline__track");
      if (!track) return;
      var items = Array.prototype.slice.call(track.querySelectorAll(".verto-timeline__item"));
      if (items.length < 2) return;

      // Chevron nav (round 4, item 15) — injected so the widget markup stays
      // untouched. Sits above the full-bleed track, right-aligned.
      var nav = document.createElement("div");
      nav.className = "verto-timeline__nav";
      var buttons = {};
      [ "prev", "next" ].forEach(function (dir) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "verto-timeline__btn";
        btn.setAttribute("aria-label", "prev" === dir ? "Previous milestones" : "Next milestones");
        btn.innerHTML = CHEVRON[dir];
        nav.appendChild(btn);
        buttons[dir] = btn;
      });
      scroller.parentNode.insertBefore(nav, scroller);

      if (reduced) {
        // Manual mode: no autoplay/progress — the chevrons page the track.
        var page = function (dir) { scroller.scrollBy({ left: dir * 264, behavior: "auto" }); };
        buttons.prev.addEventListener("click", function () { page(-1); });
        buttons.next.addEventListener("click", function () { page(1); });
        return;
      }

      // Gold progress line, injected so the widget markup stays untouched
      var progress = document.createElement("div");
      progress.className = "verto-timeline__progress";
      progress.setAttribute("aria-hidden", "true");
      track.insertBefore(progress, track.firstChild);

      var idx = 0;
      var paused = false;
      var programmatic = false;
      var progTimer = 0;
      var idleTimer = 0;

      function mark(i) {
        items.forEach(function (el, j) {
          el.classList.toggle("is-active", j === i);
          el.classList.toggle("is-visited", j <= i);
        });
        progress.style.width = items[i].offsetLeft + 8 + "px"; // 8px ≈ dot centre
      }
      function go(i) {
        idx = i;
        programmatic = true;
        clearTimeout(progTimer);
        progTimer = setTimeout(function () { programmatic = false; }, 900);
        scroller.scrollTo({ left: Math.max(0, items[i].offsetLeft - 24), behavior: "smooth" });
        mark(i);
      }

      mark(0);
      setInterval(function () {
        if (!paused) go((idx + 1) % items.length);
      }, ADVANCE_MS);

      function pause() { paused = true; clearTimeout(idleTimer); }
      function scheduleResume() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () { paused = false; }, RESUME_MS);
      }
      // Chevrons step the carousel index and hold off the autoplay a while.
      function step(dir) {
        pause();
        scheduleResume();
        go((idx + dir + items.length) % items.length);
      }
      buttons.prev.addEventListener("click", function () { step(-1); });
      buttons.next.addEventListener("click", function () { step(1); });
      scroller.addEventListener("pointerenter", pause);
      scroller.addEventListener("pointerleave", scheduleResume);
      scroller.addEventListener("touchstart", pause, { passive: true });
      scroller.addEventListener("touchend", scheduleResume);
      scroller.addEventListener("focusin", pause);
      scroller.addEventListener("focusout", scheduleResume);
      scroller.addEventListener("scroll", function () {
        if (programmatic) return;
        pause();
        scheduleResume();
        // Sync active milestone + progress to the swipe position
        var x = scroller.scrollLeft + 24;
        var nearest = 0;
        for (var i = 0; i < items.length; i++) {
          if (Math.abs(items[i].offsetLeft - x) < Math.abs(items[nearest].offsetLeft - x)) nearest = i;
        }
        idx = nearest;
        mark(nearest);
      }, { passive: true });
    });
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

/* ── 9. Apply modal (jobs board → verto_apply endpoint) ──
      Progressive enhancement over the CSS :target fallback: proper
      open/close (Escape, overlay, close links), per-row job prefill,
      client-side CV size check and async submit with inline success /
      error states. Runs regardless of prefers-reduced-motion. ── */
(function () {
  "use strict";
  var MAX_CV_BYTES = 5 * 1024 * 1024;

  document.addEventListener("DOMContentLoaded", function () {
    var modal = document.querySelector("[data-verto-apply-modal]");
    if (!modal) return;
    var form = modal.querySelector("[data-verto-apply-form]");
    var done = modal.querySelector("[data-apply-done]");
    var errBox = modal.querySelector("[data-apply-error]");
    var titleEl = modal.querySelector("[data-apply-job-label]");
    var lastFocus = null;

    function showError(msg) {
      if (errBox) {
        errBox.textContent = msg;
        errBox.hidden = false;
      }
      var btn = form && form.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = false;
        if (btn.dataset.label) btn.textContent = btn.dataset.label;
      }
    }

    function open(job) {
      lastFocus = document.activeElement;
      // Reset to a fresh form each time (a previous success hides it)
      if (form) {
        form.hidden = false;
        if (errBox) { errBox.hidden = true; errBox.textContent = ""; }
      }
      if (done) done.hidden = true;
      if (titleEl) titleEl.textContent = job && job.title ? job.title : "Join Verto";
      if (form) {
        var idField = form.querySelector('[name="verto_job_id"]');
        if (idField) idField.value = job && job.id ? job.id : "";
        var roleField = form.querySelector('[name="verto_job_title"]');
        if (roleField) roleField.value = job && job.title ? job.title : "";
      }
      modal.classList.add("is-open");
      document.body.classList.add("verto-modal-open");
      var first = form && form.querySelector('[name="verto_name"]');
      if (first) window.setTimeout(function () { first.focus(); }, 60);
    }

    function close() {
      modal.classList.remove("is-open");
      document.body.classList.remove("verto-modal-open");
      // Clear the :target fallback hash so re-clicking the same row works
      if (window.location.hash === "#verto-apply-modal") {
        history.replaceState(null, "", window.location.pathname + window.location.search);
      }
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener("click", function (e) {
      var opener = e.target.closest("[data-apply-open]");
      if (opener) {
        e.preventDefault();
        open({
          id: opener.getAttribute("data-job-id") || "",
          title: opener.getAttribute("data-job-title") || "",
        });
        return;
      }
      var closer = e.target.closest("[data-apply-close]");
      if (closer) {
        e.preventDefault();
        close();
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && (modal.classList.contains("is-open") || window.location.hash === "#verto-apply-modal")) close();
    });

    if (!form || !window.fetch || !window.FormData) return;

    // Flag async so the endpoint answers JSON instead of redirecting
    var asyncFlag = document.createElement("input");
    asyncFlag.type = "hidden";
    asyncFlag.name = "verto_async";
    asyncFlag.value = "1";
    form.appendChild(asyncFlag);

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (errBox) { errBox.hidden = true; errBox.textContent = ""; }

      var file = form.querySelector('input[type="file"]');
      if (file && file.files && file.files[0] && file.files[0].size > MAX_CV_BYTES) {
        showError("Your CV is over 5 MB – please attach a smaller file.");
        return;
      }
      var btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.dataset.label = btn.dataset.label || btn.textContent;
        btn.disabled = true;
        btn.textContent = "Sending…";
      }
      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      })
        .then(function (res) {
          return res.json().then(function (json) { return json; });
        })
        .then(function (json) {
          if (json && json.success) {
            form.hidden = true;
            if (done) done.hidden = false;
            if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label; }
          } else {
            var msg = json && json.data && json.data.message
              ? json.data.message
              : "Something went wrong – please try again.";
            showError(msg);
          }
        })
        .catch(function () {
          showError("Something went wrong sending your application – please try again, or email us your CV instead.");
        });
    });
  });
})();

/* ── 11. Testimonials carousel ([data-verto-quotes-carousel]) – quote-band
      quotes_layout=carousel: one quote per view (two side-by-side on wide
      screens via CSS flex-basis), auto-advance every 6s, pause on
      hover/focus/touch, resume after idle, prev/next chevrons + dot
      indicators injected here (widget markup stays untouched, mirroring
      the timeline module §8), swipe on mobile via native scroll-snap.
      Reduced motion – no autoplay and no smooth scrolling; chevrons, dots
      and swipe still navigate. ── */
(function () {
  "use strict";
  var ADVANCE_MS = 6000;
  var RESUME_MS = 8000;
  var CHEVRON = {
    prev: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>',
    next: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>',
  };

  document.addEventListener("DOMContentLoaded", function () {
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    document.querySelectorAll("[data-verto-quotes-carousel]").forEach(function (scroller) {
      var track = scroller.querySelector(".vbs-qcar__track");
      if (!track) return;
      var slides = Array.prototype.slice.call(track.querySelectorAll(".vbs-qcar__slide"));
      if (slides.length < 2) return;

      // Chevrons + dots, injected below the quotes so the widget markup
      // stays untouched. Order: prev, dots, next (centred cluster).
      var nav = document.createElement("div");
      nav.className = "vbs-qcar__nav";
      var dots = document.createElement("div");
      dots.className = "vbs-qcar__dots";
      var buttons = {};
      ["prev", "next"].forEach(function (dir) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "vbs-qcar__btn";
        btn.setAttribute("aria-label", "prev" === dir ? "Previous testimonial" : "Next testimonial");
        btn.innerHTML = CHEVRON[dir];
        buttons[dir] = btn;
      });
      nav.appendChild(buttons.prev);
      nav.appendChild(dots);
      nav.appendChild(buttons.next);
      scroller.parentNode.insertBefore(nav, scroller.nextSibling);

      var idx = 0;
      var paused = false;
      var programmatic = false;
      var progTimer = 0;
      var idleTimer = 0;
      var dotEls = [];

      // Offset of a slide within the scroll content (scroll-independent),
      // robust against positioned ancestors (unlike offsetLeft).
      function slideLeft(i) {
        return slides[i].getBoundingClientRect().left - track.getBoundingClientRect().left;
      }
      function perView() {
        var w = slides[0].getBoundingClientRect().width;
        return w > 0 ? Math.max(1, Math.round(scroller.clientWidth / w)) : 1;
      }
      // With two quotes per view the last page starts perView-1 slides early.
      function pageCount() {
        return Math.max(1, slides.length - perView() + 1);
      }

      function mark(i) {
        idx = Math.max(0, Math.min(i, pageCount() - 1));
        dotEls.forEach(function (d, j) {
          d.classList.toggle("is-active", j === idx);
          d.setAttribute("aria-current", j === idx ? "true" : "false");
        });
      }
      function go(i) {
        var pages = pageCount();
        i = ((i % pages) + pages) % pages;
        programmatic = true;
        clearTimeout(progTimer);
        progTimer = setTimeout(function () { programmatic = false; }, 900);
        scroller.scrollTo({
          left: slideLeft(i),
          behavior: reduced ? "auto" : "smooth",
        });
        mark(i);
      }

      function pause() { paused = true; clearTimeout(idleTimer); }
      function scheduleResume() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () { paused = false; }, RESUME_MS);
      }
      // Manual interaction: hold the autoplay off for a while.
      function hold() { pause(); scheduleResume(); }

      function buildDots() {
        dots.innerHTML = "";
        dotEls = [];
        for (var i = 0; i < pageCount(); i++) {
          (function (i) {
            var d = document.createElement("button");
            d.type = "button";
            d.className = "vbs-qcar__dot";
            d.setAttribute("aria-label", "Go to testimonial " + (i + 1));
            d.addEventListener("click", function () { hold(); go(i); });
            dots.appendChild(d);
            dotEls.push(d);
          })(i);
        }
      }

      buildDots();
      mark(0);

      buttons.prev.addEventListener("click", function () { hold(); go(idx - 1); });
      buttons.next.addEventListener("click", function () { hold(); go(idx + 1); });
      scroller.addEventListener("pointerenter", pause);
      scroller.addEventListener("pointerleave", scheduleResume);
      scroller.addEventListener("touchstart", pause, { passive: true });
      scroller.addEventListener("touchend", scheduleResume);
      [scroller, nav].forEach(function (el) {
        el.addEventListener("focusin", pause);
        el.addEventListener("focusout", scheduleResume);
      });

      // Sync the dots to manual swipes (native scroll stays the mechanism).
      scroller.addEventListener("scroll", function () {
        if (programmatic) return;
        hold();
        var x = scroller.scrollLeft;
        var nearest = 0;
        for (var i = 0; i < slides.length; i++) {
          if (Math.abs(slideLeft(i) - x) < Math.abs(slideLeft(nearest) - x)) nearest = i;
        }
        mark(nearest);
      }, { passive: true });

      // A resize can change quotes-per-view (1 vs 2) and so the page count.
      window.addEventListener("resize", function () {
        if (dotEls.length !== pageCount()) buildDots();
        mark(idx);
      });

      if (!reduced) {
        setInterval(function () {
          if (!paused && "hidden" !== document.visibilityState) go(idx + 1);
        }, ADVANCE_MS);
      }
    });
  });
})();

/* ── 10. Inline apply form (job detail pages, single-verto_job.php) ──
      Same handler + progressive enhancement as the modal (§9), but the form
      sits inline at #apply with the job prefilled server-side. Without JS:
      plain multipart POST, redirect back with ?verto_apply=… → the banner
      (scrolled into view below). With JS: async submit, success swaps the
      form for the done block. ── */
(function () {
  "use strict";
  var MAX_CV_BYTES = 5 * 1024 * 1024;

  document.addEventListener("DOMContentLoaded", function () {
    // Non-JS redirect flash: bring the banner into view so the outcome is seen.
    var flash = document.querySelector("[data-apply-flash]");
    if (flash && window.location.search.indexOf("verto_apply=") !== -1) {
      window.setTimeout(function () { flash.scrollIntoView({ block: "center" }); }, 80);
    }

    var form = document.querySelector("[data-verto-apply-inline]");
    if (!form || !window.fetch || !window.FormData) return;
    var card = form.closest("[data-verto-apply-card]");
    var done = card ? card.querySelector("[data-apply-done]") : null;
    var errBox = form.querySelector("[data-apply-error]");

    var asyncFlag = document.createElement("input");
    asyncFlag.type = "hidden";
    asyncFlag.name = "verto_async";
    asyncFlag.value = "1";
    form.appendChild(asyncFlag);

    function showError(msg) {
      if (errBox) {
        errBox.textContent = msg;
        errBox.hidden = false;
      }
      var btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = false;
        if (btn.dataset.label) btn.textContent = btn.dataset.label;
      }
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (errBox) { errBox.hidden = true; errBox.textContent = ""; }

      var file = form.querySelector('input[type="file"]');
      if (file && file.files && file.files[0] && file.files[0].size > MAX_CV_BYTES) {
        showError("Your CV is over 5 MB – please attach a smaller file.");
        return;
      }
      var btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.dataset.label = btn.dataset.label || btn.textContent;
        btn.disabled = true;
        btn.textContent = "Sending…";
      }
      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json && json.success) {
            form.hidden = true;
            if (done) {
              done.hidden = false;
              done.scrollIntoView({ block: "center" });
            }
          } else {
            var msg = json && json.data && json.data.message
              ? json.data.message
              : "Something went wrong – please try again.";
            showError(msg);
          }
        })
        .catch(function () {
          showError("Something went wrong sending your application – please try again, or email us your CV instead.");
        });
    });
  });
})();

/* ── 12. Recent-events rail (.verto-events, WGO hub) – poster tiles with
      play badges. Each <video> ships preload="none", so no bytes move
      until the badge is pressed; the press reveals native controls and
      starts playback inline. When the film ends the tile returns to its
      poster-and-badge state. */
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".verto-events__media").forEach(function (media) {
      var btn = media.querySelector(".verto-events__play");
      var video = media.querySelector("video");
      if (!btn || !video) return;
      btn.addEventListener("click", function () {
        media.classList.add("is-playing");
        video.controls = true;
        var p = video.play();
        if (p && p.catch) p.catch(function () {});
      });
      video.addEventListener("ended", function () {
        media.classList.remove("is-playing");
        video.controls = false;
        try { video.load(); } catch (e) { /* poster restore is best-effort */ }
      });
    });
  });
})();

/* ── 13. Sales-days mosaic ([data-verto-salesdays], careers) – ten compact
      muted films behind poster frames. Zero video bytes on page load: each
      <video> ships preload="none" with no src (the file waits in data-src)
      and is attached on the first hover/tap only. Fine pointers play on
      hover and pause + rewind on mouseleave; touch devices tap to toggle;
      prefers-reduced-motion means click-to-play everywhere. At most two
      tiles play at once – starting a third pauses the oldest. Keyboard:
      tiles are focusable, Enter/Space toggles. ── */
(function () {
  "use strict";
  var MAX_PLAYING = 2;

  document.addEventListener("DOMContentLoaded", function () {
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
    var fine = window.matchMedia("(hover: hover) and (pointer: fine)");

    document.querySelectorAll("[data-verto-salesdays]").forEach(function (root) {
      var hint = root.querySelector("[data-salesday-hint]");
      if (hint && !fine.matches) hint.textContent = "Tap to play";
      var playing = []; // oldest first

      // Hover drives playback only on fine pointers without reduced motion;
      // everywhere else (touch, reduced motion) the tile is click-to-play.
      function hoverMode() { return fine.matches && !reduced.matches; }

      function stop(video) {
        var i = playing.indexOf(video);
        if (i !== -1) playing.splice(i, 1);
        video.pause();
        try { video.currentTime = 0; } catch (e) { /* not seekable yet */ }
        var tile = video.closest("[data-salesday-tile]");
        if (tile) tile.classList.remove("is-playing");
      }

      function start(video) {
        // First interaction: attach the src – nothing downloaded before this.
        if (!video.getAttribute("src") && video.dataset.src) {
          video.setAttribute("src", video.dataset.src);
        }
        while (playing.length >= MAX_PLAYING) stop(playing[0]); // pause the oldest
        playing.push(video);
        video.muted = true;
        var p = video.play();
        if (p && p.catch) p.catch(function () {});
        var tile = video.closest("[data-salesday-tile]");
        if (tile) tile.classList.add("is-playing");
        if (hint) hint.hidden = true;
      }

      root.querySelectorAll("[data-salesday-tile]").forEach(function (tile) {
        var video = tile.querySelector("video");
        if (!video) return;
        tile.setAttribute("tabindex", "0");
        tile.setAttribute("role", "button");
        var label = video.getAttribute("aria-label");
        if (label) tile.setAttribute("aria-label", "Play – " + label);

        tile.addEventListener("mouseenter", function () {
          if (hoverMode()) start(video);
        });
        tile.addEventListener("mouseleave", function () {
          if (hoverMode()) stop(video);
        });
        tile.addEventListener("click", function () {
          if (hoverMode()) return; // hover already handles fine pointers
          if (video.paused) start(video); else stop(video);
        });
        tile.addEventListener("keydown", function (e) {
          if (e.key !== "Enter" && e.key !== " ") return;
          e.preventDefault();
          if (video.paused) start(video); else stop(video);
        });
      });
    });
  });
})();
