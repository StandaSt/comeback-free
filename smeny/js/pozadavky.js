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
            state.textContent = 'Požadavky obsahují neuložené změny.';
            state.classList.remove('smeny_request_state--saved');
            state.classList.add('smeny_request_state--changed');
        }
    }

    function updateDay(day, activate, changedInput) {
        var start = day.querySelector('[data-smeny-start]');
        var end = day.querySelector('[data-smeny-end]');
        var startIndex = Number(start.value);
        var endIndex = Number(end.value);
        var minimum = Number(start.min);
        var maximum = Number(start.max);
        var step = Number(start.step) || 1;
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

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLInputElement) || target.type !== 'radio' || !target.closest('[data-smeny-requests-form]')) return;
        markFormDirty(target.closest('[data-smeny-requests-form]'));
    });

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;

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
