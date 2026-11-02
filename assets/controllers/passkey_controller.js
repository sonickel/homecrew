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
            // Technical details for whoever helps; the page says in plain words what to do
            console.warn('Passkey:', error?.code ?? error?.name, error);
            this.#showMessage(this.#explain(error));
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

    /*
     * Error codes of @simplewebauthn/browser (error.code) and of the browser (error.name);
     * messages from HomeCrew itself come as error.userMessage.
     */
    #explain(error) {
        if (error?.userMessage) {
            return error.userMessage;
        }

        switch (error?.code ?? error?.name) {
            case 'ERROR_INVALID_DOMAIN':
            case 'ERROR_INVALID_RP_ID':
            case 'SecurityError':
                return 'Der Browser oder dein Passwort-Manager lässt Passkeys für diese Adresse nicht zu. Versuch es mit dem Passkey-Speicher des Geräts (z. B. Windows Hello) oder nimm die Authenticator-App.';
            case 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED':
            case 'InvalidStateError':
                return 'Auf diesem Gerät gibt es schon einen Passkey für dich. Du kannst dich damit anmelden.';
            case 'ERROR_AUTHENTICATOR_MISSING_DISCOVERABLE_CREDENTIAL_SUPPORT':
            case 'ERROR_AUTHENTICATOR_MISSING_USER_VERIFICATION_SUPPORT':
                return 'Dieses Gerät kann keinen passenden Passkey speichern (nötig: Fingerabdruck, Gesicht oder Geräte-PIN). Nimm ein anderes Gerät oder die Authenticator-App.';
            case 'ERROR_CEREMONY_ABORTED':
            case 'NotAllowedError':
            case 'AbortError':
                return 'Kein Passkey verwendet: abgebrochen, Zeit abgelaufen oder auf diesem Gerät gibt es keinen Passkey für HomeCrew. Versuch es noch einmal.';
            default:
                return 'Das hat nicht geklappt. Versuch es noch einmal oder nimm die Authenticator-App.';
        }
    }

    #showMessage(text) {
        if (this.hasMessageTarget) {
            this.messageTarget.textContent = text;
            this.messageTarget.hidden = text === '';
        }
    }
}
