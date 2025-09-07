<?php

namespace App\Services;

class YouTubeScraperService
{
    private const HL = 'pt-BR';
    private const GL = 'BR';
    private const CONSENT = 'CONSENT=YES+cb.20210328-17-p0.en+FX';

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
        $PREF = 'PREF=hl='.self::HL.'&gl='.self::GL;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
            CURLOPT_REFERER => 'https://www.youtube.com/',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: '.self::HL.',pt;q=0.9,en;q=0.6',
                'Cookie: '.$PREF.'; '.self::CONSENT,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
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

    private function parseHumanViews(string $text): ?int
    {
        $t = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $t = str_replace("\xC2\xA0", ' ', $t);
        $t = trim($t);
        $t = str_replace([' visualizações', ' views', ' de visualizações'], ['', '', ''], $t);

        if (preg_match('/([\d\.,]+)\s*(mil|k)\b/i', $t, $m)) {
            $num = (float) str_replace(',', '.', str_replace('.', '', $m[1]));

            return (int) round($num * 1000);
        }
        if (preg_match('/([\d\.,]+)\s*(mi|m)\b/i', $t, $m)) {
            $num = (float) str_replace(',', '.', str_replace('.', '', $m[1]));

            return (int) round($num * 1000000);
        }
        if (preg_match('/\d/', $t)) {
            $digitsOnly = preg_replace('/\D+/', '', $t);
            if ($digitsOnly !== '') {
                return (int) $digitsOnly;
            }
        }

        return null;
    }

    private function isBadTitle(?string $t): bool
    {
        if (! $t) {
            return true;
        }
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim($t), 'UTF-8') : strtolower(trim($t));
        $bads = ['like this video?', 'like this video', 'sign in', 'shorts', 'watch', 'youtube', 'home'];
        if (in_array($s, $bads, true)) {
            return true;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);

