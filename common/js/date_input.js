/* Spolecne datumove pole prijima D.M.RRRR i osm cislic; datum sjednoti pri opusteni a odeslani. */
(() => {
    'use strict';
    // Casti data parsujeme pred doplnenim nul, aby jednociferny den nezmenil vyznam mesice.
    const normalizedDate = value => {
        const text = String(value).trim().replace(/,/g, '.');
        const parts = text.match(/^(\d{1,2})\s*\.\s*(\d{1,2})\s*\.\s*(\d{4})$/) || text.match(/^(\d{2})(\d{2})(\d{4})$/);
        if (!parts) return null;
        const [, day, month, year] = parts;
        const date = new Date(0);
        date.setFullYear(Number(year), Number(month) - 1, Number(day));
        if (Number(year) < 1 || date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return null;
        return day.padStart(2, '0')+'.'+month.padStart(2, '0')+'.'+year;
    };
    // Psanou hodnotu nemenime po kazdem znaku, aby slo zadat i jednociferny den a mesic.
    const sync = (input, normalize) => {
        const value = normalizedDate(input.value);
        const valid = input.value.trim() === '' || value !== null;
        input.setCustomValidity(valid ? '' : 'Zadejte platné datum, například 6.11.2026.');
        input.toggleAttribute('aria-invalid', !valid);
        if (normalize && value !== null) input.value = value;
    };
    const init = (scope = document) => {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        container.querySelectorAll('input[data-cb-date]').forEach(input => {
            if (input.dataset.cbDateBound === '1') return;
            input.dataset.cbDateBound = '1';input.type = 'text';input.inputMode = 'decimal';input.autocomplete = 'off';input.maxLength = 12;input.placeholder = 'D.M.RRRR';
            sync(input, true);
            input.addEventListener('input', () => sync(input, false));
            input.addEventListener('blur', () => sync(input, true));
        });
    };
    // I Enter predava stavajicim formularum jednotny format pred sestavenim FormData.
    document.addEventListener('submit', event => {
        if (event.target instanceof HTMLFormElement) event.target.querySelectorAll('input[data-cb-date]').forEach(input => sync(input, true));
    }, true);
    window.CB_DATE_INPUT_INIT = init;
    document.addEventListener('DOMContentLoaded', () => init(document));
})();
