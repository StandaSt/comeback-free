(function(){
  'use strict';

  var numberFormatter = new Intl.NumberFormat('cs-CZ');
  var currencyFormatter = new Intl.NumberFormat('cs-CZ', {
    style: 'currency',
    currency: 'CZK',
    maximumFractionDigits: 2
  });

  function chartValue(value, unit){
    var formatted = numberFormatter.format(Number(value || 0));
    if (unit === 'CZK' || unit === 'Kč') return formatted + ' Kč';
    return unit ? formatted + ' ' + unit : formatted;
  }

  if (window.CB_GRAFY && typeof window.CB_GRAFY.register === 'function') {
    window.CB_GRAFY.register('ai_analytik', function(payload){
      var labels = Array.isArray(payload.labels) ? payload.labels.map(String) : [];
      var sourceSeries = Array.isArray(payload.series) ? payload.series : [];
      var chartType = ['bar', 'line', 'pie'].indexOf(String(payload.type || '')) >= 0
        ? String(payload.type) : 'bar';

      if (chartType === 'pie') {
        var pie = sourceSeries[0] || { name: '', unit: '', data: [] };
        return {
          title: { text: String(payload.title || ''), left: 'center', textStyle: { fontSize: 14 } },
          tooltip: {
            trigger: 'item',
            formatter: function(item){
              return String(item.name || '') + ': ' + chartValue(item.value, String(pie.unit || ''));
            }
          },
          legend: { type: 'scroll', bottom: 0 },
          series: [{
            name: String(pie.name || ''),
            type: 'pie',
            radius: ['30%', '68%'],
            center: ['50%', '48%'],
            data: labels.map(function(label, index){
              return { name: label, value: Number((pie.data || [])[index] || 0) };
            })
          }]
        };
      }

      var units = [];
      sourceSeries.forEach(function(item){
        var unit = String(item && item.unit || '');
        if (units.indexOf(unit) === -1) units.push(unit);
      });
      var rightAxes = Math.max(0, units.length - 1);
      var yAxes = units.map(function(unit, index){
        return {
          type: 'value',
          name: unit,
          position: index === 0 ? 'left' : 'right',
          offset: index <= 1 ? 0 : (index - 1) * 55,
          axisLabel: { formatter: function(value){ return chartValue(value, unit); } }
        };
      });
      if (yAxes.length === 0) yAxes.push({ type: 'value' });

      return {
        title: { text: String(payload.title || ''), left: 'center', textStyle: { fontSize: 14 } },
        grid: { left: 20, right: 25 + Math.max(0, rightAxes - 1) * 55, top: 65, bottom: 20, containLabel: true },
        legend: { top: 30 },
        tooltip: { trigger: 'axis', axisPointer: { type: chartType === 'bar' ? 'shadow' : 'line' }, renderMode: 'richText' },
        xAxis: {
          type: 'category',
          data: labels,
          axisLabel: { interval: 0, rotate: labels.length > 8 ? 25 : 0 }
        },
        yAxis: yAxes,
        series: sourceSeries.map(function(item){
          var unit = String(item && item.unit || '');
          return {
            name: String(item && item.name || ''),
            type: chartType,
            yAxisIndex: Math.max(0, units.indexOf(unit)),
            barMaxWidth: 54,
            symbolSize: 7,
            data: Array.isArray(item && item.data) ? item.data.map(function(value){
              return value == null ? null : Number(value);
            }) : []
          };
        })
      };
    });
  }

  function statusNode(root){
    var node = root.querySelector('[data-ai-analytik-status]');
    return node instanceof HTMLElement ? node : null;
  }

  function formatBytes(value){
    var bytes = Math.max(0, Number(value || 0));
    if (bytes < 1024) return numberFormatter.format(bytes) + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1).replace('.', ',') + ' kB';
    return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
  }

  function appendProgressDetails(container, meta){
    if (!meta || typeof meta !== 'object') return;
    if (typeof meta.sql === 'string' && meta.sql.trim() !== '') {
      var sqlDetails = document.createElement('details');
      sqlDetails.className = 'ai_analytik_sql_detail';
      var sqlSummary = document.createElement('summary');
      sqlSummary.textContent = 'Zobrazit SQL';
      var sql = document.createElement('pre');
      sql.textContent = meta.sql;
      sqlDetails.appendChild(sqlSummary);
      sqlDetails.appendChild(sql);
      container.appendChild(sqlDetails);
    }
    if (Number(meta.result_bytes || 0) > 0) {
      var size = document.createElement('small');
      size.className = 'ai_analytik_status_meta';
      size.textContent = 'Velikost výsledku: ' + formatBytes(meta.result_bytes);
      container.appendChild(size);
    }
    if (typeof meta.error === 'string' && meta.error.trim() !== '') {
      var errorDetails = document.createElement('details');
      errorDetails.className = 'ai_analytik_sql_detail';
      var errorSummary = document.createElement('summary');
      errorSummary.textContent = 'Zobrazit detail chyby';
      var errorText = document.createElement('pre');
      errorText.textContent = meta.error;
      errorDetails.appendChild(errorSummary);
      errorDetails.appendChild(errorText);
      container.appendChild(errorDetails);
    }
  }

  function renderProgressNode(node, state, completed){
    node.innerHTML = '';
    var history = state && Array.isArray(state.history) ? state.history : [];
    node.classList.toggle('is-error', !!(state && state.error));
    node.hidden = history.length === 0;
    if (history.length === 0) return;

    var title = document.createElement('strong');
    title.textContent = state.error ? 'Zpracování skončilo chybou' : 'Průběh zpracování';
    node.appendChild(title);
    if (state.startedAt) {
      var detail = document.createElement('span');
      detail.className = 'ai_analytik_status_detail';
      var endTime = completed && state.completedAt ? state.completedAt : Date.now();
      var seconds = Math.max(0, Math.floor((endTime - state.startedAt) / 1000));
      detail.textContent = (completed ? 'Celkem ' : 'Uplynulo ') + numberFormatter.format(seconds) + ' s · OpenAI volání: '
        + numberFormatter.format(Number(state.apiCalls || 0)) + ' · SQL dotazy: '
        + numberFormatter.format(Number(state.sqlCount || 0));
      node.appendChild(detail);
    }
    if (!completed && state.activeMessage) {
      var active = document.createElement('span');
      active.className = 'ai_analytik_status_active';
      var activeSeconds = state.activeStartedAt ? Math.max(0, Math.floor((Date.now() - state.activeStartedAt) / 1000)) : 0;
      active.textContent = 'Aktuální krok: ' + state.activeMessage + ' (' + numberFormatter.format(activeSeconds) + ' s)';
      node.appendChild(active);
    }

    var log = document.createElement('ol');
    log.className = 'ai_analytik_status_log';
    history.forEach(function(item){
      var row = document.createElement('li');
      row.className = 'ai_analytik_status_step';
      row.classList.toggle('is-error', !!item.error);
      var time = document.createElement('span');
      time.className = 'ai_analytik_status_time';
      time.textContent = '+' + numberFormatter.format(Number(item.elapsed || 0)) + ' s';
      var message = document.createElement('span');
      var body = document.createElement('div');
      body.className = 'ai_analytik_status_step_body';
      message.textContent = String(item.message || '');
      row.appendChild(time);
      body.appendChild(message);
      appendProgressDetails(body, item.meta);
      row.appendChild(body);
      log.appendChild(row);
    });
    node.appendChild(log);
  }

  function renderProgress(root, state){
    var node = statusNode(root);
    if (!node) return;
    renderProgressNode(node, state, false);
  }

  function createProcessingDetails(state){
    var details = document.createElement('details');
    details.className = 'ai_analytik_processing';
    var summary = document.createElement('summary');
    summary.textContent = 'Zobrazit zpracování promptu';
    var content = document.createElement('div');
    content.className = 'ai_analytik_status ai_analytik_status_archived';
    renderProgressNode(content, state, true);
    details.appendChild(summary);
    details.appendChild(content);
    details.addEventListener('toggle', function(){
      summary.textContent = details.open ? 'Skrýt zpracování promptu' : 'Zobrazit zpracování promptu';
    });
    return details;
  }

  function addProgress(state, message, error, heartbeat, meta){
    var text = String(message || '').trim();
    if (!text) return;
    if (heartbeat) {
      if (!state.activeMessage) state.activeMessage = text;
      return;
    }
    var last = state.history[state.history.length - 1];
    if (last && last.message === text && !!last.error === !!error) return;
    state.history.push({
      message: text,
      error: !!error,
      meta: meta && typeof meta === 'object' ? meta : {},
      elapsed: state.startedAt ? Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000)) : 0
    });
    if (!error) {
      state.activeMessage = text;
      state.activeStartedAt = Date.now();
    }
    if (error) state.error = true;
  }

  function renderSingleError(root, message){
    var state = { error: true, startedAt: 0, apiCalls: 0, sqlCount: 0, history: [] };
    addProgress(state, message, true);
    renderProgress(root, state);
  }

  function formatValue(value, type){
    if (type === 'number') return numberFormatter.format(Number(value || 0));
    if (type === 'currency') return currencyFormatter.format(Number(value || 0));
    return String(value == null ? '' : value);
  }

  function renderTable(container, columns, rows){
    container.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) return;

    var table = document.createElement('table');
    var thead = document.createElement('thead');
    var headerRow = document.createElement('tr');
    columns.forEach(function(column){
      var th = document.createElement('th');
      th.scope = 'col';
      th.textContent = String(column.label || column.key || '');
      headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    rows.forEach(function(row){
      var tr = document.createElement('tr');
      columns.forEach(function(column){
        var td = document.createElement('td');
        td.textContent = formatValue(row[column.key], column.type);
        if (column.type === 'number' || column.type === 'currency') td.className = 'is-number';
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }

  function renderChart(container, payload){
    if (!payload || typeof payload !== 'object') return false;
    container.className = 'ai_analytik_chart';
    container.setAttribute('data-graf', '1');
    var data = document.createElement('script');
    data.type = 'application/json';
    data.setAttribute('data-graf-data', '');
    data.textContent = JSON.stringify(payload);
    var canvas = document.createElement('div');
    canvas.className = 'ai_analytik_chart_canvas';
    canvas.setAttribute('data-graf-canvas', '1');
    container.appendChild(data);
    container.appendChild(canvas);
    return true;
  }

  function getRecipients(root){
    var node = root.querySelector('[data-ai-analytik-prijemci]');
    if (!(node instanceof HTMLScriptElement)) return [];
    try {
      var recipients = JSON.parse(node.textContent || '[]');
      return Array.isArray(recipients) ? recipients : [];
    } catch (error) {
      return [];
    }
  }

  function responseError(response){
    return response.text().then(function(text){
      var message = text;
      try {
        var data = JSON.parse(text);
        message = String(data.message || data.error || text);
      } catch (error) {
      }
      throw new Error(message || ('HTTP ' + response.status));
    });
  }

  function renderExportActions(root, result, exportData){
    if (!exportData || typeof exportData.payload !== 'string' || typeof exportData.signature !== 'string') return;
    var actions = document.createElement('div');
    actions.className = 'ai_analytik_export_actions';
    var label = document.createElement('strong');
    label.textContent = 'Výsledek do PDF';
    actions.appendChild(label);
    var saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'head_task_btn';
    saveButton.textContent = 'Uložit jako';
    actions.appendChild(saveButton);
    var recipient = document.createElement('select');
    recipient.setAttribute('aria-label', 'Příjemce e-mailu');
    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Vyberte příjemce';
    recipient.appendChild(placeholder);
    getRecipients(root).forEach(function(item){
      var option = document.createElement('option');
      option.value = String(item.id_user || '');
      option.textContent = String(item.name || item.email || '') + ' (' + String(item.email || '') + ')';
      recipient.appendChild(option);
    });
    actions.appendChild(recipient);
    var emailButton = document.createElement('button');
    emailButton.type = 'button';
    emailButton.className = 'head_task_btn';
    emailButton.textContent = 'Odeslat e-mailem';
    actions.appendChild(emailButton);
    var status = document.createElement('span');
    status.className = 'ai_analytik_export_status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    actions.appendChild(status);

    function requestBody(extra){
      return Object.assign({
        csrf: String(root.dataset.csrf || ''),
        payload: exportData.payload,
        signature: exportData.signature
      }, extra || {});
    }

    saveButton.addEventListener('click', function(){
      saveButton.disabled = true;
      status.textContent = 'Připravuji PDF…';
      status.classList.remove('is-error');
      fetch(String(root.dataset.pdfEndpoint || ''), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf' },
        body: JSON.stringify(requestBody())
      }).then(function(response){
        if (!response.ok) return responseError(response);
        return response.blob().then(function(blob){
          var disposition = String(response.headers.get('Content-Disposition') || '');
          var filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
          var filename = filenameMatch ? filenameMatch[1] : 'ai_analytik.pdf';
          var url = URL.createObjectURL(blob);
          var link = document.createElement('a');
          link.href = url;
          link.download = filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(url);
          status.textContent = 'PDF je připraveno.';
        });
      }).catch(function(error){
        status.textContent = String(error.message || error);
        status.classList.add('is-error');
      }).finally(function(){ saveButton.disabled = false; });
    });

    emailButton.addEventListener('click', function(){
      if (recipient.value === '') {
        status.textContent = 'Vyberte příjemce.';
        status.classList.add('is-error');
        recipient.focus();
        return;
      }
      emailButton.disabled = true;
      recipient.disabled = true;
      status.textContent = 'Odesílám PDF…';
      status.classList.remove('is-error');
      fetch(String(root.dataset.emailEndpoint || ''), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(requestBody({ id_recipient: Number(recipient.value) }))
      }).then(function(response){
        if (!response.ok) return responseError(response);
        return response.json();
      }).then(function(data){
        if (!data.ok) throw new Error(String(data.message || 'E-mail se nepodařilo odeslat.'));
        status.textContent = String(data.message || 'PDF bylo odesláno.');
      }).catch(function(error){
        status.textContent = String(error.message || error);
        status.classList.add('is-error');
      }).finally(function(){
        emailButton.disabled = false;
        recipient.disabled = false;
      });
    });
    result.appendChild(actions);
  }

  function renderResult(root, data, prompt, processingState){
    var results = root.querySelector('[data-ai-analytik-results]');
    if (!(results instanceof HTMLElement)) return null;
    var result = document.createElement('section');
    result.className = 'ai_analytik_result';
    result.appendChild(createProcessingDetails(processingState));
    var question = document.createElement('p');
    question.className = 'ai_analytik_question';
    question.textContent = prompt;
    result.appendChild(question);

    var responseMeta = data.meta || {};
    var years = Array.isArray(responseMeta.years) ? responseMeta.years.map(Number).filter(function(year){
      return Number.isInteger(year) && year > 0;
    }) : [];
    var isClarification = String(data.response_type || 'answer') === 'clarification';
    if (years.length > 0) {
      var period = document.createElement('p');
      period.className = 'ai_analytik_period';
      period.textContent = (isClarification ? 'Vybrané roky: ' : 'Zpracované roky: ') + years.join(', ');
      result.appendChild(period);
    }

    var summaryText = isClarification
      ? String(data.clarification || '').trim()
      : String(data.text || '').trim();
    if (summaryText !== '') {
      var summary = document.createElement('p');
      summary.className = 'ai_analytik_summary' + (isClarification ? ' is-clarification' : '');
      summary.textContent = summaryText;
      result.appendChild(summary);
    }

    if (isClarification && data.continuation && typeof data.continuation === 'object') {
      var clarificationForm = document.createElement('form');
      clarificationForm.className = 'ai_analytik_clarification_form';
      clarificationForm.setAttribute('data-ai-analytik-clarification-form', '');
      clarificationForm.dataset.auditId = String(data.continuation.audit_id || '');
      clarificationForm.dataset.token = String(data.continuation.token || '');
      clarificationForm._aiProcessingState = processingState;
      clarificationForm._aiOriginalPrompt = prompt;
      clarificationForm._aiDurationMs = Number(responseMeta.duration_ms || 0);
      clarificationForm._aiExpiresAt = Date.now()
        + Math.max(0, Number(data.continuation.expires_in_seconds || 0)) * 1000;
      var clarificationLabel = document.createElement('label');
      clarificationLabel.textContent = 'Vaše odpověď';
      var clarificationCountdown = document.createElement('strong');
      clarificationCountdown.className = 'ai_analytik_clarification_countdown';
      var clarificationInput = document.createElement('textarea');
      clarificationInput.rows = 2;
      clarificationInput.required = true;
      clarificationInput.placeholder = 'Napište upřesnění a pokračujte ve stejné analýze.';
      clarificationInput.setAttribute('data-ai-analytik-clarification-answer', '');
      var clarificationButton = document.createElement('button');
      clarificationButton.type = 'submit';
      clarificationButton.className = 'head_task_btn';
      clarificationButton.textContent = 'Pokračovat v analýze';
      clarificationForm.appendChild(clarificationLabel);
      clarificationForm.appendChild(clarificationCountdown);
      clarificationForm.appendChild(clarificationInput);
      clarificationForm.appendChild(clarificationButton);
      result.appendChild(clarificationForm);
      function updateClarificationCountdown(){
        var seconds = Math.max(0, Math.ceil((clarificationForm._aiExpiresAt - Date.now()) / 1000));
        var minutes = Math.floor(seconds / 60);
        var rest = String(seconds % 60).padStart(2, '0');
        clarificationCountdown.textContent = seconds > 0
          ? 'Na zadání odpovědi zbývá: ' + minutes + ':' + rest + ' min.'
          : 'Čas na odpověď vypršel. Spusťte prompt znovu.';
        if (seconds === 0) {
          window.clearInterval(clarificationForm._aiCountdownTimer);
          clarificationInput.disabled = true;
          clarificationButton.disabled = true;
          clarificationButton.textContent = 'Čas vypršel';
        }
      }
      clarificationForm._aiUpdateCountdown = updateClarificationCountdown;
      updateClarificationCountdown();
      clarificationForm._aiCountdownTimer = window.setInterval(updateClarificationCountdown, 1000);
      window.setTimeout(function(){
        clarificationForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        clarificationInput.focus();
      }, 0);
    }

    var hasChart = false;
    if (!isClarification) {
      var chart = document.createElement('div');
      hasChart = renderChart(chart, data.chart);
      if (hasChart) result.appendChild(chart);
      var columns = Array.isArray(data.columns) ? data.columns : [];
      var rows = Array.isArray(data.rows) ? data.rows : [];
      if (columns.length > 0 && rows.length > 0) {
        var table = document.createElement('div');
        table.className = 'ai_analytik_table_wrap';
        renderTable(table, columns, rows);
        result.appendChild(table);
      }
      renderExportActions(root, result, data.export);
    }

    var usage = responseMeta.usage || {};
    var usageNode = document.createElement('p');
    usageNode.className = 'ai_analytik_usage';
    var cost = Number(usage.cost_usd || 0);
    var duration = Number(responseMeta.duration_ms || 0) / 1000;
    usageNode.textContent = (isClarification ? 'Dosavadní zpracování – model: ' : 'Zpracoval model: ')
      + String(responseMeta.model || '')
      + ' za ' + duration.toFixed(1).replace('.', ',') + ' s. Spotřeba: '
      + numberFormatter.format(Number(usage.total_tokens || 0))
      + ' tokenů cena $' + cost.toFixed(cost < 0.01 ? 6 : 4)
      + ' (' + currencyFormatter.format(cost * 20.8) + ')';
    result.appendChild(usageNode);

    results.prepend(result);
    if (hasChart && window.CB_GRAFY_RENDER && typeof window.CB_GRAFY_RENDER.renderOne === 'function') {
      window.CB_GRAFY_RENDER.renderOne(chart, 0);
    }
    return result;
  }

  function consumeStream(response, onEvent){
    if (!response.ok) return responseError(response);
    if (!response.body || typeof response.body.getReader !== 'function') {
      return response.text().then(function(text){
        text.split(/\r?\n/).filter(Boolean).forEach(function(line){ onEvent(JSON.parse(line)); });
      });
    }
    var reader = response.body.getReader();
    var decoder = new TextDecoder('utf-8');
    var buffer = '';
    function read(){
      return reader.read().then(function(chunk){
        buffer += decoder.decode(chunk.value || new Uint8Array(), { stream: !chunk.done });
        var lines = buffer.split(/\r?\n/);
        buffer = lines.pop() || '';
        lines.filter(Boolean).forEach(function(line){ onEvent(JSON.parse(line)); });
        if (!chunk.done) return read();
        if (buffer.trim() !== '') onEvent(JSON.parse(buffer));
      });
    }
    return read();
  }

  document.addEventListener('input', function(event){
    var prompt = event.target instanceof Element ? event.target.closest('[data-ai-analytik-prompt]') : null;
    if (!(prompt instanceof HTMLTextAreaElement)) return;
    var form = prompt.closest('[data-ai-analytik-form]');
    if (!(form instanceof HTMLFormElement)) return;
    var submit = form.querySelector('[data-ai-analytik-submit]');
    if (submit instanceof HTMLButtonElement) submit.classList.toggle('is-ready', prompt.value.trim() !== '');
  });

  function closeInfoPanels(except){
    document.querySelectorAll('[data-ai-analytik-guide][open], [data-ai-analytik-model-info][open], [data-ai-analytik-access][open]').forEach(function(info){
      if (!(info instanceof HTMLDetailsElement) || info === except) return;
      info.open = false;
    });
    document.querySelectorAll('[data-ai-analytik-guide-toggle], [data-ai-analytik-model-info-toggle], [data-ai-analytik-access-toggle]').forEach(function(toggle){
      if (!(toggle instanceof HTMLButtonElement)) return;
      var controls = toggle.getAttribute('aria-controls');
      if (controls !== except.id) toggle.setAttribute('aria-expanded', 'false');
    });
  }

  document.addEventListener('click', function(event){
    var guideToggle = event.target instanceof Element
      ? event.target.closest('[data-ai-analytik-guide-toggle]') : null;
    if (!(guideToggle instanceof HTMLButtonElement)) return;
    var guide = document.querySelector('[data-ai-analytik-guide]');
    if (!(guide instanceof HTMLDetailsElement)) return;
    guide.open = !guide.open;
    if (guide.open) closeInfoPanels(guide);
    guideToggle.setAttribute('aria-expanded', guide.open ? 'true' : 'false');
  });

  document.addEventListener('click', function(event){
    var toggle = event.target instanceof Element
      ? event.target.closest('[data-ai-analytik-model-info-toggle]') : null;
    if (!(toggle instanceof HTMLButtonElement)) return;
    var root = toggle.closest('[data-ai-analytik]');
    var info = root ? root.querySelector('[data-ai-analytik-model-info]') : null;
    if (!(info instanceof HTMLDetailsElement)) return;
    info.open = !info.open;
    if (info.open) closeInfoPanels(info);
    toggle.setAttribute('aria-expanded', info.open ? 'true' : 'false');
  });

  document.addEventListener('click', function(event){
    var accessToggle = event.target instanceof Element
      ? event.target.closest('[data-ai-analytik-access-toggle]') : null;
    if (!(accessToggle instanceof HTMLButtonElement)) return;
    var access = document.querySelector('[data-ai-analytik-access]');
    if (!(access instanceof HTMLDetailsElement)) return;
    access.open = !access.open;
    if (access.open) closeInfoPanels(access);
    accessToggle.setAttribute('aria-expanded', access.open ? 'true' : 'false');
  });

  document.addEventListener('click', function(event){
    var target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    if (target.closest('[data-ai-analytik-guide-toggle], [data-ai-analytik-model-info-toggle], [data-ai-analytik-access-toggle]')) return;

    document.querySelectorAll('[data-ai-analytik-guide][open], [data-ai-analytik-model-info][open], [data-ai-analytik-access][open]').forEach(function(info){
      if (!(info instanceof HTMLDetailsElement)) return;
      info.open = false;
    });
    document.querySelectorAll('[data-ai-analytik-guide-toggle], [data-ai-analytik-model-info-toggle], [data-ai-analytik-access-toggle]').forEach(function(toggle){
      if (toggle instanceof HTMLButtonElement) toggle.setAttribute('aria-expanded', 'false');
    });
  });

  document.addEventListener('click', function(event){
    var actionButton = event.target instanceof Element
      ? event.target.closest('[data-ai-analytik-astra-action]') : null;
    if (!(actionButton instanceof HTMLButtonElement)) return;
    var dialog = actionButton.closest('[data-ai-analytik-astra-dialog]');
    if (!(dialog instanceof HTMLDialogElement)) return;
    var form = dialog._aiAnalytikForm;
    var action = String(actionButton.dataset.aiAnalytikAstraAction || 'back');
    dialog.close();
    if (!(form instanceof HTMLFormElement) || action === 'back') return;

    if (action === 'sol' || action === 'terra') {
      var model = form.querySelector('[data-ai-analytik-model][value="gpt-5.6-' + action + '"]');
      if (model instanceof HTMLInputElement) model.checked = true;
    } else if (action === 'confirm') {
      form.dataset.aiAstraConfirmed = '1';
    }
    form.requestSubmit();
  });

  function runAnalysis(root, payload, state, prompt, previousResult, onFinished){
    if (root.dataset.aiRunning === '1') return;
    root.dataset.aiRunning = '1';
    var cancel = root.querySelector('[data-ai-analytik-cancel]');
    if (cancel instanceof HTMLButtonElement) {
      cancel.hidden = true;
      cancel.disabled = false;
      cancel.textContent = 'Zastavit analýzu';
    }
    renderProgress(root, state);
    function updateCancelButton(){
      if (!(cancel instanceof HTMLButtonElement)) return;
      cancel.hidden = !state.cancelRequested && state.auditId <= 0;
    }
    var timer = window.setInterval(function(){
      renderProgress(root, state);
      updateCancelButton();
    }, 1000);
    var finalData = null;
    var streamError = null;

    fetch(String(root.dataset.endpoint || window.CB_ENDPOINT || 'index.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/x-ndjson, application/json',
        'X-Comeback-AI-Analytik': '1'
      },
      body: JSON.stringify(payload)
    }).then(function(response){
      var contentType = String(response.headers.get('Content-Type') || '');
      if (contentType.indexOf('application/json') >= 0) {
        return response.json().then(function(data){
          if (!response.ok || !data.ok) throw new Error(String(data.error || ('HTTP ' + response.status)));
          finalData = data;
        });
      }
      return consumeStream(response, function(message){
        if (!message || typeof message !== 'object') return;
        if (message.event === 'progress') {
          state.apiCalls = Number(message.meta && message.meta.api_calls || 0);
          state.sqlCount = Number(message.meta && message.meta.sql_count || 0);
          if (message.meta && message.meta.audit_id) state.auditId = Number(message.meta.audit_id || 0);
          addProgress(
            state,
            String(message.message || 'Pracuji…'),
            false,
            !!(message.meta && message.meta.heartbeat),
            message.meta || {}
          );
          renderProgress(root, state);
          updateCancelButton();
        } else if (message.event === 'result') {
          finalData = message.data || null;
        } else if (message.event === 'error') {
          streamError = new Error(String(message.message || 'Dotaz se nepodařilo zpracovat.'));
        }
      });
    }).then(function(){
      if (streamError) throw streamError;
      if (!finalData || !finalData.ok) throw new Error('Server nevrátil výsledek dotazu.');
      state.completedAt = Date.now();
      state.activeMessage = '';
      var isClarification = String(finalData.response_type || 'answer') === 'clarification';
      addProgress(
        state,
        isClarification ? 'Analýza čeká na vaše upřesnění.' : 'Zpracování dokončeno.',
        false,
        false,
        finalData.meta || {}
      );
      renderResult(root, finalData, prompt, state);
      if (previousResult instanceof HTMLElement) previousResult.remove();
      renderProgress(root, null);
    }).catch(function(error){
      addProgress(state, String(error.message || error), true);
      renderProgress(root, state);
    }).finally(function(){
      window.clearInterval(timer);
      delete root.dataset.aiRunning;
      if (cancel instanceof HTMLButtonElement) cancel.hidden = true;
      if (typeof onFinished === 'function') onFinished();
    });

    if (cancel instanceof HTMLButtonElement) {
      cancel.onclick = function(){
        if (state.cancelRequested || state.auditId <= 0) return;
        state.cancelRequested = true;
        cancel.disabled = true;
        cancel.hidden = false;
        cancel.textContent = 'Zastavuji analýzu…';
        addProgress(state, 'Požadavek na zastavení byl odeslán.', false);
        renderProgress(root, state);
        fetch(String(root.dataset.endpoint || window.CB_ENDPOINT || 'index.php'), {
          method: 'POST', credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-Comeback-AI-Analytik': '1'
          },
          body: JSON.stringify({
            action: 'cancel', audit_id: state.auditId, csrf: String(root.dataset.csrf || '')
          })
        }).then(function(response){
          if (!response.ok) return responseError(response);
          return response.json();
        }).then(function(data){
          if (!data.ok) throw new Error(String(data.error || 'Analýzu se nepodařilo zastavit.'));
          cancel.textContent = 'Čekám na zastavení…';
        }).catch(function(error){
          state.cancelRequested = false;
          cancel.disabled = false;
          cancel.textContent = 'Zkusit zastavit znovu';
          addProgress(state, String(error.message || error), true);
          renderProgress(root, state);
        });
      };
    }
  }

  document.addEventListener('submit', function(event){
    var clarificationForm = event.target instanceof Element
      ? event.target.closest('[data-ai-analytik-clarification-form]') : null;
    if (!(clarificationForm instanceof HTMLFormElement)) return;
    event.preventDefault();
    var root = clarificationForm.closest('[data-ai-analytik]');
    var answerNode = clarificationForm.querySelector('[data-ai-analytik-clarification-answer]');
    var button = clarificationForm.querySelector('button[type="submit"]');
    if (!(root instanceof HTMLElement) || !(answerNode instanceof HTMLTextAreaElement)
      || root.dataset.aiRunning === '1') return;
    var answer = answerNode.value.trim();
    if (answer === '') {
      answerNode.focus();
      return;
    }
    var state = clarificationForm._aiProcessingState;
    if (!state || !Array.isArray(state.history)) return;
    window.clearInterval(clarificationForm._aiCountdownTimer);
    state.error = false;
    state.cancelRequested = false;
    state.completedAt = null;
    state.startedAt = Date.now() - Math.max(0, Number(clarificationForm._aiDurationMs || 0));
    addProgress(state, 'Vaše upřesnění: ' + answer, false);
    if (button instanceof HTMLButtonElement) button.disabled = true;
    answerNode.disabled = true;
    runAnalysis(root, {
      action: 'continue',
      audit_id: Number(clarificationForm.dataset.auditId || 0),
      continuation_token: String(clarificationForm.dataset.token || ''),
      answer: answer,
      csrf: String(root.dataset.csrf || '')
    }, state, String(clarificationForm._aiOriginalPrompt || ''), clarificationForm.closest('.ai_analytik_result'), function(){
      if (button instanceof HTMLButtonElement) button.disabled = false;
      answerNode.disabled = false;
      if (clarificationForm.isConnected && typeof clarificationForm._aiUpdateCountdown === 'function') {
        clarificationForm._aiUpdateCountdown();
        clarificationForm._aiCountdownTimer = window.setInterval(
          clarificationForm._aiUpdateCountdown,
          1000
        );
      }
    });
  });

  document.addEventListener('submit', function(event){
    var form = event.target instanceof Element ? event.target.closest('[data-ai-analytik-form]') : null;
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    var root = form.closest('[data-ai-analytik]');
    if (!(root instanceof HTMLElement) || root.dataset.aiRunning === '1') return;
    var promptNode = form.querySelector('[data-ai-analytik-prompt]');
    var modelNode = form.querySelector('[data-ai-analytik-model]:checked');
    var ambiguityNode = form.querySelector('[data-ai-analytik-ambiguity]:checked');
    var yearNodes = Array.prototype.slice.call(form.querySelectorAll('[data-ai-analytik-year]:checked'));
    var textOutputNode = form.querySelector('[data-ai-analytik-vystup="text"]');
    var tableOutputNode = form.querySelector('[data-ai-analytik-vystup="tabulka"]');
    var chartOutputNode = form.querySelector('[data-ai-analytik-vystup="graf"]');
    var submit = form.querySelector('[data-ai-analytik-submit]');
    if (!(promptNode instanceof HTMLTextAreaElement) || !(modelNode instanceof HTMLInputElement)
      || !(ambiguityNode instanceof HTMLInputElement)
      || !(textOutputNode instanceof HTMLInputElement) || !(tableOutputNode instanceof HTMLInputElement)
      || !(chartOutputNode instanceof HTMLInputElement)) return;

    var prompt = promptNode.value.trim();
    if (!prompt) {
      renderSingleError(root, 'Napište dotaz.');
      promptNode.focus();
      return;
    }
    var requestedOutput = {
      text: textOutputNode.checked,
      tabulka: tableOutputNode.checked,
      graf: chartOutputNode.checked
    };
    if (!requestedOutput.text && !requestedOutput.tabulka && !requestedOutput.graf) {
      renderSingleError(root, 'Vyberte alespoň jeden požadovaný výstup.');
      return;
    }
    var years = yearNodes.map(function(node){ return Number(node.value); }).filter(Number.isInteger);
    if (years.length === 0) {
      renderSingleError(root, 'Vyberte alespoň jeden rok.');
      return;
    }
    var astraConfirmed = form.dataset.aiAstraConfirmed === '1';
    delete form.dataset.aiAstraConfirmed;
    if (modelNode.value === 'gpt-6-astra' && !astraConfirmed) {
      var astraDialog = root.querySelector('[data-ai-analytik-astra-dialog]');
      if (astraDialog instanceof HTMLDialogElement) {
        astraDialog._aiAnalytikForm = form;
        astraDialog.showModal();
        return;
      }
    }
    if (submit instanceof HTMLButtonElement) submit.disabled = true;
    var state = {
      error: false, startedAt: Date.now(), apiCalls: 0, sqlCount: 0, history: [],
      activeMessage: '', activeStartedAt: 0, auditId: 0, cancelRequested: false
    };
    addProgress(state, 'Odesílám dotaz…', false);
    runAnalysis(root, {
      prompt: prompt,
      model: modelNode.value,
      roky: years,
      nejistota: ambiguityNode.value,
      vystup: requestedOutput,
      csrf: String(root.dataset.csrf || '')
    }, state, prompt, null, function(){
      if (submit instanceof HTMLButtonElement) submit.disabled = false;
    });
  });
})();
