@props(['active'])

@php
$classes = 'dk-mobile-link' . (($active ?? false) ? ' is-active' : '');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
