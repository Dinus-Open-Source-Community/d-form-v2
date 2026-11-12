<?php

namespace App\Console\Commands\Stress;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Merakit report.html self-contained dari artefak satu run stress.
 *
 * Dipakai harness stress sebagai langkah terakhir: membaca summary.json,
 * baseline.json, queries.json, resources.csv, dan run-meta.json di satu
 * direktori run, lalu menulis report.html tanpa aset eksternal (grafik SVG
 * inline, tanpa JavaScript).
 */
final class ReportCommand extends Command
{
    /** Artefak wajib; nama dipakai apa adanya di pesan error. */
    private const REQUIRED_ARTIFACTS = ['summary.json', 'baseline.json', 'queries.json', 'resources.csv', 'run-meta.json'];

    /** Ambang titik patah sesuai spec: p95 > 2 s atau 5xx > 5%. */
    private const BREAK_P95_MS = 2000.0;

    private const BREAK_ERROR_RATE = 0.05;

    /** Target kapasitas yang gap-nya dilaporkan (spec §10). */
    private const CAPACITY_TARGET_RPS = 10000;

    /** Detik kosong diisi maksimum sebanyak ini; gap lebih panjang hanya ditandai satu bucket jeda. */
    private const MAX_FILLED_GAP_SECONDS = 60;

    /** Lebar dan tinggi viewBox grafik SVG. */
    private const CHART_WIDTH = 600;

    private const CHART_HEIGHT = 160;

    protected $signature = 'stress:report
        {run : Direktori run berisi artefak stress}
        {--out= : Path report.html (default <run>/report.html)}';

    protected $description = 'Rakit report.html self-contained dari artefak satu run stress';

    /**
     * Baca kelima artefak run, hitung agregat, lalu tulis report.html.
     */
    public function handle(): int
    {
        $runDir = rtrim((string) $this->argument('run'), '/');

        if (! is_dir($runDir)) {
            $this->error('Direktori run tidak ditemukan: '.$runDir);

            return self::FAILURE;
        }

        $missing = array_values(array_filter(
            self::REQUIRED_ARTIFACTS,
            static fn (string $name): bool => ! is_file($runDir.'/'.$name),
        ));

        if ($missing !== []) {
            $this->error('Artefak wajib tidak ada di '.$runDir.': '.implode(', ', $missing));

            return self::FAILURE;
        }

        $meta = $this->readJson($runDir.'/run-meta.json');
        $summary = $this->readJson($runDir.'/summary.json');
        $baseline = $this->readJson($runDir.'/baseline.json');
        $queries = $this->readJson($runDir.'/queries.json');

        if ($meta === null || $summary === null || $baseline === null || $queries === null) {
            return self::FAILURE;
        }

        $targets = is_file($runDir.'/targets.json') ? $this->readJson($runDir.'/targets.json', false) : null;
        $samples = $this->readResourceSamples($runDir.'/resources.csv');
        $stream = $this->capacityStream($runDir.'/stream.ndjson');
        $buckets = $stream['buckets'];
        $capacity = $this->capacitySummary($summary, $meta);
        $endpoints = $this->endpointRows([
            'summary' => $summary,
            'baseline' => $baseline,
            'targets' => $targets,
        ]);
        $digests = $this->digestRows($queries);
        $resources = $this->resourceSummary([
            'samples' => $samples,
            'requests' => $capacity['requests'],
        ]);
        $streamBreak = $this->breakPoint($buckets);

        $html = view('stress.report', [
            'runDir' => $runDir,
            'generatedAt' => now()->toIso8601String(),
            'meta' => $meta,
            'capacity' => $capacity,
            'capacityChart' => $this->capacityChart(['samples' => $samples, 'buckets' => $buckets]),
            'capacitySource' => $stream['state'],
            'streamBreak' => $streamBreak,
            'endpointTables' => [
                ['title' => 'Urut p95 tertinggi', 'rows' => $endpoints],
                ['title' => 'Urut total waktu terbesar', 'rows' => $this->sortByTotalMs($endpoints)],
            ],
            'thresholds' => [
                'capacity_target_rps' => self::CAPACITY_TARGET_RPS,
                'break_p95_ms' => self::BREAK_P95_MS,
                'break_error_pct' => self::BREAK_ERROR_RATE * 100.0,
                'max_filled_gap_seconds' => self::MAX_FILLED_GAP_SECONDS,
            ],
            'digests' => $digests,
            'queryTotals' => [
                'calls' => (int) ($queries['totals']['calls'] ?? 0),
                'total_ms' => $this->numberOr($queries['totals']['total_ms'] ?? null, 0.0),
            ],
            'resources' => $resources,
            'resourceChart' => $this->resourceChart($samples),
            'volume' => $this->volumeRows(['digests' => $digests, 'meta' => $meta]),
            'findings' => $this->findings([
                'meta' => $meta,
                'capacity' => $capacity,
                'endpoints' => $endpoints,
                'digests' => $digests,
                'resources' => $resources,
            ]),
        ])->render();

        $outPath = $this->outPath($runDir);

        if (File::put($outPath, $html) === false) {
            $this->error('Gagal menulis report: '.$outPath);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Report %d endpoint · %d digest · %s ditulis ke %s',
            count($endpoints),
            count($digests),
            $this->breakLabel($capacity, $streamBreak),
            $outPath,
        ));

