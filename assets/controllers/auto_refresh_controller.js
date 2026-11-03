import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

/*
 * Team tablet: reloads "Heute" regularly, so new tasks appear without touching the screen (konzept-2 §2, no Mercure).
 * Turbo morphs the page: the scroll position stays and the screensaver layer (data-turbo-permanent) is left alone.
 * Skipped while "Rückgängig" is offered or someone is typing. Persons do not get this – every reload would count
 * as touching the screen and keep their login alive.
 */
export default class extends Controller {
    static values = { seconds: Number };

    connect() {
        this.timer = setInterval(() => this.refresh(), this.secondsValue * 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    refresh() {
        if (document.hidden || document.querySelector('[data-controller~="undo"]')) {
            return;
        }
        const active = document.activeElement;
        if (active && active.matches('input, textarea, select')) {
            return;
        }
        visit(window.location.href, { action: 'replace' });
    }
}
