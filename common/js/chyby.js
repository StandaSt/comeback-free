// Společný převod serverových a síťových chyb na konkrétní zprávy pro uživatele.
(function () {
  'use strict';

  var PUBLIC_ERROR = 'Server vrátil chybu bez konkrétního chybového popisu.';
  var NETWORK_ERROR = 'Spojení se serverem se nezdařilo. Zkontrolujte připojení a zkuste to znovu.';

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
      return 'Server vrátil chybu HTTP ' + status + ' bez konkrétního chybového popisu.';
    }
    return String(fallback || 'Požadavek se nepodařilo dokončit.');
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
    readJson: readJson,
    errorMessage: errorMessage
  };
}());
