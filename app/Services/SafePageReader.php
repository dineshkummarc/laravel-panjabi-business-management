<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SafePageReader
{
    public function validateTarget(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (($parts['port'] ?? 443) !== 443)) {
            $this->reject();
        }
        $host = strtolower($parts['host'] ?? '');
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            $this->reject();
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! $ip) {
                continue;
            }
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                $this->reject();
            }
            if (str_contains($ip, ':')) {
                if (! str_starts_with($ip, '2') && ! str_starts_with($ip, '3')) {
                    $this->reject();
                }
            } else {
                $ips[] = $ip;
            }
        }
        if (! $ips) {
            $this->reject();
        }

return [$host, $ips[0]];
    }

    public function read(string $url): string
    {
        for ($i = 0; $i < 4; $i++) {
            [$host,$ip] = $this->validateTarget($url);
            $response = Http::timeout(12)->connectTimeout(4)->withUserAgent('PanjabiCatalogImporter/1.0')->withOptions([
                'allow_redirects' => false, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => ["$host:443:$ip"], CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS],
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > 524288) {
                        $this->reject('Page is too large.');
                    }
                },
                'progress' => function ($downloadTotal, $downloaded) {
                    if ($downloaded > 524288) {
                        $this->reject('Page is too large.');
                    }
                },
            ])->get($url);
            if ($response->redirect()) {
                $next = $response->header('Location');
                if (str_starts_with($next ?? '', '/')) {
                    $next = 'https://'.$host.$next;
                } if (! $next) {
                    $this->reject();
                } $url = $next;

                continue;
            }
            if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type') ?? ''), 'text/html')) {
                $this->reject('The source must be a readable public HTML page.');
            }
            $html = $response->body();
            if (strlen($html) > 524288) {
                $this->reject('Page is too large.');
            }
            $html = preg_replace('~<(script|style|iframe|noscript)\b[^>]*>.*?</\1>~is', '', $html);

            return mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 12000);
        }
        $this->reject('Too many redirects.');
    }

    private function reject(string $message = 'Only public HTTPS websites on port 443 are allowed.'): never
    {
        throw ValidationException::withMessages(['source' => $message]);
    }
}
