<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_wp_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name')->default('default');
            $table->text('plugins')->nullable();
            $table->text('themes')->nullable();
            $table->json('defaults')->nullable();
            $table->text('sidebar_widget')->nullable();
            $table->unsignedInteger('max_sites_per_server')->default(50);
            $table->unsignedInteger('max_concurrent_per_server')->default(3);
            $table->unsignedInteger('max_concurrent_global')->default(10);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('bulk_wp_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('batch_id')->index();
            $table->string('domain');
            $table->unsignedBigInteger('server_id');
            $table->string('title')->nullable();
            $table->string('tagline')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('admin_username')->nullable();
            $table->string('admin_email')->nullable();
            $table->text('admin_password')->nullable();
            $table->text('plugins')->nullable();
            $table->string('theme')->nullable();
            $table->string('status')->default('pending');
            $table->string('current_step')->nullable();
            $table->text('error')->nullable();
            $table->text('app_password')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('bulk_wp_configs')->onDelete('set null');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('set null');
            $table->index(['server_id', 'status']);
        });

        Schema::create('bulk_wp_server_capacities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id')->unique();
            $table->unsignedInteger('max_sites')->default(50);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_wp_sites');
        Schema::dropIfExists('bulk_wp_server_capacities');
        Schema::dropIfExists('bulk_wp_configs');
    }
};
