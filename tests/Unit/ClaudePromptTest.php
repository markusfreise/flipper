<?php

namespace Tests\Unit;

use App\Jobs\WriteListingText;
use App\Services\Claude\ClaudeClient;
use App\Services\Claude\IdentifyPrompt;
use App\Services\Claude\ListingTextPrompt;
use App\Services\Claude\PhotoCheckPrompt;
use App\Services\Claude\ResearchPrompt;
use PHPUnit\Framework\TestCase;

class ClaudePromptTest extends TestCase
{
    public function test_all_prompts_contain_the_principles(): void
    {
        foreach ([IdentifyPrompt::system(), PhotoCheckPrompt::system(), ResearchPrompt::system(), ListingTextPrompt::system()] as $system) {
            $this->assertStringContainsString('Das Produkt wird nie verändert', $system);
            $this->assertStringContainsString('Nichts erfinden', $system);
        }
    }

    public function test_text_prompt_rules(): void
    {
        $s = ListingTextPrompt::system();
        $this->assertStringContainsString('"Zustand:" (genau so, nicht "Zustand, ehrlich")', $s);
        $this->assertStringContainsString('Keine Gedankenstriche', $s);
        $this->assertStringContainsString('max. 65 Zeichen', $s);
        $this->assertStringContainsString('Keine Preise im Text', $s);
        $this->assertStringContainsString('invented', $s);
        $this->assertStringContainsString('du-Form', $s);
    }

    public function test_photo_check_never_retouches_details(): void
    {
        $this->assertStringContainsString('Nahaufnahmen von Mängeln werden nicht freigestellt', PhotoCheckPrompt::system());
    }

    public function test_research_prefers_sold_prices(): void
    {
        $this->assertStringContainsString('VERKAUFTE Preise', ResearchPrompt::system());
    }

    public function test_schemas_are_strict(): void
    {
        foreach ([IdentifyPrompt::schema(), PhotoCheckPrompt::schema(), ResearchPrompt::schema(), ListingTextPrompt::schema()] as $schema) {
            $this->assertFalse($schema['additionalProperties']);
            $this->assertSame(array_keys($schema['properties']), $schema['required']);
        }
    }

    public function test_extract_json(): void
    {
        $this->assertSame(['a' => 1], ClaudeClient::extractJson("Hier:\n```json\n{\"a\": 1}\n```"));
        $this->assertSame(['a' => 1], ClaudeClient::extractJson('Ergebnis {"a": 1} fertig'));
    }

    public function test_body_cleanup_removes_dashes_and_markdown(): void
    {
        $body = WriteListingText::cleanBody("**Luna** stand im Regal – jetzt geht sie.\n\n## Zustand, ehrlich:\n• Box 10–15 cm");
        $this->assertSame("Luna stand im Regal, jetzt geht sie.\n\nZustand:\n- Box 10-15 cm", $body);
    }

    public function test_title_is_cut_at_65_on_word_boundary(): void
    {
        $title = WriteListingText::cleanTitle('Funko Pop! Harry Potter #14 Luna Lovegood Vinyl Figur Sammlerstück OVP neuwertig');
        $this->assertLessThanOrEqual(65, mb_strlen($title));
        $this->assertStringEndsNotWith(' ', $title);
        $this->assertStringStartsWith('Funko Pop! Harry Potter #14 Luna Lovegood', $title);
    }
}
