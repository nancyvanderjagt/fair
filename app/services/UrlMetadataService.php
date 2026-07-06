<?php

declare(strict_types=1);

final class UrlMetadataService
{
    private const MAX_REDIRECTS = 5;
    private const MAX_BYTES = 2_000_000;

    public function fetch(string $inputUrl): array
    {
        $url = $this->normalizeInputUrl($inputUrl);
        $response = $this->requestWithRedirects($url);

        $contentType = strtolower(
            (string) $response['content_type']
        );

        $path = (string) (
            parse_url($response['final_url'], PHP_URL_PATH) ?? ''
        );

        $isPdf = str_contains($contentType, 'application/pdf')
            || str_ends_with(strtolower($path), '.pdf');

        if ($isPdf) {
            return $this->buildPdfResult($response);
        }

        return $this->buildHtmlResult($response);
    }

    private function normalizeInputUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException(
                'Enter a URL before fetching details.'
            );
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                'Enter a valid website address.'
            );
        }

        return $url;
    }

    private function requestWithRedirects(string $url): array
    {
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $target = $this->validateTarget($currentUrl);
            $response = $this->request($currentUrl, $target);

            if (
                $response['http_status'] >= 300
                && $response['http_status'] < 400
            ) {
                $location = $response['headers']['location'] ?? '';

                if (!is_string($location) || trim($location) === '') {
                    throw new RuntimeException(
                        'The source redirected without providing a destination.'
                    );
                }

                $currentUrl = $this->resolveRedirect(
                    $currentUrl,
                    $location
                );

                continue;
            }

            if (
                $response['http_status'] < 200
                || $response['http_status'] >= 400
            ) {
                throw new RuntimeException(
                    'The source returned HTTP status '
                    . $response['http_status']
                    . '.'
                );
            }

            $response['final_url'] = $currentUrl;

            return $response;
        }

        throw new RuntimeException(
            'The source redirected too many times.'
        );
    }

    private function validateTarget(string $url): array
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            throw new InvalidArgumentException('Invalid source URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'Only HTTP and HTTPS sources can be fetched.'
            );
        }

        if ($host === '') {
            throw new InvalidArgumentException(
                'The source URL does not contain a hostname.'
            );
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException(
                'URLs containing usernames or passwords are not allowed.'
            );
        }

        $blockedHosts = [
            'localhost',
            'localhost.localdomain',
        ];

        if (
            in_array($host, $blockedHosts, true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')
        ) {
            throw new InvalidArgumentException(
                'That hostname cannot be fetched.'
            );
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = (int) ($parts['port'] ?? $defaultPort);

        if (!in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException(
                'Only standard web ports are allowed.'
            );
        }

        $ip = $this->resolvePublicIp($host);

        return [
            'host' => $host,
            'port' => $port,
            'ip' => $ip,
        ];
    }

    private function resolvePublicIp(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!$this->isPublicIp($host)) {
                throw new InvalidArgumentException(
                    'Private or reserved IP addresses are not allowed.'
                );
            }

            return $host;
        }

        $records = dns_get_record($host, DNS_A);

        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? '';

                if (is_string($ip) && $this->isPublicIp($ip)) {
                    return $ip;
                }
            }
        }

        $fallback = gethostbyname($host);

        if (
            $fallback !== $host
            && $this->isPublicIp($fallback)
        ) {
            return $fallback;
        }

        throw new RuntimeException(
            'The hostname did not resolve to a public web address.'
        );
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function request(string $url, array $target): array
    {
        $headers = [];
        $body = '';
        $tooLarge = false;

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'The source fetcher could not be started.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT =>
                'Fair Source Research Bot/1.0 (+https://fair.sparklegoat.com)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.5',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_RESOLVE => [
                sprintf(
                    '%s:%d:%s',
                    $target['host'],
                    $target['port'],
                    $target['ip']
                ),
            ],
            CURLOPT_HEADERFUNCTION =>
                static function ($curl, string $line) use (&$headers): int {
                    $length = strlen($line);
                    $trimmed = trim($line);

                    if ($trimmed === '') {
                        return $length;
                    }

                    if (str_starts_with($trimmed, 'HTTP/')) {
                        $headers = [];

                        return $length;
                    }

                    $parts = explode(':', $line, 2);

                    if (count($parts) === 2) {
                        $name = strtolower(trim($parts[0]));
                        $value = trim($parts[1]);

                        $headers[$name] = $value;
                    }

                    return $length;
                },
            CURLOPT_WRITEFUNCTION =>
                static function ($curl, string $chunk) use (
                    &$body,
                    &$tooLarge
                ): int {
                    $remaining = self::MAX_BYTES - strlen($body);

                    if ($remaining <= 0) {
                        $tooLarge = true;

                        return 0;
                    }

                    if (strlen($chunk) > $remaining) {
                        $body .= substr($chunk, 0, $remaining);
                        $tooLarge = true;

                        return 0;
                    }

                    $body .= $chunk;

                    return strlen($chunk);
                },
        ]);

        $result = curl_exec($curl);
        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);

        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        $contentType = (string) (
            curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?? ''
        );

        curl_close($curl);

        $expectedSizeStop = $tooLarge
            && $curlErrorNumber === CURLE_WRITE_ERROR;

        if ($result === false && !$expectedSizeStop) {
            throw new RuntimeException(
                'The source could not be fetched: ' . $curlError
            );
        }

        return [
            'http_status' => $httpStatus,
            'content_type' => $contentType,
            'headers' => $headers,
            'body' => $body,
            'truncated' => $tooLarge,
        ];
    }

    private function resolveRedirect(
        string $baseUrl,
        string $location
    ): string {
        $location = trim($location);

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);

        if (!is_array($base)) {
            throw new RuntimeException(
                'The redirect destination could not be resolved.'
            );
        }

        $scheme = (string) $base['scheme'];
        $host = (string) $base['host'];
        $port = isset($base['port'])
            ? ':' . (int) $base['port']
            : '';

        $authority = $scheme . '://' . $host . $port;

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        $basePath = (string) ($base['path'] ?? '/');

        if (str_starts_with($location, '?')) {
            return $authority . $basePath . $location;
        }

        $directory = preg_replace(
            '#/[^/]*$#',
            '/',
            $basePath
        ) ?? '/';

        return $authority . $directory . $location;
    }

    private function buildHtmlResult(array $response): array
    {
        $document = new DOMDocument();

        libxml_use_internal_errors(true);

        $document->loadHTML(
            $response['body'],
            LIBXML_NOERROR
            | LIBXML_NOWARNING
            | LIBXML_NONET
            | LIBXML_COMPACT
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        $meta = [];

        foreach ($xpath->query('//meta[@content]') ?: [] as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $key = $element->getAttribute('property')
                ?: $element->getAttribute('name')
                ?: $element->getAttribute('itemprop');

            $key = strtolower(trim($key));

            if ($key !== '' && !isset($meta[$key])) {
                $meta[$key] = $this->cleanText(
                    $element->getAttribute('content')
                );
            }
        }

        $title = $meta['og:title'] ?? '';

        if ($title === '') {
            $titleNode = $xpath->query('//title')->item(0);

            if ($titleNode instanceof DOMNode) {
                $title = $this->cleanText(
                    $titleNode->textContent
                );
            }
        }

        if ($title === '') {
            $heading = $xpath->query('//h1')->item(0);

            if ($heading instanceof DOMNode) {
                $title = $this->cleanText(
                    $heading->textContent
                );
            }
        }

        $description = $meta['og:description']
            ?? $meta['description']
            ?? '';

        $siteName = $meta['og:site_name']
            ?? $meta['application-name']
            ?? '';

        $publishedDate = $meta['article:published_time']
            ?? $meta['datepublished']
            ?? $meta['date']
            ?? $meta['dc.date']
            ?? '';

        $canonicalUrl = '';

        $canonical = $xpath
            ->query('//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "canonical")]/@href')
            ->item(0);

        if ($canonical instanceof DOMAttr) {
            $canonicalUrl = trim($canonical->value);
        }

        $host = strtolower(
            (string) parse_url(
                $response['final_url'],
                PHP_URL_HOST
            )
        );

        $organization = $this->guessOrganization(
            $host,
            $response['final_url'],
            $siteName
        );

        $suggestions = $this->buildSuggestions(
            $response['final_url'],
            $title . ' ' . $description
        );

        $warnings = [];

        if ($response['truncated']) {
            $warnings[] =
                'Only the first portion of the page was analyzed.';
        }

        if ($title === '') {
            $warnings[] =
                'No page title was found. Enter the title manually.';
        }

        return [
            'url' => $response['final_url'],
            'canonical_url' => $canonicalUrl,
            'title' => $title,
            'organization' => $organization['name'],
            'organization_type' => $organization['type'],
            'source_type' => $this->guessSourceType($host),
            'publication_version' => $publishedDate,
            'date_checked' => date('Y-m-d'),
            'public_summary' => $description,
            'states' => $suggestions['states'],
            'counties' => $suggestions['counties'],
            'fairs' => $suggestions['fairs'],
            'clubs' => [],
            'projects' => $suggestions['projects'],
            'topics' => $suggestions['topics'],
            'age_groups' => $suggestions['age_groups'],
            'tags' => $suggestions['tags'],
            'http_status' => $response['http_status'],
            'content_type' => $response['content_type'],
            'warnings' => $warnings,
        ];
    }

    private function buildPdfResult(array $response): array
    {
        $path = (string) (
            parse_url($response['final_url'], PHP_URL_PATH) ?? ''
        );

        $title = rawurldecode(basename($path));
        $title = preg_replace('/\.pdf$/i', '', $title) ?? $title;
        $title = str_replace(['-', '_'], ' ', $title);
        $title = $this->cleanText($title);

        $host = strtolower(
            (string) parse_url(
                $response['final_url'],
                PHP_URL_HOST
            )
        );

        $organization = $this->guessOrganization(
            $host,
            $response['final_url'],
            ''
        );

        $suggestions = $this->buildSuggestions(
            $response['final_url'],
            $title
        );

        $lastModified =
            $response['headers']['last-modified'] ?? '';

        return [
            'url' => $response['final_url'],
            'canonical_url' => '',
            'title' => $title,
            'organization' => $organization['name'],
            'organization_type' => $organization['type'],
            'source_type' => 'pdf',
            'publication_version' => $lastModified,
            'date_checked' => date('Y-m-d'),
            'public_summary' => '',
            'states' => $suggestions['states'],
            'counties' => $suggestions['counties'],
            'fairs' => $suggestions['fairs'],
            'clubs' => [],
            'projects' => $suggestions['projects'],
            'topics' => $suggestions['topics'],
            'age_groups' => $suggestions['age_groups'],
            'tags' => $suggestions['tags'],
            'http_status' => $response['http_status'],
            'content_type' => $response['content_type'],
            'warnings' => [
                'PDF title and details may need manual review.',
            ],
        ];
    }

    private function guessOrganization(
        string $host,
        string $url,
        string $siteName
    ): array {
        if ($host === 'canr.msu.edu') {
            if (
                str_contains(
                    strtolower($url),
                    '/ionia/ionia_county_4_h'
                )
            ) {
                return [
                    'name' =>
                        'MSU Extension — Ionia County 4-H',
                    'type' => 'county_4h',
                ];
            }

            return [
                'name' => 'Michigan State University Extension',
                'type' => 'extension',
            ];
        }

        if (
            $host === 'ioniafreefair.com'
            || str_ends_with($host, '.ioniafreefair.com')
        ) {
            return [
                'name' => 'Ionia Free Fair',
                'type' => 'fair',
            ];
        }

        if (
            $host === 'fairentry.com'
            || $host === '4honline.com'
            || str_ends_with($host, '.fairentry.com')
            || str_ends_with($host, '.4honline.com')
        ) {
            return [
                'name' => 'RegistrationMax LLC',
                'type' => 'software_provider',
            ];
        }

        return [
            'name' => $siteName !== '' ? $siteName : $host,
            'type' => '',
        ];
    }

    private function guessSourceType(string $host): string
    {
        if (
            str_contains($host, 'fairentry.com')
            || str_contains($host, '4honline.com')
        ) {
            return 'portal';
        }

        return 'web_page';
    }

    private function buildSuggestions(
        string $url,
        string $text
    ): array {
        $haystack = strtolower($url . ' ' . $text);

        $states = [];
        $counties = [];
        $fairs = [];
        $projects = [];
        $topics = [];
        $ageGroups = [];
        $tags = [];

        if (
            str_contains($haystack, 'msu.edu')
            || str_contains($haystack, 'ionia')
        ) {
            $states[] = 'Michigan';
        }

        if (str_contains($haystack, 'ionia')) {
            $counties[] = 'Ionia County';
        }

        if (
            str_contains($haystack, 'ionia free fair')
            || str_contains($haystack, 'ioniafreefair.com')
        ) {
            $fairs[] = 'Ionia Free Fair';
        }

        $projectKeywords = [
            'goat' => 'Goats',
            'poultry' => 'Poultry',
            'chicken' => 'Poultry',
            'swine' => 'Swine',
            'pig' => 'Swine',
            'rabbit' => 'Rabbits',
            'cavy' => 'Cavy',
            'sheep' => 'Sheep',
            'beef' => 'Beef',
            'cattle' => 'Cattle',
            'horse' => 'Horses',
            'dairy' => 'Dairy',
            'dog' => 'Dogs',
        ];

        foreach ($projectKeywords as $keyword => $project) {
            if (str_contains($haystack, $keyword)) {
                $projects[] = $project;
            }
        }

        $topicKeywords = [
            'enroll' => 'Enrollment',
            'register' => 'Registration',
            'fairentry' => 'FairEntry',
            'record book' => 'Record Books',
            'workbook' => 'Record Books',
            'deadline' => 'Deadlines',
            'auction' => 'Auction',
            'sale' => 'Auction',
            'schedule' => 'Fair Schedule',
            'identification' => 'Animal Identification',
            'health' => 'Animal Health',
            'biosecurity' => 'Biosecurity',
            'volunteer' => 'Volunteers',
        ];

        foreach ($topicKeywords as $keyword => $topic) {
            if (str_contains($haystack, $keyword)) {
                $topics[] = $topic;
            }
        }

        if (str_contains($haystack, 'cloverbud')) {
            $ageGroups[] = 'Cloverbud';
        }

        foreach (array_merge($projects, $topics) as $value) {
            $tags[] = $this->slugify($value);
        }

        if (str_contains($haystack, 'fair')) {
            $tags[] = 'fair';
        }

        if (str_contains($haystack, '4-h')) {
            $tags[] = '4-h';
        }

        return [
            'states' => array_values(array_unique($states)),
            'counties' => array_values(array_unique($counties)),
            'fairs' => array_values(array_unique($fairs)),
            'projects' => array_values(array_unique($projects)),
            'topics' => array_values(array_unique($topics)),
            'age_groups' => array_values(array_unique($ageGroups)),
            'tags' => array_values(array_unique($tags)),
        ];
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(
            preg_replace('/\s+/u', ' ', $value) ?? $value
        );
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}