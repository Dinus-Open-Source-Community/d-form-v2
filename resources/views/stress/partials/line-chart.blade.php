<figure class="chart" id="{{ $chart['id'] }}">
    <figcaption>{{ $chart['title'] }} — skala maks {{ number_format($chart['max'], 3) }}</figcaption>
    <svg viewBox="0 0 600 160" preserveAspectRatio="none" role="img" aria-label="{{ $chart['title'] }}">
        <rect x="0" y="0" width="600" height="160" fill="#f8fafc"></rect>
        <line x1="0" y1="160" x2="600" y2="160" stroke="#cbd5e1" stroke-width="1"></line>
        @foreach ($chart['series'] as $series)
            <polyline points="{{ $series['points'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="2" vector-effect="non-scaling-stroke"></polyline>
        @endforeach
    </svg>
    <p class="legend">
        @foreach ($chart['series'] as $series)
            <span style="color: {{ $series['color'] }}">■ {{ $series['label'] }} (akhir {{ number_format($series['last'], 3) }})</span>
        @endforeach
    </p>
    <p class="caption">{{ $chart['caption'] }}</p>
</figure>
