<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * library_key marks categories/plans imported from the Gymai default library
     * (database/data/fitness-library.php) so re-importing never creates duplicates.
     */
    public function up(): void
    {
        Schema::table('plan_categories', function (Blueprint $table) {
            $table->string('library_key', 80)->nullable()->after('type');
            $table->index(['gym_id', 'library_key']);
        });

        Schema::table('fitness_plans', function (Blueprint $table) {
            $table->string('library_key', 80)->nullable()->after('type');
            $table->index(['gym_id', 'library_key']);
        });
    }

    public function down(): void
    {
        Schema::table('fitness_plans', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'library_key']);
            $table->dropColumn('library_key');
        });
        Schema::table('plan_categories', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'library_key']);
            $table->dropColumn('library_key');
        });
    }
};
