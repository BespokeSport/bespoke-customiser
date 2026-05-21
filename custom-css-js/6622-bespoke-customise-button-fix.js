// Custom CSS/JS Plugin Entry
// ID:       6622
// Title:    BEspoke Customise Button Fix
// Type:     js (footer)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
// Replace "Read more" with "View Options" - delayed to catch Elementor-rendered products
function bespoke_fix_read_more() {
	  document.querySelectorAll('.woocommerce ul.products li.product a.button').forEach(function(btn) {
		      if (btn.textContent.trim().toLowerCase() === 'read more') {
				        btn.textContent = 'View Options';
			  }
	  });
}
window.addEventListener('load', function() {
	  bespoke_fix_read_more();
	  setTimeout(bespoke_fix_read_more, 500);
	  setTimeout(bespoke_fix_read_more, 1500);
	  setTimeout(bespoke_fix_read_more, 3000);
});
})
			  }
	  })
}