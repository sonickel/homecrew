import { Controller } from '@hotwired/stimulus';

/* Opens the print dialog of the browser (print views use assets/styles/print.css). */
export default class extends Controller {
    print() {
        window.print();
    }
}
