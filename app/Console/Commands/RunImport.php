<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Import\ImportManager;
use Illuminate\Console\Command;

class RunImport extends Command
{
    protected $signature = 'import:run
                            {supplier? : the supplier code, e.g. zoommer}
                            {--all : run every active supplier}
                            {--full : walk the whole id range, not just what we hold}
                            {--from= : first supplier id to scan, for this run only}
                            {--to= : last supplier id to scan, for this run only}';

    protected $description = 'Queue a catalogue import for one supplier or all of them';

    public function handle(ImportManager $manager): int
    {
        $suppliers = $this->option('all')
            ? Supplier::active()->get()
            : Supplier::where('code', $this->argument('supplier'))->get();

        if ($suppliers->isEmpty()) {
            $this->error('No supplier matched. Pass a code or use --all.');

            return self::FAILURE;
        }

        foreach ($suppliers as $supplier) {

            if ($this->option('full')) {
                $supplier->config = array_merge($supplier->config ?? [], ['full_scan' => true]);
            }

            if ($this->option('from') !== null || $this->option('to') !== null) {
                $from = (int) ($this->option('from') ?? $supplier->config['from'] ?? 1);
                $to = (int) ($this->option('to') ?? $from);

                if ($from < 1 || $to < $from) {
                    $this->error('--from must be at least 1 and --to must not be below --from.');

                    return self::FAILURE;
                }

                $supplier->config = array_merge($supplier->config ?? [], ['from' => $from, 'to' => $to, 'full_scan' => true]);
            }

            $queued = $manager->run($supplier);
            $this->info("{$supplier->code}: {$queued} products queued on import:{$supplier->code}");
        }

        return self::SUCCESS;
    }
}
