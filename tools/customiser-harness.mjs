// Local test pages for the customiser — see HANDOVER.md §6.10.
//   node tools/customiser-harness.mjs [port]      (default 5178)
//   then open http://localhost:5178/armbands.html (any product type works:
//   /double_sided_armbands.html, /shinpads.html, /gripsocks.html …)
//
// Each request rebuilds the page from the CURRENT assets/customiser.html, the
// way customiser-frontend.php embeds it: head <style> blocks + <body> inside
// #bespoke-customiser-root, with a fake window.BespokeConfig before it and the
// real capturePreviewSVG override (PNG pipeline) lifted from
// customiser-frontend.php after it. No band artwork or registered designs —
// badges, text, steps and the add-to-basket payload are what it's for.
//
// admin-ajax is faked in the page: uploads are kept in memory and served back
// from /uploads/…, previews land in window.__previews, and the add-to-basket
// POST is captured in window.__cartPayload instead of being sent anywhere.
// alert() is logged (window.__lastAlert) so it can't block the page.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const port = +(process.argv[2] || 5178);

function capturePipeline() {
  const fe = fs.readFileSync(path.join(repo, 'includes/customiser-frontend.php'), 'utf8');
  const a = fe.indexOf('function urlToDataURI(url){');
  const b = fe.indexOf("if (document.readyState === 'loading')", a);
  if (a < 0 || b < 0) return '/* capture override not found — using customiser.html\'s own */';
  // Drop the closing brace of the init() function the helpers live in.
  return 'function applyRegisteredDesignSVG(){ return Promise.resolve(); }\n' + fe.slice(a, b).replace(/\}\s*$/, '');
}

const FETCH_STUB = `
(function(){
  var realFetch = window.fetch.bind(window);
  var store = {};
  window.__uploads = store; window.__previews = []; window.__cartPayload = null;
  window.alert = function(m){ console.log('ALERT: ' + m); window.__lastAlert = m; };
  var ok = function(data){
    return Promise.resolve(new Response(JSON.stringify({ success:true, data:data }),
      { headers:{ 'Content-Type':'application/json' } }));
  };
  window.fetch = function(url, opts){
    url = String(url);
    if (url.indexOf('/uploads/') !== -1 && store[url]) return Promise.resolve(new Response(store[url]));
    if (url.indexOf('/fake-ajax') !== -1 && opts && opts.body instanceof FormData) {
      var fd = opts.body, action = fd.get('action');
      if (action === 'bespoke_upload_badge' || action === 'bespoke_upload_preview') {
        var f = fd.get(action === 'bespoke_upload_badge' ? 'badge' : 'preview');
        var u = location.origin + '/uploads/' + Date.now() + '-' + Math.random().toString(36).slice(2) + '-' + f.name;
        store[u] = f;
        if (action === 'bespoke_upload_preview') window.__previews.push(u);
        return ok({ url:u, filename:f.name });
      }
      if (action === 'bespoke_add_to_cart') {
        var o = {};
        fd.forEach(function(v, k){ o[k] = (v instanceof Blob) ? '[blob]' : v; });
        window.__cartPayload = o;
        return ok({ cart_url:'#added' });
      }
    }
    return realFetch(url, opts);
  };
})();`;

// Pretend translation: every string customiser-i18n.php offers comes back
// marked, so a page shows at a glance which wording goes through bespokeT()
// and which is still hard-coded English. Add ?i18n to the page address.
function pseudoI18n() {
  const php = fs.readFileSync(path.join(repo, 'includes/customiser-i18n.php'), 'utf8');
  const map = {};
  for (const m of php.matchAll(/^\s*'((?:[^'\\]|\\.)*)'\s*=>\s*__\(/gm)) {
    const english = m[1].replace(/\\'/g, "'");
    map[english] = '«' + english + '»';
  }
  return map;
}

function page(type, i18n) {
  const raw = fs.readFileSync(path.join(repo, 'assets/customiser.html'), 'utf8');
  const head = (raw.match(/<head[^>]*>([\s\S]*?)<\/head>/i) || [, ''])[1];
  const styles = (head.match(/<style[^>]*>[\s\S]*?<\/style>/gi) || []).join('\n');
  const body = (raw.match(/<body[^>]*>([\s\S]*)<\/body>/i) || [, raw])[1];
  const config = {
    productId: 1, productType: type,
    inheritMap: { referee_armbands: 'armbands', double_sided_armbands: 'armbands' },
    price: '£12.00', ajaxUrl: '/fake-ajax', nonce: 'x', uploadUrl: '', pluginAssetsUrl: '/',
    sizes: [], geometry: {}, geometryIsOwn: false, canEditGeometry: false,
    adminAjaxUrl: '', geometryNonce: '', variations: []
  };
  return `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Harness: ${type}</title>
<script>window.BespokeConfig = ${JSON.stringify(config)};</script>
${i18n ? '<script>window.BESPOKE_I18N = ' + JSON.stringify(pseudoI18n()) + ';</script>' : ''}
<script>${FETCH_STUB}</script>
</head><body style="margin:0;background:#0D0D0D;">
<div id="bespoke-customiser-root" data-product-type="${type}">
${styles}
${body}
</div>
<script>${capturePipeline()}</script>
</body></html>`;
}

http.createServer((req, res) => {
  const [pathname, query = ''] = req.url.split('?');
  const m = decodeURIComponent(pathname).match(/^\/([a-z0-9_]+)\.html$/i);
  if (!m) { res.writeHead(404); return res.end('try /armbands.html'); }
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(page(m[1], /(^|&)i18n(=|$)/.test(query)));
}).listen(port, () => console.log('customiser harness on http://localhost:' + port + '/armbands.html'));
