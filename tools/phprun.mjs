// Tiny PHP runner on php-wasm (no local PHP on this machine).
//   node phprun.mjs lint   <file.php>                 -> parse check, like `php -l`
//   node phprun.mjs render <hero.php> <out.html>      -> render [bespoke_hero_world] with WP stubs
import { loadNodeRuntime } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';
import fs from 'node:fs';

const [,, mode, file, out] = process.argv;
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
const src = fs.readFileSync(file, 'utf8');

if (mode === 'lint') {
  const code = `<?php
$src = base64_decode('${Buffer.from(src).toString('base64')}');
try { token_get_all($src, TOKEN_PARSE); echo "OK: no syntax errors"; }
catch (ParseError $e) { echo "PARSE ERROR: " . $e->getMessage() . " on line " . $e->getLine(); }`;
  const r = await php.run({ code });
  console.log(r.text);
  process.exit(r.text.startsWith('OK') ? 0 : 1);
}

if (mode === 'render') {
  php.mkdir('/plug/assets/hero-world-m');
  php.writeFile('/plug/assets/hero-world-m/f0000.avif', '');
  const stubs = `<?php
define('ABSPATH', '/');
define('BESPOKE_PLUGIN_URL', 'PLUGINURL/');
define('BESPOKE_PLUGIN_DIR', '/plug/');
function add_shortcode($a,$b){} function add_action($a,$b){}
function shortcode_atts($pairs,$atts,$sc=''){ $o=[]; foreach($pairs as $k=>$v){ $o[$k]=array_key_exists($k,(array)$atts)?$atts[$k]:$v; } return $o; }
function wp_list_pluck($l,$f){ return array_map(function($x) use($f){ return $x[$f]; }, $l); }
function wp_rand($a,$b){ return 1234; }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_url($s){ return $s; }
function wp_kses($s,$a){ return $s; } function wp_kses_post($s){ return $s; }
function wp_json_encode($v){ return json_encode($v); }
`;
  const atts = JSON.parse(process.env.HERO_ATTS || '{}');
  const code = stubs + src.replace(/^<\?php/, '') + `\necho bespoke_hero_world_shortcode(json_decode('${JSON.stringify(atts)}', true));`;
  const r = await php.run({ code });
  if (r.errors) console.error('PHP stderr:', r.errors);
  fs.writeFileSync(out, r.text);
  console.log('rendered', r.text.length, 'bytes ->', out, 'exit', r.exitCode);
}

process.exit(0); // php-wasm keeps the event loop alive otherwise
