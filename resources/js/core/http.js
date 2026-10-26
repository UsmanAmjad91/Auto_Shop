/**
 * Fetch helpers with CSRF, JSON handling, toasts and validation error display.
 */
export const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export function toast(message, type = 'success', delay = 4500) {
    const container = document.getElementById('toasts');
    if (!container) return alert(message);
    const el = document.createElement('div');
    const color = { success: 'text-bg-success', error: 'text-bg-danger', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-info' }[type] || 'text-bg-secondary';
    el.className = `toast align-items-center border-0 ${color}`;
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    container.appendChild(el);
    const t = new window.bootstrap.Toast(el, { delay });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
}

export class HttpError extends Error {
    constructor(message, status, errors = {}) { super(message); this.status = status; this.errors = errors; }
}

/** api(url, {method, data, form}) -> parsed JSON; throws HttpError. */
export async function api(url, { method = 'GET', data = null, form = null, signal } = {}) {
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    let body = null;
    if (form) {
        body = form instanceof FormData ? form : new FormData(form);
    } else if (data && method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }
    // Laravel method spoofing for multipart PUT/DELETE
    if (body instanceof FormData && !['GET', 'POST'].includes(method)) {
        body.append('_method', method);
        method = 'POST';
    }
    const res = await fetch(url, { method, headers, body, signal, credentials: 'same-origin' });
    let json = null;
    try { json = await res.json(); } catch { /* non-JSON */ }
    if (!res.ok) {
        if (res.status === 419) throw new HttpError('Your session expired. Please reload the page.', 419);
        const msg = json?.message || (res.status === 403 ? 'You are not authorised to do that.' : `Request failed (${res.status}).`);
        throw new HttpError(msg, res.status, json?.errors || {});
    }
    return json;
}

export function showErrors(form, errors) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback.ajax').forEach(el => el.remove());
    Object.entries(errors || {}).forEach(([key, messages]) => {
        const name = key.includes('.') ? key.split('.').map((p, i) => i === 0 ? p : `[${p}]`).join('') : key;
        const input = form.querySelector(`[name="${name}"], [name="${name}[]"], [name="${key.replace(/\./g, '__')}"]`);
        if (input) {
            input.classList.add('is-invalid');
            const fb = document.createElement('div');
            fb.className = 'invalid-feedback ajax';
            fb.textContent = messages[0];
            (input.closest('.input-group') || input.closest('.ts-wrapper') || input).insertAdjacentElement('afterend', fb);
        }
    });
}

export function handleError(err, form = null) {
    if (form && err.errors) showErrors(form, err.errors);
    const first = err.errors && Object.values(err.errors)[0];
    toast(first ? first[0] : err.message, 'error', 7000);
}

/** <form data-ajax> submits via fetch; data-reload / data-redirect / data-table-reload control the follow-up. */
export function initAjaxForms(root = document) {
    root.querySelectorAll('form[data-ajax]:not([data-ajax-bound])').forEach(form => {
        form.dataset.ajaxBound = '1';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;
            const btn = form.querySelector('[type="submit"]');
            btn?.setAttribute('disabled', 'disabled');
            try {
                const method = (form.querySelector('[name="_method"]')?.value || form.method || 'POST').toUpperCase();
                const json = await api(form.action, { method: method === 'GET' ? 'GET' : 'POST', form });
                showErrors(form, {});
                toast(json?.message || 'Saved.');
                const modal = form.closest('.modal');
                if (modal) window.bootstrap.Modal.getInstance(modal)?.hide();
                if (json?.redirect && form.dataset.redirect !== 'false') return void (window.location = json.redirect);
                if (form.dataset.tableReload) document.querySelectorAll(form.dataset.tableReload).forEach(t => t.dispatchEvent(new CustomEvent('dt:reload')));
                if (form.hasAttribute('data-reload')) setTimeout(() => window.location.reload(), 400);
                if (form.hasAttribute('data-reset')) form.reset();
                form.dispatchEvent(new CustomEvent('ajax:success', { detail: json }));
            } catch (err) {
                handleError(err, form);
            } finally {
                btn?.removeAttribute('disabled');
            }
        });
    });
}

/** Buttons/links with data-method perform a request (with optional confirm) then reload. */
export function initActionLinks(root = document) {
    root.addEventListener('click', async (e) => {
        const el = e.target.closest('[data-method]');
        if (!el || el.closest('.dt-wrap')) return;
        e.preventDefault();
        if (el.dataset.confirm && !confirm(el.dataset.confirm)) return;
        try {
            const json = await api(el.getAttribute('href') || el.dataset.url, { method: el.dataset.method.toUpperCase(), data: el.dataset.payload ? JSON.parse(el.dataset.payload) : {} });
            toast(json?.message || 'Done.');
            if (json?.redirect) window.location = json.redirect; else setTimeout(() => window.location.reload(), 400);
        } catch (err) { handleError(err); }
    });
}

export const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

export const fmtMoney = (v) => {
    if (v === null || v === undefined || v === '') return '';
    const sym = document.body.dataset.currency || '$';
    const n = Number(v);
    return (n < 0 ? '-' : '') + sym + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
