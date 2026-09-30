<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_matrices', function (Blueprint $table) {
            $table->decimal('price_per_cu_m', 15, 2)->change();
        });

        Schema::table('truck_loads', function (Blueprint $table) {
            $table->decimal('drivers_assistance', 15, 2)->default(0)->change();
            $table->decimal('expenses_deduction', 15, 2)->default(0)->change();
            $table->decimal('travel_paper_deduction', 15, 2)->default(0)->change();
            $table->decimal('trucking_deduction', 15, 2)->default(0)->change();
            $table->decimal('cash_advance', 15, 2)->default(0)->change();
            $table->decimal('other_deduction_amount', 15, 2)->default(0)->change();
            $table->unsignedBigInteger('total_logs')->default(0)->change();
            $table->decimal('total_volume', 15, 4)->default(0)->change();
            $table->decimal('gross_amount', 15, 2)->default(0)->change();
            $table->decimal('total_deductions', 15, 2)->default(0)->change();
            $table->decimal('net_payable', 15, 2)->default(0)->change();
        });

        Schema::table('scale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('quantity')->default(1)->change();
            $table->decimal('volume', 15, 4)->change();
            $table->decimal('total_volume', 15, 4)->change();
            $table->decimal('price_per_cu_m', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('scale_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
            $table->decimal('volume', 10, 4)->change();
            $table->decimal('total_volume', 10, 4)->change();
            $table->decimal('price_per_cu_m', 10, 2)->change();
            $table->decimal('subtotal', 10, 2)->change();
        });

        Schema::table('truck_loads', function (Blueprint $table) {
            $table->decimal('drivers_assistance', 10, 2)->default(0)->change();
            $table->decimal('expenses_deduction', 10, 2)->default(0)->change();
            $table->decimal('travel_paper_deduction', 10, 2)->default(0)->change();
            $table->decimal('trucking_deduction', 10, 2)->default(0)->change();
            $table->decimal('cash_advance', 10, 2)->default(0)->change();
            $table->decimal('other_deduction_amount', 10, 2)->default(0)->change();
            $table->integer('total_logs')->default(0)->change();
            $table->decimal('total_volume', 10, 4)->default(0)->change();
            $table->decimal('gross_amount', 10, 2)->default(0)->change();
            $table->decimal('total_deductions', 10, 2)->default(0)->change();
            $table->decimal('net_payable', 10, 2)->default(0)->change();
        });

        Schema::table('price_matrices', function (Blueprint $table) {
            $table->decimal('price_per_cu_m', 10, 2)->change();
        });
    }
};
