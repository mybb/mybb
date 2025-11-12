/**
 * MyBB Theme Switcher
 * Allows users to toggle between light and dark themes
 */
(function($) {
    'use strict';

    const ThemeSwitcher = {
        STORAGE_KEY: 'mybb_theme_preference',
        THEMES: {
            LIGHT: 'light',
            DARK: 'dark'
        },

        /**
         * Initialize the theme switcher
         */
        init: function() {
            this.loadSavedTheme();
            this.bindEvents();
        },

        /**
         * Load the user's saved theme preference
         */
        loadSavedTheme: function() {
            const savedTheme = localStorage.getItem(this.STORAGE_KEY);

            if (savedTheme === this.THEMES.DARK) {
                this.applyTheme(this.THEMES.DARK);
            } else {
                // Default to light theme
                this.applyTheme(this.THEMES.LIGHT);
            }
        },

        /**
         * Apply a theme (light or dark)
         */
        applyTheme: function(theme) {
            const $darkStylesheet = $('#mybb-dark-theme');

            if (theme === this.THEMES.DARK) {
                // Load dark theme if not already loaded
                if ($darkStylesheet.length === 0) {
                    // Get the path to dark.css from the main stylesheet
                    const mainCssHref = $('link[href*="main.css"]').attr('href');
                    if (mainCssHref) {
                        const darkCssHref = mainCssHref.replace('main.css', 'dark.css');

                        $('<link>')
                            .attr({
                                id: 'mybb-dark-theme',
                                rel: 'stylesheet',
                                type: 'text/css',
                                href: darkCssHref
                            })
                            .appendTo('head');
                    }
                }

                // Update UI state
                $('body').addClass('theme-dark').removeClass('theme-light');
                $('.theme-switcher-toggle').addClass('active');
            } else {
                // Remove dark theme
                $darkStylesheet.remove();

                // Update UI state
                $('body').addClass('theme-light').removeClass('theme-dark');
                $('.theme-switcher-toggle').removeClass('active');
            }

            // Save preference
            localStorage.setItem(this.STORAGE_KEY, theme);
        },

        /**
         * Toggle between light and dark themes
         */
        toggle: function() {
            const currentTheme = $('body').hasClass('theme-dark') ? this.THEMES.DARK : this.THEMES.LIGHT;
            const newTheme = currentTheme === this.THEMES.DARK ? this.THEMES.LIGHT : this.THEMES.DARK;

            this.applyTheme(newTheme);
        },

        /**
         * Bind click events to theme toggle buttons
         */
        bindEvents: function() {
            const self = this;

            $(document).on('click', '.theme-switcher-toggle', function(e) {
                e.preventDefault();
                self.toggle();
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        ThemeSwitcher.init();
    });

})(jQuery);
