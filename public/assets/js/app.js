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

  $$('form[data-validate]').forEach((form) => {
    const fields = $$('input[id], select[id], textarea[id]', form).filter((f) => f.type !== 'hidden');
    fields.forEach((f) => {
      // Validate on blur (not while typing) — kinder than shouting mid-word.
      f.addEventListener('blur', () => { if (f.value !== '' || f.hasAttribute('aria-invalid')) setError(f, messageFor(f)); });
      f.addEventListener('input', () => { if (f.hasAttribute('aria-invalid')) setError(f, messageFor(f)); });
    });
    form.addEventListener('submit', (e) => {
      const invalid = fields.filter((f) => { const m = messageFor(f); setError(f, m); return m; });
      if (invalid.length) {
        e.preventDefault();
        e.stopImmediatePropagation();
        invalid[0].focus();
      }
    });
  });

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

  /* -------------------------------------------- Focus server error summary */
  const summary = $('[data-error-summary]');
  if (summary) summary.focus();
  else $('[aria-invalid="true"]')?.focus();
})();
