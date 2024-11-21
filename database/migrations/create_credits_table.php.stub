<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('creditable');
            $table->decimal('amount', 10, 2);
            $table->decimal('running_balance', 10, 2);
            $table->string('description', 255)->nullable();
            $table->string('type');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
            $table->index(['creditable_id', 'creditable_type', 'created_at']);
            $table->index(['creditable_id', 'creditable_type', 'running_balance']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('credits');
    }
};
