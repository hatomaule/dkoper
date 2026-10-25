@props(['active'])

@php
$classes = 'dk-nav-link' . (($active ?? false) ? ' is-active' : '');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
