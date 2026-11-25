<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('nav_servers')->default(true);
            $table->boolean('nav_shared')->default(true);
            $table->boolean('nav_reseller')->default(true);
            $table->boolean('nav_domains')->default(true);
        });
    }

    public function down(): void
    {
        // Preserve settings when rolling back; restore a verified backup if needed.
    }
};
