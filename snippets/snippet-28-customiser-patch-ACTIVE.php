<?php
/**
 * Snippet ID:    28
 * Name:          BESPOKE: Patch customiser.html with dynamic designs (run once)
 * Status:        ACTIVE
 * Last modified: 2026-05-21 16:29:17
 *
 * NOTE: snippet NAME is misleading. It does not patch dynamic designs.
 * Actual behaviour: injects CSS + JS into the front-end <head> to fix
 *   - Page title colour (white on dark bg)
 *   - BEspoke logo positioning inside top-bar
 *   - Frame/screen overflow to stop duplicate preview on scroll
 *   - Hide floating BADGE/Drag-badge panel except on desktop ≥900px
 *   - Set native colour-picker <input type=color> default to the
 *     current swatch colour instead of black.
 */

add_action('wp_footer', function() {
    if (is_admin()) return;

    // ── FIX 1: Page title colour + layout issues ──────────────────────
    $css =
    // Fix 1: Page title white so it's visible on dark background
    '.entry-title{color:#ffffff!important}' .
    // Fix 2: Ensure BEspoke logo in top-bar stays visible
    '#bespoke-customiser-root .top-bar{overflow:visible!important}' .
    '#bespoke-customiser-root .top-bar .logo{position:relative!important;left:auto!important;transform:none!important}' .
    // Fix 3: Stop duplicate preview / floating badge elements on scroll
    // The frame needs to be contained so hidden screens don't show
    '#bespoke-customiser-root .frame{overflow:hidden!important}' .
    '#bespoke-customiser-root .screen{overflow:hidden!important}' .
    // Fix 4: Floating "BADGE" label and "Drag badge" text - hide when not on badge step
    '#bespoke-customiser-root .dt-panel{display:none!important}' .
    // Show dt-panel only on desktop wide screens
    '@media(min-width:900px){#bespoke-customiser-root .dt-panel{display:block!important}}';

    echo '<style>' . $css . '</style>';

    // ── FIX 5: Native colour picker defaults to current swatch colour ──────
    $js =
    'var _fixObs=new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(n){' .
        'if(n.nodeType!==1)return;' .
        'var inputs=(n.classList&&n.classList.contains("ct"))?n.querySelectorAll("input[type=color]"):n.querySelectorAll(".ct input[type=color]");' .
        'inputs.forEach(function(inp){' .
            'var ct=inp.closest?inp.closest(".ct"):null;' .
            'if(!ct)return;' .
            'var bg=ct.style.background||"";' .
            'var hex="#ff0000";' . // default red
            'var m=bg.match(/rgb[^)]+/);' .
            'if(m){' .
                'var p=m[0].replace("rgb(","").split(",");' .
                'if(p.length>=3)hex="#"+[p[0],p[1],p[2]].map(function(x){return parseInt(x).toString(16).padStart(2,"0");}).join("");' .
            '}else{var mh=bg.match(/#[0-9a-fA-F]{6}/);if(mh)hex=mh[0];}' .
            'inp.value=hex;' .
        '});' .
    '});});});' .
    '_fixObs.observe(document.body,{childList:true,subtree:true});' .
    // Fix any already existing inputs
    'setTimeout(function(){' .
        'document.querySelectorAll(".ct input[type=color]").forEach(function(inp){' .
            'var ct=inp.closest?inp.closest(".ct"):null;' .
            'if(!ct)return;' .
            'var bg=ct.style.background||"";var hex="#ff0000";' .
            'var m=bg.match(/rgb[^)]+/);' .
            'if(m){var p=m[0].replace("rgb(","").split(",");if(p.length>=3)hex="#"+[p[0],p[1],p[2]].map(function(x){return parseInt(x).toString(16).padStart(2,"0");}).join("");}' .
            'else{var mh=bg.match(/#[0-9a-fA-F]{6}/);if(mh)hex=mh[0];}' .
            'inp.value=hex;' .
        '});' .
    '},800);';

    echo '<script>' . $js . '<' . '/script>';
}, 99);
