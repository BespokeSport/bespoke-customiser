<?php
/**
 * Snippet ID:    23
 * Name:          Shadow Highlight
 * Status:        ACTIVE
 * Last modified: 2026-03-01 11:06:59
 * Hides Fancy Product Designer "Manage Layers" rows whose label starts with
 * "__overlay_" (internal helper layers we don't want the user to see/edit).
 */

add_action('wp_footer', function () {
  if (!function_exists('is_product') || !is_product()) return;
  ?>
  <script>
  (function(){
    const PREFIX = '__overlay_';

    function getLabelText(cell) {
      // The label is the first text node inside .fpd-cell-1
      // e.g. "__overlay_highlight" before the <div class="fpd-img-meta">
      for (const node of cell.childNodes) {
        if (node.nodeType === Node.TEXT_NODE) {
          return (node.textContent || '').trim();
        }
      }
      // Fallback
      return (cell.textContent || '').trim();
    }

    function removeOverlayRows() {
      // This is the exact container shown in your HTML
      const manageLayers = document.querySelector('fpd-module-manage-layers');
      if (!manageLayers) return;

      const rows = manageLayers.querySelectorAll('.fpd-list-row');
      rows.forEach(row => {
        const cell = row.querySelector('.fpd-cell-1');
        if (!cell) return;

        const label = getLabelText(cell);
        if (label.startsWith(PREFIX)) {
          row.remove(); // remove the entire row from the list
        }
      });
    }

    // Run after load + re-run because FPD re-renders panels dynamically
    function runBurst() {
      removeOverlayRows();
      setTimeout(removeOverlayRows, 200);
      setTimeout(removeOverlayRows, 600);
      setTimeout(removeOverlayRows, 1200);
    }

    document.addEventListener('DOMContentLoaded', runBurst);

    // Watch for re-renders and re-remove the rows
    const obs = new MutationObserver(() => removeOverlayRows());
    obs.observe(document.body, { childList: true, subtree: true });

    // When user clicks around / opens Manage Design, run again shortly after
    document.addEventListener('click', () => {
      setTimeout(removeOverlayRows, 50);
      setTimeout(removeOverlayRows, 250);
    }, true);

  })();
  </script>
  <?php
}, 999);
