<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('chapters')) {
            Schema::table('chapters', function (Blueprint $table): void {
                if (! Schema::hasColumn('chapters', 'name')) {
                    $table->string('name')->nullable()->after('id');
                }

                if (! Schema::hasColumn('chapters', 'location')) {
                    $table->string('location')->nullable()->after('name');
                }

                if (! Schema::hasColumn('chapters', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('status');
                }

                if (! Schema::hasColumn('chapters', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('chapters', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
            });

            DB::table('chapters')
                ->whereNull('name')
                ->update([
                    'name' => DB::raw("COALESCE(chapter_name, chapter_code, 'Unknown Chapter')"),
                ]);

            DB::table('chapters')
                ->whereNull('location')
                ->update([
                    'location' => DB::raw("COALESCE(city, province, region, address, 'Unknown location')"),
                ]);

            DB::table('chapters')
                ->whereNull('is_active')
                ->update(['is_active' => true]);
        }

        if (! Schema::hasTable('chapter_inventories')) {
            Schema::create('chapter_inventories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
                $table->string('blood_type');
                $table->string('component_type');
                $table->integer('units_available')->default(0);
                $table->enum('status', ['adequate', 'low', 'critical'])->default('adequate');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['chapter_id', 'blood_type', 'component_type'], 'chapter_inventory_lookup_idx');
            });
        }

        if (! Schema::hasTable('chapter_api_keys')) {
            Schema::create('chapter_api_keys', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
                $table->string('api_key')->unique();
                $table->string('label')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['chapter_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('chapter_transfer_requests')) {
            Schema::create('chapter_transfer_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('source_chapter_id')->constrained('chapters');
                $table->foreignId('destination_chapter_id')->constrained('chapters');
                $table->string('blood_type');
                $table->string('component_type');
                $table->integer('units_requested');
                $table->enum('priority', ['routine', 'urgent', 'emergency']);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
                $table->timestamps();

                $table->index(['source_chapter_id', 'destination_chapter_id'], 'chapter_transfer_route_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chapter_transfer_requests')) {
            Schema::dropIfExists('chapter_transfer_requests');
        }

        if (Schema::hasTable('chapter_api_keys')) {
            Schema::dropIfExists('chapter_api_keys');
        }

        if (Schema::hasTable('chapter_inventories')) {
            Schema::dropIfExists('chapter_inventories');
        }
    }
};
