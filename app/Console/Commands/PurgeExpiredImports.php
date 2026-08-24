<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('imports:purge {--hours=24 : Age in hours after which files are deleted}')]
#[Description('Delete orphaned import uploads, sessions and error reports older than the given age')]
class PurgeExpiredImports extends Command
{
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk(config('import-export.storage.disk', 'local'));
        $deleted = 0;

        foreach ([
            config('import-export.storage.uploads'),
            config('import-export.storage.sessions'),
            config('import-export.storage.error_reports'),
        ] as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }
            foreach ($disk->files($dir) as $file) {
                if ($disk->lastModified($file) < $cutoff) {
                    $disk->delete($file);
                    $deleted++;
                }
            }
        }

        $this->info("Purged {$deleted} expired import file(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
