/* Účel: Ovládá požadavky a kopírování dnů; Kdykoliv předvyplní upravitelné dny a Reset vyprázdní celý rozpracovaný týden. */
(function () {
    'use strict';

    function timeFromIndex(index) {
        var minutes = (360 + ((index - 1) * 15)) % 1440;
        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function dayFor(target) {
        return target.closest('[data-smeny-request-day]');
    }

    function valueOnGridAtOrBefore(value, minimum, step) {
        return minimum + (Math.floor((value - minimum) / step) * step);
    }

    // Rozpracování zpřístupní uložení a skryje původní stav; samostatnou hlášku o změnách nezobrazujeme.
    function markFormDirty(form) {
        if (!form) return;
        var actions = form.querySelector('[data-smeny-actions]');
        if (actions) actions.hidden = false;
        var saveButton = form.querySelector('[data-smeny-save]');
        if (saveButton) {
            saveButton.hidden = false;
            saveButton.textContent = form.dataset.smenySaved === '1'
                ? 'Uložit upravené požadavky'
                : 'Uložit celý týden';
        }
        var copyWeek = form.querySelector('[data-smeny-copy-week]');
        if (copyWeek) copyWeek.hidden = true;
        var state = document.querySelector('[data-smeny-save-state]');
        if (state) {
            state.hidden = true;
        }
    }

    // Zaškrtnutí odpovídá sedmi plným dnům; zkrácení nebo vymazání dne vypne souhrnnou volbu.
    function syncAnytime(form) {
        var anytime = form && form.querySelector('[data-smeny-anytime]');
        if (!anytime) return;
        var days = Array.from(form.querySelectorAll('[data-smeny-request-day]'));
        anytime.checked = days.length === 7 && days.every(function (day) {
            var start=day.querySelector('[data-smeny-start]'), end=day.querySelector('[data-smeny-end]');
            return day.classList.contains('smeny_request_day--active') && start.value === start.min && end.value === end.max;
        });
        anytime.dispatchEvent(new CustomEvent('cb:smeny-anytime-synced',{bubbles:true}));
    }

    // Sladí posuvníky, přesný interval, modré označení a souhrnnou volbu bez ukládání formuláře.
    function updateDay(day, activate, changedInput) {
        var start = day.querySelector('[data-smeny-start]');
        var end = day.querySelector('[data-smeny-end]');
        var startIndex = Number(start.value);
        var endIndex = Number(end.value);
        var minimum = Number(start.min);
        var maximum = Number(start.max);
        var step = Number(start.step) || 1;
        // Konec drží původní hodinový krok a navíc přesné zavření, i když končí např. v 01:15.
        var snappedEnd = Math.min(maximum, minimum + Math.round((endIndex-minimum)/step)*step);
        endIndex = Math.abs(maximum-endIndex) < Math.abs(snappedEnd-endIndex) ? maximum : snappedEnd;
        end.value = String(endIndex);
        var minimumDuration = 12;
        var length = Math.max(1, maximum - minimum);

        if ((endIndex - startIndex) < minimumDuration) {
            if (changedInput === end) {
                if ((endIndex - minimum) < minimumDuration) {
                    endIndex = Math.min(maximum, minimum + minimumDuration);
                    end.value = String(endIndex);
                }
                startIndex = valueOnGridAtOrBefore(endIndex - minimumDuration, minimum, step);
                startIndex = Math.max(minimum, startIndex);
                start.value = String(startIndex);
            } else {
                if ((startIndex + minimumDuration) <= maximum) {
                    endIndex = startIndex + minimumDuration;
                } else {
                    endIndex = maximum;
                    startIndex = valueOnGridAtOrBefore(maximum - minimumDuration, minimum, step);
                    startIndex = Math.max(minimum, startIndex);
                    start.value = String(startIndex);
                }
                end.value = String(endIndex);
            }
        }

        day.querySelector('[data-smeny-range-track]').style.setProperty('--smeny-range-start', (((startIndex - minimum) / length) * 100) + '%');
        day.querySelector('[data-smeny-range-track]').style.setProperty('--smeny-range-end', (((endIndex - minimum) / length) * 100) + '%');
        start.setAttribute('aria-valuetext', timeFromIndex(startIndex));
        end.setAttribute('aria-valuetext', timeFromIndex(endIndex));

        if (activate) {
            day.classList.add('smeny_request_day--active');
            day.querySelector('[data-smeny-start-value]').value = timeFromIndex(startIndex);
            day.querySelector('[data-smeny-end-value]').value = timeFromIndex(endIndex);
            day.querySelector('[data-smeny-selection]').textContent = timeFromIndex(startIndex) + '–' + timeFromIndex(endIndex);
            day.querySelector('[data-smeny-clear]').disabled = false;
            var copyButton = day.querySelector('[data-smeny-copy-next]');
            if (copyButton) copyButton.disabled = false;
            syncAnytime(day.closest('[data-smeny-requests-form]'));
        }
    }

    function clearDay(day) {
        var start = day.querySelector('[data-smeny-start]');
        var end = day.querySelector('[data-smeny-end]');
        start.value = start.min;
        end.value = end.max;
        day.querySelector('[data-smeny-start-value]').value = '';
        day.querySelector('[data-smeny-end-value]').value = '';
        day.querySelector('[data-smeny-selection]').textContent = 'Nezadáno';
        day.querySelector('[data-smeny-clear]').disabled = true;
        var copyButton = day.querySelector('[data-smeny-copy-next]');
        if (copyButton) copyButton.disabled = true;
        day.classList.remove('smeny_request_day--active');
        updateDay(day, false, null);
        syncAnytime(day.closest('[data-smeny-requests-form]'));
    }

    document.addEventListener('pointerdown', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-smeny-start], [data-smeny-end]')) return;
        var day = dayFor(target);
        if (day && !day.classList.contains('smeny_request_day--active')) {
            updateDay(day, true, target);
            markFormDirty(target.closest('[data-smeny-requests-form]'));
        }
    });

    document.addEventListener('input', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLInputElement)) return;
        var day = dayFor(target);
        if (!day) return;

        if (target.matches('[data-smeny-start], [data-smeny-end]')) {
            updateDay(day, true, target);
            markFormDirty(target.closest('[data-smeny-requests-form]'));
        }
    });

    // Klávesnice prochází stejné body jako myš; jemný HTML krok slouží pouze přesnému zavření.
    document.addEventListener('keydown', function (event) {
        var end=event.target;
        if (!(end instanceof HTMLInputElement) || !end.matches('[data-smeny-end]') || end.disabled) return;
        if (!['ArrowLeft','ArrowDown','ArrowRight','ArrowUp','Home','End','PageUp','PageDown'].includes(event.key)) return;
        var day=dayFor(end), start=day.querySelector('[data-smeny-start]');
        var min=Number(end.min), max=Number(end.max), step=Number(start.step)||1, old=Number(end.value);
        var down=['ArrowLeft','ArrowDown','PageDown'].includes(event.key);
        var next=down ? min+(Math.ceil((old-min)/step)-1)*step : min+(Math.floor((old-min)/step)+1)*step;
        if(event.key==='Home') next=min;
        if(event.key==='End') next=max;
        event.preventDefault(); end.value=String(Math.max(min,Math.min(max,next)));
        end.dispatchEvent(new Event('input',{bubbles:true}));
        end.dispatchEvent(new Event('change',{bubbles:true}));
    });

    document.addEventListener('change', function (event) {
        var target = event.target;
        // Hromadná volba je jen předvyplnění. Odškrtnutí hodnoty nemaže a intervaly se nikdy neschovávají.
        if (target instanceof HTMLInputElement && target.matches('[data-smeny-anytime]')) {
            var form = target.closest('[data-smeny-requests-form]');
            if (target.checked) {
                form.querySelectorAll('[data-smeny-request-day]').forEach(function (day) {
                    var start=day.querySelector('[data-smeny-start]'), end=day.querySelector('[data-smeny-end]');
                    if (start.disabled || end.disabled) return;
                    start.value=start.min; end.value=end.max;
                    updateDay(day,true,null);
                });
                syncAnytime(form);
                // Historie od této chvíle porovnává ruční editace s právě předvyplněnými časy.
                form.dispatchEvent(new CustomEvent('cb:smeny-request-prefilled',{bubbles:true}));
            }
            markFormDirty(form);
            return;
        }
        if (!(target instanceof HTMLInputElement) || target.type !== 'radio' || !target.closest('[data-smeny-requests-form]')) return;
        markFormDirty(target.closest('[data-smeny-requests-form]'));
    });

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;

        // Reset je rozpracovaná změna stejná jako sedm vymazání dne; uloží se až s celým týdnem.
        var reset = event.target.closest('[data-smeny-reset-week]');
        if (reset && !reset.disabled) {
            var form=reset.closest('[data-smeny-requests-form]');
            form.querySelectorAll('[data-smeny-request-day]').forEach(clearDay);
            markFormDirty(form);
            form.dispatchEvent(new CustomEvent('cb:smeny-request-prefilled',{bubbles:true}));
            return;
        }

        var copyButton = event.target.closest('[data-smeny-copy-next]');
        if (copyButton && !copyButton.disabled) {
            var sourceDay = dayFor(copyButton);
            var targetDay = sourceDay ? sourceDay.nextElementSibling : null;
            if (targetDay && targetDay.matches('[data-smeny-request-day]')) {
                var targetStart = targetDay.querySelector('[data-smeny-start]');
                var targetEnd = targetDay.querySelector('[data-smeny-end]');
                if (targetStart && targetEnd && !targetStart.disabled && !targetEnd.disabled) {
                    targetStart.value = sourceDay.querySelector('[data-smeny-start]').value;
                    targetEnd.value = sourceDay.querySelector('[data-smeny-end]').value;
                    updateDay(targetDay, true, null);
                    markFormDirty(targetDay.closest('[data-smeny-requests-form]'));
                }
            }
            return;
        }

        var button = event.target.closest('[data-smeny-clear]');
        if (!button || button.disabled) return;
        var day = dayFor(button);
        if (day) {
            clearDay(day);
            markFormDirty(day.closest('[data-smeny-requests-form]'));
        }
    });

}());
