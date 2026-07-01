<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("\n                CREATE TABLE notifications_new (\n                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,\n                    user_id INTEGER NULL,\n                    type VARCHAR(100) NOT NULL,\n                    channel VARCHAR NOT NULL CHECK (channel IN ('push', 'sms', 'email')),\n                    status VARCHAR NOT NULL CHECK (status IN ('sent', 'failed', 'skipped')),\n                    response TEXT NULL,\n                    sent_at DATETIME NULL,\n                    created_at DATETIME NULL,\n                    updated_at DATETIME NULL,\n                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL\n                )\n            ");

            DB::statement('INSERT INTO notifications_new (id, user_id, type, channel, status, response, sent_at, created_at, updated_at) SELECT id, user_id, type, channel, status, response, sent_at, created_at, updated_at FROM notifications');
            DB::statement('DROP TABLE notifications');
            DB::statement('ALTER TABLE notifications_new RENAME TO notifications');

            DB::statement('CREATE INDEX notifications_user_id_channel_index ON notifications (user_id, channel)');
            DB::statement('CREATE INDEX notifications_status_sent_at_index ON notifications (status, sent_at)');
            DB::statement('CREATE INDEX notifications_type_index ON notifications (type)');

            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE notifications MODIFY status ENUM('sent','failed','skipped') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "notifications" DROP CONSTRAINT IF EXISTS notifications_status_check');
            DB::statement('ALTER TABLE "notifications" ALTER COLUMN "status" TYPE varchar(255)');
            DB::statement('ALTER TABLE "notifications" ALTER COLUMN "status" SET NOT NULL');
            DB::statement('ALTER TABLE "notifications" ADD CONSTRAINT notifications_status_check CHECK ("status" IN (\'sent\', \'failed\', \'skipped\'))');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("\n                CREATE TABLE notifications_old (\n                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,\n                    user_id INTEGER NULL,\n                    type VARCHAR(100) NOT NULL,\n                    channel VARCHAR NOT NULL CHECK (channel IN ('push', 'sms')),\n                    status VARCHAR NOT NULL CHECK (status IN ('sent', 'failed')),\n                    response TEXT NULL,\n                    sent_at DATETIME NULL,\n                    created_at DATETIME NULL,\n                    updated_at DATETIME NULL,\n                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL\n                )\n            ");

            DB::statement("INSERT INTO notifications_old (id, user_id, type, channel, status, response, sent_at, created_at, updated_at) SELECT id, user_id, type, CASE WHEN channel = 'email' THEN 'sms' ELSE channel END, CASE WHEN status = 'skipped' THEN 'failed' ELSE status END, response, sent_at, created_at, updated_at FROM notifications");
            DB::statement('DROP TABLE notifications');
            DB::statement('ALTER TABLE notifications_old RENAME TO notifications');

            DB::statement('CREATE INDEX notifications_user_id_channel_index ON notifications (user_id, channel)');
            DB::statement('CREATE INDEX notifications_status_sent_at_index ON notifications (status, sent_at)');
            DB::statement('CREATE INDEX notifications_type_index ON notifications (type)');

            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE notifications MODIFY status ENUM('sent','failed') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "notifications" DROP CONSTRAINT IF EXISTS notifications_status_check');
            DB::statement('ALTER TABLE "notifications" ALTER COLUMN "status" TYPE varchar(255)');
            DB::statement('ALTER TABLE "notifications" ALTER COLUMN "status" SET NOT NULL');
            DB::statement('ALTER TABLE "notifications" ADD CONSTRAINT notifications_status_check CHECK ("status" IN (\'sent\', \'failed\'))');
        }
    }
};
