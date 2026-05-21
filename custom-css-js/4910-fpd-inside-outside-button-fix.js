// Custom CSS/JS Plugin Entry
// ID:       4910
// Title:    FPD Inside/Outside Button Fix
// Type:     js (footer)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
(function () {
  const MOBILE_MAX = 768;

  // Attach a "direct action" handler to a side (left/right)
  function attachDirectAction(side, action) {
    const scope = document.querySelector(`[data-pos="${side}"] fpd-actions-menu`);
    if (!scope) return;

    const trigger = scope.querySelector('.fpd-collapsed-menu .fpd-dropdown-btn');
    const target  = scope.querySelector(`[data-action="${action}"]`);
    if (!trigger || !target) return;
    if (trigger.dataset.directified === '1') return;

    const handler = function (e) {
      // Make the label act like the real button
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      // Just in case: keep dropdown hidden
      const menu = scope.querySelector('.fpd-dropdown-menu');
      if (menu) menu.style.display = 'none';

      // Fire the actual FPD action that the dropdown item would run
      // Using a "real" mouse event improves compatibility
      const clickEvt = new MouseEvent('click', { bubbles: true, cancelable: true, view: window });
      target.dispatchEvent(clickEvt);
      if (typeof target.click === 'function') target.click();
      return false;
    };

    // Capture phase so we beat FPD's dropdown listener to the punch
    ['pointerdown', 'touchstart', 'mousedown', 'click'].forEach(evt => {
      trigger.addEventListener(evt, handler, { capture: true });
    });

    trigger.dataset.directified = '1';
  }

  function enhance() {
    if (window.innerWidth > MOBILE_MAX) return;
    // Left = Inside Band â†’ next view
    attachDirectAction('left',  'next-view');
    // Right = Outside Band â†’ previous view
    attachDirectAction('right', 'previous-view');
  }

  // Run at the right times
  document.addEventListener('DOMContentLoaded', enhance);
  window.addEventListener('load', enhance);
  window.addEventListener('resize', enhance);

  // If FPD rebuilds the toolbar, re-attach automatically
  const mo = new MutationObserver(enhance);
  mo.observe(document.body, { childList: true, subtree: true });
})();
