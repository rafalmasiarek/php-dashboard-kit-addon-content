## Auto-created tables

When `table` is omitted, the addon creates `content_{slug}` with:

- `id` — `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `slug` — `VARCHAR(255) UNIQUE`
- `created_at`, `updated_at` — timestamps

Adding a new field to the blueprint automatically adds the column via `ALTER TABLE` on next boot.
