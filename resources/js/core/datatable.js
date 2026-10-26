import { api, toast, handleError, debounce, fmtMoney, esc } from './http';

/**
 * Server-side data table bound to <div data-datatable ...>.
 *
 * data-url        JSON endpoint (DataTable::response)
 * data-module     key for saved presets / column prefs
 * data-columns    JSON [{key,label,type,sortable,hidden,card}]
 * data-sort/dir   default sort
 * data-bulk-url / data-bulk  JSON [{action,label,confirm,options:{value:label}}]
 * data-export-url export endpoint (format=csv|xlsx|pdf|print)
 * data-cards      enable card/image view
 * data-sync-url   keep filters in the browser URL (default on)
 */
export class DataTable {
    constructor(el) {
        this.el = el;
        this.url = el.dataset.url;
        this.module = el.dataset.module || 'list';
        this.columns = JSON.parse(el.dataset.columns || '[]');
        this.bulk = JSON.parse(el.dataset.bulk || '[]');
        this.sync = el.dataset.syncUrl !== 'false';
        this.filtersForm = document.querySelector(`[data-dt-filters="#${el.id}"]`);
        const params = new URLSearchParams(this.sync ? window.location.search : '');
        this.state = {
            q: params.get('q') || '',
            sort: params.get('sort') || el.dataset.sort || '',
            dir: params.get('dir') || el.dataset.dir || 'desc',
            per_page: params.get('per_page') || el.dataset.perPage || document.body.dataset.perPage || '25',
            page: params.get('page') || '1',
            view: params.get('view') || this.pref('view') || 'table',
        };
        this.hidden = new Set(this.pref('cols') || this.columns.filter(c => c.hidden).map(c => c.key));
        this.selected = new Set();
        this.abort = null;
        this.rows = [];
        this.render();
        this.applyParamsToFilters(params);
        this.bind();
        this.load();
    }

    pref(key, value) {
        const k = `dt:${this.module}:${key}`;
        try {
            if (value === undefined) return JSON.parse(localStorage.getItem(k) || 'null');
            localStorage.setItem(k, JSON.stringify(value));
        } catch { return null; }
    }

    render() {
        const hasCards = this.el.hasAttribute('data-cards');
        const exportUrl = this.el.dataset.exportUrl;
        this.el.classList.add('dt-wrap', 'card');
        this.el.innerHTML = `
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group input-group-sm" style="max-width:320px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control dt-search" placeholder="Search…" aria-label="Search" value="${esc(this.state.q)}">
            </div>
            ${this.bulk.length ? `<div class="dropdown dt-bulk d-none">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Bulk (<span class="dt-sel-count">0</span>)</button>
                <ul class="dropdown-menu">${this.bulk.map((b, i) => b.options
                    ? `<li><h6 class="dropdown-header">${esc(b.label)}</h6></li>${Object.entries(b.options).map(([v, l]) => `<li><a class="dropdown-item small" href="#" data-bulk="${i}" data-value="${esc(v)}">${esc(l)}</a></li>`).join('')}`
                    : `<li><a class="dropdown-item ${b.danger ? 'text-danger' : ''}" href="#" data-bulk="${i}">${esc(b.label)}</a></li>`).join('')}</ul>
            </div>` : ''}
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Saved filters"><i class="bi bi-bookmark"></i><span class="d-none d-md-inline"> Presets</span></button>
                    <div class="dropdown-menu dropdown-menu-end p-2 dt-presets" style="min-width:240px"></div>
                </div>
                <button class="btn btn-sm btn-outline-secondary dt-reset" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Columns"><i class="bi bi-layout-three-columns"></i></button>
                    <div class="dropdown-menu dropdown-menu-end p-2 dt-cols" style="max-height:60vh;overflow:auto">
                        ${this.columns.filter(c => c.type !== 'actions').map(c => `<div class="form-check"><input class="form-check-input" type="checkbox" id="${this.el.id}-col-${c.key}" data-col="${c.key}" ${this.hidden.has(c.key) ? '' : 'checked'}><label class="form-check-label small" for="${this.el.id}-col-${c.key}">${esc(c.label)}</label></div>`).join('')}
                    </div>
                </div>
                ${hasCards ? `<div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary dt-view" data-view="table" title="Table view"><i class="bi bi-table"></i></button>
                    <button class="btn btn-outline-secondary dt-view" data-view="cards" title="Card view"><i class="bi bi-grid-3x2-gap"></i></button></div>` : ''}
                ${exportUrl ? `<div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i><span class="d-none d-md-inline"> Export</span></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item dt-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                        <li><a class="dropdown-item dt-export" data-format="xlsx" href="#"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
                        <li><a class="dropdown-item dt-export" data-format="pdf" href="#"><i class="bi bi-filetype-pdf me-2"></i>PDF</a></li>
                        <li><a class="dropdown-item dt-export" data-format="print" href="#"><i class="bi bi-printer me-2"></i>Print</a></li>
                    </ul></div>` : ''}
            </div>
        </div>
        <div class="dt-body"></div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center justify-content-between small">
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm dt-per-page" style="width:auto" aria-label="Rows per page">
                    ${[10, 25, 50, 100].map(n => `<option value="${n}" ${String(n) === String(this.state.per_page) ? 'selected' : ''}>${n}</option>`).join('')}
                </select><span class="text-secondary dt-info"></span>
            </div>
            <nav><ul class="pagination pagination-sm mb-0 dt-pages"></ul></nav>
        </div>
        <div class="dt-loading"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>`;
        this.$ = (s) => this.el.querySelector(s);
        this.highlightView();
    }

