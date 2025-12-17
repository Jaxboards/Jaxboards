<?php

declare(strict_types=1);

namespace Jax;

use Dom\HTMLDocument;
use DOMDocument;
use Exception;

use function array_slice;
use function class_exists;
use function filter_var;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_substr;
use function str_starts_with;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_URL;

final readonly class OpenGraph
{
    public function __construct(
        private BBCode $bbCode,
        private FileSystem $fileSystem,
    ) {}

    /**
     * Fetches document and returns a key/value pair of open graph property/content pairs.
     *
     * Returns empty array when page cannot be loaded or parsed.
     *
     * @return array<string,string> Property/Content pairs for OpenGraph data
     */
    public function fetch(string $url): array
    {
        $url = $this->filterHTTPURL($url);

        if ($url === null) {
            return [];
        }

        try {
            $contents = $this->fileSystem->getContents($url);
            $metaValues = [];

            libxml_use_internal_errors(true);

            // HTMLDocument is the better choice but only exists in >=8.4
            // Also looked at get_meta_tags but it seemed fragile so didn't use it
            if (class_exists(HTMLDocument::class)) {
                $doc = HTMLDocument::createFromString($contents);
                $metaTags = $doc->querySelectorAll('meta[property^="og:"]');
                foreach ($metaTags as $metumTag) {
                    $metaValues[mb_substr((string) $metumTag->getAttribute('property'), 3)] = $metumTag->getAttribute('content');
                }

                // Fall back to DOMDocument
            } else {
                $doc = new DOMDocument();
                $doc->loadHTML($contents);
                $metaTags = $doc->getElementsByTagName('meta');
                foreach ($metaTags as $metumTag) {
                    $property = $metumTag->getAttribute('property');
                    if (!str_starts_with($property, 'og:')) {
                        continue;
                    }

                    $metaValues[mb_substr($property, 3)] = $metumTag->getAttribute('content');
                }
            }

            libxml_clear_errors();

            return $metaValues;
        } catch (Exception) {
            return [];
        }
    }

    public function fetchFromBBCode(string $text): array
    {
        $openGraphData = [];

        // Limit # of embeddings to prevent abuse
        $urls = array_slice($this->bbCode->getURLs($text), 0, 3);

        foreach ($urls as $url) {
            $data = $this->fetch($url);
            if ($data === []) {
                continue;
            }

            $openGraphData[$url] = $data;
        }

        return $openGraphData;
    }

    private function filterHTTPURL(?string $url): ?string
    {
        $url = filter_var($url, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE);

        if (
            !is_string($url)
            || (
                !str_starts_with($url, 'http:')
                && !str_starts_with($url, 'https:')
            )
        ) {
            return null;
        }

        return $url;
    }
}
