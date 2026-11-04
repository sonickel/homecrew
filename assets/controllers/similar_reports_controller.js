import { Controller } from '@hotwired/stimulus';

/*
 * "Schon gemeldet?" while typing the title of a report (konzept-13 §2): loads open reports with a similar title into
 * the Turbo frame above the form – a little after the last key, not for every letter.
 * Without JavaScript the server shows them when the form is sent.
 */
export default class extends Controller {
    static targets = ['frame'];
    static values = { url: String };

    disconnect() {
        clearTimeout(this.timer);
    }

    search(event) {
        clearTimeout(this.timer);
        const title = event.target.value.trim();
        this.timer = setTimeout(() => {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('titel', title);
            this.frameTarget.src = url.toString();
        }, 400);
    }
}
