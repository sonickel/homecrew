/*
 * Verwaltung (EasyAdmin) at a paired tablet: like assets/controllers/tablet_session_controller.js – after the idle time
 * without touch the page is opened again, the server ends the login and the tablet shows its start page.
 * The time comes from data-hc-tablet-logout on this script tag (App\Controller\Admin\DashboardController).
 */
(() => {
    const seconds = Number(document.currentScript?.dataset.hcTabletLogout);
    if (!seconds) {
        return;
    }

    let timer;
    const activity = () => {
        clearTimeout(timer);
        // A little later than the server's limit, so the server surely counts the login as idle
        timer = setTimeout(() => window.location.assign(window.location.pathname + window.location.search), (seconds + 3) * 1000);
    };
    for (const type of ['pointerdown', 'keydown', 'wheel', 'touchstart']) {
        document.addEventListener(type, activity, { capture: true, passive: true });
    }
    activity();
})();
