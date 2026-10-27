import { api, debounce, esc } from './http';

/** Top-bar global search across every module the user can access. */
export function initGlobalSearch() {
    const box = document.querySelector('.global-search');
    if (!box) return;
    const input = box.querySelector('input');
    const results = box.querySelector('.results');
    let active = -1;

    const run = debounce(async () => {
        const q = input.value.trim();
        if (q.length < 2) { results.classList.add('d-none'); return; }
        try {
            const json = await api(`/search?q=${encodeURIComponent(q)}`);
            const groups = Object.entries(json.results || {});
            results.innerHTML = groups.length
                ? groups.map(([g, items]) => `<div class="group-title">${esc(g)}</div>${items.map(i => `<a class="dropdown-item py-2" href="${esc(i.url)}"><i class="bi bi-${esc(i.icon)} me-2 text-secondary"></i><span class="fw-semibold">${esc(i.title)}</span><div class="small text-secondary text-truncate ms-4">${esc(i.subtitle || '')}</div></a>`).join('')}`).join('')
                    + `<div class="border-top"><a class="dropdown-item small text-center" href="/search?q=${encodeURIComponent(q)}">See all results</a></div>`
                : '<div class="p-3 small text-secondary">No results.</div>';
            results.classList.remove('d-none');
            active = -1;
        } catch { results.classList.add('d-none'); }
    }, 250);

    input.addEventListener('input', run);
    input.addEventListener('focus', () => { if (results.innerHTML) results.classList.remove('d-none'); });
    input.addEventListener('keydown', (e) => {
        const links = [...results.querySelectorAll('a')];
        if (e.key === 'ArrowDown') { active = Math.min(active + 1, links.length - 1); links[active]?.focus(); e.preventDefault(); }
        if (e.key === 'Escape') results.classList.add('d-none');
        if (e.key === 'Enter' && active === -1) window.location = `/search?q=${encodeURIComponent(input.value)}`;
    });
    document.addEventListener('click', (e) => { if (!box.contains(e.target)) results.classList.add('d-none'); });
    document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); input.focus(); } });
}
