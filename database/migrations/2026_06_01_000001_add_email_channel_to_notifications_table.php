<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Drop existing constraint if any
        DB::statement('ALTER TABLE "notifications" DROP CONSTRAINT IF EXISTS notifications_channel_check');

        // Alter column type and default separately
        DB::statement('ALTER TABLE "notifications" ALTER COLUMN "channel" TYPE varchar(255)');
        DB::statement('ALTER TABLE "notifications" ALTER COLUMN "channel" SET NOT NULL');
        DB::statement('ALTER TABLE "notifications" ALTER COLUMN "channel" SET DEFAULT \'push\'');

        // Add CHECK constraint separately (PostgreSQL requires this)
        DB::statement('ALTER TABLE "notifications" ADD CONSTRAINT notifications_channel_check CHECK ("channel" IN (\'push\', \'sms\', \'email\'))');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE "notifications" DROP CONSTRAINT IF EXISTS notifications_channel_check');
        DB::statement('ALTER TABLE "notifications" ALTER COLUMN "channel" TYPE varchar(255)');
        DB::statement('ALTER TABLE "notifications" ALTER COLUMN "channel" SET DEFAULT \'push\'');
        DB::statement('ALTER TABLE "notifications" ADD CONSTRAINT notifications_channel_check CHECK ("channel" IN (\'push\', \'sms\'))');
    }
};