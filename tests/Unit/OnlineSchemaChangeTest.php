<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Database\OnlineSchemaChange;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OnlineSchemaChangeTest extends TestCase
{
    private const string ALTER = 'ALTER TABLE `releases` ADD INDEX `ix` (`id`), ALGORITHM=INPLACE, LOCK=NONE';

    /** @var list<string> */
    private array $statements = [];

    #[Test]
    public function the_alter_waits_briefly_for_locks_and_restores_the_session_afterwards(): void
    {
        OnlineSchemaChange::run($this->connection(['lock_wait_timeout' => '86400', 'max_statement_time' => '900.000000']), self::ALTER);

        $this->assertSame([
            'SET SESSION lock_wait_timeout = 10',
            'SET SESSION max_statement_time = 0',
            self::ALTER,
            'SET SESSION lock_wait_timeout = 86400',
            'SET SESSION max_statement_time = 900.000000',
        ], $this->statements);
    }

    #[Test]
    public function the_session_is_restored_when_the_alter_fails(): void
    {
        $connection = $this->connection(['lock_wait_timeout' => '86400'], failingSql: self::ALTER);

        try {
            OnlineSchemaChange::run($connection, self::ALTER);
            $this->fail('The failed ALTER should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Lock wait timeout exceeded', $exception->getMessage());
        }

        $this->assertSame([
            'SET SESSION lock_wait_timeout = 10',
            self::ALTER,
            'SET SESSION lock_wait_timeout = 86400',
        ], $this->statements);
    }

    /**
     * @param  array<string, string>  $sessionVariables  Variables the server has; others behave as on MySQL (no row).
     */
    private function connection(array $sessionVariables, ?string $failingSql = null): ConnectionInterface
    {
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('selectOne')->willReturnCallback(static function (string $sql) use ($sessionVariables): ?object {
            preg_match("/LIKE '(\w+)'/", $sql, $match);
            $value = $sessionVariables[$match[1] ?? ''] ?? null;

            return $value === null ? null : (object) ['Variable_name' => $match[1], 'Value' => $value];
        });
        $connection->method('statement')->willReturnCallback(function (string $sql) use ($failingSql): bool {
            $this->statements[] = $sql;
            if ($sql === $failingSql) {
                throw new RuntimeException('Lock wait timeout exceeded');
            }

            return true;
        });

        return $connection;
    }
}
