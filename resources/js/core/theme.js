import { api } from './http';

/** Light / dark / auto theme (per-user, saved to the server) and sidebar behaviour. */
export function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    document.documentElement.dataset.themeMode = mode;
    document.querySelectorAll('.theme-icon').forEach(i => { i.className = `theme-icon bi bi-${mode === 'auto' ? 'circle-half' : (dark ? 'moon-stars' : 'sun')}`; });
}

export function initTheme() {
    const mode = document.documentElement.dataset.themeMode || 'auto';
    applyTheme(mode);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme(document.documentElement.dataset.themeMode));

    document.querySelectorAll('[data-theme-set]').forEach(b => b.addEventListener('click', async (e) => {
        e.preventDefault();
        const m = b.dataset.themeSet;
        applyTheme(m);
        try { await api('/profile/preferences', { method: 'PUT', data: { theme: m } }); } catch { /* keep local */ }
    }));

    const body = document.body;
    try { if (localStorage.getItem('sidebar-collapsed') !== null) body.classList.toggle('sidebar-collapsed', localStorage.getItem('sidebar-collapsed') === '1'); } catch { /* storage blocked */ }
    document.querySelectorAll('[data-sidebar-toggle]').forEach(b => b.addEventListener('click', () => {
        if (window.innerWidth < 992) { body.classList.toggle('sidebar-open'); return; }
        body.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch { /* ignore */ }
    }));
    document.querySelector('.sidebar-backdrop')?.addEventListener('click', () => body.classList.remove('sidebar-open'));
}
