@php
    $categoryIconPaths = [
        'mobile' => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
        'computers' => '<rect x="4" y="4.5" width="16" height="11" rx="1.5"/><path d="M2 19.5h20"/>',
        'tv' => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'gaming' => '<path d="M6.5 8h11a4 4 0 0 1 3.9 4.8l-.8 4a2.6 2.6 0 0 1-4.4 1.2L14.5 16h-5l-1.7 2a2.6 2.6 0 0 1-4.4-1.2l-.8-4A4 4 0 0 1 6.5 8z"/><path d="M8 11v3M6.5 12.5h3M15.5 12h.01M17.5 13.5h.01"/>',
        'photo' => '<path d="M4 7.5h3l1.5-2h7l1.5 2h3a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8.5a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
        'smart' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M9.5 15a3.5 3.5 0 0 1 5 0M11 17h2"/>',
        'small' => '<rect x="4.5" y="3" width="15" height="18" rx="2.5"/><path d="M4.5 7.5h15"/><circle cx="12" cy="14" r="4"/><path d="M10.2 14.6a2 2 0 0 0 3.6 0"/><path d="M7.5 5.2h.01M10 5.2h.01"/>',
        'kettle' => '<path d="M6.5 20h10l-1-10.5h-8z"/><path d="M8.5 9.5V8a3 3 0 0 1 6 0v1.5"/><path d="M16 12.5h1.8a1.7 1.7 0 0 1 0 3.4h-2"/><path d="M5 20h13.5"/><path d="M11.5 5V3.5"/>',
        'beauty' => '<path d="M3.5 8a4.5 4.5 0 0 1 4.5-4.5h4v9H8A4.5 4.5 0 0 1 3.5 8z"/><path d="M12 5.5h4l3.5-1.5v8L16 10.5h-4"/><path d="M7.5 12.5 9 21h2.5l-.5-8.5"/>',
        'cleaning' => '<path d="M15.5 3 10 15.5"/><path d="M14.5 3h3.5"/><path d="M5 20.5v-1.3A3.2 3.2 0 0 1 8.2 16h5.6a3.2 3.2 0 0 1 3.2 3.2v1.3z"/>',
        'scooter' => '<circle cx="5.5" cy="17.5" r="2.5"/><circle cx="18.5" cy="17.5" r="2.5"/><path d="M8 17.5h8"/><path d="M17.5 17 14.5 4.5h3"/>',
        'car' => '<path d="M5 15.5V11l2-5h10l2 5v4.5"/><path d="M3.5 15.5h17V18a1 1 0 0 1-1 1h-1.5a1 1 0 0 1-1-1v-.5H7v.5a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1z"/><path d="M5 11h14"/><path d="M7.5 13.3h.01M16.5 13.3h.01"/>',
        'tools' => '<path d="M14.7 6.3a4 4 0 0 0-5.3 5.3l-5.9 5.9a1.8 1.8 0 0 0 2.5 2.5l5.9-5.9a4 4 0 0 0 5.3-5.3l-2.5 2.5-2.4-.5-.5-2.4z"/>',
        'baby' => '<path d="M3 5h2.5l2 7"/><path d="M12 5a7 7 0 0 1 7 7H7.5"/><path d="M12 5v7"/><path d="M7.5 12 9 16h7.5l2.5-4"/><circle cx="9" cy="19" r="1.8"/><circle cx="17" cy="19" r="1.8"/>',
        'furniture' => '<path d="M5.5 11V8a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v3"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M6 18v2M18 18v2"/>',
        'security' => '<path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z"/><circle cx="12" cy="11.5" r="2.6"/><path d="M12 11.5h.01"/>',
        'kitchen' => '<circle cx="10" cy="13" r="6"/><path d="M15.4 10.4 21 7"/><path d="M7.4 13a2.6 2.6 0 0 1 2.6-2.6"/>',
        'toys' => '<circle cx="7" cy="6" r="2"/><circle cx="17" cy="6" r="2"/><circle cx="12" cy="11" r="5"/><path d="M8.6 14.6 7.5 20h9l-1.1-5.4"/><path d="M10.5 10.5h.01M13.5 10.5h.01M11 13h2"/>',
        'newyear' => '<path d="M12 3 7.5 9.5h3L6 16h12l-4.5-6.5h3z"/><path d="M12 16v4M9.5 20.5h5"/><path d="M12 3v.01"/>',
        'acc' => '<path d="M8 3v5M16 3v5"/><rect x="5" y="8" width="14" height="6" rx="2"/><path d="M12 14v3a3 3 0 0 1-3 3"/>',
        'headphones' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/>',
        'default' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="3.25"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"/>',
    ];
@endphp
<svg class="{{ $class ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke ?? 1 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $categoryIconPaths[$icon ?? 'default'] ?? $categoryIconPaths['default'] !!}</svg>
