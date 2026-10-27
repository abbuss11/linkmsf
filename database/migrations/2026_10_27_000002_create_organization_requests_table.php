<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-level table: the organisation does not exist yet when the request is filed.
        Schema::create('organization_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('organization_name');
            $table->string('contact_name');
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('country', 2)->default('NE');
            $table->text('message')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_requests');
    }
};
