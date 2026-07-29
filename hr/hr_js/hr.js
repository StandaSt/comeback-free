(() => {
    'use strict';

    // Chovani HR modulu v prohlizeci.

    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    const icon = document.querySelector('[data-theme-icon]');
    const storageKey = 'comeback_hr_theme';

    // Formatuje cesky telefon na tvar 123 456 789.
    const formatCzechPhone = (value) => {
        let digits = String(value || '').replace(/\D+/g, '');
        if (digits.length === 12 && digits.startsWith('420')) {
            digits = digits.slice(3);
        }
        if (digits.length === 14 && digits.startsWith('00420')) {
            digits = digits.slice(5);
        }
        digits = digits.slice(0, 9);
        return digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
    };

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

    document.querySelectorAll('[data-phone-cz]').forEach((input) => {
        input.value = formatCzechPhone(input.value);
        input.addEventListener('input', () => {
            input.value = formatCzechPhone(input.value);
        });
    });

    document.querySelectorAll('[data-slot-select]').forEach((select) => {
        const input = select.closest('.hr-slot-choice')?.querySelector('[data-slot-other]');
        if (!input) {
            return;
        }

        const updateOtherSlot = () => {
            const active = select.value === '__jine__';
            input.disabled = !active;
            input.required = active;
            if (!active) {
                input.value = '';
            }
        };

        updateOtherSlot();
        select.addEventListener('change', updateOtherSlot);
    });

})();
