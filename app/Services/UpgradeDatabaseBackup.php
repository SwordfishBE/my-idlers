<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Create a private SQL dump before touching an existing MySQL database. */
class UpgradeDatabaseBackup
{
    protected function supportsPrivateFiles(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    protected function isPrivateDirectory(string $directory): bool
    {
        return (fileperms($directory) & 0077) === 0;
    }

    public function create(string $directory): string
    {
        if (! $this->supportsPrivateFiles()) {
            throw new RuntimeException('The upgrade backup requires a Unix host with private file permissions.');
        }

        $directory = realpath($directory) ?: '';
        $public = realpath(public_path());
        $project = realpath(base_path());
        if ($directory === '' || ! is_dir($directory) || ! is_writable($directory)
            || ($public && ($directory === $public || str_starts_with($directory, $public.DIRECTORY_SEPARATOR)))
            || ($project && ($directory === $project || str_starts_with($directory, $project.DIRECTORY_SEPARATOR)))
            || ! $this->isPrivateDirectory($directory)) {
            throw new RuntimeException('Backup directory must exist, be writable, private (mode 0700), and outside the application and public directories.');
        }

        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql') {
            throw new RuntimeException('This upgrade command only supports an existing MySQL or MariaDB database.');
        }
        $config = $db->getConfig();
        if (isset($config['read']) || isset($config['write'])) {
            throw new RuntimeException('Split read/write database connections require a separately verified backup procedure.');
        }
        $database = $db->getDatabaseName();
        $host = $config['host'] ?? '127.0.0.1';
        $socket = (string) ($config['unix_socket'] ?? '');
        if (! is_string($host) || $database === '' || (! $socket && ! in_array($host, ['127.0.0.1', '::1'], true))) {
            throw new RuntimeException('Use a local TCP address or an explicit DB_SOCKET; the backup must connect to the same server as Laravel.');
        }
        if (! $db->getSchemaBuilder()->hasTable('migrations')) {
            throw new RuntimeException('The migrations table is missing; refusing to modify an unverified database.');
        }
        $unsafe = $db->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' AND (ENGINE IS NULL OR UPPER(ENGINE) <> 'INNODB')", [$database]);
        if ($unsafe !== []) {
            throw new RuntimeException('The database contains non-InnoDB tables; arrange and test a consistent backup before upgrading.');
        }
        $version = (string) $db->selectOne('SELECT VERSION() AS version')->version;
        $finder = new ExecutableFinder();
        $binary = stripos($version, 'mariadb') !== false
            ? ($finder->find('mariadb-dump') ?: $finder->find('mysqldump'))
            : $finder->find('mysqldump');
        if (! $binary) {
            throw new RuntimeException('No compatible dump utility found. Install the database client package for the server.');
        }

        $token = bin2hex(random_bytes(12));
        $credentials = $directory.DIRECTORY_SEPARATOR.'.myidlers-'.$token.'.cnf';
        $backup = $directory.DIRECTORY_SEPARATOR.'myidlers-'.gmdate('Ymd-His').'-'.$token.'.sql';
        $privateFile = static function (string $path) {
            $file = @fopen($path, 'x');
            if (! $file || ! chmod($path, 0600)) {
                if (is_resource($file)) { fclose($file); @unlink($path); }
                throw new RuntimeException('Could not create a private backup file.');
            }
            return $file;
        };
        $quote = static function ($value): string {
            $value = (string) $value;
            if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('Database credentials contain unsupported control characters.');
            }
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        };
        $success = false;
        try {
            $lines = ['[client]', 'user='.$quote($config['username'] ?? ''), 'password='.$quote($config['password'] ?? '')];
            if ($socket !== '') {
                $lines[] = 'socket='.$quote($socket);
                $lines[] = 'protocol=socket';
            } else {
                $lines[] = 'host='.$quote($host);
                $lines[] = 'port='.$quote($config['port'] ?? 3306);
                $lines[] = 'protocol=tcp';
            }
            $file = $privateFile($credentials);
            $content = implode("\n", $lines)."\n";
            if (fwrite($file, $content) !== strlen($content) || ! fflush($file)) {
                fclose($file);
                throw new RuntimeException('Could not write the private database client configuration.');
            }
            fclose($file);
            $file = $privateFile($backup);
            fclose($file);
            $arguments = [
                $binary, '--defaults-file='.$credentials, '--single-transaction', '--quick',
                '--skip-lock-tables', '--skip-add-drop-table', '--skip-add-locks',
                '--routines', '--events', '--triggers', '--no-tablespaces', '--hex-blob',
            ];
            if (stripos($version, 'mariadb') === false) {
                $arguments[] = '--set-gtid-purged=OFF';
            }
            array_push($arguments, '--result-file='.$backup, '--', $database);
            $process = new Process($arguments);
            $process->setTimeout(3600);
            $process->run();
            clearstatcache(true, $backup);
            if (! $process->isSuccessful() || ! is_file($backup) || filesize($backup) < 100) {
                throw new RuntimeException('Database dump failed or was empty; no migrations were run.');
            }
            $file = fopen($backup, 'rb');
            fseek($file, max(0, filesize($backup) - 4096));
            $tail = stream_get_contents($file);
            fclose($file);
            if (! str_contains($tail, 'Dump completed on')) {
                throw new RuntimeException('Database dump is incomplete; no migrations were run.');
            }
            $success = true;
            return $backup;
        } finally {
            if (is_file($credentials)) { @unlink($credentials); }
            if (! $success && is_file($backup)) { @unlink($backup); }
        }
    }
}
