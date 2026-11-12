<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HardenDevicesTable extends Migration
{
    /**
     * The original devices table had no index on user_id and allowed the same
     * FCM token to be inserted many times, so a device could receive the same
     * push twice and lookups by user were full table scans.
     */
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'platform')) {
                $table->string('platform')->nullable()->after('device_name');
            }

            if (!Schema::hasColumn('devices', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('platform');
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->index('user_id', 'devices_user_id_index');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->unique('device_token', 'devices_device_token_unique');
        });
    }

    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique('devices_device_token_unique');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_user_id_index');
        });

        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'last_used_at')) {
                $table->dropColumn('last_used_at');
            }

            if (Schema::hasColumn('devices', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }
}
