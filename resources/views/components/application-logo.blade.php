@blaze
{{--
    NNTmux brand glyph (the tile version lives in public/assets/images/brand/).
    The N's diagonal is split into segments like a multi-part Usenet post; the green
    middle segment is the part being assembled, in tmux's status-bar green.
--}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="106 86 300 300" aria-hidden="true" focusable="false" {{ $attributes->has('class') ? $attributes : $attributes->merge(['class' => 'h-6 w-6']) }}>
    <defs>
        <linearGradient id="nntmux-glyph-inline" x1="120" y1="92" x2="392" y2="380" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#67e8f9"/>
            <stop offset=".5" stop-color="#60a5fa"/>
            <stop offset="1" stop-color="#a78bfa"/>
        </linearGradient>
    </defs>
    <g fill="url(#nntmux-glyph-inline)">
        <rect x="120" y="92" width="64" height="288" rx="6"/>
        <rect x="328" y="92" width="64" height="288" rx="6"/>
        <path d="M120 92h64l73.84 102.24h-64z"/>
        <path d="M254.16 277.76h64L392 380h-64z"/>
    </g>
    <path fill="#4ade80" d="M204.24 208.64h64l39.52 54.72h-64z"/>
</svg>
