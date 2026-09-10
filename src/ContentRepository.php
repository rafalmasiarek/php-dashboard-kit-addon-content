<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitContent;

use PDO;

/**
 * Provides read and write access to content type records.
 *
 * Used both by ContentAddon for admin CRUD and by public modules in modules/
 * to fetch content for front-end rendering.
 *
 * All table names are validated to contain only alphanumeric characters and
 * underscores before use in queries.
 *
 * @package rafalmasiarek\DashboardKitContent
 */
final class ContentRepository
{
    /**
     * @param PDO $pdo Active database connection.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Return all records from a content table ordered by creation date descending.
     *
     * @param  string              $table   Content table name.
     * @param  int                 $limit   Maximum number of records to return.
     * @param  int                 $offset  Number of records to skip.
     * @return array<int,array<string,mixed>>
     */
    public function findAll(string $table, int $limit = 200, int $offset = 0): array
    {
        $this->assertSafeIdentifier($table);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `' . $table . '` ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a single record by primary key.
     *
     * @param  string   $table Content table name.
     * @param  int      $id    Record primary key.
     * @return array<string,mixed>|null Null when not found.
     */
    public function findOne(string $table, int $id): ?array
    {
        $this->assertSafeIdentifier($table);
        $stmt = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Return a single record by slug.
     *
     * @param  string $table Content table name.
     * @param  string $slug  URL-safe slug value.
     * @return array<string,mixed>|null Null when not found.
     */
    public function findBySlug(string $table, string $slug): ?array
    {
        $this->assertSafeIdentifier($table);
        $stmt = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insert a new content record.
     *
     * @param  string              $table Content table name.
     * @param  array<string,mixed> $data  Column name → value map.
     * @return int                 Inserted row ID.
     */
    public function create(string $table, array $data): int
    {
        $this->assertSafeIdentifier($table);
        foreach (\array_keys($data) as $k) {
            $this->assertSafeIdentifier($k);
        }

        $cols  = \implode(', ', \array_map(fn($k) => '`' . $k . '`', \array_keys($data)));
        $holds = \implode(', ', \array_fill(0, \count($data), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$holds})");
        $stmt->execute(\array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing content record.
     *
     * @param  string              $table Content table name.
     * @param  int                 $id    Record primary key.
     * @param  array<string,mixed> $data  Column name → value map.
     * @return void
     */
    public function update(string $table, int $id, array $data): void
    {
        $this->assertSafeIdentifier($table);
        foreach (\array_keys($data) as $k) {
            $this->assertSafeIdentifier($k);
        }

        $sets = \implode(', ', \array_map(fn($k) => '`' . $k . '` = ?', \array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$sets} WHERE id = ?");
        $stmt->execute([...\array_values($data), $id]);
    }

    /**
     * Delete a content record by primary key.
     *
     * @param  string $table Content table name.
     * @param  int    $id    Record primary key.
     * @return void
     */
    public function delete(string $table, int $id): void
    {
        $this->assertSafeIdentifier($table);
        $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Assert that a DB identifier contains only safe characters.
     *
     * @param  string $name
     * @return void
     * @throws \InvalidArgumentException When unsafe characters are detected.
     */
    private function assertSafeIdentifier(string $name): void
    {
        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException("Unsafe DB identifier: '{$name}'.");
        }
    }
}
