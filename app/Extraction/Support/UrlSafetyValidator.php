<?php

namespace App\Extraction\Support;

use App\Extraction\Exceptions\RecipeExtractionException;

/**
 * Guards against SSRF: only allows http/https URLs whose host resolves
 * exclusively to public, non-reserved IP addresses.
 */
class UrlSafetyValidator
{
    /**
     * Validate the URL and return the safe, public IPs it resolves to.
     *
     * @return array<int, string>
     *
     * @throws RecipeExtractionException
     */
    public static function ensureSafe(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw RecipeExtractionException::unsafeUrl('the URL is malformed.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw RecipeExtractionException::unsafeUrl('only http and https URLs are supported.');
        }

        $host = $parts['host'];

        $ips = self::safeIpsForHost($host);

        if ($ips === []) {
            throw RecipeExtractionException::unsafeUrl('the host does not resolve to a public address.');
        }

        return $ips;
    }

    /**
     * @return array<int, string>
     */
    private static function safeIpsForHost(string $host): array
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : self::resolve($host);

        $safe = [];

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $safe[] = $ip;
            }
        }

        return array_values(array_unique($safe));
    }

    /**
     * @return array<int, string>
     */
    private static function resolve(string $host): array
    {
        $ips = array_merge(
            gethostbynamel($host) ?: [],
            array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        return array_values(array_unique($ips));
    }
}
