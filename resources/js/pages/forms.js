import { api, toast, handleError, fmtMoney, esc } from '../core/http';
import { initSelects, openQuickCreate } from '../core/selects';
import { renderChart } from './dashboard';

const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

/** Generic repeater: container[data-repeater] + <template data-row> + [data-add-row] */
function initRepeater(container, onRow) {
    const tpl = document.querySelector(container.dataset.template);
    let i = container.querySelectorAll('[data-row]').length + 1000;
    const add = (data = {}) => {
        const html = tpl.innerHTML.replace(/__i__/g, i++);
        const wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        container.appendChild(row);
        Object.entries(data).forEach(([k, v]) => {
            const input = row.querySelector(`[data-field="${k}"]`);
            if (!input) return;
            if (input.type === 'checkbox') input.checked = !!v;
            else if (input.tagName === 'SELECT' && input.dataset.remote && v) {
                input.insertAdjacentHTML('beforeend', `<option value="${esc(v)}" selected>${esc(data.label || v)}</option>`);
            } else input.value = v ?? '';
        });
        initSelects(row);
        onRow?.(row, data);
        return row;
    };
    document.querySelectorAll(`[data-add-row="${container.dataset.template}"]`).forEach(b => b.addEventListener('click', (e) => { e.preventDefault(); add(); }));
    container.addEventListener('click', (e) => { if (e.target.closest('[data-remove-row]')) { e.preventDefault(); e.target.closest('[data-row]').remove(); container.dispatchEvent(new Event('input', { bubbles: true })); } });
    return add;
}

function initInvoiceForm() {
    const form = document.getElementById('invoice-form');
    if (!form) return;
    const body = form.querySelector('#invoice-items');
    const recalc = () => {
        let subtotal = 0, taxable = 0;
        body.querySelectorAll('[data-row]').forEach(r => {
            const line = round2(num(r.querySelector('[data-field="quantity"]').value) * num(r.querySelector('[data-field="unit_price"]').value));
            r.querySelector('.line-total').textContent = fmtMoney(line);
            subtotal += line;
            if (r.querySelector('[data-field="taxable"]').checked) taxable += line;
        });
        const dType = form.querySelector('[name="discount_type"]').value;
        const dVal = num(form.querySelector('[name="discount_value"]').value);
        const discount = round2(dType === 'percent' ? subtotal * Math.min(dVal, 100) / 100 : Math.min(dVal, subtotal));
        const taxableAfter = subtotal > 0 ? taxable - discount * (taxable / subtotal) : 0;
        const tax = round2(Math.max(0, taxableAfter) * num(form.querySelector('[name="tax_rate"]').value) / 100);
        const total = round2(subtotal - discount + tax);
        form.querySelector('#t-subtotal').textContent = fmtMoney(subtotal);
        form.querySelector('#t-discount').textContent = '-' + fmtMoney(discount);
        form.querySelector('#t-tax').textContent = fmtMoney(tax);
        form.querySelector('#t-total').textContent = fmtMoney(total);
    };
    const setType = (row) => {
        const type = row.querySelector('[data-field="item_type"]').value;
        row.querySelectorAll('[data-ref]').forEach(el => { el.classList.toggle('d-none', el.dataset.ref !== type); });
        const qty = row.querySelector('[data-field="quantity"]');
        if (['vehicle', 'service_job'].includes(type)) { qty.value = 1; qty.readOnly = true; } else qty.readOnly = false;
    };
    const onRow = (row) => {
        setType(row);
        row.querySelector('[data-field="item_type"]').addEventListener('change', () => setType(row));
        row.addEventListener('remote:selected', (e) => {
            const d = e.detail;
            if (!d) return;
            if (d.price !== undefined && d.price !== null) row.querySelector('[data-field="unit_price"]').value = d.price;
            if (d.available !== undefined && d.available <= 0) toast(`${d.text} is out of stock (${d.available} available).`, 'warning');
            recalc();
        });
    };
    const add = initRepeater(body, onRow);
    JSON.parse(form.dataset.items || '[]').forEach(item => add(item));
    if (!body.querySelector('[data-row]')) add({ item_type: form.dataset.defaultItem || 'labor', quantity: 1, taxable: true });
    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    form.querySelector('#customer_id')?.addEventListener('change', (e) => {
        form.querySelectorAll('select[data-remote="service_jobs"], select[data-remote="vehicles"][data-scope="customer"]').forEach(s => s.tomselect?.clearOptions());
    });
    recalc();
}

