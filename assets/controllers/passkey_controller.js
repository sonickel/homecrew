import { Controller } from '@hotwired/stimulus';
import { browserSupportsWebAuthn, startAuthentication, startRegistration } from '@simplewebauthn/browser';

/*
 * Passkey login and adding a passkey, talking to the endpoints of web-auth/webauthn-symfony-bundle.
 * Elements stay hidden until it is clear that the browser supports passkeys (no dead buttons).
 */
export default class extends Controller {
    static values = { optionsUrl: String, resultUrl: String };
    static targets = ['button', 'message', 'label', 'form', 'unsupported'];

    connect() {
        const supported = browserSupportsWebAuthn();
        if (this.hasFormTarget) {
            this.formTarget.hidden = !supported;
        } else {
            this.element.hidden = !supported;
        }
        if (this.hasUnsupportedTarget) {
            this.unsupportedTarget.hidden = supported;
        }
    }

    async login() {
        await this.#run(async () => {
            const options = await this.#post(this.optionsUrlValue, {});
            const credential = await startAuthentication({ optionsJSON: options });

            return this.#post(this.resultUrlValue, credential);
        });
    }

    async register() {
        await this.#run(async () => {
            const options = await this.#post(this.optionsUrlValue, {});
            const credential = await startRegistration({ optionsJSON: options });
            const label = this.hasLabelTarget ? this.labelTarget.value.trim() : '';
            const url = label === '' ? this.resultUrlValue : `${this.resultUrlValue}?bezeichnung=${encodeURIComponent(label)}`;

            return this.#post(url, credential);
        });
    }

    async #run(ceremony) {
        this.#showMessage('');
        this.buttonTargets.forEach((button) => { button.disabled = true; });
        try {
            const result = await ceremony();
            window.location.assign(result.redirect);
        } catch (error) {
            // Cancelled in the browser dialog: no error message needed
            if (error?.name !== 'NotAllowedError' && error?.name !== 'AbortError') {
                this.#showMessage(error?.userMessage ?? 'Das hat nicht geklappt. Versuch es noch einmal.');
            }
        } finally {
            this.buttonTargets.forEach((button) => { button.disabled = false; });
        }
    }

    async #post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status === 'error') {
            const error = new Error('Passkey request failed');
            error.userMessage = data.message;
            throw error;
        }

        return data;
    }

    #showMessage(text) {
        if (this.hasMessageTarget) {
            this.messageTarget.textContent = text;
            this.messageTarget.hidden = text === '';
        }
    }
}
