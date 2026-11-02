import { Controller } from '@hotwired/stimulus';

/*
 * Screensaver of the team tablet (konzept-12 §5): after the idle time a dark, opaque layer covers the page
 * (visitors in the flat see nothing) and shows time, date, 112 and the contacts for the lock screen.
 * A tap or key on the layer wakes the tablet; calling a number works directly from the layer.
 */
export default class extends Controller {
    static targets = ['overlay', 'clock', 'date'];
    static values = { seconds: Number, timezone: String };

    connect() {
        this.activity = this.activity.bind(this);
        for (const type of ['pointerdown', 'keydown', 'wheel', 'touchstart']) {
            document.addEventListener(type, this.activity, { capture: true, passive: true });
        }
        this.restart();
    }

    disconnect() {
        for (const type of ['pointerdown', 'keydown', 'wheel', 'touchstart']) {
            document.removeEventListener(type, this.activity, { capture: true });
        }
        clearTimeout(this.timer);
        clearInterval(this.clockTimer);
    }

    activity() {
        if (this.overlayTarget.hidden) {
            this.restart();
        }
    }

    restart() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.dim(), this.secondsValue * 1000);
    }

    dim() {
        this.showTime();
        this.clockTimer = setInterval(() => this.showTime(), 15000);
        this.overlayTarget.hidden = false;
        this.overlayTarget.focus();
    }

    wake(event) {
        // Calling a number from the layer works without waking first
        if (event.target.closest('a')) {
            return;
        }
        event.preventDefault();
        this.overlayTarget.hidden = true;
        clearInterval(this.clockTimer);
        this.restart();
    }

    wakeByKey(event) {
        if (event.target.closest('a') && (event.key === 'Enter' || event.key === ' ')) {
            return;
        }
        if (event.key !== 'Tab') {
            this.wake(event);
        }
    }

    showTime() {
        const now = new Date();
        const timeZone = this.timezoneValue || undefined;
        this.clockTarget.textContent = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', timeZone }).format(now);
        this.dateTarget.textContent = new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'long', timeZone }).format(now);
    }
}