function initPurchaseOrderForm() {
    const form = document.getElementById('po-form');
    if (!form) return;
    const body = form.querySelector('#po-items');
    const recalc = () => {
        let sub = 0;
        body.querySelectorAll('[data-row]').forEach(r => {
            const line = round2(num(r.querySelector('[data-field="quantity_ordered"]').value) * num(r.querySelector('[data-field="unit_cost"]').value));
            r.querySelector('.line-total').textContent = fmtMoney(line);
            sub += line;
        });
        const total = round2(sub + num(form.querySelector('[name="tax_amount"]').value) + num(form.querySelector('[name="shipping_cost"]').value));
        form.querySelector('#po-subtotal').textContent = fmtMoney(sub);
        form.querySelector('#po-total').textContent = fmtMoney(total);
    };
    const add = initRepeater(body, (row) => row.addEventListener('remote:selected', (e) => {
        if (e.detail?.cost !== undefined && e.detail.cost !== null) row.querySelector('[data-field="unit_cost"]').value = e.detail.cost;
        recalc();
    }));
    JSON.parse(form.dataset.items || '[]').forEach(item => add(item));
    if (!body.querySelector('[data-row]')) add({ quantity_ordered: 1 });
    form.addEventListener('input', recalc);
    document.getElementById('po-new-part')?.addEventListener('click', () => {
        const empty = [...body.querySelectorAll('select[data-field="part_id"]')].find(s => !s.value);
        const sel = empty || add({ quantity_ordered: 1 }).querySelector('select[data-field="part_id"]');
        openQuickCreate(sel);
    });
    recalc();
}

function initPaymentForm() {
    const form = document.getElementById('payment-form');
    if (!form) return;
    const customer = form.querySelector('#customer_id');
    const box = form.querySelector('#allocations');
    const amount = form.querySelector('[name="amount"]');
    const preselect = form.dataset.invoice;
    const sumAlloc = () => [...box.querySelectorAll('.alloc')].reduce((s, i) => s + num(i.value), 0);
    const refreshSum = () => {
        const s = round2(sumAlloc());
        const el = form.querySelector('#alloc-sum');
        el.textContent = fmtMoney(s);
        el.classList.toggle('text-danger', round2(num(amount.value)) !== s);
    };
    const load = async () => {
        box.innerHTML = '<tr><td colspan="7" class="text-center text-secondary small">Loading open invoices…</td></tr>';
        if (!customer.value) { box.innerHTML = '<tr><td colspan="7" class="text-center text-secondary small">Select a customer to see open invoices.</td></tr>'; return; }
        const list = await api(`/payments/open-invoices/${customer.value}`);
        box.innerHTML = list.length ? list.map(i => `<tr>
            <td><a href="/invoices/${i.id}" target="_blank">${esc(i.number)}</a> ${i.overdue ? '<span class="badge text-bg-danger">Overdue</span>' : ''}</td>
            <td>${esc(i.date)}</td><td>${esc(i.due)}</td><td class="money">${fmtMoney(i.total)}</td>
            <td class="money">${fmtMoney(i.balance)}${i.lender_pending > 0 ? `<div class="small text-secondary">incl. ${fmtMoney(i.lender_pending)} lender</div>` : ''}</td>
            <td style="width:150px"><input type="number" step="0.01" min="0" max="${i.balance}" class="form-control form-control-sm alloc" name="allocations[${i.id}]" data-balance="${i.balance}" data-due="${i.customer_due}" value="${String(i.id) === String(preselect) ? i.customer_due : ''}"></td>
            <td><button type="button" class="btn btn-sm btn-link p-0 alloc-full">Full</button></td></tr>`).join('')
            : '<tr><td colspan="7" class="text-center text-secondary small">This customer has no open invoices.</td></tr>';
        if (preselect && !num(amount.value)) amount.value = round2(sumAlloc()) || '';
        refreshSum();
    };
    customer.addEventListener('change', load);
    box.addEventListener('input', () => { amount.value = round2(sumAlloc()) || ''; refreshSum(); });
    box.addEventListener('click', (e) => {
        const b = e.target.closest('.alloc-full');
        if (!b) return;
        const input = b.closest('tr').querySelector('.alloc');
        input.value = input.dataset.due > 0 ? input.dataset.due : input.dataset.balance;
        amount.value = round2(sumAlloc()); refreshSum();
    });
    form.querySelector('#auto-allocate')?.addEventListener('click', () => {
        let left = num(amount.value);
        box.querySelectorAll('.alloc').forEach(i => { const take = Math.min(left, num(i.dataset.balance)); i.value = take > 0 ? round2(take) : ''; left = round2(left - take); });
        refreshSum();
        if (left > 0) toast(`${fmtMoney(left)} exceeds the open balances.`, 'warning');
    });
    amount.addEventListener('input', refreshSum);
    form.querySelector('[name="method"]').addEventListener('change', (e) => {
        form.querySelector('#card-note').classList.toggle('d-none', !['credit_card', 'debit_card'].includes(e.target.value));
    });
    if (customer.value) load();
}

