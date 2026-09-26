@props(['name' => 'grid'])
@php
$paths = [
 'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 'store' => 'M3 9h18l-2-6H5L3 9z M5 9v12h14V9 M9 21v-7h6v7',
 'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 'layers' => 'm12 3 10 5-10 5L2 8l10-5z M2 12l10 5 10-5 M2 16l10 5 10-5',
 'card' => 'M3 5h18v14H3z M3 10h18 M7 15h3',
 'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
 'clock' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M12 7v5l3 2',
 'arrow' => 'M5 12h14 M13 6l6 6-6 6',
 'logout' => 'M9 21H3V3h6 M9 12h12 M16 7l5 5-5 5',
];
@endphp
<svg {{ $attributes->class(['h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
