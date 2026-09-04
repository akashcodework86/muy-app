<?php

namespace App\Console\Commands;

use App\Services\IncubateeProvisioningService;
use Illuminate\Console\Command;

class ConvertIncubateePhoneLoginsCommand extends Command
{
    protected $signature = 'incubatees:convert-phone-logins
                            {--dry-run : Show counts without writing}';

    protected $description = 'Switch existing incubatee accounts to 10-digit mobile login (password = mobile when converting)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = app(IncubateeProvisioningService::class)->convertExisting($dry);

        $this->info("Converted: {$result['converted']}, already on phone login: {$result['skipped']}, missing phone: {$result['missing']}, duplicate phone: {$result['duplicate']}.");
        if ($dry) {
            $this->comment('Dry run — no passwords were changed.');
        } else {
            $this->comment('Converted accounts now log in with mobile number. Initial password is that mobile number until they change it.');
        }

        return self::SUCCESS;
    }
}
