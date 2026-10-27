<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('timezone', 64)->default('Asia/Jakarta')->after('password');
            $table->string('status', 20)->default('active')->index()->after('timezone');
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('suspended_at');
            $table->softDeletes();
        });

        User::withTrashed()
            ->whereNull('ulid')
            ->eachById(function (User $user): void {
                $user->forceFill(['ulid' => (string) Str::ulid()])->saveQuietly();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['ulid', 'phone', 'timezone', 'status', 'suspended_at', 'last_login_at']);
        });
    }
};
