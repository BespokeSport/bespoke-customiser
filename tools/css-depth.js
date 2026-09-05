// Count CSS brace depth, skipping comments and quoted strings.
const fs = require('fs');
const BS = String.fromCharCode(92);   // backslash
const SQ = String.fromCharCode(39);   // '
const DQ = String.fromCharCode(34);   // "
const css = fs.readFileSync(process.argv[2], 'utf8');
let depth = 0, line = 1, inComment = false, quote = null;
const orphans = [];
for (let i = 0; i < css.length; i++) {
  const c = css[i], n = css[i + 1];
  if (c === '\n') line++;
  if (inComment) { if (c === '*' && n === '/') { inComment = false; i++; } continue; }
  if (quote) { if (c === BS) { i++; continue; } if (c === quote) quote = null; continue; }
  if (c === '/' && n === '*') { inComment = true; i++; continue; }
  if (c === SQ || c === DQ) { quote = c; continue; }
  if (c === '{') depth++;
  if (c === '}') { depth--; if (depth < 0) { orphans.push(line); depth = 0; } }
}
console.log(process.argv[2],
  '| orphan } at lines:', orphans.length ? orphans.join(', ') : 'none',
  '| unclosed blocks:', depth);
