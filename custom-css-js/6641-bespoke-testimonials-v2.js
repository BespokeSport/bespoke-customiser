// Custom CSS/JS Plugin Entry
// ID:       6641
// Title:    BEspoke â€” Testimonials v2
// Type:     js (header)
// Linking:  internal
// Priority: 5
// Status:   publish (frontend=True)
/* Inject testimonials CSS */
(function(){var s=document.createElement('style');s.textContent='#bespoke-testimonials{background:#0E0E10;border-top:1px solid rgba(255,255,255,0.08);padding:120px 0 140px}.bespoke-testi-inner{max-width:1440px;margin:0 auto;padding:0 56px;text-align:center}.bespoke-testi-label{font-family:"Inter",sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:56px}.bespoke-testi-quote-wrap{min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center}.bespoke-testi-quote{font-family:"Anton",sans-serif;font-size:clamp(28px,3.5vw,52px);line-height:1.1;letter-spacing:-.01em;color:#fff;max-width:1000px;margin:0 auto 32px;transition:opacity .4s ease}.bespoke-testi-attr{display:flex;align-items:center;justify-content:center;gap:12px;transition:opacity .4s ease}.bespoke-testi-name{font-size:13px;font-weight:600;color:#fff}.bespoke-testi-divider{color:rgba(255,255,255,.3)}.bespoke-testi-product{font-size:13px;color:#5DCAA5}.bespoke-testi-dots{display:flex;justify-content:center;gap:8px;margin-top:48px}.bespoke-testi-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.2);border:none;cursor:pointer;padding:0;transition:background .3s,transform .3s}.bespoke-testi-dot.active{background:#5DCAA5;transform:scale(1.4)}';
document.head.appendChild(s);})();

(function() {
  if (document.getElementById('bespoke-testimonials')) return;

  var quotes = [
    { text: 'Ordered at the weekend and it was on our U13s captainâ€™s arm Wednesday evening!', attr: 'U13s Coach', product: 'Captainâ€™s Armbands' },
    { text: 'We have 8 teams that will all wear the BE:spoke armbands with pride. Simply had to order them for every team at our club.', attr: 'Club Secretary', product: 'Captainâ€™s Armbands' },
    { text: 'Youâ€™ll regret not using BE:spoke! Thereâ€™s only a couple of quid difference between your boring standard armbands and their bespoke armbands.', attr: 'Verified Buyer', product: 'Captainâ€™s Armbands' },
    { text: 'From initial enquiry to assisting with designs, to payments and delivery â€” top service, quick turnaround and the quality of the products is brilliant!', attr: 'Verified Buyer', product: 'Multiple Products' },
    { text: 'Every single item has come out perfectly! Always extremely quick to respond. Love that a template is sent over to check the design before itâ€™s made.', attr: 'Club Manager', product: 'Armbands, Pennants, Shin Pads & More' },
    { text: 'A unique and incredible service. Communication is spot on and the quality of the product is second to none.', attr: 'Verified Buyer', product: 'Captainâ€™s Armbands' },
    { text: 'One of the best customer services I have received in a long time!', attr: 'Verified Buyer', product: 'Personalised Kit' },
    { text: 'Even hand delivered my order! The product is pure quality and Iâ€™m so happy with them!', attr: 'Local Customer', product: 'Personalised Order' }
  ];

  var current = 0;
  var timer;

  function build() {
    if (document.getElementById('bespoke-testimonials')) return;

    // Wait for stats section if not ready yet
    var anchor = document.getElementById('bespoke-stats');
    if (!anchor) { setTimeout(build, 300); return; }

    var el = document.createElement('div');
    el.id = 'bespoke-testimonials';
    el.innerHTML =
      '<div class="bespoke-testi-inner">' +
        '<div class="bespoke-testi-label">WHAT CLUBS SAY</div>' +
        '<div class="bespoke-testi-quote-wrap">' +
          '<div class="bespoke-testi-quote" id="bt-q">â€œ' + quotes[0].text + 'â€</div>' +
          '<div class="bespoke-testi-attr" id="bt-a">' +
            '<span class="bespoke-testi-name">' + quotes[0].attr + '</span>' +
            ' <span class="bespoke-testi-divider">Â·</span> ' +
            '<span class="bespoke-testi-product">' + quotes[0].product + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="bespoke-testi-dots">' +
          quotes.map(function(q,i){ return '<button class="bespoke-testi-dot'+(i===0?' active':'')+'" data-i="'+i+'"></button>'; }).join('') +
        '</div>' +
      '</div>';

    anchor.parentNode.insertBefore(el, anchor.nextSibling);

    el.querySelectorAll('.bespoke-testi-dot').forEach(function(d) {
      d.addEventListener('click', function() { goTo(+this.getAttribute('data-i')); resetTimer(); });
    });

    startTimer();
  }

  function goTo(i) {
    current = i;
    var q = document.getElementById('bt-q'), a = document.getElementById('bt-a');
    q.style.opacity = a.style.opacity = '0';
    setTimeout(function() {
      q.textContent = 'â€œ' + quotes[i].text + 'â€';
      a.innerHTML = '<span class="bespoke-testi-name">'+quotes[i].attr+'</span> <span class="bespoke-testi-divider">Â·</span> <span class="bespoke-testi-product">'+quotes[i].product+'</span>';
      q.style.opacity = a.style.opacity = '1';
    }, 400);
    document.querySelectorAll('.bespoke-testi-dot').forEach(function(d,j){ d.classList.toggle('active', j===i); });
  }

  function startTimer() { timer = setInterval(function(){ goTo((current+1)%quotes.length); }, 5000); }
  function resetTimer() { clearInterval(timer); startTimer(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();