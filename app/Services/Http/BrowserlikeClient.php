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
        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($url);

            if ($response->successful()) {
                return [$response->status(), (string) $response->body()];
            }
        } catch (RuntimeException $throwable) {
            if (! str_contains($url, 'president.gov.ua')) {
                throw $throwable;
            }
        } catch (\Throwable $throwable) {
            if (! str_contains($url, 'president.gov.ua')) {
                throw new RuntimeException($throwable->getMessage(), 0, $throwable);
            }
        }

        if (! str_contains($url, 'president.gov.ua')) {
            throw new RuntimeException('HTTP request failed.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $cookieFile = sys_get_temp_dir().'/parser-cookies.txt';

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);

        $body = curl_exec($handle);

        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException('cURL request failed: '.$error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return [$status, (string) $body];
    }
}
