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
            allowEmptyOption: true, placeholder: sel.dataset.placeholder || 'Search…',
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
                no_results: () => '<div class="no-results small p-2 text-secondary">No matches</div>',
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

/** "Quick add" support: opens the quick-create modal and adds the result. */
function createViaModal(sel) {
    return (input, callback) => {
        const modalEl = document.querySelector(sel.dataset.create);
        if (!modalEl) return callback();
        const form = modalEl.querySelector('form');
        form.reset();
        const first = form.querySelector('[name="first_name"], [name="name"]');
        if (first) first.value = input;
        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        const onSuccess = (e) => { callback(e.detail); form.removeEventListener('ajax:success', onSuccess); };
        form.addEventListener('ajax:success', onSuccess);
        modalEl.addEventListener('hidden.bs.modal', () => callback(), { once: true });
        modal.show();
    };
}
