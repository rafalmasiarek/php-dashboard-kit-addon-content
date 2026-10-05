<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitContent;

use rafalmasiarek\DashboardKit\Model\Model;

/**
 * Provides read and write access to content type records.
 *
 * Used both by ContentAddon for admin CRUD and by public modules in modules/
 * to fetch content for front-end rendering.
 *
 * Built on Model::on() rather than a dedicated Model subclass, since each
 * content type's table name is only known at runtime (admin-defined
 * blueprints) — there is no fixed set of PHP classes to extend. Connects via
 * Model's own connection resolver, so no PDO dependency of its own.
 *
 * All table names and column names are validated to contain only
 * alphanumeric characters and underscores before use in queries.
 *
 * @package rafalmasiarek\DashboardKitContent
 */
final class ContentRepository
{
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
        return Model::on($table)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
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
        /** @var array<string,mixed>|null */
        return Model::on($table)->where('id', $id)->first();
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
        /** @var array<string,mixed>|null */
        return Model::on($table)->where('slug', $slug)->first();
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

        return (int) Model::on($table)->insert($data);
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

        Model::on($table)->where('id', $id)->update($data);
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
        Model::on($table)->where('id', $id)->forceDelete();
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
