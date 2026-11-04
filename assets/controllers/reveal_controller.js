import { Controller } from '@hotwired/stimulus';

/*
 * Shows dependent fields only while a certain choice is selected (compact forms), e.g. "Ab diesem Tag"
 * only for "Ab einem bestimmten Tag". A field may name its own choices (data-reveal-for="woechentlich monatlich"),
 * otherwise it follows the "when" value. The choice is the checked radio button inside the element – so one radio
 * group per element.
 * Without JavaScript everything stays visible.
 */
export default class extends Controller {
    static targets = ['field'];
    static values = { when: String };

    connect() {
        this.update();
    }

    update() {
        const checked = this.element.querySelector('input[type="radio"]:checked');
        for (const field of this.fieldTargets) {
            const choices = (field.dataset.revealFor ?? this.whenValue).split(' ');
            field.hidden = checked === null || !choices.includes(checked.value);
        }
    }
}
