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

  function renderProgress(root, state){
    var node = statusNode(root);
    if (!node) return;
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
      var seconds = Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000));
      detail.textContent = 'Uplynulo ' + numberFormatter.format(seconds) + ' s · OpenAI volání: '
        + numberFormatter.format(Number(state.apiCalls || 0)) + ' · SQL dotazy: '
        + numberFormatter.format(Number(state.sqlCount || 0));
      node.appendChild(detail);
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
      message.textContent = String(item.message || '');
      row.appendChild(time);
      row.appendChild(message);
      log.appendChild(row);
    });
    node.appendChild(log);
  }

  function addProgress(state, message, error){
    var text = String(message || '').trim();
    if (!text) return;
    var last = state.history[state.history.length - 1];
    if (last && last.message === text && !!last.error === !!error) return;
    state.history.push({
      message: text,
      error: !!error,
      elapsed: state.startedAt ? Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000)) : 0
    });
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

  function renderResult(root, data, prompt){
    var results = root.querySelector('[data-ai-analytik-results]');
    if (!(results instanceof HTMLElement)) return;
    var result = document.createElement('section');
    result.className = 'ai_analytik_result';
    var question = document.createElement('p');
    question.className = 'ai_analytik_question';
    question.textContent = prompt;
    result.appendChild(question);

    var summaryText = String(data.text || '').trim();
    if (summaryText !== '') {
      var summary = document.createElement('p');
      summary.className = 'ai_analytik_summary';
      summary.textContent = summaryText;
      result.appendChild(summary);
    }

    var chart = document.createElement('div');
    var hasChart = renderChart(chart, data.chart);
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
    var responseMeta = data.meta || {};
    var usage = responseMeta.usage || {};
    var usageNode = document.createElement('p');
    usageNode.className = 'ai_analytik_usage';
    var cost = Number(usage.cost_usd || 0);
    var duration = Number(responseMeta.duration_ms || 0) / 1000;
    usageNode.textContent = 'Zpracoval model: ' + String(responseMeta.model || '')
      + ' za ' + duration.toFixed(1).replace('.', ',') + ' s. Spotřeba: '
      + numberFormatter.format(Number(usage.total_tokens || 0))
      + ' tokenů cena $' + cost.toFixed(cost < 0.01 ? 6 : 4);
    result.appendChild(usageNode);

    results.prepend(result);
    if (hasChart && window.CB_GRAFY_RENDER && typeof window.CB_GRAFY_RENDER.renderOne === 'function') {
      window.CB_GRAFY_RENDER.renderOne(chart, 0);
    }
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

  document.addEventListener('submit', function(event){
    var form = event.target instanceof Element ? event.target.closest('[data-ai-analytik-form]') : null;
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    var root = form.closest('[data-ai-analytik]');
    if (!(root instanceof HTMLElement) || form.dataset.running === '1') return;
    var promptNode = form.querySelector('[data-ai-analytik-prompt]');
    var modelNode = form.querySelector('[data-ai-analytik-model]');
    var textOutputNode = form.querySelector('[data-ai-analytik-vystup="text"]');
    var tableOutputNode = form.querySelector('[data-ai-analytik-vystup="tabulka"]');
    var chartOutputNode = form.querySelector('[data-ai-analytik-vystup="graf"]');
    var submit = form.querySelector('[data-ai-analytik-submit]');
    if (!(promptNode instanceof HTMLTextAreaElement) || !(modelNode instanceof HTMLSelectElement)
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

    form.dataset.running = '1';
    if (submit instanceof HTMLButtonElement) submit.disabled = true;
    var state = { error: false, startedAt: Date.now(), apiCalls: 0, sqlCount: 0, history: [] };
    addProgress(state, 'Odesílám dotaz…', false);
    renderProgress(root, state);
    var timer = window.setInterval(function(){ renderProgress(root, state); }, 1000);
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
      body: JSON.stringify({
        prompt: prompt,
        model: modelNode.value,
        vystup: requestedOutput,
        csrf: String(root.dataset.csrf || '')
      })
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
          addProgress(state, String(message.message || 'Pracuji…'), false);
          renderProgress(root, state);
        } else if (message.event === 'result') {
          finalData = message.data || null;
        } else if (message.event === 'error') {
          streamError = new Error(String(message.message || 'Dotaz se nepodařilo zpracovat.'));
        }
      });
    }).then(function(){
      if (streamError) throw streamError;
      if (!finalData || !finalData.ok) throw new Error('Server nevrátil výsledek dotazu.');
      renderResult(root, finalData, prompt);
      renderProgress(root, null);
    }).catch(function(error){
      addProgress(state, String(error.message || error), true);
      renderProgress(root, state);
    }).finally(function(){
      window.clearInterval(timer);
      delete form.dataset.running;
      if (submit instanceof HTMLButtonElement) submit.disabled = false;
    });
  });
})();
