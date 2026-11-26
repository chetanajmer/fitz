{{--
    A gym's own dashboard colours. The whole theme reads from the --gym-* variables in app.css, so overriding
    the accent (primary) and navigation background (secondary) here re-colours buttons, active items, links,
    tints and charts. Renders nothing while the gym keeps the default Fitnix theme.
--}}
@props(['area', 'gym' => null])
@php
    $gym ??= current_gym();
    $colors = $gym?->dashboardColors($area);
@endphp
@if ($colors && $colors['custom'])
    <style>
        :root {
            --gym-orange: {{ $colors['primary'] }};
            --gym-orange-hover: {{ \App\Support\Color::mix($colors['primary'], '#000000', 0.14) }};
            --gym-orange-light: {{ \App\Support\Color::mix($colors['primary'], '#ffffff', 0.25) }};
            --gym-black-deep: {{ $colors['secondary'] }};
        }
    </style>
@endif
