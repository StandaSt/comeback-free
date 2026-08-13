(function () {
  'use strict';

  function pad2(value) {
    return String(value).padStart(2, '0');
  }

  function isTimeValue(value) {
    return /^\d{2}:\d{2}$/.test(String(value || ''));
  }

  function initRoot(periodRoot) {
    if (!periodRoot || periodRoot.getAttribute('data-cb-period-ready') === '1') {
      return;
    }
    periodRoot.setAttribute('data-cb-period-ready', '1');

    var periodToggle = periodRoot.querySelector('[data-cb-period-toggle="1"]');
    var periodPanel = periodRoot.querySelector('[data-cb-period-panel="1"]');
    var odInput = periodRoot.querySelector('#cbObdobiOd');
    var doInput = periodRoot.querySelector('#cbObdobiDo');
    var odCasInput = periodRoot.querySelector('#cbObdobiOdCas');
    var doCasInput = periodRoot.querySelector('#cbObdobiDoCas');
    var quickBtns = Array.prototype.slice.call(periodRoot.querySelectorAll('.head_interval .head_pill[data-range]'));
    var meter = periodRoot.querySelector('.head_interval .head_interval_meter');
    var meterBar = meter ? meter.querySelector('.head_interval_meter_bar') : null;
    var summaryEl = periodRoot.querySelector('[data-cb-period-summary="1"]');
    var odLabel = odInput ? odInput.closest('.head_date') : null;
    var doLabel = doInput ? doInput.closest('.head_date') : null;
    var odLabelText = odLabel ? odLabel.querySelector('.head_date_label') : null;
    var doLabelText = doLabel ? doLabel.querySelector('.head_date_label') : null;
    var activeMode = String(periodRoot.getAttribute('data-active-mode') || 'manual');
    var manualSaveDelayMs = parseInt(String(periodRoot.getAttribute('data-manual-save-delay-ms') || '3000'), 10);
    var saveUrl = String(periodRoot.getAttribute('data-save-url') || 'index.php');
    var manualSaveTimer = null;

    if (!odInput || !doInput || !odCasInput || !doCasInput || !quickBtns.length) {
      return;
    }
    if (!Number.isFinite(manualSaveDelayMs) || manualSaveDelayMs <= 0) {
      manualSaveDelayMs = 3000;
    }

    function closePeriodPanel() {
      if (!periodPanel || !periodToggle) {
        return;
      }
      periodPanel.classList.add('is-hidden');
      periodToggle.setAttribute('aria-expanded', 'false');
    }

    function openPeriodPanel() {
      if (!periodPanel || !periodToggle) {
        return;
      }
      periodPanel.classList.remove('is-hidden');
      periodToggle.setAttribute('aria-expanded', 'true');
    }

    if (periodToggle && periodPanel) {
      periodToggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (periodPanel.classList.contains('is-hidden')) {
          openPeriodPanel();
        } else {
          closePeriodPanel();
        }
      });
      periodPanel.addEventListener('click', function (event) {
        event.stopPropagation();
      });
      document.addEventListener('click', closePeriodPanel);
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closePeriodPanel();
        }
      });
    }

    var isSaving = false;
    var defaultTime = '06:00';
    var odTimeKey = 'cb_obdobi_od_cas';
    var doTimeKey = 'cb_obdobi_do_cas';
    var allowedModes = ['dnes', 'vcera', 'tyden', 'mesic', 'rok', 'vse', 'manual'];
    if (allowedModes.indexOf(activeMode) === -1) {
      activeMode = 'manual';
    }

    function fmtDate(dt) {
      return dt.getFullYear() + '-' + pad2(dt.getMonth() + 1) + '-' + pad2(dt.getDate());
    }

    function fmtTime(dt) {
      return pad2(dt.getHours()) + ':' + pad2(dt.getMinutes());
    }

    function parseDate(value) {
      var s = String(value || '').trim();
      if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        return null;
      }
      var dateParts = s.split('-');
      var y = Number(dateParts[0]);
      var m = Number(dateParts[1]);
      var d = Number(dateParts[2]);
      var dt = new Date(y, m - 1, d, 6, 0, 0, 0);
      if (dt.getFullYear() !== y || (dt.getMonth() + 1) !== m || dt.getDate() !== d) {
        return null;
      }
      return dt;
    }

    function loadTime(key) {
      try {
        var value = window.sessionStorage ? window.sessionStorage.getItem(key) : '';
        return isTimeValue(value) ? value : defaultTime;
      } catch (e) {
        return defaultTime;
      }
    }

    function saveTime(key, value) {
      try {
        if (window.sessionStorage && isTimeValue(value)) {
          window.sessionStorage.setItem(key, value);
        }
      } catch (e) {
      }
    }

    function periodValue(dateValue, timeValue) {
      var date = String(dateValue || '').trim();
      var time = isTimeValue(timeValue) ? String(timeValue) : defaultTime;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return '';
      }
      return date + ' ' + time;
    }

    function formatSummaryDateTime(value) {
      var raw = String(value || '').trim().replace('T', ' ');
      var match = raw.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
      if (!match) {
        return '';
      }
      return Number(match[3]) + '.' + Number(match[2]) + '.' + match[1] + ' ' + match[4] + ':' + match[5];
    }

    function syncSummaryFromJson(json) {
      if (!(summaryEl instanceof HTMLElement) || !json) {
        return;
      }
      var od = formatSummaryDateTime(json.od);
      var ddo = formatSummaryDateTime(json.do);
      if (od !== '' && ddo !== '') {
        summaryEl.textContent = od + ' - ' + ddo;
      }
    }

    function timeToMinutes(value) {
      if (!isTimeValue(value)) {
        return null;
      }
      var parts = String(value).split(':');
      return (Number(parts[0]) * 60) + Number(parts[1]);
    }

    function findAdjacentTime(value, direction) {
      var target = String(value || '');
      var options = Array.prototype.slice.call(odCasInput.options || []);
      var index = options.findIndex(function (option) {
        return String(option.value || '') === target;
      });
      if (index === -1) {
        return '';
      }
      var nextIndex = index + direction;
      if (nextIndex < 0 || nextIndex >= options.length) {
        return '';
      }
      return String(options[nextIndex].value || '');
    }

    function getCurrentWorkingDayStart() {
      var now = new Date();
      var today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 6, 0, 0, 0);
      if (now.getHours() < 6) {
        today.setDate(today.getDate() - 1);
      }
      return today;
    }

    function getFinishedWorkingDayStart() {
      var start = new Date(getCurrentWorkingDayStart());
      start.setDate(start.getDate() - 1);
      return start;
    }

    function getFinishedWorkingDayEnd() {
      return new Date(getCurrentWorkingDayStart());
    }

    function getNowMax() {
      var now = new Date();
      now.setSeconds(0, 0);
      return now;
    }

    function clampToMax(value, maxDate) {
      var dt = parseDate(value);
      if (!dt) {
        return '';
      }
      if (dt.getTime() > maxDate.getTime()) {
        dt = new Date(maxDate);
      }
      return fmtDate(dt);
    }

    function shiftDate(value, days) {
      var dt = parseDate(value);
      if (!dt) {
        return '';
      }
      dt.setDate(dt.getDate() + days);
      return fmtDate(dt);
    }

    function setActive(mode) {
      activeMode = mode;
      quickBtns.forEach(function (btn) {
        btn.classList.toggle('is-on', mode !== 'manual' && btn.getAttribute('data-range') === mode);
      });
    }

    function setManualHighlight(isManual) {
      if (odLabel) odLabel.classList.toggle('is-manual', !!isManual);
      if (doLabel) doLabel.classList.toggle('is-manual', !!isManual);
      if (odLabelText) odLabelText.classList.toggle('is-manual', !!isManual);
      if (doLabelText) doLabelText.classList.toggle('is-manual', !!isManual);
      if (odInput) odInput.classList.toggle('is-manual', !!isManual);
      if (doInput) doInput.classList.toggle('is-manual', !!isManual);
      if (odCasInput) odCasInput.classList.toggle('is-manual', !!isManual);
      if (doCasInput) doCasInput.classList.toggle('is-manual', !!isManual);
    }

    function savePeriod(payload) {
      if (isSaving) {
        return Promise.resolve();
      }
      isSaving = true;
      return fetch(saveUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Comeback-Set-Period': '1'
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () {
            return {};
          });
        })
        .then(function (json) {
          if (json && json.ok === true) {
            syncSummaryFromJson(json);
            document.dispatchEvent(new CustomEvent('cb:gn-changed', {
              detail: { source: 'obdobi' }
            }));
          }
        })
        .catch(function () {
        })
        .finally(function () {
          isSaving = false;
        });
    }

    function resetManualPeriodMeter() {
      if (!meter || !meterBar) {
        return;
      }
      meter.classList.remove('is-active');
      meterBar.style.transitionDuration = '0ms';
      meterBar.style.transform = 'scaleX(0)';
    }

    function startManualPeriodMeter() {
      if (!meter || !meterBar) {
        return;
      }
      meter.classList.add('is-active');
      meterBar.style.transitionDuration = '0ms';
      meterBar.style.transform = 'scaleX(0)';
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          meterBar.style.transitionDuration = manualSaveDelayMs + 'ms';
          meterBar.style.transform = 'scaleX(1)';
        });
      });
    }

    function computeRange(range) {
      var currentDayStart = getCurrentWorkingDayStart();
      var nowMax = getNowMax();
      var finishedDayStart = getFinishedWorkingDayStart();
      var finishedDayEnd = getFinishedWorkingDayEnd();
      var from = new Date(finishedDayStart);
      var to = new Date(finishedDayEnd);

      if (range === 'dnes') {
        return { od: fmtDate(currentDayStart), do: fmtDate(nowMax), doTime: fmtTime(nowMax) };
      }
      if (range === 'vcera') {
        return { od: fmtDate(from), do: fmtDate(to) };
      }
      if (range === 'tyden') {
        var day = currentDayStart.getDay();
        var mondayShift = day === 0 ? -6 : 1 - day;
        from = new Date(currentDayStart);
        from.setDate(currentDayStart.getDate() + mondayShift);
        from.setHours(6, 0, 0, 0);
        return { od: fmtDate(from), do: fmtDate(nowMax), doTime: fmtTime(nowMax) };
      }
      if (range === 'mesic') {
        from = new Date(currentDayStart.getFullYear(), currentDayStart.getMonth(), 1, 6, 0, 0, 0);
        return { od: fmtDate(from), do: fmtDate(nowMax), doTime: fmtTime(nowMax) };
      }
      if (range === 'vse') {
        return { od: '2000-01-01', do: fmtDate(nowMax), odTime: '00:00', doTime: fmtTime(nowMax) };
      }

      from = new Date(currentDayStart.getFullYear(), 0, 1, 6, 0, 0, 0);
      return { od: fmtDate(from), do: fmtDate(nowMax), doTime: fmtTime(nowMax) };
    }

    function cancelManualPeriodSave() {
      if (manualSaveTimer) {
        window.clearTimeout(manualSaveTimer);
        manualSaveTimer = null;
      }
      resetManualPeriodMeter();
    }

    quickBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        cancelManualPeriodSave();
        var range = btn.getAttribute('data-range') || 'vcera';
        var value = computeRange(range);
        var odTime = value.odTime || defaultTime;
        var doTime = value.doTime || defaultTime;

        odInput.value = value.od;
        doInput.value = value.do;
        odCasInput.value = odTime;
        doCasInput.value = doTime;
        saveTime(odTimeKey, odCasInput.value);
        saveTime(doTimeKey, doCasInput.value);
        setActive(range);
        setManualHighlight(false);
        savePeriod({
          od: periodValue(value.od, odCasInput.value),
          do: periodValue(value.do, doCasInput.value),
          mode: range
        }).then(function () {
          closePeriodPanel();
        });
      });
    });

    odCasInput.value = loadTime(odTimeKey);
    doCasInput.value = loadTime(doTimeKey);
    odInput.max = fmtDate(getNowMax());
    doInput.max = fmtDate(getNowMax());

    function normalizeManualPeriod(changedField) {
      var maxDate = getNowMax();
      var od = clampToMax(odInput.value, maxDate);
      var ddo = clampToMax(doInput.value, maxDate);
      if (!od || !ddo) {
        return null;
      }
      if (od > ddo) {
        if (changedField === 'do') {
          ddo = clampToMax(shiftDate(od, 1), maxDate);
        } else {
          od = shiftDate(ddo, -1);
        }
      }
      odInput.value = od;
      doInput.value = ddo;

      if (od === ddo) {
        var odMinutes = timeToMinutes(odCasInput.value);
        var doMinutes = timeToMinutes(doCasInput.value);
        if (odMinutes !== null && doMinutes !== null && odMinutes >= doMinutes) {
          if (changedField === 'od' || changedField === 'od_time') {
            var prevOdTime = findAdjacentTime(doCasInput.value, -1);
            if (prevOdTime !== '') {
              odCasInput.value = prevOdTime;
            }
          } else {
            var nextDoTime = findAdjacentTime(odCasInput.value, 1);
            if (nextDoTime !== '') {
              doCasInput.value = nextDoTime;
            }
          }
        }
      }

      saveTime(odTimeKey, odCasInput.value);
      saveTime(doTimeKey, doCasInput.value);
      return { od: od, ddo: ddo };
    }

    function saveManualPeriod(changedField) {
      resetManualPeriodMeter();
      var normalized = normalizeManualPeriod(changedField);
      if (!normalized) {
        return;
      }
      setActive('manual');
      setManualHighlight(true);
      savePeriod({
        od: periodValue(normalized.od, odCasInput.value),
        do: periodValue(normalized.ddo, doCasInput.value),
        mode: 'manual'
      }).then(function () {
        closePeriodPanel();
      });
    }

    function scheduleManualPeriodSave(changedField) {
      if (manualSaveTimer) {
        window.clearTimeout(manualSaveTimer);
        manualSaveTimer = null;
      }
      if (!normalizeManualPeriod(changedField)) {
        resetManualPeriodMeter();
        return;
      }
      startManualPeriodMeter();
      manualSaveTimer = window.setTimeout(function () {
        manualSaveTimer = null;
        saveManualPeriod(changedField);
      }, manualSaveDelayMs);
    }

    [odInput, doInput, odCasInput, doCasInput].forEach(function (field) {
      field.addEventListener('focus', cancelManualPeriodSave);
    });

    odInput.addEventListener('change', function () { scheduleManualPeriodSave('od'); });
    doInput.addEventListener('change', function () { scheduleManualPeriodSave('do'); });
    odCasInput.addEventListener('change', function () { scheduleManualPeriodSave('od_time'); });
    doCasInput.addEventListener('change', function () { scheduleManualPeriodSave('do_time'); });

    setActive(activeMode);
    setManualHighlight(activeMode === 'manual');
    resetManualPeriodMeter();
  }

  function initObdobi() {
    Array.prototype.slice.call(document.querySelectorAll('[data-cb-period-root="1"]')).forEach(initRoot);
  }

  document.addEventListener('cb:main-swapped', initObdobi);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initObdobi, { once: true });
  } else {
    initObdobi();
  }
}());
