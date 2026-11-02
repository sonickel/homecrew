import { Controller } from '@hotwired/stimulus';

/*
 * QR codes of an invitation (/einladung#CODE) and of a tablet pairing (/koppeln#CODE) carry the code after "#":
 * that part never reaches the server (and no log). This controller takes the code into the form, removes it from
 * the address bar and sends the form.
 */
export default class extends Controller {
    static targets = ['code'];

    connect() {
        let code = window.location.hash.slice(1);
        try {
            code = decodeURIComponent(code);
        } catch {
            // A broken "%" sequence: take the text as it is, the server says whether the code exists
        }
        code = code.trim();
        if (code === '') {
            return;
        }

        window.history.replaceState(null, '', window.location.pathname + window.location.search);
        this.codeTarget.value = code;
        this.element.requestSubmit();
    }
}
