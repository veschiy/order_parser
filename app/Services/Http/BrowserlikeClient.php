<?php

declare(strict_types=1);

namespace App\Services\Http;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BrowserlikeClient
{
    /**
     * @param  array<string, string>  $headers
     * @return array{0: int, 1: string}
     */
    public function fetch(string $url, array $headers): array
    {
        if (! str_contains($url, 'president.gov.ua')) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->get($url);

                if ($response->successful()) {
                    return [$response->status(), (string) $response->body()];
                }
            } catch (\Throwable $throwable) {
                throw new RuntimeException($throwable->getMessage(), 0, $throwable);
            }

            throw new RuntimeException('HTTP request failed.');
        }

        $status = 0;
        $body = '';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $handle = curl_init($url);

            if ($handle === false) {
                throw new RuntimeException('Unable to initialize cURL.');
            }

            $headerLines = [];

            foreach ($headers as $name => $value) {
                $headerLines[] = $name.': '.$value;
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_ENCODING => '',
            ]);

            $result = curl_exec($handle);

            if ($result === false) {
                $error = curl_error($handle);
                curl_close($handle);

                throw new RuntimeException('cURL request failed: '.$error);
            }

            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $body = (string) $result;
            curl_close($handle);

            if ($status >= 200 && $status < 300 && ! str_contains($body, 'sec-if-cpt-container')) {
                return [$status, $body];
            }

            if ($attempt < 2) {
                sleep(2);
            }
        }

        return [$status, $body];
    }
}
