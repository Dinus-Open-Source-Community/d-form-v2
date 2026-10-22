<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stress report — {{ $runDir }}</title>
<style>
    :root { color-scheme: light; }
    body { margin: 0 auto; max-width: 1040px; padding: 24px; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #0f172a; background: #ffffff; line-height: 1.5; }
    h1 { font-size: 1.6rem; margin: 0 0 4px; }
    h2 { font-size: 1.2rem; margin: 0 0 12px; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px; }
    h3 { font-size: 1rem; margin: 18px 0 8px; }
    section { margin: 28px 0; }
    .sub { color: #475569; margin: 0 0 12px; }
    .flags { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.85rem; background: #f1f5f9; padding: 10px 12px; border-radius: 6px; }
    .flags span { white-space: nowrap; margin-right: 14px; display: inline-block; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin: 8px 0 4px; }
    th, td { text-align: left; border-bottom: 1px solid #e2e8f0; padding: 6px 8px; vertical-align: top; }
    thead th { background: #f8fafc; font-weight: 600; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    code { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.8rem; word-break: break-all; }
    pre { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.72rem; white-space: pre-wrap; word-break: break-word; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; margin: 6px 0 0; max-height: 320px; overflow: auto; }
    details summary { cursor: pointer; font-size: 0.78rem; color: #1d4ed8; }
    figure { margin: 12px 0 0; }
    figcaption { font-size: 0.85rem; color: #334155; margin-bottom: 6px; }
    svg { width: 100%; height: auto; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; }
    .legend span { margin-right: 14px; font-size: 0.8rem; }
    .caption { font-size: 0.78rem; color: #64748b; margin: 6px 0 0; }
    .empty { font-size: 0.85rem; color: #64748b; font-style: italic; }
    .critical { color: #b91c1c; }
    .warning { color: #b45309; }
    .info { color: #1d4ed8; }
    ul.findings { padding-left: 20px; }
    ul.findings li { margin: 6px 0; }
    .badge { display: inline-block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; padding: 1px 6px; border-radius: 4px; border: 1px solid currentColor; margin-right: 6px; }
</style>
</head>
<body>

<header id="header">
    <h1>Laporan load test</h1>
    <p class="sub">
        commit <code>{{ $meta['git_commit'] ?? '?' }}</code> ·
        {{ $meta['started_at'] ?? '?' }} → {{ $meta['finished_at'] ?? '?' }} ·
        dibuat {{ $generatedAt }}
    </p>
    <p class="flags">
        <span>target_rps={{ $meta['target_rps'] ?? '?' }}</span>
        <span>profile={{ $meta['profile'] ?? '?' }}</span>
        <span>duration={{ $meta['duration'] ?? '?' }}</span>
        <span>workers={{ $meta['workers'] ?? '?' }}</span>
        <span>bypass={{ ($meta['bypass_throttle'] ?? false) ? 'true' : 'false' }}</span>
        <span>generator_bound={{ ($meta['generator_bound'] ?? false) ? 'true' : 'false' }}</span>
        <span>dataset_rows={{ number_format((int) ($meta['dataset_rows'] ?? 0)) }}</span>
        <span>query_capture={{ $meta['query_capture'] ?? '?' }}</span>
    </p>
</header>

<section id="capacity">
    <h2>1. Kapasitas</h2>
    <table>
        <tbody>
            <tr><th>Target rps</th><td class="num">{{ number_format($capacity['target_rps']) }}</td></tr>
            <tr><th>RPS tercapai</th><td class="num">{{ number_format($capacity['rate'], 2) }}</td></tr>
            <tr><th>Total request</th><td class="num">{{ number_format($capacity['requests']) }}</td></tr>
            <tr><th>Latency avg / p95 / max (ms)</th><td class="num">{{ number_format($capacity['avg_ms'], 2) }} / {{ number_format($capacity['p95_ms'], 2) }} / {{ number_format($capacity['max_ms'], 2) }}</td></tr>
            <tr><th>Error 5xx</th><td class="num">{{ number_format($capacity['error_rate'] * 100, 2) }}%</td></tr>
            <tr><th>429 (rate limiter)</th><td class="num">{{ number_format($capacity['throttled_rate'] * 100, 2) }}%</td></tr>
            <tr><th>Gap ke {{ number_format($thresholds['capacity_target_rps']) }} rps</th><td class="num">{{ number_format($capacity['gap_to_target'], 2) }} rps</td></tr>
            <tr>
                <th>Titik patah (agregat)</th>
                <td class="num">
                    @if ($capacity['break_point'])
                        <strong class="critical">tercapai</strong> (p95 &gt; {{ number_format($thresholds['break_p95_ms']) }} ms atau 5xx &gt; {{ number_format($thresholds['break_error_pct'], 0) }}%)
                    @else
                        belum tercapai (p95 ≤ {{ number_format($thresholds['break_p95_ms']) }} ms dan 5xx ≤ {{ number_format($thresholds['break_error_pct'], 0) }}%)
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <p class="caption">
        Sumber kurva: <code>{{ $capacitySource }}</code> —
        @if ($capacitySource === 'stream')
            rps, p95, gagal, dan 429 dihitung per detik dari Point <code>http_req_duration</code> bertag endpoint (trafik login tanpa tag tidak dihitung).
        @elseif ($capacitySource === 'empty_or_invalid')
            <code>stream.ndjson</code> ada tetapi tidak berisi Point <code>http_req_duration</code> yang valid — kurva kapasitas tidak tersedia; grafik di bawah hanya proksi CPU per sample.
        @else
            <code>stream.ndjson</code> tidak ada — kurva kapasitas (rps/p95/error per detik) tidak tersedia; grafik di bawah hanya proksi CPU per sample.
        @endif
    </p>

    @if ($streamBreak !== null && $streamBreak['found'])
        <p class="critical">
            <strong>titik patah: t={{ $streamBreak['elapsed_s'] }}s (rps={{ number_format($streamBreak['rps']) }}, p95={{ $streamBreak['p95_ms'] === null ? '—' : number_format($streamBreak['p95_ms'], 2) }} ms, gagal={{ $streamBreak['error_pct'] === null ? '—' : number_format($streamBreak['error_pct'], 2) }}%)</strong>
        </p>
    @elseif ($streamBreak !== null)
        <p class="caption">
            tidak ada bucket melewati ambang (p95 &gt; {{ number_format($thresholds['break_p95_ms']) }} ms atau gagal &gt; {{ number_format($thresholds['break_error_pct'], 0) }}%).
        </p>
    @endif

    @include('stress.partials.line-chart', ['chart' => $capacityChart])
</section>

<section id="endpoints">
    <h2>2. Top endpoint</h2>
    <p class="caption">Total waktu = avg × jumlah request. Nama asli dan URL dipetakan dari <code>targets.json</code>; tanpa berkas itu nama tersanitasi ditampilkan apa adanya.</p>
    @if ($endpointTables === [] || $endpointTables[0]['rows'] === [])
        <p class="empty">Tidak ada metrik <code>ep_dur_*</code> di summary.json.</p>
    @else
        @foreach ($endpointTables as $table)
            <h3>{{ $table['title'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>URL</th>
                        <th class="num">Status</th>
                        <th class="num">Request</th>
                        <th class="num">avg ms</th>
                        <th class="num">p95 ms</th>
                        <th class="num">max ms</th>
                        <th class="num">Total ms</th>
                        <th class="num">5xx</th>
                        <th class="num">429</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table['rows'] as $endpoint)
                        <tr>
                            <td><code>{{ $endpoint['name'] }}</code></td>
                            <td><code>{{ $endpoint['url'] ?? '—' }}</code></td>
                            <td class="num">{{ $endpoint['status'] === null ? '—' : $endpoint['status'] }}</td>
                            <td class="num">{{ number_format($endpoint['requests']) }}</td>
                            <td class="num">{{ number_format($endpoint['avg_ms'], 2) }}</td>
                            <td class="num">{{ number_format($endpoint['p95_ms'], 2) }}</td>
                            <td class="num">{{ number_format($endpoint['max_ms'], 2) }}</td>
                            <td class="num">{{ number_format($endpoint['total_ms'], 2) }}</td>
                            <td class="num">{{ number_format($endpoint['error_rate'] * 100, 2) }}%</td>
                            <td class="num">{{ number_format($endpoint['throttled_rate'] * 100, 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</section>

<section id="queries">
    <h2>3. Top query</h2>
    <p class="caption">
        {{ number_format($queryTotals['calls']) }} panggilan ·
        {{ number_format($queryTotals['total_ms'], 2) }} ms total waktu query.
        Urut total waktu terbesar; full scan = <code>no_index_used</code> &gt; 0.
    </p>
    @if ($digests === [])
        <p class="empty">Tidak ada delta digest pada run ini (queries.json kosong).</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Digest</th>
                    <th class="num">Total ms</th>
                    <th class="num">Porsi</th>
                    <th class="num">Calls</th>
                    <th class="num">avg ms</th>
                    <th class="num">Rows examined</th>
                    <th class="num">Rows sent</th>
                    <th class="num">Rasio</th>
                    <th>Indeks</th>
                    <th>EXPLAIN</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($digests as $digest)
                    <tr>
                        <td><code>{{ $digest['text'] !== '' ? $digest['text'] : $digest['digest'] }}</code></td>
                        <td class="num">{{ number_format($digest['total_ms'], 2) }}</td>
                        <td class="num">{{ number_format($digest['share_pct'], 2) }}%</td>
                        <td class="num">{{ number_format($digest['calls']) }}</td>
                        <td class="num">{{ number_format($digest['avg_ms'], 2) }}</td>
                        <td class="num">{{ number_format($digest['rows_examined']) }}</td>
                        <td class="num">{{ number_format($digest['rows_sent']) }}</td>
                        <td class="num">{{ $digest['examined_per_sent'] === null ? '—' : number_format($digest['examined_per_sent'], 2) }}</td>
                        <td>
                            @if ($digest['full_scan'])
                                <strong class="critical">full scan</strong>
                            @else
                                terpakai
                            @endif
                        </td>
                        <td>
                            @if ($digest['explain'] !== null)
                                @if ($digest['explain_summary'] !== null)
                                    <code>{{ $digest['explain_summary'] }}</code>
                                @else
                                    <code>plan tanpa node table</code>
                                @endif
                                <details>
                                    <summary>plan JSON</summary>
                                    <pre>{{ $digest['explain_json'] }}</pre>
                                </details>
                            @elseif ($digest['explain_error'] !== null)
                                <code>{{ $digest['explain_error'] }}</code>
                            @else
                                {{ $digest['explain_status'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<section id="resources">
    <h2>4. Resource (app vs db)</h2>
    <p class="caption">
        CPU core = delta <code>usage_usec</code> ÷ detik. {{ number_format($resources['samples']) }} sample.
        Sel CSV kosong dihitung null, bukan 0; statistik hanya memakai delta valid (tanpa forward-fill).
    </p>
    <table>
        <thead>
            <tr>
                <th>Container</th>
                <th class="num">avg core</th>
                <th class="num">p95 core</th>
                <th class="num">max core</th>
                <th class="num">RAM current (MiB)</th>
                <th class="num">RAM peak (MiB)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th>app</th>
                <td class="num">{{ number_format($resources['app']['avg'], 3) }}</td>
                <td class="num">{{ number_format($resources['app']['p95'], 3) }}</td>
                <td class="num">{{ number_format($resources['app']['max'], 3) }}</td>
                <td class="num">{{ $resources['ram_app_current_mib'] === null ? '—' : number_format($resources['ram_app_current_mib'], 1) }}</td>
                <td class="num">{{ $resources['ram_app_peak_mib'] === null ? '—' : number_format($resources['ram_app_peak_mib'], 1) }}</td>
            </tr>
            <tr>
                <th>db</th>
                <td class="num">{{ number_format($resources['db']['avg'], 3) }}</td>
                <td class="num">{{ number_format($resources['db']['p95'], 3) }}</td>
                <td class="num">{{ number_format($resources['db']['max'], 3) }}</td>
                <td class="num">{{ $resources['ram_db_current_mib'] === null ? '—' : number_format($resources['ram_db_current_mib'], 1) }}</td>
                <td class="num">{{ $resources['ram_db_peak_mib'] === null ? '—' : number_format($resources['ram_db_peak_mib'], 1) }}</td>
            </tr>
        </tbody>
    </table>
    <table>
        <tbody>
            <tr><th>CPU app ter-throttle</th><td class="num">{{ number_format($resources['throttled_usec'] / 1000, 2) }} ms</td></tr>
            <tr><th>Rasio CPU PHP : MySQL</th><td class="num">{{ $resources['php_db_ratio'] === null ? '—' : number_format($resources['php_db_ratio'], 2) }}</td></tr>
            <tr><th>RAM app per 1.000 request</th><td class="num">{{ $resources['ram_app_per_1k_requests_mib'] === null ? '—' : number_format($resources['ram_app_per_1k_requests_mib'], 2) }} MiB</td></tr>
        </tbody>
    </table>

    @include('stress.partials.line-chart', ['chart' => $resourceChart])
</section>

<section id="volume">
    <h2>5. Sensitivitas volume data</h2>
    <p class="caption">
        Dataset {{ number_format($volume['dataset_rows']) }} baris per tabel domain.
        Hanya digest dengan rasio <code>rows_examined / rows_sent ≥ 10</code> yang ditampilkan.
    </p>
    @if ($volume['rows'] === [])
        <p class="empty">Tidak ada digest dengan rasio pemeriksaan tinggi pada run ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Digest</th>
                    <th class="num">Rows examined</th>
                    <th class="num">Rows sent</th>
                    <th class="num">Rasio</th>
                    <th>Indeks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($volume['rows'] as $row)
                    <tr>
                        <td><code>{{ $row['text'] !== '' ? $row['text'] : $row['digest'] }}</code></td>
                        <td class="num">{{ number_format($row['rows_examined']) }}</td>
                        <td class="num">{{ number_format($row['rows_sent']) }}</td>
                        <td class="num">{{ $row['examined_per_sent'] === null ? '—' : number_format($row['examined_per_sent'], 2) }}</td>
                        <td>{{ $row['full_scan'] ? 'full scan' : 'terpakai' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p class="caption">
        Batasan: run ini hanya memakai satu dataset. Pengaruh volume dinyatakan lewat rasio baris diperiksa,
        bukan perbandingan dua ukuran dataset.
    </p>
</section>

<section id="findings">
    <h2>6. Temuan &amp; rekomendasi</h2>
    <ul class="findings">
        @foreach ($findings as $finding)
            <li class="{{ $finding['level'] }}">
                <span class="badge">{{ $finding['level'] }}</span>{{ $finding['text'] }}
            </li>
        @endforeach
    </ul>
</section>

<section id="method">
    <h2>7. Metode dan batasan</h2>
    <ul>
        <li>Beban dibuat k6 dengan <code>constant-arrival-rate</code>, satu metrik per endpoint (<code>ep_dur_*</code>, <code>ep_reqs_*</code>, <code>ep_err_*</code>, <code>ep_429_*</code>) karena summary k6 bersifat agregat.</li>
        <li>Query diambil dari <code>performance_schema.events_statements_summary_by_digest</code> sebelum dan sesudah beban; delta counter dipakai, bukan angka absolut.</li>
        <li>Resource dibaca dari cgroup tiap container lewat sampler 8 kolom. <code>usage_usec</code> dan <code>throttled_usec</code> kumulatif, jadi laporan memakai delta antar baris.</li>
        <li>Generator dan aplikasi berbagi satu host: saat CPU host jenuh, hasil ditandai <code>generator_bound</code> dan tidak boleh disimpulkan sebagai batas aplikasi.</li>
        <li>Dataset stress deterministik, bukan pola akses pengguna nyata; tidak ada hot-key sesi.</li>
        <li>Rate limiter hanya dilewati di non-production lewat flag bypass; tanpa flag itu perilaku produksi tidak berubah.</li>
        <li><strong>Kurva kapasitas hanya tersedia bila run menulis <code>stream.ndjson</code></strong> (k6 dijalankan dengan <code>--out json=&lt;run&gt;/stream.ndjson</code>). Tanpa berkas itu report hanya menampilkan proksi CPU.</li>
        @if ($capacitySource === 'stream')
            <li>Kurva kapasitas dihitung dari <code>stream.ndjson</code> k6 secara streaming: rps = jumlah Point <code>http_req_duration</code> bertag endpoint per detik (login tanpa tag tidak dihitung), p95 = nearest-rank nilai pada detik itu, gagal = persentase status kosong/0/≥500, 429 dipisah. Bucket difinalisasi saat detik berganti sehingga memori tidak menumpuk. Detik tanpa request diisi bucket rps 0 (gap &gt; {{ number_format($thresholds['max_filled_gap_seconds']) }} detik hanya ditandai satu bucket jeda). CPU app di-join ke sample terdekat maksimum 2× interval sampler; di luar itu garis diputus.</li>
        @elseif ($capacitySource === 'empty_or_invalid')
            <li><code>stream.ndjson</code> ada tetapi tidak berisi Point <code>http_req_duration</code> yang valid, sehingga kurva kapasitas tidak dapat dihitung; grafik kapasitas hanya proksi CPU per sample.</li>
        @else
            <li><code>stream.ndjson</code> tidak ada, sehingga kurva kapasitas (rps/p95/error per detik) tidak tersedia; grafik kapasitas hanya proksi CPU per sample dan tidak boleh dibaca sebagai kurva kapasitas.</li>
        @endif
    </ul>
</section>

</body>
</html>
