<?php

namespace Tests\Feature;

use App\Application\Sites\SiteKeyGenerator;
use App\Application\Sites\SiteManager;
use App\Application\Sites\SiteMapWriter;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteMapTransactionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_map_is_published_only_after_the_database_transaction_commits(): void
    {
        $writer = new class(app(ConnectionInterface::class)) implements SiteMapWriter
        {
            /** @var list<int> */
            public array $transactionLevels = [];

            public function __construct(private readonly ConnectionInterface $connection) {}

            public function regenerate(): void
            {
                $this->transactionLevels[] = $this->connection->transactionLevel();
            }
        };
        $tenant = Tenant::query()->create(['name' => 'Example tenant', 'slug' => 'example-tenant']);
        $manager = new SiteManager(new SiteKeyGenerator(), $writer);
        $transactionLevelBeforeMutation = app(ConnectionInterface::class)->transactionLevel();

        $manager->create($tenant, [
            'name' => 'Example site',
            'timezone' => 'UTC',
            'hosts' => ['example.test'],
        ]);

        self::assertSame([$transactionLevelBeforeMutation], $writer->transactionLevels);
    }
}
