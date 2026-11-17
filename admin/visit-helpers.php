<?php

declare(strict_types=1);

/**
 * Shared helpers for the pages that read the `log` table
 * (dashboard.php, visits.php, map.php).
 */

// Words that identify a crawler in the user agent. Catches the common ones, not all of them.
const BOT_PATTERN = 'bot|crawl|spider|slurp';

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

/**
 * SQL condition that keeps only non-bot rows of `log`.
 * Same rule as is_bot(), applied to the user-agent part of `geo`.
 * Contains no user input, so it is safe to put directly in a query.
 */
function sql_exclude_bots(): string
{
    return "LOWER(SUBSTRING_INDEX(COALESCE(geo, ''), 'navegador=', -1)) NOT REGEXP '" . BOT_PATTERN . "'";
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
