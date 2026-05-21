// Custom CSS/JS Plugin Entry
// ID:       6639
// Title:    BEspoke â€” Testimonials
// Type:     js (header)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
/* Inject testimonials CSS */
(function() {
  var style = document.createElement('style');
  style.textContent = '#bespoke-testimonials{background:#0E0E10;border-top:1px solid rgba(255,255,255,0.08);padding:120px 0 140px}.bespoke-testi-inner{max-width:1440px;margin:0 auto;padding:0 56px;text-align:center}.bespoke-testi-label{font-family:"Inter",sans-serif;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.4);margin-bottom:56px}.bespoke-testi-quote-wrap{min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center}.bespoke-testi-quote{font-family:"Anton",sans-serif;font-size:clamp(28px,3.5vw,52px);line-height:1.1;letter-spacing:-0.01em;color:#fff;max-width:1000px;margin:0 auto 32px;transition:opacity 0.4s ease}.bespoke-testi-attr{display:flex;align-items:center;justify-content:center;gap:12px;transition:opacity 0.4s ease}.bespoke-testi-name{font-family:"Inter",sans-serif;font-size:13px;font-weight:600;color:#fff;letter-spacing:0.05em}.bespoke-testi-divider{color:rgba(255,255,255,0.3);font-size:16px}.bespoke-testi-product{font-family:"Inter",sans-serif;font-size:13px;color:#5DCAA5;letter-spacing:0.05em}.bespoke-testi-dots{display:flex;justify-content:center;gap:8px;margin-top:48px}.bespoke-testi-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.2);border:none;cursor:pointer;padding:0;transition:background 0.3s ease,transform 0.3s ease}.bespoke-testi-dot.active{background:#5DCAA5;transform:scale(1.4)}';
  document.head.appendChild(style);
})();

(function() {
  if (document.getElementById('bespoke-testimonials')) return;

  var quotes = [
    { text: "Ordered at the weekend and it was on our U13s captainâ€™s arm Wednesday evening!", attr: "U13s Coach", product: "Captainâ€™s Armbands" },
    { text: "We ordered a few to begin with and after receiving them simply had to order them for every team at our club. We have 8 teams that will all wear the BE:spoke armbands with pride.", attr: "Club Secretary", product: "Captainâ€™s Armbands" },
    { text: "Youâ€™ll regret not using BE:spoke! Thereâ€™s only a couple of quid difference between your boring standard armbands and their bespoke armbands.", attr: "Verified Buyer", product: "Captainâ€™s Armbands" },
    { text: "From initial enquiry to assisting with designs, to payments and delivery â€” top service, quick turnaround and the quality of the products is brilliant!", attr: "Verified Buyer", product: "Multiple Products" },
    { text: "Every single item has come out perfectly! Always extremely quick to respond. Love that a template is sent over to check the design before itâ€™s made.", attr: "Club Manager", product: "Armbands, Pennants, Shin Pads & More" },
    { text: "A unique and incredible service. Communication is spot on and the quality of the product is second to none.", attr: "Verified Buyer", product: "Captainâ€™s Armbands" },
    { text: "One of the best customer services I have received in a long time!", attr: "Verified Buyer", product: "Personalised Kit" },
    { text: "Even hand delivered my order! The product is pure quality and Iâ€™m so happy with them!", attr: "Local Customer", product: "Personalised Order" }
  ];

  var current = 0;
  var timer;

  function buildTestimonials() {
    if (document.getElementById('bespoke-testimonials')) return;

    var statsEl = document.getElementById('bespoke-stats');
    if (!statsEl) return;

    var section = document.createElement('div');
    section.id = 'bespoke-testimonials';

    section.innerHTML =
      '<div class="bespoke-testi-inner">' +
        '<div class="bespoke-testi-label">WHAT CLUBS SAY</div>' +
        '<div class="bespoke-testi-quote-wrap">' +
          '<div class="bespoke-testi-quote" id="bespoke-testi-text">' +
            'â€œ' + quotes[0].text + 'â€' +
          '</div>' +
          '<div class="bespoke-testi-attr" id="bespoke-testi-attr">' +
            '<span class="bespoke-testi-name">' + quotes[0].attr + '</span>' +
            '<span class="bespoke-testi-divider">Â·</span>' +
            '<span class="bespoke-testi-product">' + quotes[0].product + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="bespoke-testi-dots" id="bespoke-testi-dots">' +
          quotes.map(function(q, i) {
            return '<button class="bespoke-testi-dot' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '"></button>';
          }).join('') +
        '</div>' +
      '</div>';

    statsEl.parentNode.insertBefore(section, statsEl.nextSibling);

    // Dot click handlers
    section.querySelectorAll('.bespoke-testi-dot').forEach(function(dot) {
      dot.addEventListener('click', function() {
        goTo(parseInt(this.getAttribute('data-idx')));
        resetTimer();
      });
    });

    startTimer();
  }

  function goTo(idx) {
    current = idx;
    var textEl = document.getElementById('bespoke-testi-text');
    var attrEl = document.getElementById('bespoke-testi-attr');
    var dots = document.querySelectorAll('.bespoke-testi-dot');

    // Fade out
    textEl.style.opacity = '0';
    attrEl.style.opacity = '0';

    setTimeout(function() {
      textEl.textContent = 'â€œ' + quotes[idx].text + 'â€';
      attrEl.innerHTML =
        '<span class="bespoke-testi-name">' + quotes[idx].attr + '</span>' +
        '<span class="bespoke-testi-divider">Â·</span>' +
        '<span class="bespoke-testi-product">' + quotes[idx].product + '</span>';

      // Fade in
      textEl.style.opacity = '1';
      attrEl.style.opacity = '1';
    }, 400);

    // Update dots
    dots.forEach(function(d, i) {
      d.classList.toggle('active', i === idx);
    });
  }

  function startTimer() {
    timer = setInterval(function() {
      goTo((current + 1) % quotes.length);
    }, 5000);
  }

  function resetTimer() {
    clearInterval(timer);
    startTimer();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildTestimonials);
  } else {
    buildTestimonials();
  }
})();