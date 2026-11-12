<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the data needed to close a visit from the mobile app.
 *
 * The guests table had no place to store what happened during the meeting nor
 * when it ended, so an accepted visit could never leave status 1. status gains
 * the value 3 (completed); the original column comment only documents
 * 0/1/-1/2 and is left untouched to avoid an ALTER blocked on MySQL < 8.
 *
 * The user_id + status composite index backs the new mobile queries
 * ("accepted visits of this ASN", "pending visits of this ASN"), which
 * otherwise scan by user_id and filter in memory.
 */
class AddCompletionToGuestsTable extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            if (!Schema::hasColumn('guests', 'meeting_result')) {
                $table->text('meeting_result')->nullable()->after('status');
            }

            if (!Schema::hasColumn('guests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('meeting_result');
            }
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'guests_user_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex('guests_user_id_status_index');
        });

        Schema::table('guests', function (Blueprint $table) {
            if (Schema::hasColumn('guests', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('guests', 'meeting_result')) {
                $table->dropColumn('meeting_result');
            }
        });
    }
}
