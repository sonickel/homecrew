import { Controller } from '@hotwired/stimulus';

/*
 * Shows dependent fields only while a certain choice is selected (compact forms), e.g. "Ab diesem Tag"
 * only for "Ab einem bestimmten Tag". Without JavaScript everything stays visible.
 */
export default class extends Controller {
    static targets = ['field'];
    static values = { when: String };

    connect() {
        this.update();
    }

    update() {
        const checked = this.element.querySelector('input[type="radio"]:checked');
        const show = checked !== null && checked.value === this.whenValue;
        for (const field of this.fieldTargets) {
            field.hidden = !show;
        }
    }
}