    bind() {
        this.$('.dt-search').addEventListener('input', debounce((e) => { this.state.q = e.target.value; this.state.page = 1; this.load(); }, 350));
        this.$('.dt-per-page').addEventListener('change', (e) => { this.state.per_page = e.target.value; this.state.page = 1; this.load(); });
        this.$('.dt-reset').addEventListener('click', () => this.reset());
        this.el.querySelectorAll('.dt-view').forEach(b => b.addEventListener('click', () => { this.state.view = b.dataset.view; this.pref('view', b.dataset.view); this.highlightView(); this.draw(); this.pushUrl(); }));
        this.el.querySelectorAll('[data-col]').forEach(cb => cb.addEventListener('change', () => {
            cb.checked ? this.hidden.delete(cb.dataset.col) : this.hidden.add(cb.dataset.col);
            this.pref('cols', [...this.hidden]); this.draw();
        }));
        this.el.querySelectorAll('.dt-export').forEach(a => a.addEventListener('click', (e) => {
            e.preventDefault();
            const p = this.params(); p.delete('page'); p.set('format', a.dataset.format);
            window.open(`${this.el.dataset.exportUrl}?${p}`, a.dataset.format === 'print' ? '_blank' : '_self');
        }));
        this.el.querySelectorAll('[data-bulk]').forEach(a => a.addEventListener('click', (e) => { e.preventDefault(); this.runBulk(this.bulk[a.dataset.bulk], a.dataset.value); }));
        this.el.addEventListener('dt:reload', () => this.load());
        this.el.querySelector('.dt-presets').parentElement.addEventListener('show.bs.dropdown', () => this.loadPresets());

        if (this.filtersForm) {
            const onChange = () => { this.state.page = 1; this.load(); };
            this.filtersForm.addEventListener('change', onChange);
            this.filtersForm.querySelectorAll('input[type="text"],input[type="search"],input[type="number"]').forEach(i => i.addEventListener('input', debounce(onChange, 450)));
            this.filtersForm.addEventListener('submit', (e) => { e.preventDefault(); onChange(); });
        }

        this.el.addEventListener('click', (e) => {
            const th = e.target.closest('th.sortable');
            if (th) {
                const key = th.dataset.sort;
                this.state.dir = this.state.sort === key && this.state.dir === 'asc' ? 'desc' : 'asc';
                this.state.sort = key; this.load();
                return;
            }
            const pg = e.target.closest('[data-page]');
            if (pg) { e.preventDefault(); this.state.page = pg.dataset.page; this.load(); return; }
            const act = e.target.closest('[data-action-idx]');
            if (act) { e.preventDefault(); this.runAction(act); }
        });
        this.el.addEventListener('change', (e) => {
            if (e.target.matches('.dt-check')) { e.target.checked ? this.selected.add(e.target.value) : this.selected.delete(e.target.value); this.updateBulk(); }
            if (e.target.matches('.dt-check-all')) {
                this.el.querySelectorAll('.dt-check').forEach(c => { c.checked = e.target.checked; e.target.checked ? this.selected.add(c.value) : this.selected.delete(c.value); });
                this.updateBulk();
            }
        });
    }

