<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RestoreDatabase extends Command
{
    protected $signature = 'db:restore 
        {backup? : Path to backup file or backup filename}
        {--list : List available backups}
        {--latest : Restore the most recent backup}';

    protected $description = 'Restore database from a backup file';

    public function handle(): int
    {
        $backupDir = database_path('backups');

        // List available backups
        if ($this->option('list')) {
            return $this->listBackups($backupDir);
        }

        // Get backup file to restore
        $backupPath = $this->getBackupPath($backupDir);

        if (! $backupPath) {
            return self::FAILURE;
        }

        // Confirm before restoring
        $this->warn('⚠️  This will replace your current database!');
        $this->info('   Current: '.database_path('database.sqlite'));
        $this->info('   Backup:  '.$backupPath);

        if (! $this->confirm('Do you want to continue?', false)) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        // Backup current database before restoring
        $currentBackup = database_path('database_before_restore_'.now()->format('Y-m-d_H-i-s').'.sqlite');
        if (File::exists(database_path('database.sqlite'))) {
            File::copy(database_path('database.sqlite'), $currentBackup);
            $this->info('📦 Current database backed up to: '.basename($currentBackup));
        }

        // Restore backup
        File::copy($backupPath, database_path('database.sqlite'));

        // Verify restoration
        if (File::exists(database_path('database.sqlite'))) {
            $this->info('✅ Database restored successfully!');
            $this->info('   From: '.$backupPath);
            $this->newLine();
            $this->warn('⚠️  Remember to run: php artisan migrate');
            $this->info('   (to apply any migrations that were added after this backup)');

            return self::SUCCESS;
        } else {
            $this->error('❌ Restore failed!');

            return self::FAILURE;
        }
    }

    protected function getBackupPath(string $backupDir): ?string
    {
        // Option: --latest
        if ($this->option('latest')) {
            $latestBackup = $this->getLatestBackup($backupDir);

            if (! $latestBackup) {
                $this->error('No backups found in: '.$backupDir);

                return null;
            }

            return $latestBackup;
        }

        // Argument: backup file/path
        if ($this->argument('backup')) {
            $input = $this->argument('backup');

            // Full path provided
            if (File::exists($input)) {
                return $input;
            }

            // Filename provided (look in backup directory)
            $path = $backupDir.'/'.$input;
            if (File::exists($path)) {
                return $path;
            }

            $this->error('Backup file not found: '.$input);

            return null;
        }

        // No argument - show list and ask user to choose
        $backups = $this->getBackupsList($backupDir);

        if (empty($backups)) {
            $this->error('No backups found in: '.$backupDir);

            return null;
        }

        $this->info('Available backups:');
        $this->table(
            ['#', 'Filename', 'Size', 'Date'],
            $backups
        );

        $choice = $this->ask('Enter backup number to restore (or 0 to cancel)');

        if ($choice == 0 || ! isset($backups[$choice - 1])) {
            $this->info('Cancelled.');

            return null;
        }

        return $backupDir.'/'.$backups[$choice - 1][1];
    }

    protected function listBackups(string $backupDir): int
    {
        $backups = $this->getBackupsList($backupDir);

        if (empty($backups)) {
            $this->info('No backups found in: '.$backupDir);

            return self::SUCCESS;
        }

        $this->info('Available database backups:');
        $this->table(
            ['#', 'Filename', 'Size', 'Date'],
            $backups
        );

        $this->newLine();
        $this->info('To restore a backup:');
        $this->info('  php artisan db:restore --latest           (restore most recent)');
        $this->info('  php artisan db:restore <filename>         (restore specific file)');

        return self::SUCCESS;
    }

    protected function getBackupsList(string $backupDir): array
    {
        if (! File::isDirectory($backupDir)) {
            return [];
        }

        return collect(File::files($backupDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sqlite'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(fn ($file, $index) => [
                $index + 1,
                $file->getFilename(),
                $this->formatBytes($file->getSize()),
                date('Y-m-d H:i:s', $file->getMTime()),
            ])
            ->toArray();
    }

    protected function getLatestBackup(string $backupDir): ?string
    {
        if (! File::isDirectory($backupDir)) {
            return null;
        }

        $latest = collect(File::files($backupDir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'database_backup_'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->first();

        return $latest ? $latest->getPathname() : null;
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
