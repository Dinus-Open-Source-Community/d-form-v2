<?php

namespace App\Console\Commands\E2e;

use App\Support\E2e\FixtureIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lakukan satu HTTP request ke base URL e2e dan kembalikan status + header.
 * Dipakai spec observasi agar assertion header tidak bergantung pada log server.
 */
final class HttpCommand extends Command
{
    protected $signature = 'e2e:http
        {--marker= : Marker kanonik milik test yang meminta}
        {--method=GET : HTTP method}
        {--path=/ : Path relatif terhadap E2E_BASE_URL}
        {--json : Cetak hasil sebagai JSON}';

    protected $description = 'Perform one HTTP request against E2E_BASE_URL and report status and headers.';

    public function handle(): int
    {
        $marker = (string) ($this->option('marker') ?? '');

        if ($marker !== '') {
            FixtureIdentity::fromMarker($marker);
        }

        $baseUrl = rtrim((string) (getenv('E2E_BASE_URL') ?: 'http://localhost:8000'), '/');
        $path = (string) ($this->option('path') ?? '/');
        $method = strtoupper((string) ($this->option('method') ?? 'GET'));

        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->send($method, $baseUrl.$path);

        $this->line(json_encode([
            'marker' => $marker === '' ? null : $marker,
            'method' => $method,
            'path' => $path,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'bodyBytes' => strlen($response->body()),
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
