<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'serve',
    description: 'Serve the application on the PHP development server (50 MB upload limit)',
)]
class ServeApplicationCommand extends ServeCommand
{
    /**
     * @return list<string>
     */
    protected function serverCommand()
    {
        $command = parent::serverCommand();
        $ini = base_path('php.ini');

        if (is_string($ini) && file_exists($ini)) {
            array_splice($command, 1, 0, ['-c', $ini]);
        } else {
            array_splice($command, 1, 0, [
                '-d', 'post_max_size=64M',
                '-d', 'upload_max_filesize=50M',
                '-d', 'max_file_uploads=20',
            ]);
        }

        return $command;
    }
}