    highlightView() { this.el.querySelectorAll('.dt-view').forEach(b => b.classList.toggle('active', b.dataset.view === this.state.view)); }

    filterValues() {
        const out = {};
        if (!this.filtersForm) return out;
        new FormData(this.filtersForm).forEach((v, k) => { if (v !== '' && v !== null) out[k] = v; });
        return out;
    }

    applyParamsToFilters(params) {
        if (!this.filtersForm) return;
        params.forEach((v, k) => {
            const input = this.filtersForm.querySelector(`[name="${CSS.escape(k)}"]`);
            if (!input) return;
            if (input.type === 'checkbox') input.checked = v === '1' || v === 'true' || v === 'on';
            else if (input.tomselect) {
                if (!input.tomselect.options[v]) input.tomselect.addOption({ id: v, text: input.dataset.selectedText || v });
                input.tomselect.setValue(v, true);
            } else input.value = v;
        });
    }

    params() {
        const p = new URLSearchParams();
        Object.entries(this.filterValues()).forEach(([k, v]) => p.set(k, v));
        ['q', 'sort', 'dir', 'per_page', 'page'].forEach(k => { if (this.state[k]) p.set(k, this.state[k]); });
        return p;
    }

    pushUrl() {
        if (!this.sync) return;
        const p = this.params();
        if (this.state.view !== 'table') p.set('view', this.state.view);
        history.replaceState(null, '', `${window.location.pathname}?${p}`);
    }

    async load() {
        this.abort?.abort();
        this.abort = new AbortController();
        this.el.classList.add('loading');
        this.pushUrl();
        try {
            const json = await api(`${this.url}?${this.params()}`, { signal: this.abort.signal });
            this.rows = json.data;
            this.meta = json.meta;
            this.draw();
            this.el.dispatchEvent(new CustomEvent('dt:loaded', { detail: json }));
        } catch (err) {
            if (err.name === 'AbortError') return;
            this.$('.dt-body').innerHTML = `<div class="empty-state text-danger"><i class="bi bi-exclamation-triangle"></i>${esc(err.message)}<div class="mt-2"><button class="btn btn-sm btn-outline-danger dt-retry">Retry</button></div></div>`;
            this.$('.dt-retry').addEventListener('click', () => this.load());
        } finally {
            this.el.classList.remove('loading');
        }
    }

    visibleColumns() { return this.columns.filter(c => !this.hidden.has(c.key)); }

