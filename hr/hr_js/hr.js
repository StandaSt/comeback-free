(() => {
    'use strict';

    // Chovani HR modulu v prohlizeci.

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

    const initHr = (scope) => {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;

        container.querySelectorAll('[data-phone-cz]').forEach((input) => {
            if (input.dataset.hrPhoneBound === '1') {
                return;
            }
            input.dataset.hrPhoneBound = '1';
            input.value = formatCzechPhone(input.value);
            input.addEventListener('input', () => {
                input.value = formatCzechPhone(input.value);
            });
        });

        container.querySelectorAll('[data-slot-select]').forEach((select) => {
            if (select.dataset.hrSlotBound === '1') {
                return;
            }
            select.dataset.hrSlotBound = '1';

            const input = select.closest('.hr_slot_choice')?.querySelector('[data-slot-other]');
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

        container.querySelectorAll('[data-hr_request_form]').forEach((form) => {
            if (form.dataset.hrRequestBound === '1') {
                return;
            }
            form.dataset.hrRequestBound = '1';

            const slot = form.querySelector('[data-hr-request-slot]');
            const submit = form.querySelector('.hr_request_submit');
            if (!slot || !submit) {
                return;
            }

            const updateRequestSubmit = () => {
                submit.classList.toggle('hr_request_submit_active', slot.value !== '');
            };

            updateRequestSubmit();
            slot.addEventListener('change', updateRequestSubmit);
        });
    };

    window.CB_HR_INIT = initHr;
    initHr(document);

})();
