<?php

namespace Tests\Unit;

use App\Services\Pricing\ShippingAdvisor;
use Tests\TestCase;

class ShippingAdvisorTest extends TestCase
{
    private function advise(?array $dims, ?float $weight = 300, ?float $value = 10): array
    {
        return (new ShippingAdvisor)->advise(['dimensions_cm' => $dims, 'weight_g' => $weight], $value);
    }

    public function test_small_box_is_paeckchen_s(): void
    {
        $r = $this->advise(['length' => 16.5, 'width' => 11.5, 'height' => 9]);
        $this->assertSame('DHL Päckchen S', $r['ebay']['service']);
        $this->assertSame(4.19, $r['ebay']['price_eur']);
    }

    public function test_medium_box_is_paeckchen_m(): void
    {
        $this->assertSame('DHL Päckchen M', $this->advise(['length' => 40, 'width' => 25, 'height' => 10])['ebay']['service']);
    }

    public function test_valuable_items_go_insured(): void
    {
        $this->assertSame('DHL Paket bis 2 kg', $this->advise(['length' => 16, 'width' => 11, 'height' => 9], 300, 80)['ebay']['service']);
    }

    public function test_too_big_or_heavy(): void
    {
        $r = $this->advise(['length' => 80, 'width' => 40, 'height' => 30], 5000);
        $this->assertNull($r['ebay']['price_eur']);
    }

    public function test_unknown_dimensions(): void
    {
        $r = $this->advise(null);
        $this->assertSame('DHL Päckchen M', $r['ebay']['service']);
        $this->assertStringContainsString('nachmessen', $r['ebay']['note']);
    }

    public function test_kleinanzeigen_text_is_constant(): void
    {
        $this->assertSame('über Kleinanzeigen versichert, ab 3,99 €', $this->advise(null)['kleinanzeigen_text']);
        $this->assertSame('über Kleinanzeigen versichert, ab 3,99 €', $this->advise(['length' => 16, 'width' => 11, 'height' => 9])['kleinanzeigen_text']);
    }
}
