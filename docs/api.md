## Index

### Classes

- [`ContentAddon`](#contentaddon) — `rafalmasiarek\DashboardKitContent`
- [`ContentRepository`](#contentrepository) — `rafalmasiarek\DashboardKitContent`
- [`ContentExtension`](#contentextension) — `rafalmasiarek\DashboardKitContent\Twig`

## `ContentAddon`

*final class* — `rafalmasiarek\DashboardKitContent\ContentAddon` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L28)

Wires the content management system into a Dashboard application.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#contentaddon-register) | public static | `void` | Register the content addon. |
| [`buildTableSchema()`](#contentaddon-buildtableschema) | private static | `array<string, mixed>` | Translates content field definitions into a dashboard-kit table schema definition. Always prepends id (INT UNSIGNED AUTO_INCREMENT PK) and slug (VARCHAR 255). Enables timestamps so that updated_at is auto-injected by ModuleSchemaBuilder. |
| [`ensureSlugUnique()`](#contentaddon-ensureslugunique) | private static | `void` | Ensures a UNIQUE index exists on the slug column of a content table. Checked via INFORMATION_SCHEMA on MySQL and CREATE UNIQUE INDEX IF NOT EXISTS on SQLite. Fully idempotent — safe to call on every boot. |
| [`registerUploadRoute()`](#contentaddon-registeruploadroute) | private static | `void` | Register the built-in image upload endpoint for the richtext editor. Accepts a multipart/form-data POST with a 'file' field. Validates the real MIME type via finfo (not the client-supplied header). Saves to $uploadDir and returns JSON {"url": "..."} on success or {"error": "..."} on failure. The filename is "{8-char sha1}_{sanitized_basename}.{ext}" — deterministic, so re-uploading the same file produces the same name without duplicates. |
| [`registerRoutes()`](#contentaddon-registerroutes) | private static | `void` | Register admin CRUD routes for one content type. All routes are protected by AuthMiddleware, CsrfMiddleware, and RoleMiddleware restricted to the 'admin' role, mirroring the protection on standard admin routes. |
| [`applyDataHook()`](#contentaddon-applydatahook) | public static | `array<string, mixed>` | Call a before_* hook that returns modified data. When no hook is registered for the given name, returns $data unchanged. |
| [`fireHook()`](#contentaddon-firehook) | public static | `void` | Call an after_* hook that returns void. Does nothing when no hook is registered for the given name. |
| [`extractData()`](#contentaddon-extractdata) | public static | `array<string, mixed>` | Extract and sanitize field values from a POST body. Toggle fields are coerced to 0/1 (unchecked checkbox sends nothing). Number fields are cast to float or null when empty. Date fields are kept as string or null when empty. All other fields are cast to string. |
| [`slugify()`](#contentaddon-slugify) | public static | `string` | Generate a URL-safe slug from an arbitrary string. Lowercases, replaces non-alphanumeric runs with hyphens, and trims edge hyphens. |
| [`defaultListColumns()`](#contentaddon-defaultlistcolumns) | private static | `list<string>` | Determine a sensible default set of list columns from field definitions. Picks up to three fields whose types render well in a table (text, select, date, number, toggle). Falls back to all field names when none match. |

<details>
<summary><code>register()</code></summary>

#### `ContentAddon register()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L52)

```php
public static function register(App $app, ContainerInterface $container, array $config): void
```

Register the content addon.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | — |
| `$container` | `ContainerInterface` | — |
| `$config` | `array<string, mixed>` | Optional configuration overrides (unused, reserved for future use). |

</details>

<details>
<summary><code>buildTableSchema()</code></summary>

#### `ContentAddon buildTableSchema()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L165)

```php
private static function buildTableSchema(array $fields): array
```

Translates content field definitions into a dashboard-kit table schema definition. Always prepends id (INT UNSIGNED AUTO_INCREMENT PK) and slug (VARCHAR 255). Enables timestamps so that updated_at is auto-injected by ModuleSchemaBuilder.

| Param | Type | Description |
|---|---|---|
| `$fields` | `array<string, mixed>` | Content field definitions. |

**Returns:** `array<string, mixed>` — Table schema compatible with SchemaStateManager::sync().

</details>

<details>
<summary><code>ensureSlugUnique()</code></summary>

#### `ContentAddon ensureSlugUnique()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L194)

```php
private static function ensureSlugUnique(PDO $pdo, string $table): void
```

Ensures a UNIQUE index exists on the slug column of a content table. Checked via INFORMATION_SCHEMA on MySQL and CREATE UNIQUE INDEX IF NOT EXISTS on SQLite. Fully idempotent — safe to call on every boot.

| Param | Type | Description |
|---|---|---|
| `$pdo` | `PDO` | Active database connection. |
| `$table` | `string` | Content table name. |

</details>

<details>
<summary><code>registerUploadRoute()</code></summary>

#### `ContentAddon registerUploadRoute()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L232)

```php
private static function registerUploadRoute(App $app, ContainerInterface $container, string $routePath, string $uploadDir, string $uploadPublicPrefix, string $dashboardPrefix): void
```

Register the built-in image upload endpoint for the richtext editor. Accepts a multipart/form-data POST with a 'file' field. Validates the real MIME type via finfo (not the client-supplied header). Saves to $uploadDir and returns JSON {"url": "..."} on success or {"error": "..."} on failure. The filename is "{8-char sha1}_{sanitized_basename}.{ext}" — deterministic, so re-uploading the same file produces the same name without duplicates.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | — |
| `$container` | `ContainerInterface` | — |
| `$routePath` | `string` | Slim route path (e.g. '/admin/panel/_upload/image'). |
| `$uploadDir` | `string` | Filesystem directory for uploaded files. |
| `$uploadPublicPrefix` | `string` | URL prefix for serving uploaded files (e.g. '/uploads/images'). |
| `$dashboardPrefix` | `string` | Dashboard URL prefix for RoleMiddleware redirects. |

</details>

<details>
<summary><code>registerRoutes()</code></summary>

#### `ContentAddon registerRoutes()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L352)

```php
private static function registerRoutes(App $app, ContainerInterface $container, string $slug, string $title, string $table, array $fields, array $hooks, array $listColumns, array $grid, string $fullAdminPrefix, string $dashboardPrefix, string $uploadUrl): void
```

Register admin CRUD routes for one content type. All routes are protected by AuthMiddleware, CsrfMiddleware, and RoleMiddleware restricted to the 'admin' role, mirroring the protection on standard admin routes.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | — |
| `$container` | `ContainerInterface` | — |
| `$slug` | `string` | Content type slug. |
| `$title` | `string` | Human-readable label. |
| `$table` | `string` | DB table name. |
| `$fields` | `array<string, mixed>` | Field definitions. |
| `$hooks` | `array<string, callable>` | Lifecycle hook callbacks keyed by hook name. |
| `$listColumns` | `list<string>` | Columns to show in the list view. |
| `$grid` | `list<list<string>>` | Grid layout: rows of field name arrays. |
| `$fullAdminPrefix` | `string` | Full URL prefix including base_path, dashboard prefix, and admin prefix. |
| `$dashboardPrefix` | `string` | Full URL prefix including base_path and dashboard prefix only. |
| `$uploadUrl` | `string` | Image upload endpoint URL passed to the richtext editor. |

</details>

<details>
<summary><code>applyDataHook()</code></summary>

#### `ContentAddon applyDataHook()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L550)

```php
public static function applyDataHook(array $hooks, string $name, array $data, mixed $extra): array
```

Call a before_* hook that returns modified data. When no hook is registered for the given name, returns $data unchanged.

| Param | Type | Description |
|---|---|---|
| `$hooks` | `array<string, callable>` | — |
| `$name` | `string` | Hook name (e.g. 'before_create'). |
| `$data` | `array<string, mixed>` | Data to pass as the first argument. |
| `$extra` | `mixed` | — |

**Returns:** `array<string, mixed>`

</details>

<details>
<summary><code>fireHook()</code></summary>

#### `ContentAddon fireHook()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L568)

```php
public static function fireHook(array $hooks, string $name, mixed $args): void
```

Call an after_* hook that returns void. Does nothing when no hook is registered for the given name.

| Param | Type | Description |
|---|---|---|
| `$hooks` | `array<string, callable>` | — |
| `$name` | `string` | Hook name (e.g. 'after_create'). |
| `$args` | `mixed` | — |

</details>

<details>
<summary><code>extractData()</code></summary>

#### `ContentAddon extractData()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L587)

```php
public static function extractData(array $fields, array $post): array
```

Extract and sanitize field values from a POST body. Toggle fields are coerced to 0/1 (unchecked checkbox sends nothing). Number fields are cast to float or null when empty. Date fields are kept as string or null when empty. All other fields are cast to string.

| Param | Type | Description |
|---|---|---|
| `$fields` | `array<string, mixed>` | Field definitions. |
| `$post` | `array<string, mixed>` | Raw POST data ($_POST / getParsedBody()). |

**Returns:** `array<string, mixed>`

</details>

<details>
<summary><code>slugify()</code></summary>

#### `ContentAddon slugify()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L615)

```php
public static function slugify(string $value): string
```

Generate a URL-safe slug from an arbitrary string. Lowercases, replaces non-alphanumeric runs with hyphens, and trims edge hyphens.

| Param | Type | Description |
|---|---|---|
| `$value` | `string` | Raw input string. |

**Returns:** `string` — URL-safe slug.

</details>

<details>
<summary><code>defaultListColumns()</code></summary>

#### `ContentAddon defaultListColumns()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentAddon.php#L631)

```php
private static function defaultListColumns(array $fields): array
```

Determine a sensible default set of list columns from field definitions. Picks up to three fields whose types render well in a table (text, select, date, number, toggle). Falls back to all field names when none match.

| Param | Type | Description |
|---|---|---|
| `$fields` | `array<string, mixed>` | Field definitions. |

**Returns:** `list<string>`

</details>

## `ContentRepository`

*final class* — `rafalmasiarek\DashboardKitContent\ContentRepository` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L25)

Provides read and write access to content type records. Used both by ContentAddon for admin CRUD and by public modules in modules/ to fetch content for front-end rendering. Built on Model::on() rather than a dedicated Model subclass, since each content type's table name is only known at runtime (admin-defined blueprints) — there is no fixed set of PHP classes to extend. Connects via Model's own connection resolver, so no PDO dependency of its own. All table names and column names are validated to contain only alphanumeric characters and underscores before use in queries.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`findAll()`](#contentrepository-findall) | public | `array<int, array<string, mixed>>` | Return all records from a content table ordered by creation date descending. |
| [`findOne()`](#contentrepository-findone) | public | `array<string, mixed>\|null` | Return a single record by primary key. |
| [`findBySlug()`](#contentrepository-findbyslug) | public | `array<string, mixed>\|null` | Return a single record by slug. |
| [`create()`](#contentrepository-create) | public | `int` | Insert a new content record. |
| [`update()`](#contentrepository-update) | public | `void` | Update an existing content record. |
| [`delete()`](#contentrepository-delete) | public | `void` | Delete a content record by primary key. |
| [`assertSafeIdentifier()`](#contentrepository-assertsafeidentifier) | private | `void` | Assert that a DB identifier contains only safe characters. |

<details>
<summary><code>findAll()</code></summary>

#### `ContentRepository findAll()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L35)

```php
public function findAll(string $table, int $limit, int $offset): array
```

Return all records from a content table ordered by creation date descending.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$limit` | `int` | Maximum number of records to return. |
| `$offset` | `int` | Number of records to skip. |

**Returns:** `array<int, array<string, mixed>>`

</details>

<details>
<summary><code>findOne()</code></summary>

#### `ContentRepository findOne()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L53)

```php
public function findOne(string $table, int $id): ?array
```

Return a single record by primary key.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$id` | `int` | Record primary key. |

**Returns:** `array<string, mixed>\|null` — Null when not found.

</details>

<details>
<summary><code>findBySlug()</code></summary>

#### `ContentRepository findBySlug()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L67)

```php
public function findBySlug(string $table, string $slug): ?array
```

Return a single record by slug.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$slug` | `string` | URL-safe slug value. |

**Returns:** `array<string, mixed>\|null` — Null when not found.

</details>

<details>
<summary><code>create()</code></summary>

#### `ContentRepository create()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L81)

```php
public function create(string $table, array $data): int
```

Insert a new content record.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$data` | `array<string, mixed>` | Column name → value map. |

**Returns:** `int` — Inserted row ID.

</details>

<details>
<summary><code>update()</code></summary>

#### `ContentRepository update()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L99)

```php
public function update(string $table, int $id, array $data): void
```

Update an existing content record.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$id` | `int` | Record primary key. |
| `$data` | `array<string, mixed>` | Column name → value map. |

</details>

<details>
<summary><code>delete()</code></summary>

#### `ContentRepository delete()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L116)

```php
public function delete(string $table, int $id): void
```

Delete a content record by primary key.

| Param | Type | Description |
|---|---|---|
| `$table` | `string` | Content table name. |
| `$id` | `int` | Record primary key. |

</details>

<details>
<summary><code>assertSafeIdentifier()</code></summary>

#### `ContentRepository assertSafeIdentifier()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/ContentRepository.php#L129)

```php
private function assertSafeIdentifier(string $name): void
```

Assert that a DB identifier contains only safe characters.

| Param | Type | Description |
|---|---|---|
| `$name` | `string` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When unsafe characters are detected. |

</details>

## `ContentExtension`

*final class* — `rafalmasiarek\DashboardKitContent\Twig\ContentExtension` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/Twig/ContentExtension.php#L25)

Provides the content_render Twig filter for rendering richtext fields. Uses league/commonmark with: - HTML passthrough ('html_input' => 'allow') so raw HTML is preserved. - AttributesExtension so {.class #id} syntax adds CSS classes to elements. Usage in templates: {{ page.body|content_render|raw }}

extends `AbstractExtension`

**Depends on:** `MarkdownConverter`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$converter` | `MarkdownConverter\|null` | private | Lazily-initialized converter instance. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`getFilters()`](#contentextension-getfilters) | public | `list<TwigFilter>` | Return the Twig filters provided by this extension. |
| [`render()`](#contentextension-render) | public | `string` | Convert a richtext field value to HTML. Supports Markdown syntax, raw HTML blocks, and {.class} attribute notation. Returns an empty string for null or empty input. |

<details>
<summary><code>getFilters()</code></summary>

#### `ContentExtension getFilters()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/Twig/ContentExtension.php#L35)

```php
public function getFilters(): array
```

Return the Twig filters provided by this extension.

**Returns:** `list<TwigFilter>`

</details>

<details>
<summary><code>render()</code></summary>

#### `ContentExtension render()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-content/blob/main/src/Twig/ContentExtension.php#L51)

```php
public function render(?string $content): string
```

Convert a richtext field value to HTML. Supports Markdown syntax, raw HTML blocks, and {.class} attribute notation. Returns an empty string for null or empty input.

| Param | Type | Description |
|---|---|---|
| `$content` | `string\|null` | Raw richtext content from the database. |

**Returns:** `string` — Rendered HTML.

</details>

