<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use RuntimeException;

class UpgradeMigrationReview
{
    public function verify(Migrator $migrator): void
    {
        if (! $migrator->repositoryExists()) {
            throw new RuntimeException('The migrations table is missing.');
        }
        $reviewed = config('reviewed-upgrade-migrations', []);
        $ran = $migrator->getRepository()->getRan();
        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
        foreach ($files as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $contents = file_get_contents($file);
            $hash = $contents === false ? '' : hash('sha256', str_replace("\r\n", "\n", $contents));
            if (! isset($reviewed[$name]) || ! hash_equals($reviewed[$name], $hash)) {
                throw new RuntimeException('An unreviewed or modified migration is pending: '.$name.'. Review it for data preservation before upgrading.');
            }
        }
    }
}
