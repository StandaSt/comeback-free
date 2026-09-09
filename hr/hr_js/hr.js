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

    // Overi pocet hodin: jsou povoleny jen cele hodiny nebo pulhodiny v danem rozsahu.
    const validateWorkHours = (input, maximum) => {
        const value = input.value.replace(',', '.').replace(/[^\d.]/g, '');
        const parts = value.split('.');
        const hours = parts[0] || '';
        const decimal = (parts[1] || '').slice(0, 1);
        input.value = decimal === '' ? hours : `${hours}.${decimal === '5' ? '5' : '0'}`;

        if (input.value === '') {
            input.setCustomValidity('Zadejte počet hodin týdně.');
            return false;
        }
        const number = Number(input.value);
        if (number <= 0 || number > maximum) {
            input.setCustomValidity(`Zadejte počet hodin v rozsahu 0,5 až ${maximum}.`);
            return false;
        }
        input.setCustomValidity('');
        return true;
    };

    // Overi pouze syntaxi ceskeho rodneho cisla, ne jeho prirazeni konkretni osobe.
    const validateBirthNumber = (input) => {
        const value = input.value.trim();
        if (value === '') {
            input.setCustomValidity('');
            return true;
        }

        const number = value.replace(/[\s/]/g, '');
        if (!/^\d{9}(?:\d)?$/.test(number) || /^(\d)\1+$/.test(number)) {
            input.setCustomValidity('Zadejte platné rodné číslo ve tvaru YYMMDD/XXX(X).');
            return false;
        }

        const yearPart = Number(number.slice(0, 2));
        let month = Number(number.slice(2, 4));
        const day = Number(number.slice(4, 6));
        if (month >= 71 && month <= 82) {
            month -= 70;
        } else if (month >= 51 && month <= 62) {
            month -= 50;
        } else if (month >= 21 && month <= 32) {
            month -= 20;
        }
        const year = number.length === 10 && yearPart <= new Date().getFullYear() % 100 ? 2000 + yearPart : 1900 + yearPart;
        const date = new Date(year, month - 1, day);
        if (month < 1 || month > 12 || date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            input.setCustomValidity('Rodné číslo neobsahuje platné datum narození.');
            return false;
        }
        if (number.length === 10 && Number(number) % 11 !== 0) {
            input.setCustomValidity('Rodné číslo není dělitelné 11.');
            return false;
        }

        input.setCustomValidity('');
        return true;
    };

    // Inicializuje chovani HR prvku v predanem obsahu stranky.
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

        container.querySelectorAll('[data-photo-input]').forEach((input) => {
            if (input.dataset.hrPhotoBound === '1') {
                return;
            }
            input.dataset.hrPhotoBound = '1';

            const form = input.closest('form');
            const row = input.closest('.hr_new_employee_photo_row');
            const preview = row?.querySelector('[data-photo-preview]');
            const image = preview?.querySelector('img');
            const cropOpen = row?.querySelector('[data-photo-crop-open]');
            const dialog = form?.closest('.hr_panel')?.querySelector('[data-photo-crop-dialog]');
            const cropSurface = dialog?.querySelector('[data-photo-crop-surface]');
            const cropImage = dialog?.querySelector('[data-photo-crop-image]');
            const selection = dialog?.querySelector('[data-photo-crop-selection]');
            const cropApply = dialog?.querySelector('[data-photo-crop-apply]');
            const cropCancel = dialog?.querySelector('[data-photo-crop-cancel]');
            if (!preview || !image || !cropOpen || !dialog || !cropSurface || !cropImage || !selection || !cropApply || !cropCancel) {
                return;
            }

            let selectedFile = null;
            let cropUrl = '';
            let selecting = null;

            const showPreview = (file) => {
                const previousUrl = image.dataset.previewUrl;
                if (previousUrl) {
                    URL.revokeObjectURL(previousUrl);
                    delete image.dataset.previewUrl;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!file || !allowedTypes.includes(file.type)) {
                    image.removeAttribute('src');
                    preview.hidden = true;
                    cropOpen.disabled = true;
                    cropOpen.classList.remove('is-visible');
                    input.setCustomValidity(file ? 'Fotografie musí být JPEG, PNG nebo WebP.' : '');
                    return;
                }

                input.setCustomValidity('');
                const previewUrl = URL.createObjectURL(file);
                image.src = previewUrl;
                image.dataset.previewUrl = previewUrl;
                preview.hidden = false;
                cropOpen.disabled = false;
                cropOpen.classList.add('is-visible');
            };

            const setSelection = (left, top, width, height) => {
                const box = cropImage.getBoundingClientRect();
                const safeLeft = Math.max(0, Math.min(left, box.width - 1));
                const safeTop = Math.max(0, Math.min(top, box.height - 1));
                const safeWidth = Math.max(1, Math.min(width, box.width - safeLeft));
                const safeHeight = Math.max(1, Math.min(height, box.height - safeTop));
                selection.style.left = `${safeLeft}px`;
                selection.style.top = `${safeTop}px`;
                selection.style.width = `${safeWidth}px`;
                selection.style.height = `${safeHeight}px`;
            };

            const startSelection = () => {
                const box = cropImage.getBoundingClientRect();
                const size = Math.min(box.width, box.height) * .7;
                setSelection((box.width - size) / 2, (box.height - size) / 2, size, size);
            };

            const closeCropDialog = () => {
                if (dialog.open) {
                    dialog.close();
                }
                if (cropUrl) {
                    URL.revokeObjectURL(cropUrl);
                    cropUrl = '';
                }
                cropImage.removeAttribute('src');
                selecting = null;
            };

            input.addEventListener('change', () => {
                selectedFile = input.files?.[0] ?? null;
                showPreview(selectedFile);
            });

            cropOpen.addEventListener('click', () => {
                if (!selectedFile) {
                    return;
                }
                if (cropUrl) {
                    URL.revokeObjectURL(cropUrl);
                }
                cropUrl = URL.createObjectURL(selectedFile);
                cropImage.src = cropUrl;
                dialog.showModal();
            });

            cropImage.addEventListener('load', startSelection);
            cropCancel.addEventListener('click', closeCropDialog);
            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                closeCropDialog();
            });

            cropSurface.addEventListener('pointerdown', (event) => {
                const box = cropImage.getBoundingClientRect();
                const x = Math.max(0, Math.min(event.clientX - box.left, box.width));
                const y = Math.max(0, Math.min(event.clientY - box.top, box.height));
                selecting = { x, y };
                cropSurface.setPointerCapture(event.pointerId);
                setSelection(x, y, 1, 1);
            });

            cropSurface.addEventListener('pointermove', (event) => {
                if (!selecting) {
                    return;
                }
                const box = cropImage.getBoundingClientRect();
                const x = Math.max(0, Math.min(event.clientX - box.left, box.width));
                const y = Math.max(0, Math.min(event.clientY - box.top, box.height));
                setSelection(Math.min(selecting.x, x), Math.min(selecting.y, y), Math.abs(x - selecting.x), Math.abs(y - selecting.y));
            });

            cropSurface.addEventListener('pointerup', () => {
                selecting = null;
            });

            cropApply.addEventListener('click', () => {
                const imageBox = cropImage.getBoundingClientRect();
                const selectionBox = selection.getBoundingClientRect();
                const scaleX = cropImage.naturalWidth / imageBox.width;
                const scaleY = cropImage.naturalHeight / imageBox.height;
                const sourceX = Math.round((selectionBox.left - imageBox.left) * scaleX);
                const sourceY = Math.round((selectionBox.top - imageBox.top) * scaleY);
                const sourceWidth = Math.round(selectionBox.width * scaleX);
                const sourceHeight = Math.round(selectionBox.height * scaleY);
                if (sourceWidth < 1 || sourceHeight < 1) {
                    return;
                }

                const targetScale = Math.min(1, 1200 / Math.max(sourceWidth, sourceHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(sourceWidth * targetScale));
                canvas.height = Math.max(1, Math.round(sourceHeight * targetScale));
                const context = canvas.getContext('2d');
                if (!context) {
                    return;
                }
                context.drawImage(cropImage, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        return;
                    }
                    const croppedFile = new File([blob], 'fotografie-vyrez.jpg', { type: 'image/jpeg' });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    input.files = transfer.files;
                    selectedFile = croppedFile;
                    showPreview(croppedFile);
                    closeCropDialog();
                }, 'image/jpeg', .9);
            });
        });

        container.querySelectorAll('[data-birth-number]').forEach((input) => {
            if (input.dataset.hrBirthNumberBound === '1') {
                return;
            }
            input.dataset.hrBirthNumberBound = '1';
            validateBirthNumber(input);
            input.addEventListener('input', () => validateBirthNumber(input));
            input.addEventListener('blur', () => validateBirthNumber(input));
        });

        container.querySelectorAll('[data-hr-branch-picker]').forEach((picker) => {
            if (picker.dataset.hrBranchBound === '1') {
                return;
            }
            picker.dataset.hrBranchBound = '1';

            const form = picker.closest('form');
            const toggle = picker.querySelector('[data-hr-branch-toggle]');
            const panel = picker.querySelector('[data-hr-branch-panel]');
            const branchInputs = [...picker.querySelectorAll('[data-hr-branch-option]')];
            const mainBranch = form?.querySelector('[data-hr-main-branch]');
            if (!form || !toggle || !panel || !mainBranch || branchInputs.length === 0) {
                return;
            }

            const updateBranches = () => {
                const selected = branchInputs.filter((input) => input.checked);
                branchInputs[0].required = selected.length === 0;
                const previousMain = mainBranch.value;
                mainBranch.replaceChildren();

                if (selected.length === 0) {
                    mainBranch.disabled = true;
                    mainBranch.add(new Option('Nejprve vyberte pobočky', ''));
                    toggle.textContent = 'Vyberte pobočky';
                    return;
                }

                mainBranch.disabled = false;
                mainBranch.add(new Option('Vyberte', ''));
                selected.forEach((input) => {
                    mainBranch.add(new Option(input.dataset.hrBranchName || '', input.value));
                });
                mainBranch.value = selected.some((input) => input.value === previousMain) ? previousMain : selected[0].value;

                const names = selected.map((input) => input.dataset.hrBranchName || '');
                toggle.textContent = names.length <= 2 ? names.join(', ') : `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
            };

            toggle.addEventListener('click', () => {
                panel.hidden = !panel.hidden;
            });
            branchInputs.forEach((input) => input.addEventListener('change', updateBranches));
            form.addEventListener('submit', () => {
                if (branchInputs.every((input) => !input.checked)) {
                    branchInputs[0].required = true;
                }
            });
            updateBranches();
        });

        container.querySelectorAll('[data-hr-work-relation-form]').forEach((form) => {
            if (form.dataset.hrWorkRelationBound === '1') {
                return;
            }
            form.dataset.hrWorkRelationBound = '1';

            const type = form.querySelector('[data-hr-work-relation-type]');
            const workload = form.querySelector('[data-hr-workload-kind]');
            const hours = form.querySelector('[data-hr-workload-hours]');
            const salaryType = form.querySelector('[data-hr-salary-type]');
            const salaryAmount = form.querySelector('[data-hr-salary-amount]');
            const salaryLabel = form.querySelector('[data-hr-salary-label]');
            const workloadParts = form.querySelectorAll('[data-hr-workload-kind-heading], [data-hr-workload-kind-cell]');
            const hoursParts = form.querySelectorAll('[data-hr-workload-hours-heading], [data-hr-workload-hours-cell]');
            const dppParts = form.querySelectorAll('[data-hr-workload-dpp-heading], [data-hr-workload-dpp-cell]');
            if (!type || !workload || !hours) {
                return;
            }

            const setHidden = (items, hidden) => items.forEach((item) => { item.hidden = hidden; });
            const hpp = form.dataset.hrWorkTypeHpp;
            const dpp = form.dataset.hrWorkTypeDpp;
            const dpc = form.dataset.hrWorkTypeDpc;

            // Prepne popisek castky podle hodinove nebo fixni mzdy.
            const updateSalary = () => {
                if (!salaryType || !salaryLabel) {
                    return;
                }
                salaryLabel.textContent = salaryType.value === '2' ? 'Částka Kč / měsíc' : 'Částka Kč / hodinu';
            };

            // Prepne vstupy podle zvoleneho typu pracovniho vztahu.
            const updateWorkload = () => {
                const typeId = type.value;
                const isHpp = typeId === hpp;
                const isDpc = typeId === dpc;
                const isDpp = typeId === dpp;
                const isUnspecified = typeId === '';
                setHidden(workloadParts, !(isHpp || isUnspecified));
                setHidden(hoursParts, isDpp);
                setHidden(dppParts, !isDpp);

                if (isUnspecified) {
                    workload.disabled = false;
                    hours.value = '';
                    hours.disabled = true;
                    hours.required = false;
                    hours.setCustomValidity('');
                    return;
                }

                if (isDpp) {
                    workload.disabled = true;
                    hours.disabled = true;
                    hours.required = false;
                    hours.value = '';
                    hours.setCustomValidity('');
                    return;
                }

                workload.value = isDpc ? '0' : workload.value;
                workload.disabled = !isHpp;
                hours.disabled = false;
                hours.required = true;

                if (isHpp && workload.value !== '0') {
                    const fixedHours = { 1: '40', 2: '20', 4: '10' };
                    hours.value = fixedHours[workload.value] || '';
                    hours.disabled = true;
                    hours.required = false;
                    hours.setCustomValidity('');
                    return;
                }

                if (isDpc && hours.value === '') {
                    hours.value = '20';
                }
                validateWorkHours(hours, isDpc ? 60 : 99.5);
            };

            type.addEventListener('change', updateWorkload);
            workload.addEventListener('change', updateWorkload);
            if (salaryType) {
                salaryType.addEventListener('change', updateSalary);
            }
            if (salaryAmount) {
                salaryAmount.addEventListener('input', () => {
                    salaryAmount.value = salaryAmount.value.replace(/\D+/g, '');
                    salaryAmount.setCustomValidity(salaryAmount.value === '' || Number(salaryAmount.value) <= 0 ? 'Zadejte částku mzdy v celých Kč.' : '');
                });
            }
            hours.addEventListener('input', () => {
                if (!hours.disabled) {
                    validateWorkHours(hours, type.value === dpc ? 60 : 99.5);
                }
            });
            hours.addEventListener('blur', () => {
                if (!hours.disabled) {
                    validateWorkHours(hours, type.value === dpc ? 60 : 99.5);
                }
            });
            form.addEventListener('submit', (event) => {
                updateWorkload();
                if (!hours.disabled && !validateWorkHours(hours, type.value === dpc ? 60 : 99.5)) {
                    event.preventDefault();
                    hours.reportValidity();
                }
            });
            updateWorkload();
            updateSalary();
        });

        if (typeof window.CB_DATE_INPUT_INIT === 'function') {
            window.CB_DATE_INPUT_INIT(container);
        }

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
            const agreedInputs = Array.from(form.querySelectorAll('[data-hr-vd-podminka]'));
            const agreedRequiredInputs = Array.from(form.querySelectorAll('[data-hr-vd-podminka-required]'));
            const agreedAreas = Array.from(form.querySelectorAll('[data-hr-vd-oblast]'));
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

            const syncAgreedAreas = (isAgreedStart) => {
                const hasCheckedArea = agreedAreas.some((input) => input.checked);
                agreedAreas.forEach((input, index) => {
                    input.required = Boolean(isAgreedStart && !hasCheckedArea && index === 0);
                });
            };

            const updateTerm = () => {
                const selected = rows.find((row) => String(row.id_vd_akce_vysledek) === result.value);
                const needsDate = selected && Number(selected.vyzaduje_termin_date) === 1;
                const needsTime = selected && Number(selected.vyzaduje_termin_time) === 1;
                const isAgreedStart = selected && Number(selected.id_cilovy_vd_stav) === 24;
                term.hidden = !needsDate;
                agreedStart.hidden = !isAgreedStart;
                agreedRequiredInputs.forEach((input) => {
                    input.required = Boolean(isAgreedStart);
                });
                if (!isAgreedStart) {
                    agreedInputs.forEach((input) => {
                        if (input instanceof HTMLInputElement && (input.type === 'checkbox' || input.type === 'radio')) {
                            input.checked = false;
                        } else {
                            input.value = '';
                        }
                    });
                }
                syncAgreedAreas(isAgreedStart);
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
            agreedAreas.forEach((input) => {
                input.addEventListener('change', () => syncAgreedAreas(!agreedStart.hidden));
            });
            hour.addEventListener('change', syncTime);
            minute.addEventListener('change', syncTime);
            updateResults();
        });
    };

    window.CB_HR_INIT = initHr;
    initHr(document);

})();
