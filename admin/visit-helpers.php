<?php

declare(strict_types=1);

/**
 * Shared helpers for the pages that read the `log` table
 * (dashboard.php, visits.php, map.php).
 */

// Words that identify a crawler in the user agent. Catches the common ones, not all of them.
const BOT_PATTERN = 'bot|crawl|spider|slurp';

/**
 * Cloud / hosting providers, matched only against the `isp` and `org` fields
 * (never `as`: home Iliad connections show "SCALEWAY" there).
 * Real people browse from telecom operators, so these visits are treated as bots.
 * Deliberately left out:
 *  - plain "google" (Google Fiber is a home ISP) -> only "google llc" / "google cloud"
 *  - Akamai / Cloudflare (used by Apple iCloud Private Relay for real Safari users)
 *    -> only "akamai connected cloud" (ex-Linode servers)
 * Lowercase, no quotes: the pattern is also used inside an SQL string.
 */
const CLOUD_PATTERN = 'amazon|aws|microsoft|azure|google llc|google cloud|digitalocean|linode'
    . '|akamai connected cloud|ovh|hetzner|contabo|vultr|choopa|oracle cloud|alibaba|tencent'
    . '|leaseweb|m247|cdnext|datacamp|cdn77|hostinger|scaleway|meta platforms|facebook'
    . '|dataprovider|palo alto networks|censys';

/**
 * Parse the `geo` column ("key=value;key=value;...;navegador=<user agent>").
 * The user agent is always the last key and may itself contain ";",
 * so everything after "navegador=" is taken as-is.
 */
function parse_geo(string $geo): array
{
    $agent = '';
    $pos = strpos($geo, 'navegador=');
    if ($pos !== false) {
        $agent = substr($geo, $pos + strlen('navegador='));
        $geo = substr($geo, 0, $pos);
    }

    $data = [];
    foreach (explode(';', $geo) as $pair) {
        if (strpos($pair, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $pair, 2);
        $data[trim($key)] = trim($value);
    }
    $data['navegador'] = trim($agent);

    return $data;
}

// True when the user agent looks like a crawler.
function is_bot(string $userAgent): bool
{
    return (bool)preg_match('/' . BOT_PATTERN . '/i', $userAgent);
}

// True when the visit comes from a cloud / hosting provider (see CLOUD_PATTERN).
function is_cloud(array $geo): bool
{
    $pattern = '/' . CLOUD_PATTERN . '/i';
    return preg_match($pattern, $geo['isp'] ?? '') === 1
        || preg_match($pattern, $geo['org'] ?? '') === 1;
}

/**
 * Classify a parsed visit:
 *  'bot'     - the user agent says it is a crawler
 *  'cloud'   - comes from a cloud / hosting provider
 *  'visitor' - everything else
 */
function visit_type(array $geo): string
{
    if (is_bot($geo['navegador'] ?? '')) {
        return 'bot';
    }
    if (is_cloud($geo)) {
        return 'cloud';
    }
    return 'visitor';
}

/**
 * SQL condition that keeps only 'visitor' rows of `log`.
 * Same rules as visit_type(), applied to the raw `geo` text:
 *  - user-agent part (after "navegador=") must not match BOT_PATTERN
 *  - the isp= / org= values (before "navegador=") must not match CLOUD_PATTERN
 * Contains no user input, so it is safe to put directly in a query.
 */
function sql_exclude_bots(): string
{
    return "LOWER(SUBSTRING_INDEX(COALESCE(geo, ''), 'navegador=', -1)) NOT REGEXP '" . BOT_PATTERN . "'"
        . " AND LOWER(SUBSTRING_INDEX(COALESCE(geo, ''), 'navegador=', 1))"
        . " NOT REGEXP '(^|;)(isp|org)=[^;]*(" . CLOUD_PATTERN . ")'";
}

// Country name from parsed geo data, or "Unknown" when the lookup failed.
function geo_country(array $geo): string
{
    return ($geo['status'] ?? '') === 'success' && ($geo['country'] ?? '') !== ''
        ? $geo['country']
        : 'Unknown';
}

// Short, readable summary of a user agent, e.g. "Chrome · Windows" or "bingbot".
function summarize_agent(string $ua): string
{
    if ($ua === '') {
        return 'Unknown';
    }

    if (is_bot($ua)) {
        if (preg_match('/([a-z0-9._-]*(?:' . BOT_PATTERN . ')[a-z0-9._-]*)/i', $ua, $m)) {
            return $m[1];
        }
        return 'Bot';
    }

    $browser = match (true) {
        str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'EdgiOS/') => 'Edge',
        str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
        str_contains($ua, 'SamsungBrowser/') => 'Samsung Internet',
        str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/') => 'Firefox',
        str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'Other browser',
    };

    $os = match (true) {
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
        str_contains($ua, 'CrOS') => 'ChromeOS',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'Other OS',
    };

    return "$browser · $os";
}
