/* Účel: Otevírá tabulku členů role na hover či fokus; kliknutí ji připne bez zásahu do práv. */
(() => {
  'use strict';
  let anchor = null, popup = null, pinned = false, timer;
  // Jediný plovoucí panel se při výměně pracovní plochy nezanechá v dokumentu.
  function close() {
    clearTimeout(timer);
    if (anchor) anchor.setAttribute('aria-expanded', 'false');
    if (popup) popup.remove();
    anchor = popup = null; pinned = false;
  }
  // Všechny role kotvíme ke stejnému záhlaví; výška názvu ani obsahu neposouvá horní okraj panelu.
  function position() {
    if (!anchor || !anchor.isConnected) return close();
    const rect = anchor.getBoundingClientRect();
    const header = anchor.closest('thead').getBoundingClientRect();
    const size = popup.getBoundingClientRect();
    popup.style.left = Math.max(8, Math.min(rect.left, innerWidth - size.width - 8)) + 'px';
    const top = Math.max(8, Math.min(header.bottom + 7, innerHeight - 180));
    popup.style.top = top + 'px';
    popup.querySelector('.admin_role_popup_scroll').style.maxHeight = Math.max(60, Math.min(340, innerHeight * 0.5, innerHeight - top - 70)) + 'px';
  }
  // Obsah už dodal server; otevření neposílá další požadavek ani nemění přiřazení.
  function open(button) {
    clearTimeout(timer);
    if (anchor === button) return;
    if (pinned) return;
    const template = document.getElementById(button.dataset.roleMembers);
    if (!template) return;
    close(); anchor = button;
    popup = document.createElement('section');
    popup.className = 'admin_role_popup'; popup.id = 'admin-role-popup';
    popup.setAttribute('role', 'region');
    popup.setAttribute('aria-label', button.getAttribute('aria-label'));
    popup.append(template.content.cloneNode(true)); document.body.append(popup);
    anchor.setAttribute('aria-expanded', 'true'); position();
  }
  // Krátká prodleva dovolí přejet mezeru mezi názvem role a posuvnou tabulkou.
  function scheduleClose() {
    clearTimeout(timer);
    timer = setTimeout(() => {
      if (!pinned && popup && !popup.matches(':hover') && !popup.contains(document.activeElement) && !anchor.matches(':hover') && anchor !== document.activeElement) close();
    }, 200);
  }
  document.addEventListener('pointerover', e => {
    const button = e.target.closest('[data-role-members]');
    if (button && e.pointerType !== 'touch') open(button);
    if (popup && popup.contains(e.target)) clearTimeout(timer);
  });
  document.addEventListener('pointerout', scheduleClose);
  document.addEventListener('focusin', e => {
    const button = e.target.closest('[data-role-members]');
    if (button) open(button);
  });
  document.addEventListener('focusout', scheduleClose);
  document.addEventListener('click', e => {
    const button = e.target.closest('[data-role-members]');
    if (button) {
      if (anchor === button && pinned) return close();
      if (anchor !== button) { close(); open(button); }
      pinned = true; return;
    }
    if (e.target.closest('[data-role-close]')) {
      const previous = anchor; close();
      // Fokus se vrátí bez opětovného otevření delegovaným focusin handlerem.
      if (previous) { pinned = true; previous.focus(); pinned = false; }
      return;
    }
    if (popup && !popup.contains(e.target)) close();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (popup) position(); });
  document.addEventListener('scroll', e => { if (popup && !popup.contains(e.target)) position(); }, true);
  new MutationObserver(() => { if (anchor && !anchor.isConnected) close(); }).observe(document.documentElement, {childList:true,subtree:true});
})();
