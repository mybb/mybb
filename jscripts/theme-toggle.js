(function($) {
    'use strict';

    $(document).ready(function() {
        var themeToggle = $('#theme-toggle');

        // Find the main stylesheet by looking for main.css or dark.css in href
        var themeStylesheet = $('link[rel="stylesheet"]').filter(function() {
            return $(this).attr('href') && $(this).attr('href').match(/\/(main|dark)\.css/);
        }).first();

        if (!themeToggle.length || !themeStylesheet.length) {
            return;
        }

        // Check if user prefers dark mode at system level
        var systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');

        function getSystemTheme() {
            return systemPrefersDark && systemPrefersDark.matches ? 'dark' : 'light';
        }

        function getStoredMode() {
            return localStorage.getItem('mybb-theme-mode') || 'system';
        }

        function getEffectiveTheme(mode) {
            if (mode === 'system') {
                return getSystemTheme();
            }
            return mode;
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);

            var stylesheetFile = theme === 'dark' ? 'dark.css' : 'main.css';
            var currentHref = themeStylesheet.attr('href');
            var newHref = currentHref.replace(/\/(main|dark)\.css/, '/' + stylesheetFile);

            if (currentHref !== newHref) {
                themeStylesheet.attr('href', newHref);
            }
        }

        function setMode(mode) {
            localStorage.setItem('mybb-theme-mode', mode);
            applyTheme(getEffectiveTheme(mode));
            themeToggle.val(mode);
        }

        // Initialize with stored mode
        var initialMode = getStoredMode();
        themeToggle.val(initialMode);
        applyTheme(getEffectiveTheme(initialMode));

        // Handle dropdown change
        themeToggle.on('change', function() {
            setMode($(this).val());
        });

        // Listen for system preference changes when in system mode
        if (systemPrefersDark) {
            systemPrefersDark.addEventListener('change', function() {
                if (getStoredMode() === 'system') {
                    applyTheme(getSystemTheme());
                }
            });
        }
    });
})(jQuery);
