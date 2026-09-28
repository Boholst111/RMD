<?php

namespace Tests\Unit;

use Database\Seeders\WoodScalingSeeder;
use Tests\TestCase;

class WoodScalingSeederTest extends TestCase
{
    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);

        (new WoodScalingSeeder())->run();
    }
}