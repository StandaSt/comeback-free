// Společný převod JSON, HTML a síťových chyb na konkrétní zprávy pro uživatele.
(function () {
  'use strict';

  var PUBLIC_ERROR = 'Server vrátil chybu bez konkrétního chybového popisu.';
  var NETWORK_ERROR = 'Spojení se serverem se nezdařilo. Zkontrolujte připojení a zkuste to znovu.';

  // Konkrétní důvod má přednost; při chybě bez popisu neslibujeme neprovedené oznámení adminovi.
  function responseMessage(response, data, fallback) {
    if (data && typeof data.err === 'string' && data.err.trim() !== '') {
      return data.err.trim();
    }
    var status = Number(response && response.status ? response.status : 0);
    if (status === 401) {
      return 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.';
    }
    if (status === 403) {
      return 'K této akci nemáte oprávnění.';
    }
    if (status >= 500) {
      return String(fallback || 'Operaci se nepodařilo dokončit.') + ' Server selhal a neposkytl podrobnosti. Při hlášení uveďte akci a čas chyby.';
    }
    if (status >= 400) {
      return String(fallback || 'Požadavek se nepodařilo dokončit.') + ' Server požadavek odmítl bez konkrétního důvodu. Při hlášení uveďte akci a čas chyby.';
    }
    return String(fallback || 'Požadavek se nepodařilo dokončit.');
  }

  // Čteme jen zprávu určenou uživateli; celé HTML stránky ani technický výpis nezobrazujeme.
  function responseTextMessage(response, text, fallback) {
    var body = String(text || '').trim();
    try {
      var data = JSON.parse(body);
      if (data && typeof data.err === 'string' && data.err.trim() !== '') {
        return responseMessage(response, data, fallback);
      }
    } catch (ignore) {
      // HTML odpověď společné PHP obsluhy označuje zprávu pomocí role alert.
    }
    var document = new DOMParser().parseFromString(body, 'text/html');
    var alert = document.querySelector('[role="alert"]');
    if (alert && alert.textContent.trim() !== '') {
      return alert.textContent.trim();
    }
    return responseMessage(response, {}, fallback);
  }

  // Úspěšné HTML předáme beze změny, z neúspěšné odpovědi získáme skutečný důvod.
  function readHtml(response, fallback) {
    return response.text().then(function (text) {
      if (!response.ok) {
        throw new Error(responseTextMessage(response, text, fallback));
      }
      return text;
    });
  }

  function readJson(response, fallback) {
    return response.json().catch(function () { return {}; }).then(function (data) {
      return {
        ok: response.ok && data && data.ok === true,
        response: response,
        data: data,
        message: responseMessage(response, data, fallback)
      };
    });
  }

  function errorMessage(error, fallback) {
    if (error instanceof TypeError) {
      return NETWORK_ERROR;
    }
    if (error && typeof error.message === 'string' && error.message.trim() !== '') {
      return error.message.trim();
    }
    return String(fallback || NETWORK_ERROR);
  }

  window.CB_CHYBY = {
    publicMessage: PUBLIC_ERROR,
    networkMessage: NETWORK_ERROR,
    responseMessage: responseMessage,
    responseTextMessage: responseTextMessage,
    readHtml: readHtml,
    readJson: readJson,
    errorMessage: errorMessage
  };
}());
