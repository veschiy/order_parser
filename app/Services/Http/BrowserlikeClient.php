<?php

declare(strict_types=1);

namespace App\Services\Http;

use App\Exceptions\HttpRequestFailedException;
use Illuminate\Support\Facades\Http;

final class BrowserlikeClient
{
    private const int MAX_ATTEMPTS = 5;

    private const int BASE_DELAY_MICROSECONDS = 2_000_000;

    private const int MAX_DELAY_MICROSECONDS = 16_000_000;

    private const string PRIME_URL = 'https://www.president.gov.ua/';

    /** @var callable(int): void */
    private $sleeper;

    private ?float $lastRequestAt = null;

    /** @var non-empty-string|null */
    private ?string $cookieFile = null;

    private bool $primed = false;

    public function __construct(
        ?callable $sleeper = null,
        private readonly int $minIntervalMs = 1000,
        private readonly bool $useCookies = true,
    ) {
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    public function __destruct()
    {
        if ($this->cookieFile !== null && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: int, 1: string}
     */
    public function fetch(string $url, array $headers): array
    {
        if (! str_contains(strtolower($url), 'president.gov.ua')) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->get($url);

                if ($response->successful()) {
                    return [$response->status(), (string) $response->body()];
                }
            } catch (\Throwable $throwable) {
                throw new HttpRequestFailedException($throwable->getMessage(), 0, $throwable);
            }

            throw new HttpRequestFailedException('HTTP request failed with status '.$response->status().'.');
        }

        // The retry/backoff sleeps below can exceed PHP's default 30s limit,
        // especially in the packaged Electron app where max_execution_time cannot
        // easily be changed via php.ini.
        set_time_limit(0);

        $this->throttle();

        $reprimed = false;

        if (! $this->primed) {
            $this->prime($headers);
            $this->primed = true;
        }

        $status = 0;
        $body = '';

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            try {
                [$status, $body] = $this->perform($url, $headers);
            } catch (\Throwable $throwable) {
                if ($attempt >= self::MAX_ATTEMPTS - 1) {
                    throw $throwable;
                }

                $this->backoff($attempt);

                continue;
            }

            if (! $this->isBlocked($status, $body)) {
                return [$status, $body];
            }

            // Re-prime cookies once if the edge still blocks us, then retry.
            if (! $reprimed && $attempt === 1) {
                // Drop any stale/checkpoint cookies before re-priming so the
                // retry starts from a clean (or empty) jar. Prefer unlinking;
                // fall back to emptying the file if deletion fails.
                if ($this->cookieFile !== null && is_file($this->cookieFile)) {
                    if (@unlink($this->cookieFile)) {
                        $this->cookieFile = null;
                    } else {
                        @file_put_contents($this->cookieFile, '');
                    }
                }

                $this->primed = false;
                $this->prime($headers);
                $this->primed = true;
                $reprimed = true;
            }

            if ($attempt < self::MAX_ATTEMPTS - 1) {
                $this->backoff($attempt);
            }
        }

        if ($this->isBlocked($status, $body)) {
            throw new HttpRequestFailedException('Request blocked by president.gov.ua after '.self::MAX_ATTEMPTS.' attempts.');
        }

        return [$status, $body];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function prime(array $headers): void
    {
        try {
            $this->perform(self::PRIME_URL, $headers);
        } catch (\Throwable) {
            // Priming is best-effort; continue with the real request.
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: int, 1: string}
     */
    private function perform(string $url, array $headers): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new HttpRequestFailedException('Unable to initialize cURL.');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_ENCODING => '',
        ];

        $cookieFile = $this->cookieFile();

        if ($cookieFile !== null) {
            $options[CURLOPT_COOKIEJAR] = $cookieFile;
            $options[CURLOPT_COOKIEFILE] = $cookieFile;
        }

        if (curl_setopt_array($handle, $options) === false) {
            $error = curl_error($handle) ?: 'Unable to configure cURL.';
            curl_close($handle);

            throw new HttpRequestFailedException('cURL configuration failed: '.$error);
        }

        $result = curl_exec($handle);

        if ($result === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new HttpRequestFailedException('cURL request failed: '.$error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $body = (string) $result;
        curl_close($handle);

        return [$status, $body];
    }

    private function isBlocked(int $status, string $body): bool
    {
        if (in_array($status, [429, 403, 503], true)) {
            return true;
        }

        $lowerBody = strtolower($body);

        return str_contains($lowerBody, 'sec-if-cpt-container')
            || str_contains($lowerBody, 'checkpoint')
            || str_contains($lowerBody, 'cf-challenge');
    }

    /** @return non-empty-string|null */
    private function cookieFile(): ?string
    {
        if (! $this->useCookies) {
            return null;
        }

        if ($this->cookieFile === null) {
            $path = tempnam(sys_get_temp_dir(), 'president_cookies_');

            $this->cookieFile = $path === false ? null : $path;
        }

        return $this->cookieFile;
    }

    private function throttle(): void
    {
        if ($this->lastRequestAt === null || $this->minIntervalMs <= 0) {
            $this->lastRequestAt = microtime(true);

            return;
        }

        $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;
        $waitMs = $this->minIntervalMs - $elapsedMs;

        if ($waitMs > 0) {
            ($this->sleeper)((int) ceil($waitMs * 1000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function backoff(int $attempt): void
    {
        $base = self::BASE_DELAY_MICROSECONDS * (1 << $attempt);
        $delay = min($base, self::MAX_DELAY_MICROSECONDS);
        $jitter = random_int(0, 500_000);
        ($this->sleeper)(min($delay + $jitter, self::MAX_DELAY_MICROSECONDS));
    }
}
