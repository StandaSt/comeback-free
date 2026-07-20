(() => {
    'use strict';

    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    const icon = document.querySelector('[data-theme-icon]');
    const storageKey = 'comeback_hr_theme';

    const applyTheme = (theme) => {
        const normalized = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = normalized;
        if (icon) {
            icon.textContent = normalized === 'dark' ? '☀' : '☾';
        }
    };

    const savedTheme = localStorage.getItem(storageKey);
    applyTheme(savedTheme || 'light');

    button?.addEventListener('click', () => {
        const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(storageKey, nextTheme);
        applyTheme(nextTheme);
    });

})();
