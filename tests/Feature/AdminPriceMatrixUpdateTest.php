<?php

namespace Tests\Feature;

use App\Models\PriceMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPriceMatrixUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_update_persists_to_all_duplicate_rows_for_the_same_diameter_range(): void
    {
        $user = User::factory()->create([
            'name' => 'Super Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $first = PriceMatrix::create([
            'category' => 'FALCATA',
            'length' => 2.6,
            'dia_min' => 16,
            'dia_max' => 18,
            'price_per_cu_m' => 1400,
        ]);

        $second = PriceMatrix::create([
            'category' => 'FALCATA',
            'length' => 2.6,
            'dia_min' => 16,
            'dia_max' => 18,
            'price_per_cu_m' => 1400,
        ]);

        $response = $this->actingAs($user)->post(route('admin.prices.update'), [
            'prices' => [
                ['id' => $first->id, 'price' => 1600],
            ],
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertSame(1600.0, (float) PriceMatrix::find($first->id)->price_per_cu_m);
        $this->assertSame(1600.0, (float) PriceMatrix::find($second->id)->price_per_cu_m);
    }
}
