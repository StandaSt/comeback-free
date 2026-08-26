(() => {
    'use strict';

    // Jednoúčelové ovládání textových datumů ve formátu DD.MM.RRRR.
    const currentYear = new Date().getFullYear();

    const isRealDate = (digits) => {
        if (digits.length !== 8) return false;
        const day = Number(digits.slice(0, 2));
        const month = Number(digits.slice(2, 4));
        const year = Number(digits.slice(4, 8));
        const date = new Date(year, month - 1, day);
        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    };

    const isAllowedPrefix = (digits) => {
        if (digits.length >= 2) {
            const day = Number(digits.slice(0, 2));
            if (day < 1 || day > 31) return false;
        }
        if (digits.length >= 4) {
            const month = Number(digits.slice(2, 4));
            if (month < 1 || month > 12) return false;
        }
        if (digits.length === 8 && (Number(digits.slice(4, 8)) > currentYear || !isRealDate(digits))) return false;
        return true;
    };

    const acceptedDigits = (value) => {
        let result = '';
        for (const character of String(value).replace(/\D/g, '').slice(0, 8)) {
            if (isAllowedPrefix(result + character)) result += character;
        }
        return result;
    };

    const format = (digits) => {
        if (digits.length <= 2) return digits;
        if (digits.length <= 4) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
        return `${digits.slice(0, 2)}.${digits.slice(2, 4)}.${digits.slice(4)}`;
    };

    const digitPosition = (value, caretPosition) => String(value).slice(0, caretPosition).replace(/\D/g, '').length;

    const caretPosition = (value, digitIndex) => {
        if (digitIndex <= 0) return 0;
        let digits = 0;
        for (let index = 0; index < value.length; index += 1) {
            if (/\d/.test(value[index])) digits += 1;
            if (digits === digitIndex) return index + 1;
        }
        return value.length;
    };

    const setValidity = (input, valid) => {
        const message = valid ? '' : 'Zadejte platné datum ve formátu DD.MM.RRRR.';
        input.setCustomValidity(message);
        input.toggleAttribute('aria-invalid', !valid);
    };

    const initInput = (input) => {
        if (input.dataset.cbDateBound === '1') return;
        input.dataset.cbDateBound = '1';
        input.type = 'text';
        input.inputMode = 'numeric';
        input.autocomplete = 'off';
        input.maxLength = 10;
        input.placeholder = 'DD.MM.RRRR';

        const sync = () => {
            const position = digitPosition(input.value, input.selectionStart ?? input.value.length);
            const digits = acceptedDigits(input.value);
            input.value = format(digits);
            const caret = caretPosition(input.value, Math.min(position, digits.length));
            input.setSelectionRange(caret, caret);
            setValidity(input, digits.length === 0 || isRealDate(digits));
        };

        sync();
        input.addEventListener('input', sync);
        input.addEventListener('blur', () => {
            const digits = acceptedDigits(input.value);
            input.value = format(digits);
            setValidity(input, digits.length === 0 || isRealDate(digits));
            if (input.validationMessage !== '') input.reportValidity();
        });
    };

    const init = (scope = document) => {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        container.querySelectorAll('input[data-cb-date]').forEach(initInput);
    };

    window.CB_DATE_INPUT_INIT = init;
    document.addEventListener('DOMContentLoaded', () => init(document));
})();
