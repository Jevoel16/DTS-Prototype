/**
 * Shared by the app, guest and login layouts (loaded at the end of <body>):
 * the light/dark theme toggle and server-side "console_error" reporting.
 */
(function () {
    'use strict';

    var themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    var themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    var themeToggleBtn = document.getElementById('theme-toggle');

    if (themeToggleDarkIcon && themeToggleLightIcon) {
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            themeToggleDarkIcon.classList.toggle('hidden');
            themeToggleLightIcon.classList.toggle('hidden');

            if (localStorage.getItem('theme')) {
                if (localStorage.getItem('theme') === 'light') {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            } else {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
            }
        });
    }

    // Simple show/hide toggles: <button data-show-modal="id"> / <button data-hide-modal="id"> (replaces inline onclick)
    document.addEventListener('click', function (e) {
        var shower = e.target.closest('[data-show-modal]');
        if (shower) {
            var shown = document.getElementById(shower.dataset.showModal);
            if (shown) shown.classList.remove('hidden');
            return;
        }
        var hider = e.target.closest('[data-hide-modal]');
        if (hider) {
            var hidden = document.getElementById(hider.dataset.hideModal);
            if (hidden) hidden.classList.add('hidden');
        }
    });

    // Integrity "Action Denied" details are logged to the console rather than shown as a toast
    var consoleError = document.getElementById('dts-console-error');
    if (consoleError) {
        try {
            console.error(JSON.parse(consoleError.textContent));
        } catch (e) { /* ignore malformed payload */ }
    }
})();