        return self::SUCCESS;
    }

    /**
     * Label status titik patah untuk output terminal: stream dulu, lalu agregat.
     *
     * @param  array<string,mixed>  $capacity
     * @param  array{found:bool,elapsed_s:int,rps:int,p95_ms:float|null,error_pct:float|null}|null  $streamBreak
     */
    private function breakLabel(array $capacity, ?array $streamBreak): string
    {
        if ($streamBreak !== null && $streamBreak['found']) {
            return 'titik patah t='.$streamBreak['elapsed_s'].'s';
        }

        return $capacity['break_point'] ? 'titik patah agregat' : 'tanpa titik patah';
    }

    /**
     * Ringkas kapasitas global: rps, latency, error, gap ke target, titik patah.
     *
     * @param  array<string,mixed>  $summary
     * @param  array<string,mixed>  $meta
     * @return array{requests:int,rate:float,target_rps:int,avg_ms:float,p95_ms:float,max_ms:float,error_rate:float,throttled_rate:float,gap_to_target:float,break_point:bool}
     */
    private function capacitySummary(array $summary, array $meta): array
    {
        $requests = (int) ($this->metricValues($summary, 'http_reqs')['count'] ?? 0);
        $rate = $this->numberOr($this->metricValues($summary, 'http_reqs')['rate'] ?? null, 0.0);

        if ($rate <= 0.0 && $requests > 0) {
            $window = $this->runWindowSeconds($meta);
            $rate = $window > 0 ? $requests / $window : 0.0;
        }

        $latency = $this->metricValues($summary, 'http_req_duration');
        $errorRate = $this->numberOr($this->metricValues($summary, 'http_5xx_rate')['rate'] ?? null, 0.0);
        $p95 = $this->numberOr($latency['p(95)'] ?? null, 0.0);

        return [
            'requests' => $requests,
            'rate' => $rate,
            'target_rps' => (int) ($meta['target_rps'] ?? 0),
            'avg_ms' => $this->numberOr($latency['avg'] ?? null, 0.0),
            'p95_ms' => $p95,
            'max_ms' => $this->numberOr($latency['max'] ?? null, 0.0),
            'error_rate' => $errorRate,
            'throttled_rate' => $this->numberOr($this->metricValues($summary, 'http_429_rate')['rate'] ?? null, 0.0),
            'gap_to_target' => max(0.0, self::CAPACITY_TARGET_RPS - $rate),
            'break_point' => $p95 > self::BREAK_P95_MS || $errorRate > self::BREAK_ERROR_RATE,
        ];
    }

    /**
     * Susun tabel per-endpoint dari metrik ep_* dan peta balik ke target asli.
     *
     * @param  array{summary:array<string,mixed>,baseline:array<string,mixed>,targets:array<string,mixed>|null}  $inputs
     * @return list<array{metric:string,name:string,url:string|null,status:int|null,requests:int,avg_ms:float,p95_ms:float,max_ms:float,total_ms:float,error_rate:float,throttled_rate:float}>
     */
    private function endpointRows(array $inputs): array
    {
        $summary = $inputs['summary'];
        $baseline = $inputs['baseline'];
        $targets = $this->targetMap($inputs['targets']);
        $metrics = is_array($summary['metrics'] ?? null) ? $summary['metrics'] : [];

        $rows = [];

        foreach ($metrics as $metricName => $metric) {
            if (! is_string($metricName) || ! str_starts_with($metricName, 'ep_dur_')) {
                continue;
            }

            $suffix = substr($metricName, strlen('ep_dur_'));
            $values = is_array($metric['values'] ?? null) ? $metric['values'] : [];
            $requests = (int) ($this->metricValues($summary, 'ep_reqs_'.$suffix)['count'] ?? 0);
            $average = $this->numberOr($values['avg'] ?? null, 0.0);
            $target = $targets[$suffix] ?? null;
            $baselineName = $target['name'] ?? $suffix;
            $status = $baseline[$baselineName]['status'] ?? null;

            $rows[] = [
                'metric' => $suffix,
                'name' => $baselineName,
                'url' => $target['url'] ?? null,
                'status' => is_int($status) ? $status : null,
                'requests' => $requests,
                'avg_ms' => $average,
                'p95_ms' => $this->numberOr($values['p(95)'] ?? null, 0.0),
                'max_ms' => $this->numberOr($values['max'] ?? null, 0.0),
                'total_ms' => $average * $requests,
                'error_rate' => $this->numberOr($this->metricValues($summary, 'ep_err_'.$suffix)['rate'] ?? null, 0.0),
                'throttled_rate' => $this->numberOr($this->metricValues($summary, 'ep_429_'.$suffix)['rate'] ?? null, 0.0),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['p95_ms'] <=> $left['p95_ms']);

        return $rows;
    }

    /**
     * Urut ulang baris endpoint dari total waktu terbesar (urutan kedua di laporan).
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function sortByTotalMs(array $rows): array
    {
        usort($rows, static fn (array $left, array $right): int => $right['total_ms'] <=> $left['total_ms']);

        return $rows;
    }

    /**
     * Peta nama endpoint tersanitasi → nama dan URL asli dari targets.json.
     *
     * @param  array<string,mixed>|null  $targets
     * @return array<string,array{name:string,url:string|null}>
     */
    private function targetMap(?array $targets): array
    {
        $map = [];

        foreach (is_array($targets['targets'] ?? null) ? $targets['targets'] : [] as $target) {
            if (! is_array($target) || ! is_string($target['name'] ?? null)) {
                continue;
            }

            $map[$this->sanitiseName($target['name'])] = [
                'name' => $target['name'],
                'url' => is_string($target['url'] ?? null) ? $target['url'] : null,
            ];
        }

        return $map;
    }

    /**
     * Samakan aturan sanitasi nama dengan k6: alfanumerik/underscore saja.
     */
    private function sanitiseName(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '_', $name);
    }

    /**
     * Susun tabel digest query terurut dari total waktu terbesar.
     *
     * @param  array<string,mixed>  $queries
     * @return list<array{digest:string,text:string,calls:int,total_ms:float,avg_ms:float,rows_examined:int,rows_sent:int,examined_per_sent:float|null,no_index_used:int,full_scan:bool,share_pct:float,explain_status:string,explain:array<mixed>|null,explain_summary:string|null,explain_json:string|null,explain_error:string|null}>
     */
    private function digestRows(array $queries): array
    {
        $totalMs = $this->numberOr($queries['totals']['total_ms'] ?? null, 0.0);
        $rows = [];

        foreach (is_array($queries['deltas'] ?? null) ? $queries['deltas'] : [] as $delta) {
            if (! is_array($delta)) {
                continue;
            }

            $rowTotal = $this->numberOr($delta['total_ms'] ?? null, 0.0);
            $examined = (int) ($delta['rows_examined'] ?? 0);
            $sent = (int) ($delta['rows_sent'] ?? 0);
            $noIndex = (int) ($delta['no_index_used'] ?? 0);
            $explain = $delta['explain'] ?? null;
            $explainError = is_string($delta['explain_error'] ?? null) ? $delta['explain_error'] : null;

            $rows[] = [
                'digest' => (string) ($delta['digest'] ?? ''),
                'text' => (string) ($delta['digest_text'] ?? ''),
                'calls' => (int) ($delta['calls'] ?? 0),
                'total_ms' => $rowTotal,
                'avg_ms' => $this->numberOr($delta['avg_ms'] ?? null, 0.0),
                'rows_examined' => $examined,
                'rows_sent' => $sent,
                'examined_per_sent' => $sent > 0 ? $examined / $sent : null,
                'no_index_used' => $noIndex,
                'full_scan' => $noIndex > 0,
                'share_pct' => $totalMs > 0 ? $rowTotal / $totalMs * 100.0 : 0.0,
                'explain_status' => is_array($explain) ? 'ok' : ($explainError === null ? 'tidak dijalankan' : 'gagal'),
                'explain' => is_array($explain) ? $explain : null,
                'explain_summary' => is_array($explain) ? $this->explainSummary($explain) : null,
                'explain_json' => is_array($explain) ? $this->prettyJson($explain) : null,
                'explain_error' => $explainError,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['total_ms'] <=> $left['total_ms']);

        return $rows;
    }

    /**
     * Ringkasan EXPLAIN yang bisa dibaca: node table pertama beserta akses dan indeksnya.
     *
     * @param  array<mixed>  $explain
     */
    private function explainSummary(array $explain): ?string
    {
        $table = $this->findExplainTable($explain);

        if ($table === null) {
            return null;
        }

        $name = is_string($table['table_name'] ?? null) ? $table['table_name'] : '—';
        $access = is_string($table['access_type'] ?? null) ? $table['access_type'] : '—';
        $key = is_string($table['key'] ?? null) ? $table['key'] : '—';
        $rows = $table['rows_examined_per_scan'] ?? $table['rows'] ?? null;
        $filtered = $table['filtered'] ?? null;

        return sprintf(
            'table=%s · access_type=%s · key=%s · rows_examined_per_scan=%s · filtered=%s',
            $name,
            $access,
            $key,
            is_numeric($rows) ? number_format((float) $rows) : '—',
            is_numeric($filtered) ? number_format((float) $filtered, 2) : '—',
        );
    }

    /**
     * Cari node table pertama di dalam plan EXPLAIN (bentuknya bisa bersarang).
     *
     * @param  array<mixed>  $node
     * @return array<string,mixed>|null
     */
    private function findExplainTable(array $node): ?array
    {
        if (is_string($node['table_name'] ?? null)) {
            return $node;
        }

        foreach ($node as $child) {
            if (! is_array($child)) {
                continue;
            }

            $found = $this->findExplainTable($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * JSON plan terformat untuk blok <details>; selalu string agar aman di-escape Blade.
     *
     * @param  array<mixed>  $payload
     */
    private function prettyJson(array $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    /**
     * Ringkas pemakaian CPU/RAM/throttling app vs db dari sample cgroup.
     *
     * @param  array{samples:list<array<string,int|null>>,requests:int}  $inputs
     * @return array{app:array{count:int,avg:float,p95:float,max:float},db:array{count:int,avg:float,p95:float,max:float},throttled_usec:int,ram_app_current_mib:float|null,ram_db_current_mib:float|null,ram_app_peak_mib:float|null,ram_db_peak_mib:float|null,php_db_ratio:float|null,ram_app_per_1k_requests_mib:float|null,samples:int}
     */
    private function resourceSummary(array $inputs): array
    {
        $samples = $inputs['samples'];
        $requests = (int) $inputs['requests'];

        // Statistik hanya memakai delta valid; forward-fill khusus untuk menggambar garis.
        $appDeltas = $this->validNumbers($this->deltaRateSeries($samples, 'app_usage_usec'));
        $dbDeltas = $this->validNumbers($this->deltaRateSeries($samples, 'db_usage_usec'));

        $appCpu = array_sum($appDeltas);
        $dbCpu = array_sum($dbDeltas);
        $appPeakMiB = $this->memoryMaxMiB($samples, 'app_mem_peak');
        $ramPerThousand = $appPeakMiB !== null && $requests > 0 ? $appPeakMiB * 1000 / $requests : null;

        return [
            'app' => $this->statsFor($appDeltas),
            'db' => $this->statsFor($dbDeltas),
            'throttled_usec' => $this->deltaSum($samples, 'app_throttled_usec'),
            'ram_app_current_mib' => $this->memoryLastMiB($samples, 'app_mem_current'),
            'ram_db_current_mib' => $this->memoryLastMiB($samples, 'db_mem_current'),
            'ram_app_peak_mib' => $appPeakMiB,
            'ram_db_peak_mib' => $this->memoryMaxMiB($samples, 'db_mem_peak'),
            'php_db_ratio' => $dbCpu > 0 ? $appCpu / $dbCpu : null,
            'ram_app_per_1k_requests_mib' => $ramPerThousand,
            'samples' => count($samples),
        ];
    }

    /**
     * Deret CPU core per sample dari counter usage_usec kumulatif.
     *
     * @param  list<array<string,int|null>>  $samples
     * @return list<float|null>
     */
    private function deltaRateSeries(array $samples, string $key): array
    {
        $series = [];
        $previous = null;

        foreach ($samples as $sample) {
            $value = $sample[$key] ?? null;

            if ($value === null || $previous === null || $previous[$key] === null
                || $sample['ts'] === null || $previous['ts'] === null) {
                $series[] = null;
            } else {
                $seconds = max(0.001, (float) $sample['ts'] - (float) $previous['ts']);
                $series[] = max(0.0, ($value - $previous[$key]) / 1e6 / $seconds);
            }

            if ($value !== null) {
                $previous = $sample;
            }
        }

        return $series;
    }

    /**
     * Total delta counter kumulatif antar sample (mis. throttled_usec).
     *
     * @param  list<array<string,int|null>>  $samples
     */
    private function deltaSum(array $samples, string $key): int
    {
        $total = 0;
        $previous = null;

        foreach ($samples as $sample) {
            $value = $sample[$key] ?? null;

            if ($value !== null && $previous !== null) {
                $total += max(0, $value - $previous);
            }

            if ($value !== null) {
                $previous = $value;
            }
        }

        return $total;
    }

    /**
     * Nilai RAM terakhir yang terbaca, dalam MiB; null bila seluruh sel kosong.
     *
     * @param  list<array<string,int|null>>  $samples
     */
    private function memoryLastMiB(array $samples, string $key): ?float
    {
        foreach (array_reverse($samples) as $sample) {
            $value = $sample[$key] ?? null;

            if ($value !== null) {
                return $value / 1048576;
            }
        }

        return null;
    }

    /**
     * Nilai RAM maksimum yang terbaca, dalam MiB; null bila seluruh sel kosong.
     *
     * @param  list<array<string,int|null>>  $samples
     */
    private function memoryMaxMiB(array $samples, string $key): ?float
    {
        $max = null;

        foreach ($samples as $sample) {
            $value = $sample[$key] ?? null;

            if ($value !== null && ($max === null || $value > $max)) {
                $max = $value;
            }
        }

        return $max === null ? null : $max / 1048576;
    }

    /**
     * Grafik kapasitas: deret waktu dari stream.ndjson bila ada, selain itu proksi CPU.
     *
     * @param  array{samples:list<array<string,int|null>>,buckets:list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>}  $inputs
     * @return array{id:string,title:string,caption:string,marker:array{x:float,x_text:float,anchor:string,label:string}|null,series:list<array{label:string,color:string,segments:list<string>,segments_count:int,segments_label:string,last:float,max:float}>}
     */
    private function capacityChart(array $inputs): array
    {
        return $inputs['buckets'] === []
            ? $this->proxyCapacityChart($inputs['samples'])
            : $this->streamCapacityChart($inputs['buckets'], $inputs['samples']);
    }

    /**
     * Grafik kapasitas per detik: rps, p95, gagal, 429, dan CPU app.
     *
     * Sumbu x memakai waktu asli, jadi detik kosong tetap terlihat sebagai jarak.
     *
     * @param  list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>  $buckets
     * @param  list<array<string,int|null>>  $samples
     * @return array{id:string,title:string,caption:string,marker:array{x:float,x_text:float,anchor:string,label:string}|null,series:list<array{label:string,color:string,segments:list<string>,segments_count:int,segments_label:string,last:float,max:float}>}
     */
    private function streamCapacityChart(array $buckets, array $samples): array
    {
        $times = array_map(static fn (array $bucket): int => $bucket['ts'], $buckets);

        $series = [
            $this->chartSeries([
                'values' => array_map(static fn (array $bucket): float => (float) $bucket['rps'], $buckets),
                'times' => $times,
                'label' => 'rps',
                'color' => '#2563eb',
            ]),
            $this->chartSeries([
                'values' => array_map(static fn (array $bucket): ?float => $bucket['p95_ms'], $buckets),
                'times' => $times,
                'label' => 'p95 ms',
                'color' => '#dc2626',
            ]),
            $this->chartSeries([
                'values' => array_map(static fn (array $bucket): ?float => $bucket['error_pct'], $buckets),
                'times' => $times,
                'label' => 'gagal %',
                'color' => '#b45309',
            ]),
            $this->chartSeries([
                'values' => array_map(static fn (array $bucket): ?float => $bucket['pct429'], $buckets),
                'times' => $times,
                'label' => '429 %',
                'color' => '#7c3aed',
            ]),
        ];

        $cpuPoints = $this->cpuPoints($samples);

        if ($cpuPoints !== []) {
            $tolerance = $this->samplerIntervalSeconds($samples) * 2;
            $cpu = [];

            foreach ($buckets as $bucket) {
                $cpu[] = $this->nearestCpuCores($cpuPoints, $bucket['ts'], $tolerance);
            }

            $series[] = $this->chartSeries([
                'values' => $cpu,
                'times' => $times,
                'label' => 'app CPU core',
                'color' => '#0f766e',
            ]);
        }

        return [
            'id' => 'capacity-curve',
            'title' => 'Kurva kapasitas per detik — rps, p95, gagal, 429, CPU app',
            'caption' => 'Dihitung dari stream.ndjson k6 (Point http_req_duration bertag endpoint; trafik login tanpa tag tidak dihitung). '
                .'rps = jumlah request per detik, p95 = nearest-rank, gagal = status kosong/0/≥500, 429 dipisah. '
                .'CPU app di-join ke sample terdekat maksimum 2× interval sampler; di luar itu garis diputus. '
                .'Tiap seri memakai skala sendiri. '.$this->streamAggregateNote($buckets),
            'marker' => $this->breakMarker($buckets),
            'series' => $series,
        ];
    }

    /**
     * Ringkasan agregat di caption: 429, gagal, tanpa status, dan jumlah detik kosong.
     *
     * @param  list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>  $buckets
     */
    private function streamAggregateNote(array $buckets): string
    {
        $totalRps = 0;
        $noStatus = 0;
        $emptyBuckets = 0;
        $failedWeighted = 0.0;
        $throttledWeighted = 0.0;

        foreach ($buckets as $bucket) {
            $totalRps += $bucket['rps'];
            $noStatus += $bucket['no_status'];

            if ($bucket['rps'] === 0) {
                $emptyBuckets++;

                continue;
            }

            $failedWeighted += ($bucket['error_pct'] ?? 0.0) * $bucket['rps'];
            $throttledWeighted += ($bucket['pct429'] ?? 0.0) * $bucket['rps'];
        }

        $failedPct = $totalRps > 0 ? $failedWeighted / $totalRps : 0.0;
        $throttledPct = $totalRps > 0 ? $throttledWeighted / $totalRps : 0.0;

        return sprintf(
            'Agregat: total=%s request, 429=%s%%, gagal=%s%%, tanpa status=%s request, detik kosong (rps 0)=%s.',
            number_format($totalRps),
            number_format($throttledPct, 2),
            number_format($failedPct, 2),
            number_format($noStatus),
            number_format($emptyBuckets),
        );
    }

    /**
     * Grafik cadangan saat stream.ndjson tidak tersedia: CPU per sample sebagai proksi beban.
     *
     * @param  list<array<string,int|null>>  $samples
     * @return array{id:string,title:string,caption:string,marker:null,series:list<array{label:string,color:string,segments:list<string>,segments_count:int,segments_label:string,last:float,max:float}>}
     */
    private function proxyCapacityChart(array $samples): array
    {
        $times = array_map(static fn (array $sample): ?int => $sample['ts'], $samples);

        return [
            'id' => 'capacity-curve',
            'title' => 'Proksi beban — CPU per sample (core)',
            'caption' => 'Kurva kapasitas (rps/p95/error per detik) tidak tersedia; grafik ini hanya proksi beban dari CPU per sample. '
                .'Gap sampler memutus garis, bukan diisi nilai sebelumnya.',
            'marker' => null,
            'series' => [
                $this->chartSeries([
                    'values' => $this->deltaRateSeries($samples, 'app_usage_usec'),
                    'times' => $times,
                    'label' => 'app CPU core',
                    'color' => '#2563eb',
                ]),
                $this->chartSeries([
                    'values' => $this->deltaRateSeries($samples, 'db_usage_usec'),
                    'times' => $times,
                    'label' => 'db CPU core',
                    'color' => '#dc2626',
                ]),
            ],
        ];
    }

    /**
     * Penanda titik patah di grafik: bucket pertama dengan p95 > ambang atau gagal > ambang.
     *
     * @param  list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>  $buckets
     * @return array{x:float,x_text:float,anchor:string,label:string}|null
     */
    private function breakMarker(array $buckets): ?array
    {
        $break = $this->breakPoint($buckets);

        if ($break === null || $break['found'] === false) {
            return null;
        }

        $first = $buckets[0]['ts'];
        $last = $buckets[count($buckets) - 1]['ts'];
        $span = max(1, $last - $first);
        $x = ($break['elapsed_s']) / $span * self::CHART_WIDTH;
        $anchor = $x > self::CHART_WIDTH - 120 ? 'end' : 'start';

        return [
            'x' => round($x, 2),
            'x_text' => round($anchor === 'end' ? $x - 4 : $x + 4, 2),
            'anchor' => $anchor,
            'label' => sprintf('t=%ds', $break['elapsed_s']),
        ];
    }

    /**
     * Detik pertama yang melewati ambang titik patah, atau status "tidak ada".
     *
     * @param  list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>  $buckets
     * @return array{found:bool,index:int,elapsed_s:int,rps:int,p95_ms:float|null,error_pct:float|null}|null
     */
    private function breakPoint(array $buckets): ?array
    {
        if ($buckets === []) {
            return null;
        }

        $first = $buckets[0]['ts'];

        foreach ($buckets as $index => $bucket) {
            $p95Breached = $bucket['p95_ms'] !== null && $bucket['p95_ms'] > self::BREAK_P95_MS;
            $errorBreached = $bucket['error_pct'] !== null && $bucket['error_pct'] > self::BREAK_ERROR_RATE * 100.0;

            if ($p95Breached || $errorBreached) {
                return [
                    'found' => true,
                    'index' => $index,
                    'elapsed_s' => $bucket['ts'] - $first,
                    'rps' => $bucket['rps'],
                    'p95_ms' => $bucket['p95_ms'],
                    'error_pct' => $bucket['error_pct'],
                ];
            }
        }

        return [
            'found' => false,
            'index' => 0,
            'elapsed_s' => 0,
            'rps' => 0,
            'p95_ms' => null,
            'error_pct' => null,
        ];
    }

    /**
     * Baca stream.ndjson k6 secara streaming, finalisasi bucket saat detik berganti.
     *
     * Hanya durasi dalam satu detik yang ditahan di memori (O(request per detik)),
     * sehingga file besar tidak menumpuk jutaan float. Hanya Point bertag endpoint
     * yang dihitung sebagai trafik kapasitas; login tanpa tag diabaikan. Stream
     * diasumsikan kronologis karena k6 menulis Point berurutan waktu.
     *
     * @return array{state:string,buckets:list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>}
     */
    private function capacityStream(string $path): array
    {
        if (! is_file($path)) {
            return ['state' => 'absent', 'buckets' => []];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['state' => 'empty_or_invalid', 'buckets' => []];
        }

        $rows = [];
        $pending = null;
        $parsedPoints = 0;

        while (($line = fgets($handle)) !== false) {
            if (! str_contains($line, 'http_req_duration')) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'Point') {
                continue;
            }

            if (($decoded['metric'] ?? null) !== 'http_req_duration') {
                continue;
            }

            $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
            $second = $this->epochSecond($data['time'] ?? null);
            $value = $data['value'] ?? null;
            $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];
            $endpoint = $tags['endpoint'] ?? null;

            if ($second === null || ! (is_int($value) || is_float($value))) {
                continue;
            }

            $parsedPoints++;

            if (! is_string($endpoint) || $endpoint === '') {
                continue;
            }

            if ($pending !== null && $second > $pending['second']) {
                $rows[] = $this->finaliseBucket($pending);
                $pending = null;
            }

            $pending ??= ['second' => $second, 'durations' => [], 'total' => 0, 'failed' => 0, 'no_status' => 0, 'err429' => 0];
            $pending['durations'][] = (float) $value;
            $pending['total']++;

            $status = $tags['status'] ?? null;
            $hasStatus = is_numeric($status) && (int) $status !== 0;

            if (! $hasStatus) {
                $pending['no_status']++;
                $pending['failed']++;

                continue;
            }

            $code = (int) $status;

            if ($code >= 500) {
                $pending['failed']++;
            }

            if ($code === 429) {
                $pending['err429']++;
            }
        }

        if ($pending !== null) {
            $rows[] = $this->finaliseBucket($pending);
        }

        fclose($handle);

        if ($parsedPoints === 0) {
            return ['state' => 'empty_or_invalid', 'buckets' => []];
        }

        return ['state' => 'stream', 'buckets' => $this->fillSecondGaps($rows)];
    }

    /**
     * Ubah bucket yang masih menahan durasi menjadi agregat, lalu lepaskan memorinya.
     *
     * @param  array{second:int,durations:list<float>,total:int,failed:int,no_status:int,err429:int}  $pending
     * @return array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}
     */
    private function finaliseBucket(array $pending): array
    {
        $total = $pending['total'];

        return [
            'ts' => $pending['second'],
            'rps' => $total,
            'p95_ms' => $this->percentile($pending['durations'], 95.0),
            'error_pct' => $total > 0 ? $pending['failed'] / $total * 100.0 : null,
            'pct429' => $total > 0 ? $pending['err429'] / $total * 100.0 : null,
            'no_status' => $pending['no_status'],
            'gap' => false,
        ];
    }

    /**
     * Isi detik yang terlewat dengan bucket kosong agar sumbu waktu tidak memampatkan gap.
     *
     * Gap lebih panjang dari MAX_FILLED_GAP_SECONDS hanya ditandai satu bucket jeda.
     *
     * @param  list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>  $rows
     * @return list<array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}>
     */
    private function fillSecondGaps(array $rows): array
    {
        $filled = [];
        $previous = null;

        foreach ($rows as $row) {
            if ($previous !== null && $row['ts'] - $previous > 1) {
                $missing = $row['ts'] - $previous - 1;

                if ($missing > self::MAX_FILLED_GAP_SECONDS) {
                    $filled[] = $this->emptyBucket($previous + 1, true);
                } else {
                    for ($second = $previous + 1; $second < $row['ts']; $second++) {
                        $filled[] = $this->emptyBucket($second, false);
                    }
                }
            }

            $filled[] = $row;
            $previous = $row['ts'];
        }

        return $filled;
    }

    /**
     * Bucket kosong untuk detik tanpa request; nilai turunan null agar garis diputus.
     *
     * @return array{ts:int,rps:int,p95_ms:float|null,error_pct:float|null,pct429:float|null,no_status:int,gap:bool}
     */
    private function emptyBucket(int $second, bool $gap): array
    {
        return [
            'ts' => $second,
            'rps' => 0,
            'p95_ms' => null,
            'error_pct' => null,
            'pct429' => null,
            'no_status' => 0,
            'gap' => $gap,
        ];
    }

    /**
     * Ubah waktu k6 (ISO string atau epoch number) menjadi detik bulat.
     */
    private function epochSecond(mixed $time): ?int
    {
        if (is_int($time) || is_float($time)) {
            return (int) floor((float) $time);
        }

        if (! is_string($time) || trim($time) === '') {
            return null;
        }

        $parsed = strtotime($time);

        return $parsed === false ? null : $parsed;
    }

    /**
     * Titik CPU app dari resources.csv, hanya delta valid (tanpa forward-fill).
     *
     * @param  list<array<string,int|null>>  $samples
     * @return list<array{ts:int,cores:float}>
     */
    private function cpuPoints(array $samples): array
    {
        $series = $this->deltaRateSeries($samples, 'app_usage_usec');
        $points = [];

        foreach ($samples as $index => $sample) {
            $cores = $series[$index] ?? null;

            if ($cores !== null && $sample['ts'] !== null) {
                $points[] = ['ts' => $sample['ts'], 'cores' => $cores];
            }
        }

        return $points;
    }

    /**
     * Interval sampler = selisih ts terkecil antar sample (cadence), default 1 detik.
     *
     * @param  list<array<string,int|null>>  $samples
     */
    private function samplerIntervalSeconds(array $samples): float
    {
        $smallest = null;
        $previous = null;

        foreach ($samples as $sample) {
            $second = $sample['ts'] ?? null;

            if ($second === null) {
                continue;
            }

            if ($previous !== null && $second > $previous) {
                $distance = $second - $previous;
                $smallest = $smallest === null ? $distance : min($smallest, $distance);
            }

            $previous = $second;
        }

        return $smallest === null ? 1.0 : (float) $smallest;
    }

    /**
     * CPU app pada sample terdekat; null bila jaraknya melebihi toleransi sampler.
     *
     * Mengembalikan null (bukan 0.0) penting agar sampler yang mati tidak tampak sehat.
     *
     * @param  list<array{ts:int,cores:float}>  $cpuPoints
     */
    private function nearestCpuCores(array $cpuPoints, int $second, float $tolerance): ?float
    {
        $nearest = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($cpuPoints as $point) {
            $distance = abs($point['ts'] - $second);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $nearest = $point['cores'];
            }
        }

        return $bestDistance > $tolerance ? null : $nearest;
    }

    /**
     * Grafik resource: RAM current app dan db per sample, dalam MiB.
     *
     * @param  list<array<string,int|null>>  $samples
     * @return array{id:string,title:string,caption:string,marker:null,series:list<array{label:string,color:string,segments:list<string>,segments_count:int,segments_label:string,last:float,max:float}>}
     */
    private function resourceChart(array $samples): array
    {
        $times = array_map(static fn (array $sample): ?int => $sample['ts'], $samples);
        $appMemory = array_map(
            static fn (array $sample): ?float => self::memoryValueMiB($sample['app_mem_current'] ?? null),
            $samples,
        );
        $dbMemory = array_map(
            static fn (array $sample): ?float => self::memoryValueMiB($sample['db_mem_current'] ?? null),
            $samples,
        );

        $scale = $this->chartMax([$this->validNumbers($appMemory), $this->validNumbers($dbMemory)]);

        return [
            'id' => 'resource-curve',
            'title' => 'RAM current per sample (MiB)',
            'caption' => 'Nilai cgroup memory.current apa adanya per sample; sel kosong memutus garis dan tidak diisi 0.',
            'marker' => null,
            'series' => [
                $this->chartSeries(['values' => $appMemory, 'times' => $times, 'label' => 'app RAM MiB', 'color' => '#0f766e', 'scale' => $scale]),
                $this->chartSeries(['values' => $dbMemory, 'times' => $times, 'label' => 'db RAM MiB', 'color' => '#b45309', 'scale' => $scale]),
            ],
        ];
    }

    /**
     * Ubah satu byte count menjadi MiB; null tetap null.
     */
    private static function memoryValueMiB(?int $bytes): ?float
    {
        return $bytes === null ? null : $bytes / 1048576;
    }

    /**
     * Skala vertikal bersama untuk semua deret dalam satu grafik.
     *
     * @param  list<list<float>>  $seriesList
     */
    private function chartMax(array $seriesList): float
    {
        $max = 0.0;

        foreach ($seriesList as $series) {
            foreach ($series as $value) {
                $max = max($max, $value);
            }
        }

        return $max;
    }

    /**
     * Satu deret grafik: potongan polyline, nilai terakhir, dan skala yang dipakai.
     *
     * Nilai atau timestamp null memutus garis (tidak di-forward-fill). Tanpa
     * 'scale' tiap seri memakai skala sendiri; dengan 'scale' seri satu grafik
     * memakai skala bersama agar tinggi bisa dibandingkan.
     *
     * @param  array{values:list<float|null>,times:list<int|null>,label:string,color:string,scale?:float}  $spec
     * @return array{label:string,color:string,segments:list<string>,segments_count:int,segments_label:string,last:float,max:float}
     */
    private function chartSeries(array $spec): array
    {
        $valid = $this->validNumbers($spec['values']);
        $max = $spec['scale'] ?? $this->chartMax([$valid]);
        $segments = $this->polylineSegments($spec['values'], $spec['times'], $max);

        return [
            'label' => $spec['label'],
            'color' => $spec['color'],
            'segments' => $segments,
            'segments_count' => count($segments),
            'segments_label' => count($segments) > 1 ? ' · '.count($segments).' segmen' : '',
            'last' => $valid === [] ? 0.0 : (float) $valid[count($valid) - 1],
            'max' => round($max, 3),
        ];
    }

    /**
     * Potongan polyline pada viewBox 600×160 dengan sumbu x proporsional waktu.
     *
     * Dipakai view untuk menggambar grafik tanpa JavaScript; null memutus garis.
     *
     * @param  list<float|null>  $values
     * @param  list<int|null>  $times
     * @return list<string>
     */
    private function polylineSegments(array $values, array $times, float $max): array
    {
        $knownTimes = array_values(array_filter($times, static fn (?int $time): bool => $time !== null));

        if ($knownTimes === []) {
            return [];
        }

        $first = min($knownTimes);
        $span = max(1, max($knownTimes) - $first);
        $scale = $max > 0 ? $max : 1.0;

        $segments = [];
        $current = [];

        foreach ($values as $index => $value) {
            $time = $times[$index] ?? null;

            if ($value === null || $time === null) {
                if ($current !== []) {
                    $segments[] = implode(' ', $current);
                    $current = [];
                }

                continue;
            }

            $x = ($time - $first) / $span * self::CHART_WIDTH;
            $y = self::CHART_HEIGHT - min(1.0, max(0.0, $value / $scale)) * self::CHART_HEIGHT;
            $current[] = round($x, 2).','.round($y, 2);
        }

        if ($current !== []) {
            $segments[] = implode(' ', $current);
        }

        return $segments;
    }

    /**
     * Buang nilai null sebelum statistik dihitung (bukan diisi 0 atau nilai sebelumnya).
     *
     * @param  list<float|null>  $values
     * @return list<float>
     */
    private function validNumbers(array $values): array
    {
        return array_values(array_filter($values, static fn (?float $value): bool => $value !== null));
    }

    /**
     * Statistik ringkas (count/avg/p95/max) untuk satu deret angka.
     *
     * @param  list<float>  $values
     * @return array{count:int,avg:float,p95:float,max:float}
     */
    private function statsFor(array $values): array
    {
        if ($values === []) {
            return ['count' => 0, 'avg' => 0.0, 'p95' => 0.0, 'max' => 0.0];
        }

        return [
            'count' => count($values),
            'avg' => array_sum($values) / count($values),
            'p95' => $this->percentile($values, 95.0),
            'max' => max($values),
        ];
    }

    /**
     * Persentil nearest-rank dari deret angka.
     *
     * @param  list<float>  $values
     */
    private function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $index = (int) ceil($percentile / 100 * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }

    /**
     * Temuan otomatis dari angka run; setiap item punya level dan teks.
     *
     * @param  array{meta:array<string,mixed>,capacity:array<string,mixed>,endpoints:list<array<string,mixed>>,digests:list<array<string,mixed>>,resources:array<string,mixed>}  $inputs
     * @return list<array{level:string,text:string}>
     */
    private function findings(array $inputs): array
    {
        $meta = $inputs['meta'];
        $capacity = $inputs['capacity'];
        $endpoints = $inputs['endpoints'];
        $digests = $inputs['digests'];
        $resources = $inputs['resources'];

        $items = [];

        if (($meta['generator_bound'] ?? false) === true) {
            $items[] = [
                'level' => 'critical',
                'text' => 'generator_bound=true: host generator (k6) jenuh. Angka latency dan RPS di sini bukan batas aplikasi; ulangi dengan generator terpisah sebelum menyimpulkan kapasitas.',
            ];
        }

        if (($meta['bypass_throttle'] ?? false) === true) {
            $items[] = [
                'level' => 'warning',
                'text' => 'bypass_throttle=true: rate limiter dilewati, jadi 429 di laporan ini tidak mencerminkan perilaku produksi. Jalankan juga tanpa bypass untuk melihat batas limiter.',
            ];
        }

        if ($capacity['throttled_rate'] > 0.0) {
            $items[] = [
                'level' => 'warning',
                'text' => sprintf('%.2f%% request dijawab 429; periksa apakah itu limiter yang diinginkan atau tanda beban berlebih.', $capacity['throttled_rate'] * 100),
            ];
        }

        if ($capacity['error_rate'] > 0.0) {
            $items[] = [
                'level' => 'critical',
                'text' => sprintf('%.2f%% request berakhir 5xx; periksa log aplikasi sebelum menaikkan beban.', $capacity['error_rate'] * 100),
            ];
        }

        if ($capacity['break_point']) {
            $items[] = [
                'level' => 'critical',
                'text' => sprintf('Titik patah tercapai: p95 %.2f ms (ambang %s ms) atau 5xx %.2f%% (ambang %.0f%%).', $capacity['p95_ms'], number_format(self::BREAK_P95_MS), $capacity['error_rate'] * 100, self::BREAK_ERROR_RATE * 100),
            ];
        }

        if ($capacity['gap_to_target'] > 0.0) {
            $items[] = [
                'level' => 'info',
                'text' => sprintf('RPS tercapai %s dari target %s (%.2f%%); sisa %s rps. Plafon teoretis = workers × 1000 / latency_ms.', number_format($capacity['rate'], 2), number_format(self::CAPACITY_TARGET_RPS), $capacity['rate'] / self::CAPACITY_TARGET_RPS * 100, number_format($capacity['gap_to_target'], 2)),
            ];
        }

        foreach (array_slice($digests, 0, 3) as $digest) {
            if ($digest['full_scan'] || ($digest['examined_per_sent'] !== null && $digest['examined_per_sent'] >= 100.0)) {
                $items[] = [
                    'level' => 'warning',
                    'text' => sprintf('Query %s %s memeriksa %s baris untuk %s baris hasil; kandidat indeks.', substr($digest['digest'], 0, 12), $digest['full_scan'] ? 'tanpa indeks' : 'dengan rasio besar', number_format($digest['rows_examined']), number_format($digest['rows_sent'])),
                ];
            }
        }

        if ($endpoints !== []) {
            $slowest = $endpoints[0];
            $items[] = [
                'level' => 'info',
                'text' => sprintf('Endpoint p95 tertinggi: %s (%.2f ms, %s request).', $slowest['name'], $slowest['p95_ms'], number_format($slowest['requests'])),
            ];
        }

        if ($resources['throttled_usec'] > 0) {
            $items[] = [
                'level' => 'warning',
                'text' => sprintf('CPU app ter-throttle %s ms selama run; cgroup limit membatasi throughput.', number_format($resources['throttled_usec'] / 1000, 2)),
            ];
        }

        if (($meta['query_capture'] ?? '') === 'emulated_prepares') {
            $items[] = [
                'level' => 'info',
                'text' => 'Query capture memakai emulated prepares (text protocol) agar performance_schema mencatat digest; perilaku sedikit berbeda dari native prepared statement.',
            ];
        }

        if ($items === []) {
            $items[] = ['level' => 'info', 'text' => 'Tidak ada anomali yang terdeteksi dari artefak run ini.'];
        }

        return $items;
    }

    /**
     * Bagian sensitivitas volume: digest dengan rows_examined besar + status indeks.
     *
     * @param  array{digests:list<array<string,mixed>>,meta:array<string,mixed>}  $inputs
     * @return array{dataset_rows:int,rows:list<array<string,mixed>>}
     */
    private function volumeRows(array $inputs): array
    {
        $rows = array_values(array_filter(
            $inputs['digests'],
            static fn (array $digest): bool => $digest['examined_per_sent'] !== null && $digest['examined_per_sent'] >= 10.0,
        ));

        usort($rows, static fn (array $left, array $right): int => $right['rows_examined'] <=> $left['rows_examined']);

        return [
            'dataset_rows' => (int) ($inputs['meta']['dataset_rows'] ?? 0),
            'rows' => array_slice($rows, 0, 10),
        ];
    }

    /**
     * Baca resource sampler; sel kosong dipetakan ke null, bukan 0.
     *
     * @return list<array{ts:int|null,app_usage_usec:int|null,app_throttled_usec:int|null,app_mem_current:int|null,app_mem_peak:int|null,db_usage_usec:int|null,db_mem_current:int|null,db_mem_peak:int|null}>
     */
    private function readResourceSamples(string $path): array
    {
        $lines = preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [];
        $samples = [];
        $first = true;

        foreach ($lines as $line) {
            if ($first) {
                $first = false;

                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            $samples[] = [
                'ts' => $this->nullableInt($cells[0] ?? null),
                'app_usage_usec' => $this->nullableInt($cells[1] ?? null),
                'app_throttled_usec' => $this->nullableInt($cells[2] ?? null),
                'app_mem_current' => $this->nullableInt($cells[3] ?? null),
                'app_mem_peak' => $this->nullableInt($cells[4] ?? null),
                'db_usage_usec' => $this->nullableInt($cells[5] ?? null),
                'db_mem_current' => $this->nullableInt($cells[6] ?? null),
                'db_mem_peak' => $this->nullableInt($cells[7] ?? null),
            ];
        }

        return $samples;
    }

    /**
     * Sel CSV kosong/non-angka menjadi null; angka menjadi int.
     */
    private function nullableInt(?string $cell): ?int
    {
        if ($cell === null) {
            return null;
        }

        $trimmed = trim($cell);

        return $trimmed === '' || is_numeric($trimmed) === false ? null : (int) $trimmed;
    }

    /**
     * Nilai dalam satu metrik k6; array kosong bila metrik tidak ada.
     *
     * @param  array<string,mixed>  $summary
     * @return array<string,mixed>
     */
    private function metricValues(array $summary, string $name): array
    {
        $values = $summary['metrics'][$name]['values'] ?? null;

        return is_array($values) ? $values : [];
    }

    /**
     * Nilai numerik dari JSON k6, dengan fallback eksplisit.
     */
    private function numberOr(mixed $value, float $fallback): float
    {
        return is_int($value) || is_float($value) ? (float) $value : (is_string($value) && is_numeric($value) ? (float) $value : $fallback);
    }

    /**
     * Durasi run dalam detik dari started_at/finished_at, fallback ke string duration.
     *
     * @param  array<string,mixed>  $meta
     */
    private function runWindowSeconds(array $meta): float
    {
        $started = is_string($meta['started_at'] ?? null) ? strtotime($meta['started_at']) : false;
        $finished = is_string($meta['finished_at'] ?? null) ? strtotime($meta['finished_at']) : false;

        if ($started !== false && $finished !== false && $finished > $started) {
            return (float) ($finished - $started);
        }

        return (float) $this->durationSeconds(is_string($meta['duration'] ?? null) ? $meta['duration'] : '');
    }

    /**
     * Ubah durasi k6 ("30s", "2m", "1h30m") menjadi detik; 0 bila tak terbaca.
     */
    private function durationSeconds(string $duration): int
    {
        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', trim($duration), $matches) !== 1) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0)) * 3600 + ((int) ($matches[2] ?? 0)) * 60 + ((int) ($matches[3] ?? 0));
    }

    /**
     * Path report.html tujuan; opsi --out menang atas default <run>/report.html.
     */
    private function outPath(string $runDir): string
    {
        $out = $this->option('out');

        return is_string($out) && trim($out) !== '' ? trim($out) : $runDir.'/report.html';
    }

    /**
     * Baca artefak JSON; artefak wajib yang rusak dilaporkan dengan nama path-nya.
     *
     * @return array<string,mixed>|null
     */
    private function readJson(string $path, bool $required = true): ?array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if ($required) {
            $this->error('Artefak bukan JSON objek yang valid: '.basename($path));
        }

        return null;
    }
}
