@props(['href' => '/'])

<li>
    <a href="{{ $href }}">
        {{ $slot }}
    </a>
</li>
