(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Theme toggle script loaded');

        var themeToggle = $('#theme-toggle');

        // Find the main stylesheet by looking for main.css or dark.css in href
        var themeStylesheet = $('link[rel="stylesheet"]').filter(function() {
            return $(this).attr('href') && $(this).attr('href').match(/\/(main|dark)\.css/);
        }).first();

        console.log('Theme toggle button found:', themeToggle.length);
        console.log('Theme stylesheet found:', themeStylesheet.length);

        if (themeStylesheet.length) {
            console.log('Stylesheet href:', themeStylesheet.attr('href'));
        }

        if (!themeToggle.length) {
            console.error('Theme toggle button not found!');
            return;
        }

        if (!themeStylesheet.length) {
            console.error('Theme stylesheet not found!');
            return;
        }

        function getTheme() {
            return localStorage.getItem('mybb-theme') || 'light';
        }

        function setTheme(theme) {
            console.log('Setting theme to:', theme);
            localStorage.setItem('mybb-theme', theme);
            document.documentElement.setAttribute('data-theme', theme);

            var stylesheetFile = theme === 'dark' ? 'dark.css' : 'main.css';
            var currentHref = themeStylesheet.attr('href');
            var newHref = currentHref.replace(/\/(main|dark)\.css/, '/' + stylesheetFile);

            console.log('Changing stylesheet from', currentHref, 'to', newHref);
            themeStylesheet.attr('href', newHref);
        }

        function toggleTheme() {
            var currentTheme = getTheme();
            var newTheme = currentTheme === 'light' ? 'dark' : 'light';
            console.log('Toggling theme from', currentTheme, 'to', newTheme);
            setTheme(newTheme);
        }

        var initialTheme = getTheme();
        console.log('Initial theme:', initialTheme);

        if (initialTheme === 'dark') {
            var currentHref = themeStylesheet.attr('href');
            var newHref = currentHref.replace(/\/main\.css/, '/dark.css');
            themeStylesheet.attr('href', newHref);
        }

        themeToggle.on('click', function(e) {
            console.log('Theme toggle button clicked!');
            e.preventDefault();
            e.stopPropagation();
            toggleTheme();
        });

        console.log('Theme toggle initialized successfully');
    });
})(jQuery);
