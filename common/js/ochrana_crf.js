/*
 * Účel souboru: Doplní jednotný CRF token do formulářů a zápisových fetch
 * požadavků stejného původu. Server token ověřuje v common/lib/ochrana_crf.php.
 */
(function () {
  'use strict';

  var token = String(window.CB_CRF_TOKEN || '');
  var pcSessionToken = String(window.CB_PC_SESSION_TOKEN || '');
  if (token === '' && pcSessionToken === '') return;

  function isWriteMethod(method) {
    return ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(String(method || 'GET').toUpperCase()) !== -1;
  }

  function isSameOrigin(input) {
    try {
      var url = input instanceof Request ? input.url : String(input || '');
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (error) {
      return false;
    }
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !isWriteMethod(form.method) || !isSameOrigin(form.action || window.location.href)) return;

    var input = form.querySelector('input[name="cb_crf"]');
    if (!(input instanceof HTMLInputElement)) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'cb_crf';
      form.appendChild(input);
    }
    input.value = token;

    if (pcSessionToken !== '') {
      var pcInput = form.querySelector('input[name="cb_pc_session"]');
      if (!(pcInput instanceof HTMLInputElement)) {
        pcInput = document.createElement('input');
        pcInput.type = 'hidden';
        pcInput.name = 'cb_pc_session';
        form.appendChild(pcInput);
      }
      pcInput.value = pcSessionToken;
    }
  }, true);

  var nativeFetch = window.fetch;
  if (typeof nativeFetch !== 'function') return;

  window.fetch = function (input, init) {
    var options = init || {};
    var method = options.method || (input instanceof Request ? input.method : 'GET');
    if (!isSameOrigin(input)) {
      return nativeFetch.call(window, input, options);
    }

    var headers = new Headers(options.headers || (input instanceof Request ? input.headers : undefined));
    if (isWriteMethod(method) && token !== '') {
      headers.set('X-Comeback-Crf', token);
    }
    if (pcSessionToken !== '') {
      headers.set('X-Comeback-Pc-Session', pcSessionToken);
    }
    var next = Object.assign({}, options, { headers: headers });
    return nativeFetch.call(window, input, next);
  };
}());
