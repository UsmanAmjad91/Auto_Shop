import * as bootstrap from 'bootstrap';
import { api, toast, handleError, initAjaxForms, initActionLinks, fmtMoney, esc, debounce } from './core/http';
import { initDataTables } from './core/datatable';
import { initSelects } from './core/selects';
import { initGlobalSearch } from './core/search';
import { initNotifications } from './core/notifications';
import { initTheme } from './core/theme';
import { initDashboard } from './pages/dashboard';
import { initForms } from './pages/forms';

window.bootstrap = bootstrap;
window.AutoShop = { api, toast, handleError, fmtMoney, esc, debounce, initSelects, initAjaxForms };

function initUi(root = document) {
    initSelects(root);
    initAjaxForms(root);
    initDataTables(root);
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initUi();
    initActionLinks();
    initGlobalSearch();
    initNotifications();
    initDashboard();
    initForms();

    // Flash messages rendered by the server
    document.querySelectorAll('[data-flash]').forEach(el => toast(el.dataset.flash, el.dataset.type || 'success', 6000));

    // Keep the active tab in the URL hash
    const hash = window.location.hash;
    if (hash) document.querySelector(`[data-bs-toggle="tab"][href="${CSS.escape(hash)}"], [data-bs-toggle="tab"][data-bs-target="${CSS.escape(hash)}"]`)?.click();
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(t => t.addEventListener('shown.bs.tab', () => history.replaceState(null, '', t.getAttribute('href') || t.dataset.bsTarget)));
});
