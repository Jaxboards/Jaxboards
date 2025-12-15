<?php

declare(strict_types=1);

namespace Jax;

use Dom\HTMLDocument;
use DOMDocument;
use Exception;

class OpenGraph {

    private readonly ?string $url;

    public function __construct(string $url) {
        $this->url = $this->filterHTTPURL($url);
    }

    /**
     * Fetches document and returns a key/value pair of open graph property/content pairs.
     *
     * Returns empty array when page cannot be loaded or parsed.
     *
     * @return array<string,string> Property/Content pairs for OpenGraph data
     */
    public function fetch(): array
    {
        if ($this->url === null) {
            return [];
        }

        try {
            $contents = file_get_contents($this->url);
            $metaValues = [];

            // HTMLDocument is the better choice but only exists in >=8.4
            // Also looked at get_meta_tags but it seemed fragile so didn't use it
            if (class_exists(HTMLDocument::class)) {
                $doc = HTMLDocument::createFromString($contents);
                $metaTags = $doc->querySelectorAll('meta[property^=og:]');
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

            return $metaValues;

        } catch (Exception) {
            return [];
        }
    }

    private function filterHTTPURL(?string $url): ?string {
        $url = filter_var($url, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE);

        if (!is_string($url) || (!str_starts_with($url, 'http:') && !str_starts_with($url, 'https:'))) {
            return null;
        }

        return $url;
    }
}
