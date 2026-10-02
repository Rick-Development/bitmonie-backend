<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columns = collect(DB::select('SHOW COLUMNS FROM beneficiaries'));
        $idColumn = $columns->firstWhere('Field', 'id');

        if (!$idColumn) {
            return;
        }

        $autoIncrementColumns = $columns->filter(function ($column) {
            return str_contains(strtolower((string) $column->Extra), 'auto_increment');
        });

        if ($autoIncrementColumns->isNotEmpty() && !$autoIncrementColumns->contains('Field', 'id')) {
            throw new RuntimeException('Cannot make beneficiaries.id AUTO_INCREMENT because another column is already AUTO_INCREMENT.');
        }

        $indexes = collect(DB::select('SHOW INDEX FROM beneficiaries'));
        $idHasKey = $indexes->contains('Column_name', 'id');

        if (!$idHasKey) {
            $hasPrimaryKey = $indexes->contains('Key_name', 'PRIMARY');

            if ($hasPrimaryKey) {
                DB::statement('ALTER TABLE beneficiaries ADD INDEX beneficiaries_id_index (id)');
            } else {
                DB::statement('ALTER TABLE beneficiaries ADD PRIMARY KEY (id)');
            }
        }

        DB::statement('ALTER TABLE beneficiaries MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE beneficiaries MODIFY id BIGINT UNSIGNED NOT NULL');
    }
};
