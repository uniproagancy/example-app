<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HOURS = 4;

    private const SKIPPED_TABLES = ['migrations', 'abandoned_carts', 'price_alerts'];

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            return;
        }

        $schema = $driver === 'sqlite' ? 'main' : DB::connection()->getDatabaseName();

        foreach (Schema::getTableListing($schema, false) as $table) {
            if (in_array($table, self::SKIPPED_TABLES, true)) {
                continue;
            }

            $columns = collect(Schema::getColumns($table))
                ->filter(fn (array $column): bool => in_array(strtolower($column['type_name']), ['timestamp', 'datetime'], true))
                ->pluck('name');

            if ($columns->isEmpty()) {
                continue;
            }

            DB::table($table)->update($columns->mapWithKeys(fn (string $column) => [
                $column => DB::raw($this->expression($driver, $column, $hours)),
            ])->all());
        }
    }

    private function expression(string $driver, string $column, int $hours): string
    {
        $wrapped = DB::getQueryGrammar()->wrap($column);

        if ($driver === 'sqlite') {
            return sprintf("datetime(%s, '%+d hours')", $wrapped, $hours);
        }

        return sprintf('DATE_ADD(%s, INTERVAL %d HOUR)', $wrapped, $hours);
    }
};
