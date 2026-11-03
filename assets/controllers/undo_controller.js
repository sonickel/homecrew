import { Controller } from '@hotwired/stimulus';

/*
 * "Rückgängig" after "Erledigt" or "Überspringen" (homecrew-ui: undo instead of asking): the hint stays for
 * a short time, then it disappears. While the pointer or the keyboard focus is on it, the time stands still
 * (keyboard and screen reader users need longer to get there). The server accepts the undo only a little longer
 * than the hint shows (App\Service\Task\TaskActions) – after that it explains why it did not work.
 */
export default class extends Controller {
    static values = { seconds: Number };

    connect() {
        this.remaining = this.secondsValue * 1000;
        this.pause = this.pause.bind(this);
        this.resume = this.resume.bind(this);
        for (const type of ['focusin', 'pointerenter']) {
            this.element.addEventListener(type, this.pause);
        }
        for (const type of ['focusout', 'pointerleave']) {
            this.element.addEventListener(type, this.resume);
        }
        this.resume();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    pause() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
            this.remaining -= Date.now() - this.startedAt;
        }
    }

    resume() {
        if (this.element.contains(document.activeElement) || this.timer) {
            return;
        }
        this.startedAt = Date.now();
        this.timer = setTimeout(() => this.element.remove(), Math.max(this.remaining, 0));
    }
}
