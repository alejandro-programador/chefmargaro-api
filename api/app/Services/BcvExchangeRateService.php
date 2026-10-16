<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BcvExchangeRateService
{
    /**
     * @return array{usd: string, eur: string, date: string, usd_value: ?float, eur_value: ?float}
     */
    public function fetch(): array
    {
        $ttl = (int) config('bcv.cache_ttl', 3600);

        if ($ttl > 0) {
            return Cache::remember('bcv.exchange_rates', $ttl, fn () => $this->scrape());
        }

        return $this->scrape();
    }

    /**
     * @return array{usd: string, eur: string, date: string, usd_value: ?float, eur_value: ?float}
     */
    private function scrape(): array
    {
        $response = $this->httpClient()->get((string) config('bcv.url', 'https://www.bcv.org.ve/'));

        if (! $response->successful()) {
            throw new RuntimeException(
                'No se pudo obtener la página del BCV (HTTP '.$response->status().').'
            );
        }

        $xpath = $this->createXPath($response->body());

        $usd = $this->extractText($xpath, '//*[@id="dolar"]//strong');
        $eur = $this->extractText($xpath, '//*[@id="euro"]//strong');
        $date = $this->extractText(
            $xpath,
            '//*[contains(@class,"pull-right") and contains(@class,"dinpro") and contains(@class,"center")]//span'
        );

        if ($usd === null && $eur === null) {
            throw new RuntimeException('No se encontraron las tasas en la página del BCV.');
        }

        return [
            'usd' => $usd ?? '',
            'eur' => $eur ?? '',
            'date' => $date ?? '',
            'usd_value' => $this->parseRate($usd),
            'eur_value' => $this->parseRate($eur),
        ];
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout((int) config('bcv.timeout', 30))
            ->withHeaders([
                'User-Agent' => (string) config('bcv.user_agent'),
            ]);

        if (! config('bcv.verify_ssl', false)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    private function createXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    private function extractText(DOMXPath $xpath, string $expression): ?string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $nodes->item(0)?->textContent ?? '') ?? '');

        return $text !== '' ? $text : null;
    }

    private function parseRate(?string $rate): ?float
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        // Formato BCV: coma como separador decimal (ej. 235,67230000)
        $normalized = str_replace(',', '.', trim($rate));
        $normalized = preg_replace('/[^\d.]/', '', $normalized) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
