'use strict';

(function () {
  window.cbAdminResponseError = function (status, data, fallback) {
    var serverMessage = data && (data.err || data.chyba || data.message);
    if (typeof serverMessage === 'string' && serverMessage.trim() !== '') {
      return serverMessage.trim();
    }
    if (Number(status) === 401) {
      return 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.';
    }
    if (Number(status) === 403) {
      return 'K této akci nemáte oprávnění.';
    }
    if (Number(status) >= 500) {
      return 'Je nám líto, vyskytla se chyba, admin již byl informován.';
    }
    return String(fallback || 'Požadavek se nepodařilo dokončit.');
  };

  window.cbAdminErrorMessage = function (error, fallback) {
    if (error instanceof TypeError) {
      return 'Spojení se serverem se nezdařilo. Zkontrolujte připojení a zkuste to znovu.';
    }
    if (error && typeof error.message === 'string' && error.message.trim() !== '') {
      return error.message.trim();
    }
    return String(fallback || 'Požadavek se nepodařilo dokončit.');
  };
}());