    draw() {
        const body = this.$('.dt-body');
        if (!this.rows.length) {
            body.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i>No records match your filters.${this.hasActiveFilters() ? '<div class="mt-2"><button class="btn btn-sm btn-outline-secondary dt-clear">Clear filters</button></div>' : ''}</div>`;
            body.querySelector('.dt-clear')?.addEventListener('click', () => this.reset());
        } else if (this.state.view === 'cards') {
            body.innerHTML = `<div class="dt-cards">${this.rows.map((r, i) => this.card(r, i)).join('')}</div>`;
        } else {
            const cols = this.visibleColumns();
            body.innerHTML = `<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>
                ${this.bulk.length ? '<th style="width:32px"><input type="checkbox" class="form-check-input dt-check-all" aria-label="Select all"></th>' : ''}
                ${cols.map(c => {
                    const sortKey = c.sort || c.key;
                    const active = this.state.sort === sortKey;
                    return `<th class="${c.sortable ? 'sortable' : ''} ${c.class || ''}" ${c.sortable ? `data-sort="${sortKey}"` : ''}>${esc(c.label)}${active ? ` <i class="bi bi-caret-${this.state.dir === 'asc' ? 'up' : 'down'}-fill"></i>` : ''}</th>`;
                }).join('')}</tr></thead>
                <tbody>${this.rows.map((r, i) => `<tr>${this.bulk.length ? `<td><input type="checkbox" class="form-check-input dt-check" value="${esc(r.id)}" ${this.selected.has(String(r.id)) ? 'checked' : ''} aria-label="Select row"></td>` : ''}${cols.map(c => `<td class="${c.class || ''}">${this.cell(c, r, i)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
        }
        const m = this.meta || {};
        this.$('.dt-info').textContent = m.total ? `Showing ${m.from}–${m.to} of ${m.total}` : '0 records';
        this.drawPages(m);
    }

    hasActiveFilters() { return this.state.q || Object.keys(this.filterValues()).length > 0; }

    drawPages(m) {
        const ul = this.$('.dt-pages');
        if (!m.last_page || m.last_page <= 1) { ul.innerHTML = ''; return; }
        const cur = m.current_page, last = m.last_page;
        const pages = new Set([1, last, cur - 1, cur, cur + 1, cur - 2, cur + 2].filter(p => p >= 1 && p <= last));
        let prev = 0, html = `<li class="page-item ${cur === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${cur - 1}" aria-label="Previous">‹</a></li>`;
        [...pages].sort((a, b) => a - b).forEach(p => {
            if (p - prev > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            html += `<li class="page-item ${p === cur ? 'active' : ''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
            prev = p;
        });
        html += `<li class="page-item ${cur === last ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${cur + 1}" aria-label="Next">›</a></li>`;
        ul.innerHTML = html;
    }

    cell(c, r, i) {
        const v = r[c.key];
        switch (c.type) {
            case 'money': return v === null || v === undefined ? '<span class="text-secondary">—</span>' : `<span class="money">${fmtMoney(v)}</span>`;
            case 'profit': return v === null || v === undefined ? '<span class="text-secondary">—</span>'
                : `<span class="money ${v >= 0 ? 'text-profit' : 'text-loss'}">${fmtMoney(v)}</span>${r.profit_estimated ? ' <small class="text-secondary">(est.)</small>' : ''}`;
            case 'badge': return v ? `<span class="badge text-bg-${esc(v.color || 'secondary')}">${esc(v.label)}</span>` : '';
            case 'image': return v ? `<img src="${esc(v)}" class="dt-thumb" alt="" loading="lazy">` : '<div class="dt-thumb d-grid place-items-center text-secondary"><i class="bi bi-image m-auto"></i></div>';
            case 'link': return r.url ? `<a href="${esc(r.url)}" class="fw-semibold">${esc(v)}</a>` : esc(v);
            case 'mono': return `<span class="font-monospace small">${esc(v)}</span>`;
            case 'bool': return v ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-secondary"></i>';
            case 'actions': return this.actions(r, i);
            default: return v === null || v === undefined ? '' : esc(v);
        }
    }

    actions(r, i) {
        const acts = r.actions || [];
        if (!acts.length) return '';
        const [first, ...rest] = acts;
        const btn = (a, j, cls) => a.url && !a.method && !a.event
            ? `<a href="${esc(a.url)}" class="${cls}" ${a.target ? `target="${esc(a.target)}"` : ''}><i class="bi bi-${esc(a.icon || 'dot')} me-1"></i>${esc(a.label)}</a>`
            : `<a href="#" class="${cls} ${a.danger ? 'text-danger' : ''}" data-action-idx="${j}" data-row="${i}"><i class="bi bi-${esc(a.icon || 'dot')} me-1"></i>${esc(a.label)}</a>`;
        return `<div class="btn-group btn-group-sm">${btn(first, 0, 'btn btn-sm btn-outline-secondary')}
            ${rest.length ? `<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More actions"></button>
            <ul class="dropdown-menu dropdown-menu-end">${rest.map((a, j) => `<li>${btn(a, j + 1, 'dropdown-item')}</li>`).join('')}</ul>` : ''}</div>`;
    }

    card(r, i) {
        const cardCols = this.columns.filter(c => c.card);
        const title = r.title || r.name || r[this.columns[1]?.key];
        return `<div class="card vcard h-100">
            ${r.image ? `<img src="${esc(r.image)}" alt="" loading="lazy">` : '<div class="noimg"><i class="bi bi-car-front"></i></div>'}
            <div class="card-body">
                <h6 class="mb-1"><a href="${esc(r.url || '#')}">${esc(title)}</a></h6>
                ${cardCols.map(c => `<div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-secondary">${esc(c.label)}</span><span>${this.cell(c, r, i)}</span></div>`).join('')}
            </div>
            <div class="card-footer bg-transparent">${this.actions(r, i)}</div></div>`;
    }

    async runAction(el) {
        const row = this.rows[el.dataset.row];
        const a = row.actions[el.dataset.actionIdx];
        if (a.event) { this.el.dispatchEvent(new CustomEvent(a.event, { detail: row, bubbles: true })); return; }
        if (a.confirm && !confirm(a.confirm)) return;
        try {
            const json = await api(a.url, { method: a.method || 'POST', data: a.data || {} });
            toast(json?.message || 'Done.');
            this.load();
        } catch (err) { handleError(err); }
    }

    updateBulk() {
        const box = this.$('.dt-bulk');
        if (!box) return;
        box.classList.toggle('d-none', this.selected.size === 0);
        box.querySelector('.dt-sel-count').textContent = this.selected.size;
    }

    async runBulk(b, value) {
        if (!this.selected.size) return;
        if (b.confirm && !confirm(b.confirm.replace(':count', this.selected.size))) return;
        try {
            const json = await api(this.el.dataset.bulkUrl, { method: 'POST', data: { ids: [...this.selected], action: b.action, value } });
            toast(json?.message || 'Done.');
            this.selected.clear(); this.updateBulk(); this.load();
        } catch (err) { handleError(err); }
    }

    reset() {
        this.state.q = ''; this.state.page = 1; this.state.sort = this.el.dataset.sort || ''; this.state.dir = this.el.dataset.dir || 'desc';
        this.$('.dt-search').value = '';
        if (this.filtersForm) {
            this.filtersForm.reset();
            this.filtersForm.querySelectorAll('select').forEach(s => s.tomselect?.clear(true));
            this.filtersForm.querySelectorAll('input:not([type=checkbox]):not([type=radio]):not([type=hidden])').forEach(i => { i.value = ''; });
        }
        this.load();
    }

    async loadPresets() {
        const box = this.$('.dt-presets');
        box.innerHTML = '<div class="small text-secondary p-2">Loading…</div>';
        try {
            const list = await api(`/saved-filters/${encodeURIComponent(this.module)}`);
            box.innerHTML = `${list.length ? list.map(p => `<div class="d-flex align-items-center gap-1"><a href="#" class="dropdown-item small flex-fill" data-preset='${esc(JSON.stringify(p.filters))}'>${esc(p.name)}</a><button class="btn btn-sm btn-link text-danger p-0" data-del="${p.id}" title="Delete preset"><i class="bi bi-x"></i></button></div>`).join('') : '<div class="small text-secondary px-2">No saved presets.</div>'}
                <hr class="my-2"><button class="btn btn-sm btn-primary w-100 dt-save-preset"><i class="bi bi-plus"></i> Save current filters</button>`;
            box.querySelectorAll('[data-preset]').forEach(a => a.addEventListener('click', (e) => {
                e.preventDefault();
                const f = JSON.parse(a.dataset.preset);
                this.filtersForm?.reset();
                const p = new URLSearchParams(f);
                this.state.q = f.q || ''; this.state.sort = f.sort || this.state.sort; this.state.dir = f.dir || this.state.dir;
                this.$('.dt-search').value = this.state.q;
                this.applyParamsToFilters(p); this.state.page = 1; this.load();
            }));
            box.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async (e) => {
                e.stopPropagation();
                await api(`/saved-filters/${b.dataset.del}`, { method: 'DELETE' }); this.loadPresets();
            }));
            box.querySelector('.dt-save-preset').addEventListener('click', async () => {
                const name = prompt('Preset name');
                if (!name) return;
                const filters = Object.fromEntries(this.params()); delete filters.page;
                try { await api(`/saved-filters/${encodeURIComponent(this.module)}`, { method: 'POST', data: { name, filters } }); toast('Preset saved.'); }
                catch (err) { handleError(err); }
            });
        } catch (err) { box.innerHTML = `<div class="small text-danger p-2">${esc(err.message)}</div>`; }
    }
}

export function initDataTables(root = document) {
    root.querySelectorAll('[data-datatable]:not([data-dt-ready])').forEach(el => { el.dataset.dtReady = '1'; el.datatable = new DataTable(el); });
}
