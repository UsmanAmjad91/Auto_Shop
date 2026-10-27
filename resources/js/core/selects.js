import TomSelect from 'tom-select';
import { api, esc } from './http';

/**
 * <select data-remote="customers" data-params='{"for_sale":1}' data-depends="#customer_id">
 * Remote searchable dropdown backed by /lookup/{type}. Selected option data
 * (price, cost, available ...) is exposed through the "remote:selected" event.
 */
export function initSelects(root = document) {
    root.querySelectorAll('select[data-remote]:not(.tomselected)').forEach(sel => {
        const type = sel.dataset.remote;
        const ts = new TomSelect(sel, {
            valueField: 'id', labelField: 'text', searchField: ['text', 'sub'],
            preload: 'focus', loadThrottle: 300, maxOptions: 50,
            allowEmptyOption: false, placeholder: sel.dataset.placeholder || 'Search…',
            create: sel.dataset.create ? createViaModal(sel) : false,
            plugins: sel.multiple ? ['remove_button'] : (sel.required ? [] : ['clear_button']),
            load: async (query, cb) => {
                try {
                    const params = new URLSearchParams({ q: query, ...(JSON.parse(sel.dataset.params || '{}')) });
                    if (sel.dataset.depends) {
                        const dep = document.querySelector(sel.dataset.depends);
                        if (dep?.value) params.set(dep.name.replace(/\[\]$/, ''), dep.value);
                    }
                    const json = await api(`/lookup/${type}?${params}`);
                    cb(json.results);
                } catch { cb(); }
            },
            render: {
                option: (d) => `<div><div>${esc(d.text)}</div>${d.sub ? `<div class="sub">${esc(d.sub)}</div>` : ''}</div>`,
                item: (d) => `<div>${esc(d.text)}</div>`,
                no_results: () => `<div class="no-results small p-2 text-secondary">No matches${sel.dataset.create ? ' — type a name and choose <strong>Add…</strong>, or use <strong>+ New</strong>' : ''}</div>`,
            },
            onChange(value) {
                sel.dispatchEvent(new CustomEvent('remote:selected', { detail: value ? this.options[value] : null, bubbles: true }));
            },
        });
        if (sel.dataset.depends) {
            document.querySelector(sel.dataset.depends)?.addEventListener('change', () => { ts.clearOptions(); ts.load(''); });
        }
    });

    root.querySelectorAll('select[data-searchable]:not(.tomselected)').forEach(sel => new TomSelect(sel, {
        allowEmptyOption: true, maxOptions: 500, plugins: sel.multiple ? ['remove_button'] : [],
    }));
}

/** "Quick add" support: opens the quick-create modal and hands the created record back. */
function createViaModal(sel) {
    return (input, callback) => {
        const modalEl = document.querySelector(sel.dataset.create);
        if (!modalEl) return callback();
        const form = modalEl.querySelector('form');
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        const first = form.querySelector('[name="first_name"], [name="name"]');
        if (first && input) first.value = input;
        let done = false;
        const finish = (data) => {
            if (done) return;
            done = true;
            form.removeEventListener('ajax:success', onSuccess);
            callback(data);
        };
        const onSuccess = (e) => finish(e.detail);
        form.addEventListener('ajax:success', onSuccess);
        modalEl.addEventListener('hidden.bs.modal', () => finish(), { once: true });
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setTimeout(() => first?.focus(), 300);
    };
}

/** Open the quick-create modal for a remote select and select the new record. */
export function openQuickCreate(sel, input = '') {
    const ts = sel.tomselect;
    createViaModal(sel)(input, (data) => {
        if (!data || !ts) return;
        ts.addOption(data);
        ts.addItem(String(data.id));
    });
}

// "+ New" links rendered next to remote-select labels.
document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-quick-for]');
    if (!link) return;
    e.preventDefault();
    const sel = document.getElementById(link.dataset.quickFor);
    if (sel) openQuickCreate(sel);
});
