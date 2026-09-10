# dashboard-kit-addon-content

Blueprint-driven content type addon for [rafalmasiarek/dashboard-kit](https://github.com/rafalmasiarek/php-dashboard-kit).

Define content types via a `content` blueprint in any admin module — the addon auto-creates the database table, generates admin CRUD routes, and provides a `ContentRepository` for reading records in front-end modules.

## Requirements

- PHP 8.2+
- `rafalmasiarek/dashboard-kit: *`
- `league/commonmark: ^2.0`

## Installation

```bash
composer require rafalmasiarek/dashboard-kit-addon-content
```

The addon is auto-discovered by `Dashboard::create()` when the package is installed. No explicit `register()` call is needed.

## Blueprint format

Add a `content` key to any admin module's `module.php`:

```php
return [
    'title' => 'Pages',
    'icon'  => '📄',
    'content' => [
        'table'  => 'pages',               // optional; omit to auto-create content_{slug}
        'list'   => ['title', 'status'],   // columns shown in the admin list view
        'fields' => [
            'title'  => ['type' => 'text',     'label' => 'Title',   'required' => true],
            'body'   => ['type' => 'richtext',  'label' => 'Content'],
            'status' => ['type' => 'select',    'label' => 'Status',  'options' => ['draft', 'published']],
        ],
    ],
];
```

### Field types

| Type | Description |
|------|-------------|
| `text` | Single-line text input |
| `richtext` | WYSIWYG editor (Quill) |
| `select` | Dropdown; requires `options` key |
| `date` | Date picker |
| `number` | Numeric input |
| `toggle` | Boolean checkbox |

All fields accept an optional `width` key (Bootstrap column 1–12).

### Hooks

```php
'hooks' => [
    'before_create' => function (array $data): array { return $data; },
    'before_update' => function (array $data, array $existing): array { return $data; },
    'after_create'  => function (int $id, array $data): void {},
    'after_update'  => function (int $id, array $data): void {},
    'before_delete' => function (int $id, array $existing): void {},
    'after_delete'  => function (int $id): void {},
],
```

## Admin routes

All routes are protected by Auth + CSRF + admin Role middleware.

| Route | Description |
|-------|-------------|
| `GET {prefix}/{slug}` | List records |
| `GET {prefix}/{slug}/new` | Create form |
| `POST {prefix}/{slug}` | Save new record |
| `GET {prefix}/{slug}/{id}/edit` | Edit form |
| `POST {prefix}/{slug}/{id}` | Save updated record |
| `POST {prefix}/{slug}/{id}/delete` | Delete record |

## Reading records in modules

```php
use rafalmasiarek\DashboardKitContent\ContentRepository;

$repo = $container->get(ContentRepository::class);

$all    = $repo->findAll('pages');
$one    = $repo->findOne('pages', $id);
$bySlug = $repo->findBySlug('pages', 'about');
```

## Twig filter

```twig
{{ page.body|content_render|raw }}
```

Renders Markdown with HTML passthrough and `{.class}` attribute syntax (league/commonmark).

## Auto-created tables

When `table` is omitted, the addon creates `content_{slug}` with:

- `id` — `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `slug` — `VARCHAR(255) UNIQUE`
- `created_at`, `updated_at` — timestamps

Adding a new field to the blueprint automatically adds the column via `ALTER TABLE` on next boot.

## License

Business Source License 1.1 — see [LICENSE](LICENSE).
For alternative licensing, [contact us](https://masiarek.pl/contact/?af_subject=Commercial+license+%E2%80%94+dashboard-kit-addon-content&af_message=Hello%2C+I+am+interested+in+a+commercial+license+for+dashboard-kit-addon-content.).
