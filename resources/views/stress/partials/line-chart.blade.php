<figure class="chart" id="{{ $chart['id'] }}">
    <figcaption>{{ $chart['title'] }}</figcaption>
    <svg viewBox="0 0 600 160" preserveAspectRatio="none" role="img" aria-label="{{ $chart['title'] }}">
        <rect x="0" y="0" width="600" height="160" fill="#f8fafc"></rect>
        <line x1="0" y1="160" x2="600" y2="160" stroke="#cbd5e1" stroke-width="1"></line>
        @if (($chart['marker'] ?? null) !== null)
            <line x1="{{ $chart['marker']['x'] }}" y1="0" x2="{{ $chart['marker']['x'] }}" y2="160"
                  stroke="#b91c1c" stroke-width="1" stroke-dasharray="4 3" vector-effect="non-scaling-stroke"></line>
            <text x="{{ $chart['marker']['x_text'] }}" y="12" fill="#b91c1c" font-size="9"
                  text-anchor="{{ $chart['marker']['anchor'] }}">{{ $chart['marker']['label'] }}</text>
        @endif
        @foreach ($chart['series'] as $series)
            <polyline points="{{ $series['points'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="2" vector-effect="non-scaling-stroke"></polyline>
        @endforeach
    </svg>
    <p class="legend">
        @foreach ($chart['series'] as $series)
            <span style="color: {{ $series['color'] }}">■ {{ $series['label'] }} (akhir {{ number_format($series['last'], 3) }}, skala {{ number_format($series['max'], 3) }})</span>
        @endforeach
    </p>
    <p class="caption">{{ $chart['caption'] }}</p>
</figure>
