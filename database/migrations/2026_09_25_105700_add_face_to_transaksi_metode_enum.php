<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transaksi MODIFY metode ENUM('tunai', 'online', 'face') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transaksi MODIFY metode ENUM('tunai', 'online') NOT NULL");
    }
};
