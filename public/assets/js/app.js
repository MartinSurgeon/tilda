/* RUMA IT Support — progressive enhancement. The app works without JS;
   this adds inline validation, dialogs, loading states and live updates. */
(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const ICON_ALERT = '<svg class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';

  window.RUMA = {
    csrf: () => $('meta[name="csrf-token"]')?.content || '',
    base: () => ($('meta[name="base-path"]')?.content || '/').replace(/\/$/, ''),
  };

  /* ---------------------------------------------------------------- Dialogs */
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-open-dialog]');
    if (opener) {
      const dlg = document.getElementById(opener.dataset.openDialog);
      if (dlg?.showModal) dlg.showModal();
      return;
    }
    if (e.target.closest('[data-close-dialog]')) {
      e.target.closest('dialog')?.close();
      return;
    }
    // Click on the backdrop (the dialog element itself) closes it.
    if (e.target instanceof HTMLDialogElement) e.target.close();
  });

  /* ------------------------------------------- Dropdowns (<details>) */
  document.addEventListener('click', (e) => {
    $$('details[data-dropdown][open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    $$('details[data-dropdown][open]').forEach((d) => { d.removeAttribute('open'); $('summary', d)?.focus(); });
  });

  /* ---------------------------------------------------------- Flash dismiss */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-dismiss]');
    if (btn) btn.closest('[data-flash]')?.remove();
  });

  /* ------------------------------------------------------- Password toggle */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    const input = document.getElementById(btn.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });

  /* ------------------------------------------------- Password rules meter */
  $$('[data-password-meter]').forEach((input) => {
    const list = $(`[data-rules-list="${input.dataset.passwordMeter}"]`);
    if (!list) return;
    const min = parseInt(input.getAttribute('minlength') || '12', 10);
    const update = () => {
      const v = input.value;
      const classes = [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z\d]/].filter((r) => r.test(v)).length;
      const results = { length: v.length >= min, mix: classes >= 3 };
      Object.entries(results).forEach(([rule, ok]) => {
        const li = $(`[data-rule="${rule}"]`, list);
        if (!li) return;
        li.classList.toggle('text-green-text', ok);
        li.classList.toggle('text-muted', !ok);
        li.dataset.met = ok ? 'yes' : 'no';
      });
    };
    input.addEventListener('input', update);
    update();
  });

  /* ------------------------------------------------------------ Copy text */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const text = document.getElementById(btn.dataset.copy)?.textContent.trim() || '';
    try {
      await navigator.clipboard.writeText(text);
      const old = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = old; }, 2000);
    } catch { /* clipboard blocked: the text is still selectable */ }
  });

  /* ------------------------------------------------ Confirm destructive */
  const confirmDialog = (() => {
    let dlg;
    return (message, confirmLabel = 'Yes, continue') => new Promise((resolve) => {
      if (!dlg) {
        dlg = document.createElement('dialog');
        dlg.className = 'w-[calc(100%-2rem)] max-w-md rounded-xl p-0 backdrop:bg-ink/40';
        dlg.setAttribute('aria-labelledby', 'confirm-title');
        dlg.innerHTML = `
          <div class="p-6">
            <h2 id="confirm-title" class="text-lg font-semibold">Are you sure?</h2>
            <p class="mt-2 text-sm text-charcoal" data-confirm-message></p>
          </div>
          <div class="flex flex-col-reverse gap-3 border-t border-line px-6 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="btn-ghost" value="cancel">Cancel</button>
            <button type="button" class="btn-danger" value="ok" data-confirm-ok></button>
          </div>`;
        document.body.appendChild(dlg);
      }
      $('[data-confirm-message]', dlg).textContent = message;
      $('[data-confirm-ok]', dlg).textContent = confirmLabel;
      const done = (result) => { dlg.close(); resolve(result); };
      dlg.onclick = (e) => {
        const b = e.target.closest('button');
        if (b) done(b.value === 'ok');
        else if (e.target === dlg) done(false);
      };
      dlg.oncancel = (e) => { e.preventDefault(); done(false); };
      dlg.showModal();
      $('button[value="cancel"]', dlg).focus(); // safe default
    });
  })();

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches('[data-confirm]') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    if (await confirmDialog(form.dataset.confirm, form.dataset.confirmLabel)) {
      form.dataset.confirmed = '1';
      form.requestSubmit(e.submitter || undefined);
    }
  }, true);

  /* ------------------------------------------------- Inline validation */
  const messageFor = (input) => {
    const v = input.value.trim();
    if (input.required && v === '') return input.dataset.msgRequired || 'This field is required.';
    if (v === '') return null;
    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return input.dataset.msgType || 'Please enter a valid email address.';
    const min = parseInt(input.getAttribute('minlength') || '0', 10);
    if (min && input.value.length < min) return input.dataset.msgMin || `Please use at least ${min} characters.`;
    if (input.dataset.match) {
      const other = document.getElementById(input.dataset.match);
      if (other && other.value !== input.value) return input.dataset.msgMatch || 'The values do not match.';
    }
    return null;
  };

  const setError = (input, message) => {
    const id = `${input.id}-error`;
    let el = document.getElementById(id);
    const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    if (message) {
      if (!el) {
        el = document.createElement('p');
        el.id = id;
        el.className = 'field-error';
        (input.closest('.relative') || input).insertAdjacentElement('afterend', el);
      }
      el.innerHTML = `${ICON_ALERT}<span></span>`;
      el.lastElementChild.textContent = message;
      input.setAttribute('aria-invalid', 'true');
      describedBy.add(id);
    } else {
      el?.remove();
      input.removeAttribute('aria-invalid');
      describedBy.delete(id);
    }
    if (describedBy.size) input.setAttribute('aria-describedby', [...describedBy].join(' '));
    else input.removeAttribute('aria-describedby');
  };

  /* Required radio groups (e.g. ticket category tiles). Error sits after the group. */
  const radioGroupError = (form, name) => {
    const radios = $$(`input[type="radio"][name="${name}"]`, form);
    if (!radios.length || !radios[0].required) return null;
    const fieldset = radios[0].closest('fieldset');
    const id = `${name}-error`;
    let el = document.getElementById(id);
    if (radios.some((r) => r.checked)) {
      el?.remove();
      fieldset?.removeAttribute('aria-describedby');
      return null;
    }
    if (!el) {
      el = document.createElement('p');
      el.id = id;
      el.className = 'field-error';
      el.innerHTML = `${ICON_ALERT}<span>Please choose one option.</span>`;
      (fieldset || radios[radios.length - 1].parentElement).appendChild(el);
    }
    fieldset?.setAttribute('aria-describedby', id);
    return radios[0];
  };

  /* File inputs: catch too many / too large before a slow upload. */
  const fileError = (input) => {
    const max = parseInt(input.dataset.maxFiles || '0', 10);
    const bytes = parseInt(input.dataset.maxBytes || '0', 10);
    const files = Array.from(input.files || []);
    if (max && files.length > max) return `You can attach up to ${max} files.`;
    const big = files.find((f) => bytes && f.size > bytes);
    if (big) return `“${big.name}” is too large. The limit is ${Math.round(bytes / 1048576)} MB per file.`;
    return null;
  };
  /* Per-element behaviour lives in enhance(root) so refreshed live regions get it too. */
  const enhance = (root) => {
  $$('input[type="file"][data-max-files]', root).forEach((input) => {
    input.addEventListener('change', () => setError(input, fileError(input)));
  });

  $$('form[data-validate]', root).forEach((form) => {
    const radioNames = [...new Set($$('input[type="radio"][required]', form).map((r) => r.name))];
    radioNames.forEach((name) => $$(`input[name="${name}"]`, form).forEach((r) => {
      r.addEventListener('change', () => radioGroupError(form, name));
    }));
    form.addEventListener('submit', (e) => {
      const firstBadRadio = radioNames.map((n) => radioGroupError(form, n)).find(Boolean);
      const badFile = $$('input[type="file"][data-max-files]', form).find((i) => { const m = fileError(i); setError(i, m); return m; });
      const target = firstBadRadio || badFile;
      if (target) {
        e.preventDefault(); // field checks below still run, so every error shows at once
        target.focus();
      }
    });
  });

  $$('form[data-validate]', root).forEach((form) => {
    const fields = $$('input[id], select[id], textarea[id]', form).filter((f) => f.type !== 'hidden' && f.type !== 'file');
    fields.forEach((f) => {
      // Validate on blur (not while typing) — kinder than shouting mid-word.
      f.addEventListener('blur', () => { if (f.value !== '' || f.hasAttribute('aria-invalid')) setError(f, messageFor(f)); });
      f.addEventListener('input', () => { if (f.hasAttribute('aria-invalid')) setError(f, messageFor(f)); });
    });
    form.addEventListener('submit', (e) => {
      const focused = e.defaultPrevented;
      const invalid = fields.filter((f) => { const m = messageFor(f); setError(f, m); return m; });
      if (invalid.length) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!focused) invalid[0].focus();
      }
    });
  });

  /* Inline disclosures: focus the field they reveal. */
  $$('details[data-disclosure]', root).forEach((d) => {
    d.addEventListener('toggle', () => {
      if (d.open) $('textarea, input:not([type="hidden"]), select', d)?.focus();
    });
  });
  };
  enhance(document);

  /* ------------------------------------------- Submit loading state */
  document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const btn = e.submitter || $('button[type="submit"]', e.target);
    if (!btn || !btn.dataset.loadingText) return;
    // Defer so the button's value is still submitted.
    setTimeout(() => {
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.textContent = btn.dataset.loadingText;
    }, 0);
  });

  /* ------------------------------- Sub-category follows chosen category */
  const sub = $('[data-subcategory]');
  if (sub) {
    const groups = $$('optgroup', sub).map((g) => ({ parent: g.dataset.parent, options: $$('option', g).map((o) => o.cloneNode(true)) }));
    const wrap = $('[data-subcategory-wrap]');
    const blank = sub.querySelector('option[value=""]').cloneNode(true);
    const render = () => {
      const chosen = $('input[name="category_id"]:checked');
      const group = groups.find((g) => g.parent === chosen?.value);
      const keep = sub.value;
      sub.replaceChildren(blank.cloneNode(true), ...(group ? group.options.map((o) => o.cloneNode(true)) : []));
      sub.value = group && group.options.some((o) => o.value === keep) ? keep : '';
      // Progressive disclosure: only ask once a category with sub-types is chosen.
      wrap.hidden = !group;
    };
    $$('input[name="category_id"]').forEach((r) => r.addEventListener('change', render));
    render();
  }

  /* ================================================== Trend chart (SVG) */
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const svgEl = (name, attrs = {}, parent) => {
    const node = document.createElementNS(SVG_NS, name);
    Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, String(v)));
    if (parent) parent.appendChild(node);
    return node;
  };
  const niceStep = (max) => {
    if (max <= 5) return 1;
    if (max <= 10) return 2;
    if (max <= 25) return 5;
    const mag = 10 ** Math.floor(Math.log10(max / 4));
    return [1, 2, 5, 10].map((f) => f * mag).find((s) => max / s <= 5) || mag * 10;
  };

  const drawChart = (box) => {
    let data;
    try { data = JSON.parse(box.dataset.chart); } catch { return; }
    const W = box.clientWidth;
    if (!W) return;
    const H = W < 480 ? 200 : 240;
    const m = { t: 12, r: 40, b: 28, l: 32 };
    const iw = W - m.l - m.r;
    const ih = H - m.t - m.b;
    const n = data.labels.length;
    const max = Math.max(1, ...data.series.flatMap((s) => s.values));
    const step = niceStep(max);
    const top = Math.ceil(max / step) * step;
    const x = (i) => m.l + (n === 1 ? iw / 2 : (i * iw) / (n - 1));
    const y = (v) => m.t + ih - (v / top) * ih;

    $('svg', box)?.remove();
    const svg = svgEl('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, 'aria-hidden': 'true', focusable: 'false' });

    // Recessive hairline grid + clean integer ticks.
    for (let v = 0; v <= top; v += step) {
      svgEl('line', { class: 'chart-grid', x1: m.l, x2: W - m.r, y1: y(v), y2: y(v) }, svg);
      svgEl('text', { class: 'chart-axis', x: m.l - 8, y: y(v) + 4, 'text-anchor': 'end' }, svg).textContent = v.toLocaleString();
    }
    // X labels thinned so they never collide; the last day is always labelled.
    const every = Math.max(1, Math.ceil(64 / (iw / Math.max(1, n - 1))));
    data.labels.forEach((label, i) => {
      const isLast = i === n - 1;
      if (!(i % every === 0 || isLast) || (!isLast && n - 1 - i < every)) return;
      svgEl('text', { class: 'chart-axis', x: x(i), y: H - 8, 'text-anchor': isLast ? 'end' : (i === 0 ? 'start' : 'middle') }, svg).textContent = label;
    });

    // 2px lines, ringed end dots, value labels at the end (nudged apart if close).
    const ends = [];
    data.series.forEach((s) => {
      const d = s.values.map((v, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
      svgEl('path', { class: `chart-line series-${s.key}`, d }, svg);
      const last = s.values[n - 1];
      svgEl('circle', { class: `chart-dot series-${s.key}`, cx: x(n - 1), cy: y(last), r: 4 }, svg);
      ends.push({ y: y(last), v: last });
    });
    if (ends.length === 2 && Math.abs(ends[0].y - ends[1].y) < 14) {
      const [a, b] = ends[0].y <= ends[1].y ? [ends[0], ends[1]] : [ends[1], ends[0]];
      const mid = (a.y + b.y) / 2;
      a.y = mid - 7; b.y = mid + 7;
    }
    ends.forEach((e) => {
      svgEl('text', { class: 'chart-value', x: W - m.r + 10, y: e.y + 4 }, svg).textContent = e.v.toLocaleString();
    });

    // Hover layer: crosshair snaps to the nearest day; one tooltip lists every series.
    const cross = svgEl('g', { visibility: 'hidden' }, svg);
    svgEl('line', { class: 'chart-crosshair', y1: m.t, y2: m.t + ih, x1: 0, x2: 0 }, cross);
    const hoverDots = data.series.map((s) => svgEl('circle', { class: `chart-dot series-${s.key}`, r: 4, cx: 0, cy: 0 }, cross));
    const hit = svgEl('rect', { x: m.l - 8, y: 0, width: iw + 16, height: H, fill: 'transparent' }, svg);
    box.prepend(svg);

    let tip = $('.chart-tooltip', box);
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'chart-tooltip';
      tip.hidden = true;
      box.appendChild(tip);
    }
    const show = (i) => {
      box.dataset.index = String(i);
      cross.setAttribute('visibility', 'visible');
      $('line', cross).setAttribute('x1', x(i));
      $('line', cross).setAttribute('x2', x(i));
      hoverDots.forEach((dot, si) => { dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(data.series[si].values[i])); });
      // Built with textContent: labels are data, never markup.
      tip.replaceChildren();
      const head = document.createElement('p');
      head.className = 'mb-1 text-xs text-muted';
      head.textContent = data.labels[i];
      tip.appendChild(head);
      data.series.forEach((s) => {
        const row = document.createElement('p');
        row.className = 'flex items-center gap-2';
        const key = document.createElement('span');
        key.className = `legend-line legend-${s.key}`;
        const val = document.createElement('strong');
        val.className = 'text-ink';
        val.textContent = s.values[i].toLocaleString();
        const name = document.createElement('span');
        name.className = 'text-muted';
        name.textContent = s.name;
        row.append(key, val, name);
        tip.appendChild(row);
      });
      tip.hidden = false;
      const left = Math.min(Math.max(0, x(i) + 12), W - tip.offsetWidth);
      tip.style.left = `${x(i) + 12 + tip.offsetWidth > W ? Math.max(0, x(i) - 12 - tip.offsetWidth) : left}px`;
      tip.style.top = '0px';
    };
    const hide = () => { cross.setAttribute('visibility', 'hidden'); tip.hidden = true; delete box.dataset.index; };
    const nearest = (clientX) => {
      const px = clientX - svg.getBoundingClientRect().left;
      return Math.max(0, Math.min(n - 1, Math.round(((px - m.l) / iw) * (n - 1))));
    };
    hit.addEventListener('pointermove', (e) => show(nearest(e.clientX)));
    hit.addEventListener('pointerleave', hide);
    box.onkeydown = (e) => {
      const cur = box.dataset.index === undefined ? n : parseInt(box.dataset.index, 10);
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(Math.max(0, Math.min(cur, n) - 1)); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); show(Math.min(n - 1, cur === n ? n - 1 : cur + 1)); }
      else if (e.key === 'Escape') hide();
    };
    box.onblur = hide;
  };

  const chartObserver = 'ResizeObserver' in window
    ? new ResizeObserver((entries) => entries.forEach((en) => drawChart(en.target)))
    : null;
  const initCharts = (root) => $$('[data-chart]', root).forEach((box) => {
    drawChart(box);
    chartObserver?.observe(box);
  });
  initCharts(document);

  /* =================================================== Notification sound */
  // Plays the hospital's chosen sound (public/assets/audio/notification.mp3).
  // Browsers only let a page play sound after the person has clicked, tapped
  // or typed on THAT page, so: (1) the sound is primed on the first
  // interaction with every page, and (2) if a sound is still blocked, it is
  // kept and the "Turn on sound" button appears by the bell. Clicking it (or
  // anywhere) plays the missed sound, so an alert is never silently lost.
  const soundOn = $('meta[name="notify-sound"]')?.content === 'on';
  const soundSrc = $('meta[name="notify-sound-src"]')?.content || '';

  // One sound per new notification, even with several tabs open: the newest id
  // that has already sounded is shared between tabs through localStorage.
  const sharedLastChimed = {
    get: () => { try { return parseInt(localStorage.getItem('ruma-last-chimed') || '0', 10); } catch { return 0; } },
    set: (id) => { try { localStorage.setItem('ruma-last-chimed', String(id)); } catch { /* private mode: per-tab only */ } },
  };

  const sound = (() => {
    let audio = null;
    let unlocked = false;
    let pending = null; // { id, kind, at }
    const el = () => {
      if (!audio && soundSrc) {
        audio = new Audio(soundSrc);
        audio.preload = 'auto';
      }
      return audio;
    };
    const showBlocked = (blocked) => $$('[data-sound-blocked]').forEach((b) => { b.hidden = !blocked; });

    const start = async (kind) => {
      const a = el();
      if (!a) return 'none';
      // Urgent (Critical tickets, SLA warnings) plays twice at full volume.
      let repeats = kind === 'urgent' ? 1 : 0;
      a.onended = () => {
        if (repeats-- > 0) { a.currentTime = 0; a.play().catch(() => {}); }
      };
      a.muted = false;
      a.volume = kind === 'urgent' ? 1 : 0.8;
      a.currentTime = 0;
      try {
        await a.play();
        unlocked = true;
        showBlocked(false);
        return 'played';
      } catch (err) {
        return err && err.name === 'NotAllowedError' ? 'blocked' : 'error';
      }
    };

    // Runs on every real interaction: plays a missed sound, or primes the
    // audio so the next notification is allowed to play.
    const unlock = () => {
      if (pending && Date.now() - pending.at < 10 * 60 * 1000) {
        const p = pending;
        pending = null;
        if (p.id === 0 || p.id > sharedLastChimed.get()) {
          start(p.kind).then((r) => { if (r === 'played' && p.id) sharedLastChimed.set(p.id); });
        }
        return;
      }
      if (unlocked || !soundOn) return;
      const a = el();
      if (!a) return;
      a.muted = true;
      a.play().then(() => { a.pause(); a.currentTime = 0; a.muted = false; unlocked = true; showBlocked(false); })
        .catch(() => { a.muted = false; });
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach((t) => document.addEventListener(t, unlock, { passive: true }));

    return {
      /**
       * Ask the browser now, silently, whether this page may play sound.
       * Volume 0 still counts as "audible" to the autoplay rules, so a refusal
       * here means a real notification would be blocked too: show the button
       * straight away instead of after an alert has been missed.
       */
      check() {
        const a = el();
        if (!a || unlocked) return;
        a.muted = false;
        a.volume = 0;
        a.play()
          .then(() => { a.pause(); a.currentTime = 0; unlocked = true; showBlocked(false); })
          .catch((err) => { if (err && err.name === 'NotAllowedError') showBlocked(true); });
      },
      /** @returns {Promise<'played'|'blocked'|'error'|'none'>} */
      async play(kind, id = 0) {
        const r = await start(kind);
        if (r === 'blocked') {
          pending = { id, kind, at: Date.now() };
          showBlocked(true);
        }
        return r;
      },
    };
  })();

  if (soundOn) sound.check();

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-test-sound]');
    if (btn) sound.play(btn.dataset.testSound);
  });

  let lastSeenId = parseInt($('meta[name="last-notification"]')?.content || '0', 10);
  const maybeChime = async (latest) => {
    if (!latest || latest.id <= lastSeenId) return;
    lastSeenId = latest.id;
    if (!soundOn || latest.id <= sharedLastChimed.get()) return;
    sharedLastChimed.set(latest.id); // claim it first so other tabs stay quiet
    const r = await sound.play(latest.urgent ? 'urgent' : 'normal', latest.id);
    if (r === 'blocked') sharedLastChimed.set(latest.id - 1); // let an unlocked tab, or this one later, play it
  };

  /* ============================================= Live updates (polling) */
  // Polling, not SSE: on shared hosting a held-open request ties up a PHP
  // worker per open tab. A small request every few seconds scales fine.
  const metaContent = (name) => $(`meta[name="${name}"]`)?.content;
  let version = metaContent('live-version');
  const intervalMs = Math.max(5, parseInt(metaContent('poll-interval') || '15', 10)) * 1000;

  let announcer = $('[data-live-stamp]');
  if (!announcer && version) {
    announcer = document.createElement('p');
    announcer.className = 'sr-only';
    announcer.setAttribute('aria-live', 'polite');
    document.body.appendChild(announcer);
  }

  // Every unread counter on the page moves together: top-bar bell, mobile
  // "Alerts", sidebar and menu badges, the Notifications heading, and the
  // browser tab title, e.g. "(7) Dashboard", so a background tab shows it too.
  const baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
  const setBell = (count) => {
    const text = count > 99 ? '99+' : String(count);
    $$('[data-bell-count]').forEach((el) => {
      el.textContent = text;
      el.classList.toggle('hidden', count === 0);
    });
    $$('[data-bell-count-nav]').forEach((el) => {
      el.textContent = text;
      el.parentElement.hidden = count === 0;
    });
    $$('[data-unread-text]').forEach((el) => { el.textContent = count ? `${count} unread` : 'You are all caught up'; });
    $$('[data-unread-action]').forEach((el) => { el.hidden = count === 0; });
    $('[data-bell]')?.setAttribute('aria-label', count ? `Notifications, ${count} unread` : 'Notifications');
    document.title = count ? `(${text}) ${baseTitle}` : baseTitle;
  };
  setBell(parseInt($('[data-bell-count]')?.textContent || '0', 10) || 0);

  // Never pull the rug from under someone: skip a region while they are using it.
  const isBusy = (region) => {
    const active = document.activeElement;
    if (active && active !== document.body && region.contains(active)) return true;
    if ($$('details[open]', region).some((d) => $('form', d))) return true;
    return $$('textarea, input[type="text"], input[type="search"], input:not([type])', region).some((f) => f.value !== f.defaultValue);
  };

  let refreshing = false;
  const refreshLive = async () => {
    const regions = $$('[data-live][id]');
    if (!regions.length) return { done: true, changed: 0 };
    if (refreshing) return { done: false, changed: 0 };
    refreshing = true;
    try {
      const res = await fetch(window.location.href, { headers: { 'X-Background': '1' }, credentials: 'same-origin' });
      if (!res.ok || res.redirected) return { done: false, changed: 0 };
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      let done = true;
      let changed = 0;
      regions.forEach((region) => {
        const fresh = doc.getElementById(region.id);
        if (!fresh) return;
        if (isBusy(region)) { done = false; return; }
        if (region.innerHTML !== fresh.innerHTML) {
          region.innerHTML = fresh.innerHTML; // same-origin, server-escaped markup
          enhance(region);
          initCharts(region);
          changed++;
        }
        region.hidden = fresh.hidden;
      });
      return { done, changed };
    } finally {
      refreshing = false;
    }
  };

  let timer = null;
  let lastPollAt = 0;
  const poll = async () => {
    if (!version) return;
    // Hidden tabs: only keep listening if the chime is on, and then less often.
    if (document.hidden) {
      if (!soundOn || Date.now() - lastPollAt < 30000) return;
    }
    lastPollAt = Date.now();
    try {
      const res = await fetch(`${window.RUMA.base()}/api/poll`, {
        headers: { 'X-Background': '1', Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.status === 401 || res.status === 403) { clearInterval(timer); return; } // signed out: stop quietly
      if (!res.ok) return;
      const data = await res.json();
      setBell(data.unread);
      maybeChime(data.latest);
      if (data.version !== version && !document.hidden) { // refresh content when someone can see it
        const { done, changed } = await refreshLive();
        if (done) version = data.version; // otherwise retry on the next tick
        if (changed && announcer) {
          announcer.textContent = `Updated at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        }
      }
    } catch { /* offline or server busy: try again next tick */ }
  };
  if (version) {
    timer = setInterval(poll, intervalMs);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  }

  /* -------------------------------------------- Focus server error summary */
  const summary = $('[data-error-summary]');
  if (summary) summary.focus();
  else $('[aria-invalid="true"]')?.focus();
})();
