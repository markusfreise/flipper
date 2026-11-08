<?php

namespace Tests\Feature;

use App\Jobs\IdentifyProduct;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Gefunden mit der DJI Osmo 360: KI-Variante mit 126 Zeichen, "Stimmt so" scheiterte mit "validation.max.string". */
class ProductValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_german(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->for($user)->create(['status' => 'identified', 'product' => ['name' => 'x'], 'photo_check' => []]);

        $this->actingAs($user)
            ->putJson("/api/v1/listings/{$listing->id}/product", ['name' => 'DJI Osmo 360', 'variant' => str_repeat('a', 126)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.variant.0', 'Variante darf höchstens 120 Zeichen haben.');
    }

    public function test_ai_values_are_clamped_to_what_the_confirm_step_accepts(): void
    {
        $product = IdentifyProduct::clamp([
            'name' => str_repeat('n', 250),
            'variant' => 'Standardausgabe (vermutlich); Zubehör auf den Fotos: 2 aufsetzbare Objektivschützer, Silikonhülle, USB-C-Kabel, Originalkarton',
            'number' => null,
            'year' => 2025,
        ]);

        foreach (IdentifyProduct::MAX_LENGTHS as $field => $max) {
            if (is_string($product[$field] ?? null)) {
                $this->assertLessThanOrEqual($max, mb_strlen($product[$field]), $field);
            }
        }
        $this->assertNull($product['number']);
        $this->assertSame(2025, $product['year']);

        $user = User::factory()->create();
        $listing = Listing::factory()->for($user)->create(['status' => 'identified', 'product' => $product, 'photo_check' => []]);
        $body = array_intersect_key($product, IdentifyProduct::MAX_LENGTHS + ['year' => 0]);
        $this->actingAs($user)->putJson("/api/v1/listings/{$listing->id}/product", array_filter($body))
            ->assertOk();
    }
}
