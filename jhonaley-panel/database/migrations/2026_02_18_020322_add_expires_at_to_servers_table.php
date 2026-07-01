<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasColumn('servers', 'expires_at')) {
            return; // sudah ada, skip (idempotent)
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasColumn('servers', 'expires_at')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};