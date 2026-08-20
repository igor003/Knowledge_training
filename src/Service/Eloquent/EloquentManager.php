<?php

namespace App\Service\Eloquent;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;
use InvalidArgumentException;

final class EloquentManager
{
    private bool $booted = false;
    private ?Capsule $capsule = null;

    public function __construct(private readonly string $databaseUrl)
    {
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->capsule = new Capsule();
        $this->capsule->addConnection($this->connectionConfig());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->booted = true;
    }

    public function schema(): Builder
    {
        $this->boot();

        return $this->capsule->schema();
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        $parts = parse_url($this->databaseUrl);

        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Invalid DATABASE_URL for Eloquent connection.');
        }

        return [
            'driver' => $this->driverName($parts['scheme']),
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 3306,
            'database' => ltrim($parts['path'], '/'),
            'username' => $parts['user'] ?? '',
            'password' => $parts['pass'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
    }

    private function driverName(string $scheme): string
    {
        return match ($scheme) {
            'mysql', 'mariadb' => 'mysql',
            default => throw new InvalidArgumentException(sprintf('Unsupported database driver "%s".', $scheme)),
        };
    }
}
