import { api, esc } from './http';

export function initNotifications() {
    const dd = document.getElementById('notif-dropdown');
    if (!dd) return;
    const list = dd.querySelector('.notif-list');
    const badge = dd.querySelector('.notif-count');

    const setCount = (n) => { badge.textContent = n > 99 ? '99+' : n; badge.classList.toggle('d-none', !n); };
    const load = async () => {
        try {
            const json = await api('/notifications/feed');
            setCount(json.unread);
            list.innerHTML = json.items.length ? json.items.map(n => `
                <a href="#" class="dropdown-item py-2 ${n.read ? '' : 'bg-body-tertiary'}" data-id="${esc(n.id)}" data-url="${esc(n.url || '')}">
                    <div class="d-flex gap-2"><i class="bi bi-${esc(n.icon)} text-${esc(n.level)} fs-5"></i>
                    <div class="flex-fill text-wrap"><div class="fw-semibold small">${esc(n.title)}</div><div class="small text-secondary">${esc(n.message)}</div><div class="small text-secondary">${esc(n.time)}</div></div></div>
                </a>`).join('') : '<div class="p-3 small text-secondary text-center">You are all caught up.</div>';
        } catch { /* offline */ }
    };
    dd.addEventListener('show.bs.dropdown', load);
    list.addEventListener('click', async (e) => {
        const a = e.target.closest('[data-id]');
        if (!a) return;
        e.preventDefault();
        await api(`/notifications/${a.dataset.id}/read`, { method: 'POST' }).catch(() => {});
        if (a.dataset.url) window.location = a.dataset.url; else load();
    });
    dd.querySelector('.notif-read-all')?.addEventListener('click', async (e) => {
        e.preventDefault(); e.stopPropagation();
        await api('/notifications/read-all', { method: 'POST' }); load();
    });
    setInterval(async () => { try { setCount((await api('/notifications/feed')).unread); } catch { /* ignore */ } }, 60000);
}
