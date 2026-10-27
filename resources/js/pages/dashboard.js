import Chart from 'chart.js/auto';
import { api, esc, fmtMoney } from '../core/http';

const charts = {};
const palette = ['#1f6feb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#14b8a6', '#f97316', '#64748b', '#ec4899'];

export function renderChart(canvas, cfg, moneyAxis = true) {
    const key = canvas.id;
    charts[key]?.destroy();
    const isPie = ['doughnut', 'pie'].includes(cfg.type);
    charts[key] = new Chart(canvas, {
        type: cfg.type,
        data: {
            labels: cfg.labels,
            datasets: cfg.datasets.map((d, i) => ({
                ...d,
                backgroundColor: isPie ? palette : (cfg.type === 'line' ? palette[i] + '33' : palette[i]),
                borderColor: isPie ? 'transparent' : palette[i],
                fill: cfg.type === 'line', tension: .3, borderRadius: cfg.type === 'bar' ? 4 : 0,
            })),
        },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: cfg.horizontal ? 'y' : 'x',
            plugins: {
                legend: { display: isPie || cfg.datasets.length > 1, position: 'bottom' },
                tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${moneyAxis && !isPie && !cfg.horizontal && cfg.money !== false ? fmtMoney(c.parsed.y ?? c.parsed) : (c.parsed.x ?? c.parsed.y ?? c.parsed)}` } },
            },
            scales: isPie ? {} : {
                x: { stacked: !!cfg.stacked, grid: { display: false } },
                y: { stacked: !!cfg.stacked, beginAtZero: true, ticks: { callback: (v) => (moneyAxis && !cfg.horizontal && cfg.money !== false ? fmtMoney(v).replace(/\.00$/, '') : v) } },
            },
        },
    });
}

export function initDashboard() {
    const root = document.getElementById('dashboard');
    if (!root) return;
    const cardsEl = document.getElementById('dash-cards');
    const chartsEl = document.getElementById('dash-charts');

    const load = async () => {
        root.classList.add('opacity-75');
        try {
            const d = await api('/dashboard/data');
            cardsEl.innerHTML = d.cards.map(c => `
                <div class="col-6 col-md-4 col-xl-3 ${root.dataset.layout === 'compact' ? 'col-xxl-2' : ''}">
                  <div class="card stat-card h-100"><div class="card-body d-flex gap-3 align-items-center">
                    <div class="stat-icon text-bg-${esc(c.color)} bg-opacity-10"><i class="bi bi-${esc(c.icon)}"></i></div>
                    <div class="min-w-0"><div class="stat-value text-truncate">${esc(c.value)}</div><div class="stat-label">${esc(c.label)}</div></div>
                    ${c.url ? `<a href="${esc(c.url)}" class="stretched-link" aria-label="${esc(c.label)}"></a>` : ''}
                  </div></div>
                </div>`).join('') || '<div class="col"><div class="alert alert-info">No dashboard widgets are available for your role.</div></div>';

            chartsEl.innerHTML = Object.entries(d.charts).map(([k, c]) => `
                <div class="${['inventory', 'repair_status', 'outstanding'].includes(k) ? 'col-lg-4' : 'col-lg-6'}">
                  <div class="card h-100"><div class="card-header">${esc(c.title)}</div><div class="card-body"><div class="chart-box"><canvas id="chart-${k}"></canvas></div></div></div>
                </div>`).join('');
            Object.entries(d.charts).forEach(([k, c]) => renderChart(document.getElementById(`chart-${k}`), { ...c, money: !['inventory', 'repair_status'].includes(k) }));

            const alerts = document.getElementById('dash-alerts');
            alerts.innerHTML = d.alerts.length ? d.alerts.map(a => `<a href="${esc(a.url)}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center"><i class="bi bi-${esc(a.icon)} text-${esc(a.level)}"></i><span class="small">${esc(a.text)}</span></a>`).join('')
                : '<div class="list-group-item small text-secondary">No alerts — all good.</div>';
            const act = document.getElementById('dash-activity');
            act.innerHTML = d.activity.length ? d.activity.map(a => `<li><div class="small"><strong>${esc(a.user)}</strong> — ${esc(a.description || a.event)}</div><div class="small text-secondary">${esc(a.time)}</div></li>`).join('')
                : '<li class="small text-secondary border-0">No recent activity.</li>';
            document.getElementById('dash-updated').textContent = `Updated ${d.updated_at}`;
        } finally { root.classList.remove('opacity-75'); }
    };
    load();
    document.getElementById('dash-refresh')?.addEventListener('click', load);
    setInterval(() => { if (!document.hidden) load(); }, 120000);
}