        return $len < 5;
    }

    public function fetchById(string $id): array
    {
        $meta = ['id' => $id, 'title' => null, 'channel' => null, 'views' => null];

        if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return $meta;
        }

        $oembedUrl = 'https://www.youtube.com/oembed?format=json'
            .'&hl='.rawurlencode(self::HL)
            .'&url='.rawurlencode("https://www.youtube.com/watch?v=$id");
        if ($json = $this->curlGet($oembedUrl)) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                if (! $this->isBadTitle($data['title'] ?? null)) {
                    $meta['title'] = $data['title'] ?? $meta['title'];
                }
                $meta['channel'] = $data['author_name'] ?? $meta['channel'];
            }
        }

        $watchUrl = "https://www.youtube.com/watch?v=$id"
            .'&bpctr=9999999999&has_verified=1'
            .'&hl='.rawurlencode(self::HL)
            .'&gl='.rawurlencode(self::GL);

        if ($html = $this->curlGet($watchUrl)) {

            if (preg_match('/"videoDetails"\s*:\s*\{[^}]*"viewCount"\s*:\s*"(?P<num>\d+)"/s', $html, $mv)) {
                $meta['views'] = (int) $mv['num'];
            }

            if (preg_match('/ytInitialPlayerResponse\s*=\s*(\{.*?\});/s', $html, $mj)) {
                $j = json_decode($mj[1], true);
                if (is_array($j)) {
                    $raw = $j['videoDetails']['viewCount'] ?? null;
                    if ($meta['views'] === null && is_numeric($raw)) {
                        $meta['views'] = (int) $raw;
                    }
                    $title_from_json = $j['videoDetails']['title'] ?? null;
                    $channel_from_json = $j['videoDetails']['author'] ?? null;
                    if ($meta['title'] === null && ! $this->isBadTitle($title_from_json)) {
                        $meta['title'] = $title_from_json;
                    }
                    if ($meta['channel'] === null && ! empty($channel_from_json)) {
                        $meta['channel'] = $channel_from_json;
                    }
                }
            }

            if ($meta['views'] === null && preg_match(
                '/"videoPrimaryInfoRenderer"\s*:\s*\{(?P<blk>.*?)}\s*,\s*"[A-Za-z_]/s',
                $html,
                $mBlock
            )) {
                $primary = $mBlock['blk'];

                if (preg_match(
                    '/"viewCount"\s*:\s*\{\s*"videoViewCountRenderer"\s*:\s*\{\s*"viewCount"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/s',
                    $primary, $m1
                )) {
                    $v = $this->parseHumanViews($m1[1]);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }

                if ($meta['views'] === null && preg_match(
                    '/"viewCount"\s*:\s*\{\s*"videoViewCountRenderer"\s*:\s*\{\s*"viewCount"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"([^"]+)"/s',
                    $primary, $m2
                )) {
                    $v = $this->parseHumanViews($m2[1]);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }

                if ($meta['views'] === null && preg_match(
                    '/"viewCountText"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/s',
                    $primary, $m3
                )) {
                    $v = $this->parseHumanViews($m3[1]);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }

                if ($meta['views'] === null && preg_match(
                    '/"viewCountText"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"([^"]+)"/s',
                    $primary, $m4
                )) {
                    $v = $this->parseHumanViews($m4[1]);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }

                if ($meta['views'] === null && preg_match(
                    '/"viewCount"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/s',
                    $primary, $m5
                )) {
                    $v = $this->parseHumanViews($m5[1]);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }
            }

            if ($meta['views'] === null && preg_match(
                '/"videoViewCountRenderer"\s*:\s*\{[\s\S]*?"(viewCount|viewCountText)"\s*:\s*\{\s*"(simpleText|runs)"\s*:\s*(?:"([^"]+)"|\[\s*\{\s*"text"\s*:\s*"([^"]+)")/i',
                $html, $mAny
            )) {
                $raw = $mAny[4] ?? $mAny[5] ?? null;
                if ($raw !== null) {
                    $v = $this->parseHumanViews($raw);
                    if ($v !== null) {
                        $meta['views'] = $v;
                    }
                }
            }
            if ($meta['views'] === null && preg_match(
                '/"viewCount"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/i',
                $html, $mVC
            )) {
                $v = $this->parseHumanViews($mVC[1]);
                if ($v !== null) {
                    $meta['views'] = $v;
                }
            }
            if ($meta['views'] === null && preg_match(
                '/"shortViewCount"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/i',
                $html, $mSVC
            )) {
                $v = $this->parseHumanViews($mSVC[1]);
                if ($v !== null) {
                    $meta['views'] = $v;
                }
            }

            if ($meta['title'] === null) {
                if (preg_match(
                    '/"playerMicroformatRenderer"\s*:\s*\{(?:(?!\}\s*,\s*"[A-Za-z_]).)*?"title"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/s',
                    $html, $mmf
                )) {
                    $cand = html_entity_decode($mmf[1], ENT_QUOTES | ENT_HTML5);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
                if ($meta['title'] === null && preg_match(
                    '/"playerMicroformatRenderer"\s*:\s*\{(?:(?!\}\s*,\s*"[A-Za-z_]).)*?"title"\s*:\s*"([^"]+)"/s',
                    $html, $mmf2
                )) {
                    $cand = html_entity_decode($mmf2[1], ENT_QUOTES | ENT_HTML5);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
                if ($meta['title'] === null && preg_match(
                    '/"videoPrimaryInfoRenderer"\s*:\s*\{(?:(?!\}\s*,\s*"[A-Za-z_]).)*?"title"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"([^"]+)"/s',
                    $html, $mPRIruns
                )) {
                    $cand = html_entity_decode($mPRIruns[1], ENT_QUOTES | ENT_HTML5);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
                if ($meta['title'] === null && preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $mt)) {
                    $cand = html_entity_decode($mt[1], ENT_QUOTES | ENT_HTML5);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
                if ($meta['title'] === null && preg_match('/<meta\s+name="title"\s+content="([^"]+)"/i', $html, $mname)) {
                    $cand = html_entity_decode($mname[1], ENT_QUOTES | ENT_HTML5);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
                if ($meta['title'] === null && preg_match('/<title>(.*?)<\/title>/is', $html, $tt)) {
                    $cand = trim(html_entity_decode($tt[1], ENT_QUOTES | ENT_HTML5));
                    $cand = preg_replace('/\s*-\s*YouTube\s*$/i', '', $cand);
                    if (! $this->isBadTitle($cand)) {
                        $meta['title'] = $cand;
                    }
                }
            }
        }

        return $meta;
    }

    public function fetch(string $input): ?array
    {
        $id = $this->extractId($input);

        return $id ? $this->fetchById($id) : null;
    }
}
