<?php

namespace Tests\Unit;

use App\Models\PriceMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPriceMatrixLayoutTest extends TestCase
{
	use RefreshDatabase;

	public function test_opening_admin_dashboard_preserves_price_matrix_rows(): void
	{
		$user = User::factory()->create([
			'role' => 'super_admin',
			'status' => 'active',
		]);

		$priceMatrix = PriceMatrix::create([
			'category' => 'LAUAN',
			'length' => 2.6,
			'dia_min' => 16,
			'dia_max' => 18,
			'price_per_cu_m' => 1500,
		]);

		$this->actingAs($user)
			->get(route('admin.dashboard'))
			->assertOk();

		$this->assertDatabaseHas('price_matrices', [
			'id' => $priceMatrix->id,
			'category' => 'LAUAN',
			'price_per_cu_m' => 1500,
		]);
	}
}
