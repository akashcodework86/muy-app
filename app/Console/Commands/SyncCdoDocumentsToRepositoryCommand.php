<?php

namespace App\Console\Commands;

use App\Services\HubBatchService;
use Illuminate\Console\Command;

class SyncCdoDocumentsToRepositoryCommand extends Command
{
    protected $signature = 'documents:sync-cdo {--batch-id= : Sync only one onboarding_batches.id}';

    protected $description = 'Sync hub CDO upload PDFs into admin document repository categories';

    public function handle(): int
    {
        $batchIdOpt = $this->option('batch-id');
        $batchId = ($batchIdOpt !== null && $batchIdOpt !== '') ? (int) $batchIdOpt : null;

        $result = app(HubBatchService::class)->backfillCdoDocuments($batchId);

        $this->info('CDO document sync complete.');
        $this->line('Scanned: '.$result['scanned']);
        $this->line('Synced: '.$result['synced']);
        $this->line('Skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
