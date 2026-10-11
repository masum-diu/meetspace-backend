<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sign-in moved from Google directly to Clerk; the column now holds the Clerk user id.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('google_id', 'clerk_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('clerk_id', 'google_id');
        });
    }
};
