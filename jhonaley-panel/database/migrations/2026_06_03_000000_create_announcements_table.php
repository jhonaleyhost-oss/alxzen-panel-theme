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
        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->text('content');
                $table->enum('type', ['info', 'warning', 'critical', 'promo'])->default('info');
                $table->tinyInteger('priority')->default(2);
                $table->boolean('is_active')->default(false);
                $table->json('target_display')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        } else {
            Schema::table('announcements', function (Blueprint $table) {
                if (!Schema::hasColumn('announcements', 'title')) {
                    $table->string('title', 255)->default('Announcement');
                }
                if (!Schema::hasColumn('announcements', 'content')) {
                    $table->text('content')->nullable();
                }
                if (!Schema::hasColumn('announcements', 'type')) {
                    $table->string('type', 20)->default('info')->after('content');
                }
                if (!Schema::hasColumn('announcements', 'priority')) {
                    $table->tinyInteger('priority')->default(2)->after('type');
                }
                if (!Schema::hasColumn('announcements', 'is_active')) {
                    $table->boolean('is_active')->default(false)->after('priority');
                }
                if (!Schema::hasColumn('announcements', 'target_display')) {
                    $table->json('target_display')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('announcements', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('target_display');
                }
                if (!Schema::hasColumn('announcements', 'created_by')) {
                    $table->unsignedInteger('created_by')->nullable()->after('expires_at');
                }
                if (!Schema::hasColumn('announcements', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (!Schema::hasTable('announcement_reads')) {
            Schema::create('announcement_reads', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('announcement_id');
                $table->timestamp('read_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('announcement_id')->references('id')->on('announcements')->onDelete('cascade');
                $table->unique(['user_id', 'announcement_id']);
            });
        } else {
            Schema::table('announcement_reads', function (Blueprint $table) {
                if (!Schema::hasColumn('announcement_reads', 'user_id')) {
                    $table->unsignedInteger('user_id')->after('id');
                }
                if (!Schema::hasColumn('announcement_reads', 'announcement_id')) {
                    $table->unsignedBigInteger('announcement_id')->after('user_id');
                }
                if (!Schema::hasColumn('announcement_reads', 'read_at')) {
                    $table->timestamp('read_at')->useCurrent()->after('announcement_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
