import { Controller } from '@hotwired/stimulus';

/*
 * A personal login at a paired tablet (PIN or "Admin-Anmeldung") ends after the tablet's idle time (konzept-12 §5).
 * The server decides (SessionIdleSubscriber): after the time without touch this controller simply opens the page
 * again, the server finds the login idle, ends it and shows the tablet's start page – no personal data stays on screen.
 * The same logic for the Verwaltung (without Stimulus): assets/admin/tablet_session.js.
 */
export default class extends Controller {
    static values = { seconds: Number };

    connect() {
        this.activity = this.activity.bind(this);
        for (const type of ['pointerdown', 'keydown', 'wheel', 'touchstart']) {
            document.addEventListener(type, this.activity, { capture: true, passive: true });
        }
        this.activity();
    }

    disconnect() {
        for (const type of ['pointerdown', 'keydown', 'wheel', 'touchstart']) {
            document.removeEventListener(type, this.activity, { capture: true });
        }
        clearTimeout(this.timer);
    }

    activity() {
        clearTimeout(this.timer);
        // A little later than the server's limit, so the server surely counts the login as idle
        this.timer = setTimeout(() => window.location.assign(window.location.pathname + window.location.search), (this.secondsValue + 3) * 1000);
    }
}
