<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dlr_sync_operations', function (Blueprint $table) {
            $table->string('sync_id', 64)->primary();  // content-hash from SyncId
            $table->string('model_class', 255)->index();
            $table->string('model_id', 64)->index();
            $table->string('operation', 20);            // created|updated|deleted
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->timestamp('model_updated_at')->nullable();
            $table->timestamps();

            // Common query: "give me all failed ops for model X"
            $table->index(['model_class', 'status']);
            // Common query: "all pending ops ordered by age"
            $table->index(['status', 'created_at']);
            // Multi-tenant: "all dead ops for tenant Y"
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dlr_sync_operations');
    }
};
