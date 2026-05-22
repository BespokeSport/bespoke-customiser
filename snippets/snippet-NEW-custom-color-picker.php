<?php
/**
 * Snippet ID:    29
 * Name:          BESPOKE: Custom colour picker (test)
 * Status:        TEST / TEMPORARY
 *
 * Injects a custom HSV colour picker into the customiser to replace the
 * native <input type="color"> behaviour. Required because mobile Android
 * shows the OS HSV picker which doesn't match the desktop gradient+hue UI.
 *
 * When ready to ship, this code will be folded into assets/customiser.html
 * and this snippet will be deactivated.
 *
 * Note: uses PHP nowdoc (<<<'TAG') so JS template literals, $ chars and
 * backticks pass through unmodified. The `?>` mode-switch pattern is NOT
 * supported by Code Snippets v3's function wrapping.
 */

add_action('wp_footer', function() {
    if (is_admin()) return;
    echo <<<'BCPSCRIPT'
<script>
(function(){
  function init(){
    if (window.__bcp_loaded) return;
    if (!document.querySelector('.ct input[type=color]')) { setTimeout(init, 200); return; }
    window.__bcp_loaded = true;

    const css = document.createElement('style');
    css.textContent = `
      #bcp-overlay{position:fixed!important;top:0!important;left:0!important;width:100vw!important;height:100vh!important;background:transparent!important;display:none;align-items:center!important;justify-content:center!important;z-index:2147483647!important;font-family:'Inter',sans-serif}
      #bcp-overlay.open{display:flex}
      .bcp-panel{background:#1A1A1A;border:1px solid #2A2A2A;border-radius:12px;padding:20px;width:300px;max-width:90vw;box-sizing:border-box;box-shadow:0 12px 40px rgba(0,0,0,0.5)}
      .bcp-title{font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:14px}
      .bcp-sv{position:relative;width:100%;height:180px;border-radius:8px;touch-action:none;user-select:none;cursor:crosshair;overflow:hidden}
      .bcp-sv-cursor{position:absolute;width:14px;height:14px;border:2px solid #fff;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;box-shadow:0 1px 3px rgba(0,0,0,0.5)}
      .bcp-hue{position:relative;width:100%;height:18px;border-radius:9px;margin-top:14px;background:linear-gradient(to right,#f00,#ff0,#0f0,#0ff,#00f,#f0f,#f00);touch-action:none;user-select:none;cursor:pointer}
      .bcp-hue-cursor{position:absolute;top:50%;width:14px;height:14px;border:2px solid #fff;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;box-shadow:0 1px 3px rgba(0,0,0,0.5)}
      .bcp-row{display:flex;gap:8px;margin-top:14px;align-items:center}
      .bcp-hex{flex:1;background:#0E0E10;border:1px solid #2A2A2A;border-radius:6px;padding:8px 10px;color:#fff;font-family:'Inter',sans-serif;font-size:13px;outline:none;text-transform:uppercase;letter-spacing:0.04em}
      .bcp-hex:focus{border-color:#5DCAA5}
      .bcp-done{background:#5DCAA5;color:#04342C;border:none;border-radius:6px;padding:9px 18px;font-family:'Inter',sans-serif;font-size:12px;font-weight:600;letter-spacing:0.06em;cursor:pointer;text-transform:uppercase}
      .bcp-done:hover{background:#4FB996}
      @media(min-width:900px){#bcp-overlay{justify-content:flex-start!important;padding-left:60px!important;box-sizing:border-box!important}}
      @media(max-width:899px){#bcp-overlay{align-items:flex-end!important;justify-content:center!important;padding:0!important}.bcp-panel{width:100%!important;max-width:100%!important;border-radius:16px 16px 0 0!important;padding:16px!important}.bcp-sv{height:160px!important}}
      #dt-label,#dt-hint{align-self:center!important;background:#5DCAA5!important;color:#04342C!important;border-radius:999px!important;font-family:'Inter',sans-serif!important;position:relative!important;z-index:5!important}
      #dt-label{order:0!important;font-size:18px!important;font-weight:600!important;letter-spacing:0.12em!important;text-transform:uppercase!important;padding:10px 24px!important;margin-bottom:6px!important}
      #dt-hint{order:1!important;font-size:12px!important;font-weight:500!important;letter-spacing:0.02em!important;padding:6px 14px!important;margin-bottom:8px!important}
      #dt-svg-wrap{order:2!important}
      html,body{overflow-x:hidden!important;max-width:100vw!important}
      #bespoke-marquee{max-width:100vw!important;overflow-x:hidden!important;box-sizing:border-box!important}
    `;
    document.head.appendChild(css);

    const overlay = document.createElement('div');
    overlay.id = 'bcp-overlay';
    overlay.innerHTML = '<div class="bcp-panel"><div class="bcp-title">Choose colour</div><div class="bcp-sv" id="bcp-sv"><div class="bcp-sv-cursor" id="bcp-sv-cursor"></div></div><div class="bcp-hue" id="bcp-hue"><div class="bcp-hue-cursor" id="bcp-hue-cursor"></div></div><div class="bcp-row"><input class="bcp-hex" id="bcp-hex" type="text" maxlength="7" /><button class="bcp-done" id="bcp-done">Done</button></div></div>';
    document.body.appendChild(overlay);

    let activeInput = null, h=0, s=1, v=1;
    const $ = id => document.getElementById(id);
    const sv = $('bcp-sv'), svC = $('bcp-sv-cursor'), hue = $('bcp-hue'), hueC = $('bcp-hue-cursor'), hex = $('bcp-hex'), done = $('bcp-done');

    function hsv2rgb(h,s,v){const c=v*s,x=c*(1-Math.abs(((h/60)%2)-1)),m=v-c;let r=0,g=0,b=0;if(h<60){r=c;g=x}else if(h<120){r=x;g=c}else if(h<180){g=c;b=x}else if(h<240){g=x;b=c}else if(h<300){r=x;b=c}else{r=c;b=x}return[Math.round((r+m)*255),Math.round((g+m)*255),Math.round((b+m)*255)]}
    function rgb2hsv(r,g,b){r/=255;g/=255;b/=255;const mx=Math.max(r,g,b),mn=Math.min(r,g,b),d=mx-mn;let H=0;if(d){if(mx===r)H=((g-b)/d)%6;else if(mx===g)H=(b-r)/d+2;else H=(r-g)/d+4;H=Math.round(H*60);if(H<0)H+=360}return[H,mx===0?0:d/mx,mx]}
    function hex2rgb(x){x=x.replace(/^#/,'');if(x.length===3)x=x[0]+x[0]+x[1]+x[1]+x[2]+x[2];return[parseInt(x.slice(0,2),16),parseInt(x.slice(2,4),16),parseInt(x.slice(4,6),16)]}
    function rgb2hex(r,g,b){return'#'+[r,g,b].map(n=>n.toString(16).padStart(2,'0')).join('')}
    function paintSV(){sv.style.background='linear-gradient(to top,#000,transparent),linear-gradient(to right,#fff,hsl('+h+',100%,50%))'}
    function refresh(){paintSV();svC.style.left=(s*100)+'%';svC.style.top=((1-v)*100)+'%';hueC.style.left=(h/360*100)+'%';const[R,G,B]=hsv2rgb(h,s,v);if(document.activeElement!==hex)hex.value=rgb2hex(R,G,B).toUpperCase();push()}
    let _pushRaf=false;function push(){if(!activeInput)return;if(_pushRaf)return;_pushRaf=true;requestAnimationFrame(()=>{_pushRaf=false;if(!activeInput)return;const[R,G,B]=hsv2rgb(h,s,v);activeInput.value=rgb2hex(R,G,B);activeInput.dispatchEvent(new Event('input',{bubbles:true}))})}
    function setHex(x){const[R,G,B]=hex2rgb(x);[h,s,v]=rgb2hsv(R,G,B);refresh()}

    function drag(el,fn){
      function down(e){
        e.preventDefault(); fn(e);
        function mv(e){fn(e)}
        function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('touchmove',mv);document.removeEventListener('mouseup',up);document.removeEventListener('touchend',up)}
        document.addEventListener('mousemove',mv);
        document.addEventListener('touchmove',mv,{passive:false});
        document.addEventListener('mouseup',up);
        document.addEventListener('touchend',up);
      }
      el.addEventListener('mousedown',down);
      el.addEventListener('touchstart',down,{passive:false});
    }

    drag(sv,e=>{
      const r=sv.getBoundingClientRect(),t=e.touches?e.touches[0]:e,
        x=Math.max(0,Math.min(r.width,t.clientX-r.left)),
        y=Math.max(0,Math.min(r.height,t.clientY-r.top));
      s=x/r.width; v=1-(y/r.height);
      svC.style.left=(s*100)+'%'; svC.style.top=((1-v)*100)+'%';
      const[R,G,B]=hsv2rgb(h,s,v);
      if(document.activeElement!==hex) hex.value=rgb2hex(R,G,B).toUpperCase();
      push();
    });
    drag(hue,e=>{
      const r=hue.getBoundingClientRect(),t=e.touches?e.touches[0]:e,
        x=Math.max(0,Math.min(r.width,t.clientX-r.left));
      h=(x/r.width)*360;
      refresh();
    });

    hex.addEventListener('input',e=>{
      const v=e.target.value.replace(/[^0-9a-fA-F]/g,'').slice(0,6);
      if(v.length===6||v.length===3){try{setHex('#'+v)}catch(_){}}
    });

    function close(){overlay.classList.remove('open');activeInput=null}
    done.addEventListener('click',close);
    overlay.addEventListener('click',e=>{if(e.target===overlay)close()});

    document.querySelectorAll('.ct input[type=color]').forEach(i=>{i.style.pointerEvents='none'});
    document.querySelectorAll('.ct').forEach(ct=>{
      function open(){
        const i=ct.querySelector('input[type=color]');
        if(!i)return;
        activeInput=i;
        setHex(i.value||'#ff0000');
        overlay.classList.add('open');
        try{overlay.scrollIntoView({block:'center',inline:'center'})}catch(_){}
        hex.value=(i.value||'#FF0000').toUpperCase();
      }
      ct.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();open()});
      ct.addEventListener('touchend',e=>{e.preventDefault();open()},{passive:false});
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
BCPSCRIPT;
}, 100);
