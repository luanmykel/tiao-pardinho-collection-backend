<?php

namespace App\Services;

class YouTubeScraperService
{
    public function extractId(string $input): ?string
    {
        $s = trim($input);

        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $s)) {
            return $s;
        }

        if (strpos($s, 'v=') !== false && preg_match('~v=([A-Za-z0-9_-]{11})~', $s, $m)) {
            return $m[1];
        }

        if (! preg_match('~^https?://~i', $s) && (str_contains($s, 'youtube.com') || str_contains($s, 'youtu.be'))) {
            $s = 'https://'.ltrim($s, '/');
        }

        $url = @parse_url($s);
        if ($url) {
            $host = strtolower($url['host'] ?? '');
            $path = $url['path'] ?? '';
            $queryStr = $url['query'] ?? '';

            $host = preg_replace('~^(www|m)\.~i', '', $host);

            if ($host === 'youtu.be') {
                $seg = explode('/', trim($path, '/'));
                if (! empty($seg[0]) && preg_match('~^[A-Za-z0-9_-]{11}$~', $seg[0])) {
                    return $seg[0];
                }
            }

            if (preg_match('~(^|\.)youtube\.com$~i', $host)) {
                if ($path === '/watch' || $path === 'watch') {
                    parse_str($queryStr, $q);
                    if (! empty($q['v']) && preg_match('~^[A-Za-z0-9_-]{11}$~', $q['v'])) {
                        return $q['v'];
                    }
                }

                if (preg_match('~^/embed/([A-Za-z0-9_-]{11})~', $path, $m)) {
                    return $m[1];
                }

                if (preg_match('~^/shorts/([A-Za-z0-9_-]{11})~', $path, $m)) {
                    return $m[1];
                }
            }
        }

        if (isset($host) && (
            $host === 'youtu.be' ||
            preg_match('~(^|\.)youtube\.com$~i', $host)
        )
        ) {
            if (preg_match('~([A-Za-z0-9_-]{11})~', $path ?? '', $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function curlGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Top5Bot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $code >= 400) {
            return null;
        }

        return is_string($resp) ? $resp : null;
    }

    public function fetchById(string $id): array
    {
        $meta = ['id' => $id, 'title' => null, 'channel' => null, 'views' => null];

        if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return $meta;
        }

        $oembedUrl = 'https://www.youtube.com/oembed?format=json&url='.rawurlencode("https://www.youtube.com/watch?v={$id}");
        if ($json = $this->curlGet($oembedUrl)) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $meta['title'] = $data['title'] ?? $meta['title'];
                $meta['channel'] = $data['author_name'] ?? $meta['channel'];
            }
        }

        $watchUrl = "https://www.youtube.com/watch?v={$id}";
        if ($html = $this->curlGet($watchUrl)) {

            if (preg_match('/"viewCount"\s*:\s*"(?P<count>\d+)"/', $html, $m)) {
                $meta['views'] = (int) $m['count'];
            } elseif (preg_match('/"shortViewCountText"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/', $html, $m2)) {
                $meta['views'] = $this->parseHumanViews($m2[1]);
            }

            if ($meta['title'] === null && preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $mt)) {
                $meta['title'] = html_entity_decode($mt[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return $meta;
    }

    public function fetch(string $input): ?array
    {
        $id = $this->extractId($input);

        return $id ? $this->fetchById($id) : null;
    }

    private function parseHumanViews(string $text): ?int
    {
        $t = mb_strtolower($text, 'UTF-8');

        $t = str_replace(['.', ' visualizações', ' views'], ['', '', ''], $t);
        $t = str_replace(',', '.', $t);

        if (preg_match('/([\d\.]+)\s*(mil|k)/i', $t, $m)) {
            return (int) round(((float) $m[1]) * 1_000);
        }

        if (preg_match('/([\d\.]+)\s*(mi|m)/i', $t, $m)) {
            return (int) round(((float) $m[1]) * 1_000_000);
        }

        if (preg_match('/(\d+)/', $t, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
