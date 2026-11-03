import './stimulus_bootstrap.js';

// Styles are linked in templates/base.html.twig, not imported here: CSS imports become
// "data:" modules in the import map, which the strict CSP (script-src 'self' + nonce) blocks.

// Turbo would run inline scripts of a newly loaded page – but the browser keeps the CSP of the first page, so they
// are blocked (console error). HomeCrew has no inline scripts (Stimulus only); in dev the web debug toolbar brings
// some – it shows up again after a full page load.
document.addEventListener('turbo:before-render', (event) => {
    for (const script of event.detail.newBody.querySelectorAll('script:not([src])')) {
        script.setAttribute('data-turbo-eval', 'false');
    }
});
