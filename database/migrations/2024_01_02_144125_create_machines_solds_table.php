<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
    * Run the migrations.
    */
    public function up(): void
    {
        Schema::create('machines_solds', function (Blueprint $table) {
            $table->id();
            $table->string('model');
            $table->string('brand');
            $table->string('serial_number');
            $table->date('sale_date')->nullable();
            $table->string('old_buyer')->nullable();
            $table->string('buyer')->nullable();
            $table->date('warranty_expiration_date')->nullable();
            $table->date('registration_date')->nullable();
            $table->string('delivery_ddt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unsignedBigInteger('warranty_type_id')->nullable();
            $table->foreign('warranty_type_id')->references('id')->on('warranty_types')->onDelete('set null');
        });
    }
    
    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        Schema::dropIfExists('machines_solds');
    }
};
