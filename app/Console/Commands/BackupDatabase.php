<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup 
        {--output= : Custom backup file path}
        {--force : Backup even if database is small}';

    protected $description = 'Create a backup of the SQLite database';

    public function handle(): int
    {
        $dbPath = database_path('database.sqlite');

        if (! File::exists($dbPath)) {
            $this->error('Database file not found: '.$dbPath);

            return self::FAILURE;
        }

        $dbSize = File::size($dbPath);

        // Skip small databases unless forced
        if ($dbSize < 102400 && ! $this->option('force')) {
            $this->warn('Database is too small ('.$this->formatBytes($dbSize).') - likely empty.');
            $this->info('Use --force to backup anyway.');

            return self::FAILURE;
        }

        // Determine backup path
        if ($this->option('output')) {
            $backupPath = $this->option('output');
        } else {
            $backupDir = database_path('backups');

            if (! File::isDirectory($backupDir)) {
                File::makeDirectory($backupDir, 0755, true);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupPath = $backupDir.'/database_backup_'.$timestamp.'.sqlite';
        }

        // Create backup
        $this->info('Creating backup...');
        File::copy($dbPath, $backupPath);

        // Verify backup
        if (File::exists($backupPath) && File::size($backupPath) === $dbSize) {
            $this->info('✅ Database backed up successfully!');
            $this->info('   Source: '.$dbPath);
            $this->info('   Backup: '.$backupPath);
            $this->info('   Size:   '.$this->formatBytes($dbSize));

            // Clean old backups (keep last 10)
            $this->cleanOldBackups();

            return self::SUCCESS;
        } else {
            $this->error('❌ Backup failed - file verification error');

            return self::FAILURE;
        }
    }

    protected function cleanOldBackups(): void
    {
        $backupDir = database_path('backups');

        if (! File::isDirectory($backupDir)) {
            return;
        }

        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'database_backup_'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($backups->count() > 10) {
            $deleted = 0;
            foreach ($backups->slice(10) as $oldBackup) {
                File::delete($oldBackup);
                $deleted++;
            }

            if ($deleted > 0) {
                $this->info("   Cleaned $deleted old backup(s)");
            }
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        } else {
            return $bytes.' bytes';
        }
    }
}
