// Custom CSS/JS Plugin Entry
// ID:       6637
// Title:    BEspoke â€” Stats Bar
// Type:     js (header)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
(function() {
  if (document.getElementById('bespoke-stats')) return;

  function buildStats() {
    if (document.getElementById('bespoke-stats')) return;

    // Find the product grid section and insert stats after it
    var article = document.querySelector('main article, .site-main article');
    if (!article) return;

    // The product grid is the 3rd section (index 2)
    var sections = article.querySelectorAll('.elementor-section');
    if (sections.length < 3) return;
    var afterSection = sections[2]; // after products section

    var stats = [
      { num: 'NO', sub: 'MIN.', label: 'Minimum order quantity', body: 'Order one shin pad. One armband. One of anything. Every item made just for you.' },
      { num: '5', sub: 'DAYS', label: 'From design to your door', body: 'Sign off your proof on Monday, kit on the pitch by the weekend. Express print available.' },
      { num: 'UK', sub: 'MADE', label: 'Printed in Hampshire', body: 'Designed, printed and packed by us in Waterlooville. Your kit, our press, no middlemen.' }
    ];

    var strip = document.createElement('div');
    strip.id = 'bespoke-stats';
    strip.innerHTML = `
      <div class="bespoke-stats-inner">
        <div class="bespoke-stats-label">THE BESPOKE PROMISE</div>
        <div class="bespoke-stats-grid">
          ${stats.map(function(s, i) {
            return `<div class="bespoke-stat-item">
              <div class="bespoke-stat-index">0${i+1}</div>
              <div class="bespoke-stat-big">${s.num}<span class="bespoke-stat-sub"> ${s.sub}</span></div>
              <div class="bespoke-stat-label">${s.label}</div>
              <p class="bespoke-stat-body">${s.body}</p>
            </div>`;
          }).join('')}
        </div>
      </div>
    `;

    afterSection.parentNode.insertBefore(strip, afterSection.nextSibling);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildStats);
  } else {
    buildStats();
  }
})();