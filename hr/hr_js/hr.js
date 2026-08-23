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

        container.querySelectorAll('[data-hr-vd-action-form]').forEach((form) => {
            if (form.dataset.hrVdActionBound === '1') {
                return;
            }
            form.dataset.hrVdActionBound = '1';

            const type = form.querySelector('[data-hr-vd-action-type]');
            const result = form.querySelector('[data-hr-vd-action-result]');
            const term = form.querySelector('[data-hr-vd-term]');
            const date = form.querySelector('[data-hr-vd-term-date]');
            const time = form.querySelector('[data-hr-vd-term-time]');
            const hour = form.querySelector('[data-hr-vd-term-hour]');
            const minute = form.querySelector('[data-hr-vd-term-minute]');
            const timeWrap = form.querySelector('[data-hr-vd-term-time-wrap]');
            const agreedStart = form.querySelector('[data-hr-vd-domluveny-nastup]');
            const source = form.querySelector('[data-hr-vd-action-results]');
            if (!type || !result || !term || !date || !time || !hour || !minute || !timeWrap || !agreedStart || !source) {
                return;
            }

            let rows = [];
            try {
                rows = JSON.parse(source.textContent || '[]');
            } catch (error) {
                rows = [];
            }

            const syncTime = () => {
                time.value = `${hour.value}:${minute.value}`;
            };

            const updateTerm = () => {
                const selected = rows.find((row) => String(row.id_vd_akce_vysledek) === result.value);
                const needsDate = selected && Number(selected.vyzaduje_termin_date) === 1;
                const needsTime = selected && Number(selected.vyzaduje_termin_time) === 1;
                const isAgreedStart = selected && Number(selected.id_cilovy_vd_stav) === 24;
                term.hidden = !needsDate;
                agreedStart.hidden = !isAgreedStart;
                agreedStart.querySelectorAll('[data-hr-vd-podminka]').forEach((input) => {
                    input.required = Boolean(isAgreedStart);
                    if (!isAgreedStart) input.value = '';
                });
                date.required = Boolean(needsDate);
                time.required = Boolean(needsTime);
                timeWrap.hidden = !needsDate;
                if (needsTime && time.value === '') {
                    hour.value = '8';
                    minute.value = '00';
                    syncTime();
                }
                if (!needsDate) {
                    date.value = '';
                    time.value = '';
                    hour.value = '8';
                    minute.value = '00';
                }
            };

            const updateResults = () => {
                const typeId = type.value;
                result.replaceChildren();
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = typeId === '' ? 'Nejprve vyberte akci' : 'Vyberte';
                result.append(placeholder);

                rows.filter((row) => String(row.id_vd_akce_typ) === typeId).forEach((row) => {
                    const option = document.createElement('option');
                    option.value = String(row.id_vd_akce_vysledek);
                    option.textContent = row.vysledek;
                    result.append(option);
                });
                result.disabled = typeId === '';
                updateTerm();
            };

            type.addEventListener('change', updateResults);
            result.addEventListener('change', updateTerm);
            hour.addEventListener('change', syncTime);
            minute.addEventListener('change', syncTime);
            updateResults();
        });
    };

    window.CB_HR_INIT = initHr;
    initHr(document);

})();
