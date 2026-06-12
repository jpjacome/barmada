<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push-notification device registry for the staff mobile app (Phase 0 of
 * the Flutter track). Registration only — FCM delivery lands with the
 * push infrastructure in a later PR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 64);
            $table->string('name')->nullable();
            $table->string('platform', 16)->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_devices');
    }
};
