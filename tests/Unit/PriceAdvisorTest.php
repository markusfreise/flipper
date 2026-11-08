<?php

namespace Tests\Unit;

use App\Services\Pricing\PriceAdvisor;
use PHPUnit\Framework\TestCase;

class PriceAdvisorTest extends TestCase
{
    private function research(array $prices): array
    {
        return ['sold_prices' => array_map(fn ($p) => ['price_eur' => $p, 'source_url' => 'https://example.com/'.$p, 'note' => null], $prices)];
    }

    public function test_luna_reference_run(): void
    {
        $r = (new PriceAdvisor)->advise($this->research([10, 12, 13, 11.5]));

        $this->assertSame(11.75, $r['median_eur']);
        $this->assertSame(12.0, $r['kleinanzeigen_eur']);
        $this->assertSame(14.9, $r['ebay_eur']);
        $this->assertCount(4, $r['sources']);
    }

    public function test_median_odd_and_even(): void
    {
        $this->assertSame(3.0, PriceAdvisor::median([5, 1, 3]));
        $this->assertSame(2.5, PriceAdvisor::median([4, 1, 3, 2]));
    }

    public function test_rounding_to_even_amounts(): void
    {
        $this->assertSame(1.0, PriceAdvisor::roundEven(0.3));
        $this->assertSame(17.0, PriceAdvisor::roundEven(16.6));
        $this->assertSame(45.0, PriceAdvisor::roundEven(43));
        $this->assertSame(130.0, PriceAdvisor::roundEven(127));
    }

    public function test_ebay_is_twenty_percent_more_and_ends_in_90(): void
    {
        $this->assertSame(14.9, PriceAdvisor::ebay(12));
        $this->assertSame(59.9, PriceAdvisor::ebay(50));
    }

    public function test_condition_deduction(): void
    {
        $r = (new PriceAdvisor)->advise($this->research([40]), ['Gebrauchsspuren']);

        $this->assertSame(30.0, $r['kleinanzeigen_eur']);
        $this->assertStringContainsString('25 % Abschlag', $r['rationale']);
    }

    public function test_no_sold_prices(): void
    {
        $r = (new PriceAdvisor)->advise(['sold_prices' => []]);

        $this->assertNull($r['kleinanzeigen_eur']);
        $this->assertStringContainsString('Keine verkauften', $r['rationale']);
    }

    public function test_us_prices_are_marked(): void
    {
        $r = (new PriceAdvisor)->advise(['sold_prices' => [['price_eur' => 20, 'source_url' => null, 'note' => 'US-Preis, Orientierung']]]);

        $this->assertStringContainsString('US-Preise', $r['rationale']);
    }
}
