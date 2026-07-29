(() => {
    'use strict';

    // Chovani verejneho HR dotazniku.

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

    document.querySelectorAll('[data-phone-cz]').forEach((input) => {
        input.value = formatCzechPhone(input.value);
        input.addEventListener('input', () => {
            input.value = formatCzechPhone(input.value);
        });
    });
})();