function initThemePreview() {
    const form = document.getElementById('settings-theme');
    if (!form) return;
    const map = { theme__primary_color: '--as-primary', theme__secondary_color: '--as-secondary', theme__sidebar_color: '--as-sidebar', theme__header_color: '--as-header' };
    form.addEventListener('input', (e) => {
        if (map[e.target.name]) document.documentElement.style.setProperty(map[e.target.name], e.target.value);
        if (e.target.name === 'theme__font') document.documentElement.style.setProperty('--as-font', `"${e.target.value}", system-ui, sans-serif`);
        if (e.target.name === 'theme__logo_height') document.documentElement.style.setProperty('--as-logo-h', `${e.target.value}px`);
    });
    form.addEventListener('change', (e) => {
        if (e.target.name === 'theme__sidebar_collapsed') document.body.classList.toggle('sidebar-collapsed', e.target.checked);
    });
}

function initAttendanceModal() {
    const table = document.getElementById('attendance-table');
    const modalEl = document.getElementById('attendanceModal');
    if (!table || !modalEl) return;
    const form = modalEl.querySelector('form');
    const fill = (row) => {
        form.reset();
        form.action = row ? `/attendance/${row.id}` : form.dataset.storeUrl;
        form.querySelector('[name="_method"]').value = row ? 'PUT' : 'POST';
        const emp = form.querySelector('[name="employee_id"]');
        if (row) {
            emp.tomselect?.addOption({ id: row.employee_id, text: row.employee });
            emp.tomselect?.setValue(row.employee_id, true);
            emp.tomselect?.disable();
            ['clock_in', 'clock_out', 'break_start', 'break_end', 'break_minutes', 'notes'].forEach(k => { form.querySelector(`[name="${k}"]`).value = row[k] ?? ''; });
            form.querySelector('[name="work_date"]').value = row.work_date;
            form.querySelector('[name="status"]').value = row.status_raw;
        } else {
            emp.tomselect?.enable(); emp.tomselect?.clear(true);
        }
        modalEl.querySelector('.modal-title').textContent = row ? `Correct attendance — ${row.employee}` : 'Add attendance record';
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };
    table.addEventListener('attendance:edit', (e) => fill(e.detail));
    document.getElementById('attendance-add')?.addEventListener('click', () => fill(null));
    form.addEventListener('submit', () => { form.querySelector('[name="employee_id"]').tomselect?.enable(); }, true);
}

function initPunch() {
    document.querySelectorAll('[data-punch]').forEach(b => b.addEventListener('click', async () => {
        try {
            const json = await api('/attendance/punch', { method: 'POST', data: { action: b.dataset.punch } });
            toast(json.message); setTimeout(() => window.location.reload(), 600);
        } catch (err) { handleError(err); }
    }));
}

function initReportChart() {
    const c = document.getElementById('report-chart');
    if (c) renderChart(c, JSON.parse(c.dataset.chart), c.dataset.money !== 'false');
}

function initJobItemForm() {
    const form = document.getElementById('job-item-form');
    if (!form) return;
    const type = form.querySelector('[name="item_type"]');
    const toggle = () => form.querySelectorAll('[data-show]').forEach(el => el.classList.toggle('d-none', !el.dataset.show.split(',').includes(type.value)));
    type.addEventListener('change', toggle);
    toggle();
    form.querySelector('[name="part_id"]')?.addEventListener('remote:selected', (e) => {
        if (!e.detail) return;
        form.querySelector('[name="unit_price"]').value = e.detail.price ?? '';
        if (e.detail.cost !== undefined && e.detail.cost !== null) form.querySelector('[name="unit_cost"]').value = e.detail.cost;
        form.querySelector('#part-available').textContent = `${e.detail.available} available`;
    });
    form.querySelector('[name="service_type_id"]')?.addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        if (!opt?.value) return;
        form.querySelector('[name="description"]').value = opt.textContent.trim();
        form.querySelector('[name="unit_price"]').value = opt.dataset.price || '';
        form.querySelector('[name="unit_cost"]').value = opt.dataset.cost || '';
    });
}

function initStockForm() {
    const form = document.getElementById('stock-form');
    if (!form) return;
    const op = form.querySelector('[name="operation"]');
    const toggle = () => {
        form.querySelectorAll('[data-show]').forEach(el => el.classList.toggle('d-none', !el.dataset.show.split(',').includes(op.value)));
        form.querySelector('#qty-label').textContent = op.value === 'adjust' ? 'Counted quantity at location' : 'Quantity';
    };
    op.addEventListener('change', toggle);
    toggle();
}

export function initForms() {
    initInvoiceForm();
    initPurchaseOrderForm();
    initPaymentForm();
    initThemePreview();
    initAttendanceModal();
    initPunch();
    initReportChart();
    initJobItemForm();
    initStockForm();

    // Show customer selector only for customer-owned vehicles
    const own = document.querySelector('[name="ownership_type"]');
    if (own) {
        const t = () => document.querySelectorAll('[data-owner-only]').forEach(el => el.classList.toggle('d-none', own.value !== 'customer'));
        own.addEventListener('change', t); t();
    }
}
