<?php
/**
 * Snippet ID:    24
 * Name:          MSPC Colour
 * Status:        INACTIVE
 * Last modified: 2026-03-01 12:00:20
 * Forces the FPD .picker_wrapper colour picker into its full "default" layout
 * (hue + saturation + alpha sliders all visible) instead of the compact block.
 */

add_action('wp_footer', function () {
  if (!function_exists('is_product') || !is_product()) return;
  ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {

    function forceAdvancedPicker() {

      const pickers = document.querySelectorAll('.picker_wrapper');

      pickers.forEach(function(picker) {

        // Ensure full layout class is applied
        picker.classList.remove('layout_block');
        picker.classList.add('layout_default');

        // Ensure sliders are visible
        const hue = picker.querySelector('.picker_hue');
        const sl = picker.querySelector('.picker_sl');
        const alpha = picker.querySelector('.picker_alpha');

        if (hue) hue.style.display = 'block';
        if (sl) sl.style.display = 'block';
        if (alpha) alpha.style.display = 'block';
      });

    }

    // Run multiple times because FPD builds dynamically
    setTimeout(forceAdvancedPicker, 300);
    setTimeout(forceAdvancedPicker, 800);
    setTimeout(forceAdvancedPicker, 1500);

    const observer = new MutationObserver(forceAdvancedPicker);
    observer.observe(document.body, { childList: true, subtree: true });

  });
  </script>
  <?php
}, 999);
