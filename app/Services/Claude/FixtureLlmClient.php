<?php

namespace App\Services\Claude;

use App\Contracts\LlmClient;

/**
 * Antwortet mit den Fixtures aus dem Luna-Referenzlauf (tests/Fixtures/luna).
 * Für Tests und für lokale Entwicklung ohne API-Key: FLOHMARKT_FAKE_AI=true.
 */
class FixtureLlmClient implements LlmClient
{
    /** @var list<array{system: string, content: array, webSearches: int}> */
    public array $calls = [];

    public function __construct(private readonly string $dir) {}

    public function json(string $system, array $content, array $schema, int $webSearches = 0): array
    {
        $this->calls[] = compact('system', 'content', 'webSearches');
        $keys = $schema['required'] ?? [];

        $file = match (true) {
            in_array('confidence', $keys, true) => 'identify',
            in_array('missing', $keys, true) => 'photo_check',
            in_array('sold_prices', $keys, true) => 'research',
            in_array('body', $keys, true) => 'text',
            default => throw new ClaudeException('Kein Fixture für dieses Schema.'),
        };

        $data = json_decode((string) file_get_contents("{$this->dir}/{$file}.json"), true);
        $sources = $file === 'research' ? ['https://www.ebay.de/sch/i.html?_nkw=funko+luna+14&LH_Sold=1'] : [];

        return ['data' => $data, 'sources' => $sources];
    }
}
