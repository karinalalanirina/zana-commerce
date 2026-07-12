<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * Drives the admin Updater: verify purchase → backup → upload ZIP → apply
 * code → migrate → finalize, with rollback. Ported from the SnapNest updater
 * and extended with an Envato purchase-code verification step. NEVER touches
 * .env, storage, the database files, vendor/ or user-uploaded public assets.
 */
class UpdaterService
{
    private string $backupDir;
    private string $tempDir;

    /** Paths that must NEVER be overwritten by an update. */
    private const PROTECTED_PATHS = [
        '.env', 'storage', 'database/database.sqlite', 'vendor', 'node_modules',
        // Node bridge runtime data that MUST survive an update: its own env file,
        // installed deps, and the live WhatsApp (Unofficial API) login sessions.
        // The root entries above only match the root subtree (path-prefix), so
        // the node/ equivalents are listed explicitly — a mis-packaged update ZIP
        // must never overwrite node secrets or wipe connected-number sessions.
        'node/.env', 'node/node_modules', 'node/baileys_auth',
    ];

    /** File extensions in public/ that must never be touched (user assets). */
    private const PROTECTED_PUBLIC_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav',
        'zip', 'rar', 'tar', 'gz', 'ttf', 'otf', 'woff', 'woff2', 'eot',
    ];

    /** Paths that get updated (code only). */
    private const UPDATABLE_PATHS = [
        'app', 'config', 'database/migrations',
        'public/css', 'public/js', 'public/build',
        'resources', 'routes', 'node',
    ];

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        $this->tempDir   = storage_path('app/temp/updater');
    }

    // ------------------------------------------------------------------
    //  STEP 0: Envato purchase verification
    // ------------------------------------------------------------------

    /**
     * Verify a CodeCanyon purchase code against the Envato API and confirm it
     * belongs to THIS item. On success the code is remembered so the buyer
     * doesn't have to re-enter it next time.
     *
     * @return array{ok: bool, message: string, buyer?: string, item?: string}
     */
    public function verifyPurchase(string $code): array
    {
        $code  = trim($code);
        $token = (string) config('version.envato.token');
        $item  = (string) config('version.envato.item_id');

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter your CodeCanyon purchase code.'];
        }
        if ($token === '') {
            return ['ok' => false, 'message' => 'Updater is not configured — the Envato token is missing from config/license.php.'];
        }

        try {
            $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent'    => 'WaDesk-Updater',
                ])
                ->timeout(20)
                ->get('https://api.envato.com/v3/market/author/sale', ['code' => $code]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Envato to verify the code. Check the server\'s internet connection.'];
        }

        if ($res->status() === 404) {
            return ['ok' => false, 'message' => 'Invalid purchase code — Envato has no sale for it.'];
        }
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Envato verification failed (HTTP ' . $res->status() . '). Check the author token.'];
        }

        $data   = $res->json();
        $soldId = (string) data_get($data, 'item.id', '');

        if ($item !== '' && $soldId !== '' && $soldId !== $item) {
            return ['ok' => false, 'message' => 'This purchase code is for a different product, not this one.'];
        }

        // Remembered for next time + audit.
        SystemSetting::set('envato_purchase_code', $code, 'string');
        SystemSetting::set('envato_verified_at', now()->toIso8601String(), 'string');

        return [
            'ok'      => true,
            'message' => 'Purchase verified — you can proceed with the update.',
            'buyer'   => (string) data_get($data, 'buyer', ''),
            'item'    => (string) data_get($data, 'item.name', ''),
        ];
    }

    // ------------------------------------------------------------------
    //  Version helpers
    // ------------------------------------------------------------------

    public function currentVersion(): string
    {
        return (string) config('version.version', '1.0.0');
    }

    public function currentBuild(): int
    {
        return (int) config('version.build', 0);
    }

    /** Read the version from config/version.php inside an uploaded ZIP. */
    public function getZipVersion(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $candidates = ['config/version.php'];
        for ($i = 0; $i < min($zip->numFiles, 50); $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/config/version\.php$#', $name, $m)) {
                $candidates[] = $name;
                break;
            }
        }

        $version = null;
        foreach ($candidates as $candidate) {
            $content = $zip->getFromName($candidate);
            if ($content !== false) {
                if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m)) {
                    $version = $m[1];
                }
                break;
            }
        }

        $zip->close();

        return $version;
    }

    // ------------------------------------------------------------------
    //  STEP 1: Backup
    // ------------------------------------------------------------------

    /** @return array{code: string, database: string, dir: string} */
    public function createBackup(): array
    {
        $version = $this->currentVersion();
        $ts  = now()->format('Y-m-d_His');
        $dir = $this->backupDir . "/v{$version}_{$ts}";
        File::ensureDirectoryExists($dir, 0755, true);

        $codeZip = $dir . '/code_backup.zip';
        $this->zipCodeFiles($codeZip);

        $dbFile = $dir . '/database_backup.sql';
        $this->dumpDatabase($dbFile);

        File::put($dir . '/rollback.json', json_encode([
            'version'     => $version,
            'created_at'  => now()->toIso8601String(),
            'code_backup' => $codeZip,
            'db_backup'   => $dbFile,
        ], JSON_PRETTY_PRINT));

        return ['code' => $codeZip, 'database' => $dbFile, 'dir' => $dir];
    }

    // ------------------------------------------------------------------
    //  STEP 2: Upload
    // ------------------------------------------------------------------

    public function saveUploadedZip($uploadedFile): string
    {
        File::ensureDirectoryExists($this->tempDir, 0755, true);
        $path = $this->tempDir . '/update.zip';
        $uploadedFile->move($this->tempDir, 'update.zip');

        return $path;
    }

    // ------------------------------------------------------------------
    //  STEP 3: Extract & Apply
    // ------------------------------------------------------------------

    public function applyUpdate(string $zipPath): array
    {
        $stagingDir = $this->tempDir . '/staging';
        if (File::isDirectory($stagingDir)) {
            File::deleteDirectory($stagingDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open update ZIP.');
        }
        $zip->extractTo($stagingDir);
        $zip->close();

        // Step into a single root folder wrapper if present.
        $items = File::directories($stagingDir);
        if (count($items) === 1 && count(File::files($stagingDir)) === 0) {
            $stagingDir = $items[0];
        }

        $basePath = base_path();
        $updated  = [];

        foreach (self::UPDATABLE_PATHS as $relPath) {
            $srcPath  = $stagingDir . '/' . $relPath;
            $destPath = $basePath . '/' . $relPath;

            if (! File::exists($srcPath)) {
                continue;
            }

            if (File::isDirectory($srcPath)) {
                if (str_contains($relPath, 'migrations')) {
                    $this->mergeMigrations($srcPath, $destPath);
                } else {
                    $this->safeCopyDirectory($srcPath, $destPath, $relPath);
                }
            } elseif (! $this->isProtected($relPath)) {
                File::ensureDirectoryExists(dirname($destPath));
                File::copy($srcPath, $destPath);
            }

            $updated[] = $relPath;
        }

        foreach (['composer.json', 'composer.lock', '.env.example'] as $rootFile) {
            $srcFile = $stagingDir . '/' . $rootFile;
            if (File::exists($srcFile)) {
                File::copy($srcFile, $basePath . '/' . $rootFile);
            }
        }

        $newVersionFile = $stagingDir . '/config/version.php';
        if (File::exists($newVersionFile)) {
            File::copy($newVersionFile, config_path('version.php'));
        }

        return $updated;
    }

    // ------------------------------------------------------------------
    //  STEP 4: Migrate
    // ------------------------------------------------------------------

    public function runMigrations(): string
    {
        // Fast path: everything applies cleanly (the normal case). Laravel
        // already SKIPS any migration recorded in the `migrations` table, so a
        // re-run is a no-op for those.
        try {
            Artisan::call('migrate', ['--force' => true]);

            return Artisan::output();
        } catch (\Throwable $e) {
            $log = trim(Artisan::output()) . "\n" . $e->getMessage();
        }

        // Resilient path. The one gap Laravel does NOT cover is a brand-new
        // migration FILE whose schema change is ALREADY present in the database
        // (the table/column was added by a prior partial update, a manual
        // hot-fix, or a re-uploaded package). That throws "table/column already
        // exists" and aborts the WHOLE update. Step through each pending file on
        // its own so one such failure can't take the rest down: when a file
        // fails because its change already exists, mark it as run and carry on.
        $log .= "\n\nRetrying migrations one-by-one (skipping already-applied ones)…\n";

        $repository = app('migration.repository');
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        $ran   = $repository->getRan();                 // names already recorded
        $batch = $repository->getNextBatchNumber();
        $dir   = database_path('migrations');

        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $name = basename($path, '.php');
            if (in_array($name, $ran, true)) {
                continue;                                // truly already run
            }

            try {
                Artisan::call('migrate', [
                    '--path'  => 'database/migrations/' . basename($path),
                    '--force' => true,
                ]);
                $log .= '  migrated: ' . $name . "\n";
            } catch (\Throwable $ex) {
                if ($this->migrationAlreadyApplied($ex)) {
                    // The change is already in the DB — record the migration so
                    // it is never retried, and keep going.
                    $repository->log($name, $batch);
                    $log .= '  skipped (already applied): ' . $name . "\n";
                    continue;
                }
                // A genuine, unexpected failure — surface it so the admin sees it.
                throw new RuntimeException('Migration ' . $name . ' failed: ' . $ex->getMessage(), 0, $ex);
            }
        }

        return $log;
    }

    /**
     * True when a migration error means "this change is already in the database"
     * — i.e. the migration is effectively already applied and is safe to skip
     * rather than abort the update. Covers MySQL/MariaDB, Postgres and SQLite.
     */
    private function migrationAlreadyApplied(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        foreach ([
            'already exists',          // generic / Postgres / SQLite "table ... already exists"
            'duplicate column',        // MySQL: duplicate column
            'duplicate key name',      // MySQL: duplicate index
            'duplicate table',
            '1050',                    // MySQL: table already exists
            '1060',                    // MySQL: duplicate column name
            '1061',                    // MySQL: duplicate key name
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    //  STEP 5: Finalize
    // ------------------------------------------------------------------

    public function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** @return array<string,bool> */
    public function healthCheck(): array
    {
        $results = [];

        try {
            DB::connection()->getPdo();
            $results['database'] = true;
        } catch (\Throwable $e) {
            $results['database'] = false;
        }

        $results['env_file']        = File::exists(base_path('.env'));
        $results['storage_writable'] = is_writable(storage_path());
        $results['uploads_exist']   = File::isDirectory(storage_path('app/public'));

        return $results;
    }

    // ------------------------------------------------------------------
    //  Rollback
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function listBackups(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $backups = [];
        foreach (File::directories($this->backupDir) as $dir) {
            $rollbackFile = $dir . '/rollback.json';
            if (File::exists($rollbackFile)) {
                $info = json_decode(File::get($rollbackFile), true) ?: [];
                $info['path'] = $dir;
                $backups[] = $info;
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $backups;
    }

    public function rollback(string $backupDir): void
    {
        $rollbackFile = $backupDir . '/rollback.json';
        if (! File::exists($rollbackFile)) {
            throw new RuntimeException('Rollback info not found.');
        }

        $info = json_decode(File::get($rollbackFile), true);

        if (! empty($info['code_backup']) && File::exists($info['code_backup'])) {
            $zip = new ZipArchive();
            if ($zip->open($info['code_backup']) === true) {
                $zip->extractTo(base_path());
                $zip->close();
            }
        }

        if (! empty($info['db_backup']) && File::exists($info['db_backup'])) {
            $this->restoreDatabase($info['db_backup']);
        }

        $this->clearCaches();
    }

    public function cleanup(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }

    // ------------------------------------------------------------------
    //  Private helpers
    // ------------------------------------------------------------------

    private function isProtected(string $relPath): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if ($relPath === $protected || str_starts_with($relPath, $protected . '/')) {
                return true;
            }
        }

        if (str_starts_with($relPath, 'public/')) {
            $allowedCodeDirs = ['public/css/', 'public/js/', 'public/build/'];
            $inCodeDir = false;
            foreach ($allowedCodeDirs as $dir) {
                if (str_starts_with($relPath, $dir)) {
                    $inCodeDir = true;
                    break;
                }
            }
            if (! $inCodeDir) {
                $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                if (in_array($ext, self::PROTECTED_PUBLIC_EXTENSIONS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function safeCopyDirectory(string $srcDir, string $destDir, string $baseRelPath): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::allFiles($srcDir) as $file) {
            $relPath = $baseRelPath . '/' . $file->getRelativePathname();
            if ($this->isProtected($relPath)) {
                continue;
            }
            $dest = $destDir . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($dest), 0755, true);
            File::copy($file->getPathname(), $dest);
        }
    }

    private function mergeMigrations(string $srcDir, string $destDir): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::files($srcDir) as $file) {
            $dest = $destDir . '/' . $file->getFilename();
            if (! File::exists($dest)) {
                File::copy($file->getPathname(), $dest);
            }
        }
    }

    private function zipCodeFiles(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup ZIP.');
        }

        $basePath = base_path();
        foreach (self::UPDATABLE_PATHS as $relPath) {
            $fullPath = $basePath . '/' . $relPath;
            if (File::isDirectory($fullPath)) {
                foreach (File::allFiles($fullPath) as $file) {
                    $zip->addFile($file->getPathname(), $relPath . '/' . $file->getRelativePathname());
                }
            } elseif (File::exists($fullPath)) {
                $zip->addFile($fullPath, $relPath);
            }
        }

        $zip->close();
    }

    private function dumpDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            if (File::exists($dbPath)) {
                File::copy($dbPath, $path);
            }
            return;
        }

        if ($driver === 'mysql') {
            // mysqldump is unreliable on shared hosting — go straight to the
            // PHP dump which works everywhere.
            $this->dumpDatabasePhp($path);
            return;
        }

        File::put($path, "-- Database backup not supported for driver: {$driver}\n");
    }

    private function dumpDatabasePhp(string $path): void
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.' . config('database.default') . '.database');
        $key = "Tables_in_{$dbName}";

        $sql = "-- WaDesk Database Backup\n-- Date: " . now()->toIso8601String() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$key;
            $create = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $create[0]->{'Create Table'} . ";\n\n";

            foreach (DB::table($tableName)->get() as $row) {
                $values = collect((array) $row)->map(function ($val) {
                    return is_null($val) ? 'NULL' : "'" . addslashes((string) $val) . "'";
                })->implode(', ');
                $sql .= "INSERT INTO `{$tableName}` VALUES ({$values});\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($path, $sql);
    }

    private function restoreDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            File::copy($path, config("database.connections.{$connection}.database"));
            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared(File::get($path));
        }
    }
}
