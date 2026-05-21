// Custom CSS/JS Plugin Entry
// ID:       6625
// Title:    BEspoke â€” Marquee Ticker
// Type:     js (footer)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
(function() {
  if (document.getElementById('bespoke-marquee')) return;

  var items = [
    'NO MINIMUM ORDER', 'UK MADE', '5 DAY DISPATCH',
    'YOUR CREST YOUR COLOURS', 'GRASSROOTS TO ELITE', 'EXPRESS PRINT AVAILABLE',
    'NO MINIMUM ORDER', 'UK MADE', '5 DAY DISPATCH',
    'YOUR CREST YOUR COLOURS', 'GRASSROOTS TO ELITE', 'EXPRESS PRINT AVAILABLE',
    'NO MINIMUM ORDER', 'UK MADE', '5 DAY DISPATCH',
    'YOUR CREST YOUR COLOURS', 'GRASSROOTS TO ELITE', 'EXPRESS PRINT AVAILABLE'
  ];

  function buildMarquee() {
    if (document.getElementById('bespoke-marquee')) return;

    var article = document.querySelector('main article, .site-main article');
    if (!article) return;
    var heroSection = article.querySelector('.elementor-section');
    if (!heroSection) return;

    var strip = document.createElement('div');
    strip.id = 'bespoke-marquee';

    var track = document.createElement('div');
    track.className = 'bespoke-marquee-track';
    track.innerHTML = items.map(function(t) {
      return '<span class="bespoke-marquee-item">' + t +
             '<span class="bespoke-marquee-dot"></span></span>';
    }).join('');

    strip.appendChild(track);
    heroSection.parentNode.insertBefore(strip, heroSection.nextSibling);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildMarquee);
  } else {
    buildMarquee();
  }
})();