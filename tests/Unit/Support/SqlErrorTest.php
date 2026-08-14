<?php

namespace Tests\Unit\Support;

use App\Support\SqlError;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;

class SqlErrorTest extends TestCase
{
    public function test_record_changed_since_last_read_is_transient(): void
    {
        $pdo = new \PDOException("SQLSTATE[HY000]: General error: 1020 Record has changed since last read in table 'collections'; try restarting transaction");
        $pdo->errorInfo = ['HY000', 1020, "Record has changed since last read in table 'collections'"];

        $this->assertTrue(SqlError::isTransientLock(new QueryException('mariadb', 'INSERT ...', [], $pdo)));
    }

    public function test_deadlock_and_lock_wait_timeout_are_transient(): void
    {
        foreach ([1213, 1205] as $code) {
            $pdo = new \PDOException("SQLSTATE[40001]: Serialization failure: {$code}");
            $pdo->errorInfo = ['40001', $code, 'Serialization failure'];

            $this->assertTrue(SqlError::isTransientLock(new QueryException('mariadb', 'INSERT ...', [], $pdo)));
        }
    }

    public function test_duplicate_key_is_not_transient(): void
    {
        $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

        $this->assertFalse(SqlError::isTransientLock(new QueryException('mariadb', 'INSERT ...', [], $pdo)));
        $this->assertFalse(SqlError::isTransientLock(new \RuntimeException('unrelated failure')));
    }

    public function test_message_fallback_detects_transient_plain_exceptions(): void
    {
        $this->assertTrue(SqlError::isTransientLock(new \RuntimeException('Deadlock found when trying to get lock')));
        $this->assertTrue(SqlError::isTransientLock(new \RuntimeException('Lock wait timeout exceeded')));
        $this->assertTrue(SqlError::isTransientLock(new \RuntimeException("Record has changed since last read in table 'collections'")));
        $this->assertTrue(SqlError::isTransientLock(new \RuntimeException('database is locked')));
    }

    public function test_describe_strips_connection_and_sql_payload(): void
    {
        $pdo = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
        $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];
        $queryException = new QueryException('mariadb', 'INSERT INTO collections VALUES (?)', ['binary-garbage'], $pdo);

        $description = SqlError::describe($queryException);

        $this->assertStringContainsString('Deadlock found', $description);
        $this->assertStringNotContainsString('(Connection:', $description);
        $this->assertStringNotContainsString('binary-garbage', $description);
    }

    public function test_describe_truncates_overlong_messages(): void
    {
        $description = SqlError::describe(new \RuntimeException(str_repeat('x', 1000)));

        $this->assertLessThanOrEqual(501, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
    }
}
