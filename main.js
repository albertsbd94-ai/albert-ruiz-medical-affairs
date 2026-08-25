(function () {
  // main.js — entry point. Classic script, IIFE. No imports/exports.
  "use strict";

  var data = window.__BRAND__ || {};
  var reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;

  var $ = function (sel, scope) { return (scope || document).querySelector(sel); };
  var $$ = function (sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); };

  function safe(fn, name) {
    try { fn(); } catch (e) { if (window.console) console.warn("[" + name + "] failed:", e); }
  }

  /* -----------------------------------------------------------
     Nav: mobile toggle + active link + shrink-on-scroll shadow
     ----------------------------------------------------------- */
  function initNav() {
    var nav = $("[data-nav]");
    if (!nav) return;
    var toggle = $("[data-nav-toggle]", nav);
    var mobile = $("[data-nav-mobile]", nav);

    if (toggle && mobile) {
      toggle.addEventListener("click", function () {
        var isOpen = nav.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      });
      $$("a", mobile).forEach(function (a) {
        a.addEventListener("click", function () {
          nav.classList.remove("is-open");
          toggle.setAttribute("aria-expanded", "false");
        });
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && nav.classList.contains("is-open")) {
          nav.classList.remove("is-open");
          toggle.setAttribute("aria-expanded", "false");
          toggle.focus();
        }
      });
    }

    // Mark current page link as aria-current
    var path = (location.pathname.split("/").pop() || "index.html");
    $$("a[href]", nav).forEach(function (a) {
      var href = (a.getAttribute("href") || "").split("/").pop();
      if (href === path || (path === "" && href === "index.html")) {
        a.setAttribute("aria-current", "page");
      }
    });
  }

  /* -----------------------------------------------------------
     Smooth-scroll for in-page anchors (native, offset for sticky nav)
     ----------------------------------------------------------- */
  function initAnchorScroll() {
    document.addEventListener("click", function (e) {
      var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
      if (!a) return;
      var id = a.getAttribute("href");
      if (!id || id === "#") return;
      var el;
      try { el = document.querySelector(id); } catch (_) { el = null; }
      if (!el) return;
      e.preventDefault();
      var navEl = $("[data-nav]");
      var offset = navEl ? navEl.getBoundingClientRect().height + 12 : 80;
      var top = el.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top: top, behavior: reduced ? "auto" : "smooth" });
    });
  }

  /* -----------------------------------------------------------
     Scroll reveal — fade-up / fade-in / scale-in
     ----------------------------------------------------------- */
  function initReveals() {
    var targets = $$(".reveal, .reveal-fade, .reveal-scale");
    if (!targets.length) return;

    if (!("IntersectionObserver" in window)) {
      targets.forEach(function (el) { el.classList.add("is-visible"); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.01, rootMargin: "0px 0px -2% 0px" });

    targets.forEach(function (el) { io.observe(el); });

    // Safety net: force-reveal anything still hidden after 6s
    setTimeout(function () {
      $$(".reveal:not(.is-visible), .reveal-fade:not(.is-visible), .reveal-scale:not(.is-visible)").forEach(function (el) {
        if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add("is-visible");
      });
    }, 6000);
  }

  /* -----------------------------------------------------------
     Newsletter form — simulated submit (no backend yet)
     ----------------------------------------------------------- */
  function initNewsletterForms() {
    $$("[data-newsletter-form]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;
        var success = $("[data-newsletter-success]", form.closest("[data-newsletter]") || document);
        form.reset();
        if (success) success.classList.add("is-visible");
        form.setAttribute("data-submitted", "true");
      });
    });
  }

  /* -----------------------------------------------------------
     Generic contact / work-with-me forms — mailto fallback
     ----------------------------------------------------------- */
  function initContactForms() {
    $$("[data-mailto-form]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        if (!form.reportValidity()) { e.preventDefault(); return; }
        var to = form.getAttribute("data-mailto-form") || (data.email || "");
        var nameField = $('[name="name"]', form);
        var emailField = $('[name="email"]', form);
        var msgField = $('[name="message"]', form);
        var subjectField = $('[name="subject"]', form);
        var subject = encodeURIComponent((subjectField && subjectField.value) || "Website contact — " + (nameField ? nameField.value : ""));
        var body = encodeURIComponent(
          (msgField ? msgField.value : "") +
          "\n\n---\nFrom: " + (nameField ? nameField.value : "") +
          " <" + (emailField ? emailField.value : "") + ">"
        );
        e.preventDefault();
        window.location.href = "mailto:" + to + "?subject=" + subject + "&body=" + body;
      });
    });
  }

  /* -----------------------------------------------------------
     FAQ accordions use native <details> — nothing to bind.
     Footer year, small enrichments.
     ----------------------------------------------------------- */
  function mountYear() {
    $$("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });
  }

  /* -----------------------------------------------------------
     Pill filters (Insights / Lab / Resources category filters)
     ----------------------------------------------------------- */
  function initFilters() {
    $$("[data-filter-group]").forEach(function (group) {
      var pills = $$("[data-filter]", group);
      var targetSel = group.getAttribute("data-filter-group");
      var items = targetSel ? $$(targetSel) : [];
      if (!pills.length || !items.length) return;
      pills.forEach(function (pill) {
        pill.addEventListener("click", function () {
          var value = pill.getAttribute("data-filter");
          pills.forEach(function (p) { p.classList.toggle("is-active", p === pill); p.setAttribute("aria-current", p === pill ? "true" : "false"); });
          items.forEach(function (item) {
            var cats = (item.getAttribute("data-category") || "").split(",");
            var show = value === "all" || cats.indexOf(value) !== -1;
            item.style.display = show ? "" : "none";
          });
        });
      });
    });
  }

  /* -----------------------------------------------------------
     Stripe checkout buttons — until real Payment Link URLs are
     pasted in, clicking shows an inline note instead of a dead link.
     ----------------------------------------------------------- */
  function initStripeCheckout() {
    $$("[data-stripe-checkout]").forEach(function (a) {
      a.addEventListener("click", function (e) {
        var href = a.getAttribute("href") || "";
        if (href.indexOf("PENDING_") === -1) return; // real Stripe link — let it navigate
        e.preventDefault();
        var note = a.parentElement ? a.parentElement.querySelector("[data-stripe-pending-note]") : null;
        if (note) note.hidden = false;
      });
    });
  }

  function boot() {
    safe(initNav, "initNav");
    safe(initAnchorScroll, "initAnchorScroll");
    safe(initReveals, "initReveals");
    safe(initNewsletterForms, "initNewsletterForms");
    safe(initContactForms, "initContactForms");
    safe(mountYear, "mountYear");
    safe(initFilters, "initFilters");
    safe(initStripeCheckout, "initStripeCheckout");
    document.documentElement.classList.add("is-ready");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
