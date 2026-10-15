/* Seskupuje kola podle ID auditu do jednoho promptu, nejnovejsi upresneni zobrazuje nahore. */
(function () {
  'use strict';

  // Pracovni plocha ma jeden blok na audit; archiv jiz seskupuje kola pod konkretnim pouzitim.
  function insert(results, result, meta, prompt) {
    var id = Number(meta.audit_id || 0);
    if (!results.hasAttribute('data-ai-analytik-results') || id <= 0) {
      results.prepend(result);
      return;
    }
    var group = Array.from(results.children).find(function (element) {
      return element.classList.contains('ai_analytik_prompt_group') && element.dataset.auditId === String(id);
    });
    if (!group) {
      group = document.createElement('section');
      group.className = 'ai_analytik_prompt_group';
      group.dataset.auditId = String(id);
      var heading = document.createElement('header');
      heading.className = 'ai_analytik_prompt_group_heading';
      var title = document.createElement('h3');
      title.textContent = String(meta.original_prompt || prompt);
      var number = document.createElement('small');
      number.textContent = String(id);
      number.title = 'Číslo promptu';
      heading.append(title, number);
      var turns = document.createElement('div');
      turns.className = 'ai_analytik_prompt_turns';
      group.append(heading, turns);
    }
    var container = group.querySelector('.ai_analytik_prompt_turns');
    var previous = Array.from(container.children).find(function (element) {
      return result.dataset.historyId && element.dataset.historyId === result.dataset.historyId;
    });
    var question = result.querySelector('.ai_analytik_question');
    if (question) {
      if (prompt === group.querySelector('h3').textContent) question.hidden = true;
      else question.textContent = 'Upřesnění: ' + prompt;
    }
    if (previous) previous.replaceWith(result);
    else container.prepend(result);
    // Ulozeni stejneho promptu nabizime jen u posledniho kola, starsi vysledky zustavaji k nahlednuti.
    Array.from(container.children).forEach(function (turn, index) {
      var save = turn.querySelector('.ai_analytik_save_prompt');
      if (save) save.hidden = index !== 0;
    });
    // Aktivni prompt se presune nahoru jako celek; uvnitr je posledni vysledek take nahore.
    results.prepend(group);
  }

  window.CB_AI_RESULT_GROUP = { insert: insert };
})();
