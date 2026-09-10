<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitContent;

use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use rafalmasiarek\DashboardKit\Flash;
use rafalmasiarek\DashboardKit\Middleware\AuthMiddleware;
use rafalmasiarek\DashboardKit\Middleware\CsrfMiddleware;
use rafalmasiarek\DashboardKit\Middleware\RoleMiddleware;
use rafalmasiarek\DashboardKit\Schema\ModuleSchemaBuilder;
use rafalmasiarek\DashboardKit\Schema\SchemaInspector;
use rafalmasiarek\DashboardKit\Schema\SchemaStateManager;
use rafalmasiarek\DashboardKitContent\Twig\ContentExtension;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Twig\Loader\FilesystemLoader;

/**
 * Wires the content management system into a Dashboard application.
 *
 * @package rafalmasiarek\DashboardKitContent
 */
final class ContentAddon
{
    /**
     * Maps content field types to dashboard-kit schema column definitions.
     *
     * @var array<string, array<string, mixed>>
     */
    private const FIELD_TYPE_MAP = [
        'text'     => ['type' => 'varchar(500)',  'null' => false, 'default' => ''],
        'richtext' => ['type' => 'longtext',      'null' => false],
        'select'   => ['type' => 'varchar(100)',  'null' => false, 'default' => ''],
        'date'     => ['type' => 'date',          'null' => true,  'default' => null],
        'number'   => ['type' => 'decimal(12,4)', 'null' => true,  'default' => null],
        'toggle'   => ['type' => 'tinyint(1)',    'null' => false, 'default' => '0'],
    ];

    /**
     * Register the content addon.
     *
     * @param App                 $app
     * @param ContainerInterface  $container
     * @param array<string,mixed> $config Optional configuration overrides (unused, reserved for future use).
     * @return void
     */
    public static function register(App $app, ContainerInterface $container, array $config = []): void
    {
        if (!\class_exists(\rafalmasiarek\DashboardKit\Dashboard::class)) {
            throw new \LogicException(
                static::class . ' is a dashboard-kit addon and requires rafalmasiarek/dashboard-kit. '
                . 'Run: composer require rafalmasiarek/dashboard-kit'
            );
        }

        $appConfig   = $container->has('app.config') ? (array) $container->get('app.config') : [];
        $dashCfg     = (array) ($appConfig['dashboard'] ?? []);
        $basePath    = (string) ($appConfig['app']['base_path'] ?? '');
        $dashPrefix  = (string) ($dashCfg['prefix'] ?? '');
        $adminPrefix = (string) ($dashCfg['admin_prefix'] ?? '/admin');

        $fullAdminPrefix = $basePath . $dashPrefix . $adminPrefix;
        $dashboardPrefix = $basePath . $dashPrefix;

        $container->set(
            ContentRepository::class,
            static fn() => new ContentRepository($container->get(PDO::class))
        );

        $view = $container->get('view');
        $env  = $view->getEnvironment();
        $env->addExtension(new ContentExtension());

        $loader = $env->getLoader();
        if ($loader instanceof FilesystemLoader) {
            $loader->addPath(__DIR__ . '/../templates', 'content');
        }

        // Register the built-in image upload handler if no external plugin has claimed the slot.
        if (!$container->has('content.image_upload_url')) {
            $appRootDir         = (string) $container->get('app.root_dir');
            $uploadDir          = $appRootDir . '/public/uploads/images';
            $uploadRoutePath    = $fullAdminPrefix . '/_upload/image';
            $uploadPublicPrefix = \rtrim($basePath, '/') . '/uploads/images';

            self::registerUploadRoute($app, $container, $uploadRoutePath, $uploadDir, $uploadPublicPrefix, $dashboardPrefix);
            $container->set('content.image_upload_url', $uploadRoutePath);
        }

        $uploadUrl = (string) $container->get('content.image_upload_url');

        $adminRegistry = $container->get('admin_module_registry');
        $pdo           = $container->get(PDO::class);

        $contentModules = [];

        foreach ($adminRegistry->all() as $module) {
            $contentDef = $module['content'] ?? null;
            if (!\is_array($contentDef)) {
                continue;
            }

            $slug   = (string) ($module['slug'] ?? '');
            $title  = (string) ($module['title'] ?? \ucfirst($slug));
            $table  = (string) ($contentDef['table'] ?? 'content_' . $slug);
            $fields = (array) ($contentDef['fields'] ?? []);
            $hooks  = isset($contentDef['hooks']) && \is_array($contentDef['hooks'])
                ? $contentDef['hooks']
                : [];
            $list   = isset($contentDef['list'])
                ? (array) $contentDef['list']
                : self::defaultListColumns($fields);
            $grid   = isset($contentDef['grid'])
                ? (array) $contentDef['grid']
                : \array_map(static fn(string $n) => [$n], \array_keys($fields));

            if (!isset($contentDef['table'])) {
                $contentModules[$slug] = [
                    'schema' => [$table => self::buildTableSchema($fields)],
                ];
            }

            self::registerRoutes(
                $app,
                $container,
                $slug,
                $title,
                $table,
                $fields,
                $hooks,
                $list,
                $grid,
                $fullAdminPrefix,
                $dashboardPrefix,
                $uploadUrl,
            );
        }

        if ($contentModules !== []) {
            $manager = new SchemaStateManager($pdo, new ModuleSchemaBuilder(), new SchemaInspector());
            $manager->sync($contentModules);

            foreach ($contentModules as $moduleSchema) {
                foreach (\array_keys($moduleSchema['schema']) as $table) {
                    self::ensureSlugUnique($pdo, (string) $table);
                }
            }
        }
    }

    /**
     * Translates content field definitions into a dashboard-kit table schema definition.
     *
     * Always prepends id (INT UNSIGNED AUTO_INCREMENT PK) and slug (VARCHAR 255).
     * Enables timestamps so that updated_at is auto-injected by ModuleSchemaBuilder.
     *
     * @param  array<string,mixed> $fields Content field definitions.
     * @return array<string,mixed>         Table schema compatible with SchemaStateManager::sync().
     */
    private static function buildTableSchema(array $fields): array
    {
        $columns = [
            'id'   => ['type' => 'int unsigned', 'null' => false, 'auto_increment' => true],
            'slug' => ['type' => 'varchar(255)', 'null' => false, 'default' => ''],
        ];

        foreach ($fields as $name => $def) {
            $type           = (string) ($def['type'] ?? 'text');
            $columns[$name] = self::FIELD_TYPE_MAP[$type] ?? ['type' => 'varchar(500)', 'null' => false, 'default' => ''];
        }

        return [
            'primary'    => 'id',
            'timestamps' => true,
            'columns'    => $columns,
        ];
    }

    /**
     * Ensures a UNIQUE index exists on the slug column of a content table.
     *
     * Checked via INFORMATION_SCHEMA on MySQL and CREATE UNIQUE INDEX IF NOT EXISTS
     * on SQLite. Fully idempotent — safe to call on every boot.
     *
     * @param  PDO    $pdo   Active database connection.
     * @param  string $table Content table name.
     * @return void
     */
    private static function ensureSlugUnique(PDO $pdo, string $table): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS `uq_{$table}_slug` ON `{$table}` (`slug`)");
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $stmt->execute([$table, 'uq_slug']);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_slug` (`slug`)");
        }
    }

    /**
     * Register the built-in image upload endpoint for the richtext editor.
     *
     * Accepts a multipart/form-data POST with a 'file' field. Validates the real
     * MIME type via finfo (not the client-supplied header). Saves to $uploadDir
     * and returns JSON {"url": "..."} on success or {"error": "..."} on failure.
     *
     * The filename is "{8-char sha1}_{sanitized_basename}.{ext}" — deterministic,
     * so re-uploading the same file produces the same name without duplicates.
     *
     * @param App                $app
     * @param ContainerInterface $container
     * @param string             $routePath         Slim route path (e.g. '/admin/panel/_upload/image').
     * @param string             $uploadDir         Filesystem directory for uploaded files.
     * @param string             $uploadPublicPrefix URL prefix for serving uploaded files (e.g. '/uploads/images').
     * @param string             $dashboardPrefix   Dashboard URL prefix for RoleMiddleware redirects.
     * @return void
     */
    private static function registerUploadRoute(
        App $app,
        ContainerInterface $container,
        string $routePath,
        string $uploadDir,
        string $uploadPublicPrefix,
        string $dashboardPrefix,
    ): void {
        $appConfig = $container->has('app.config') ? (array) $container->get('app.config') : [];
        $maxBytes  = (int) ($appConfig['content']['upload_max_bytes'] ?? 10 * 1024 * 1024);

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        $internalHandler = static function (ServerRequestInterface $req, ResponseInterface $res) use (
            $uploadDir,
            $uploadPublicPrefix,
            $maxBytes,
            $allowed,
            $extMap,
        ): ResponseInterface {
            $jsonErr = static function (ResponseInterface $res, string $message): ResponseInterface {
                $res->getBody()->write((string) \json_encode(['error' => $message]));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(422);
            };

            $fileData = $_FILES['file'] ?? null;
            $errCode  = (int) ($fileData['error'] ?? \UPLOAD_ERR_NO_FILE);

            if ($fileData === null || $errCode !== \UPLOAD_ERR_OK) {
                $msg = match ($errCode) {
                    \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'File too large for server.',
                    \UPLOAD_ERR_NO_FILE                         => 'No file received.',
                    default                                     => 'Upload error (code ' . $errCode . ').',
                };
                return $jsonErr($res, $msg);
            }

            if ((int) $fileData['size'] > $maxBytes) {
                $maxMb = \round($maxBytes / 1024 / 1024, 1);
                return $jsonErr($res, "File too large (max {$maxMb} MB).");
            }

            if (!\is_dir($uploadDir)) {
                \mkdir($uploadDir, 0755, true);
            }

            $tmp = $uploadDir . '/.tmp_' . \bin2hex(\random_bytes(8));

            if (!\move_uploaded_file((string) $fileData['tmp_name'], $tmp)) {
                return $jsonErr($res, 'Could not save file.');
            }

            $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
            $realMime = (string) $finfo->file($tmp);

            if (!\in_array($realMime, $allowed, true)) {
                \unlink($tmp);
                return $jsonErr($res, 'File type not allowed.');
            }

            $ext          = $extMap[$realMime];
            $content      = (string) \file_get_contents($tmp);
            $hash         = \substr(\sha1($content), 0, 8);
            $original     = \pathinfo((string) ($fileData['name'] ?? ''), \PATHINFO_FILENAME);
            $safe         = \substr(\preg_replace('/[^a-z0-9_-]/', '', \strtolower($original)) ?? '', 0, 40);
            $filename     = $safe !== '' ? "{$hash}_{$safe}.{$ext}" : "{$hash}.{$ext}";
            $final        = $uploadDir . '/' . $filename;

            if (\file_exists($final)) {
                \unlink($tmp);
            } else {
                \rename($tmp, $final);
            }

            $scheme       = $req->getUri()->getScheme();
            $host         = $req->getUri()->getHost();
            $port         = $req->getUri()->getPort();
            $defaultPorts = ['http' => 80, 'https' => 443];
            $portSuffix   = ($port !== null && $port !== ($defaultPorts[$scheme] ?? null)) ? ':' . $port : '';
            $origin       = $scheme . '://' . $host . $portSuffix;

            $url = $origin . \rtrim($uploadPublicPrefix, '/') . '/' . $filename;
            $res->getBody()->write((string) \json_encode(['url' => $url], \JSON_UNESCAPED_SLASHES));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
        };

        $app->post(
            $routePath,
            function (ServerRequestInterface $req, ResponseInterface $res) use ($container, $internalHandler): ResponseInterface {
                if ($container->has('images.upload_handler')) {
                    return ($container->get('images.upload_handler'))($req, $res, $container);
                }
                return $internalHandler($req, $res);
            }
        )
        ->add(new RoleMiddleware($container, ['admin'], $dashboardPrefix))
        ->add(AuthMiddleware::class);
    }

    /**
     * Register admin CRUD routes for one content type.
     *
     * All routes are protected by AuthMiddleware, CsrfMiddleware, and RoleMiddleware
     * restricted to the 'admin' role, mirroring the protection on standard admin routes.
     *
     * @param App                    $app
     * @param ContainerInterface     $container
     * @param string                 $slug            Content type slug.
     * @param string                 $title           Human-readable label.
     * @param string                 $table           DB table name.
     * @param array<string,mixed>    $fields          Field definitions.
     * @param array<string,callable> $hooks           Lifecycle hook callbacks keyed by hook name.
     * @param list<string>           $listColumns     Columns to show in the list view.
     * @param list<list<string>>     $grid            Grid layout: rows of field name arrays.
     * @param string                 $fullAdminPrefix Full URL prefix including base_path, dashboard prefix, and admin prefix.
     * @param string                 $dashboardPrefix Full URL prefix including base_path and dashboard prefix only.
     * @param string                 $uploadUrl       Image upload endpoint URL passed to the richtext editor.
     * @return void
     */
    private static function registerRoutes(
        App $app,
        ContainerInterface $container,
        string $slug,
        string $title,
        string $table,
        array $fields,
        array $hooks,
        array $listColumns,
        array $grid,
        string $fullAdminPrefix,
        string $dashboardPrefix,
        string $uploadUrl,
    ): void {
        $contentBase = $fullAdminPrefix . '/' . $slug;

        $app->group(
            $contentBase,
            function (RouteCollectorProxy $g) use (
                $container,
                $slug,
                $title,
                $table,
                $fields,
                $hooks,
                $listColumns,
                $grid,
                $contentBase,
                $uploadUrl,
            ): void {

                $g->get('', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                ) use ($container, $title, $slug, $table, $listColumns, $contentBase): ResponseInterface {
                    $repo  = $container->get(ContentRepository::class);
                    $items = $repo->findAll($table);
                    $view  = $container->get('view');
                    return $view->render($res, '@content/list.twig', [
                        'title'        => $title,
                        'slug'         => $slug,
                        'items'        => $items,
                        'columns'      => $listColumns,
                        'content_base' => $contentBase,
                        'breadcrumbs'  => [['label' => $title]],
                    ]);
                });

                $g->get('/new', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                ) use ($container, $title, $slug, $fields, $grid, $contentBase, $uploadUrl): ResponseInterface {
                    $view = $container->get('view');
                    return $view->render($res, '@content/form.twig', [
                        'title'        => 'New ' . $title,
                        'slug'         => $slug,
                        'fields'       => $fields,
                        'grid'         => $grid,
                        'item'         => null,
                        'form_action'  => $contentBase,
                        'content_base' => $contentBase,
                        'upload_url'   => $uploadUrl,
                        'breadcrumbs'  => [
                            ['label' => $title, 'url' => $contentBase],
                            ['label' => 'New'],
                        ],
                    ]);
                });

                $g->post('', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                ) use ($container, $slug, $table, $fields, $hooks, $contentBase): ResponseInterface {
                    $repo  = $container->get(ContentRepository::class);
                    $flash = $container->get(Flash::class);
                    $post  = (array) $req->getParsedBody();
                    $data  = ContentAddon::extractData($fields, $post);

                    if (($data['slug'] ?? '') === '' && ($data['title'] ?? '') !== '') {
                        $data['slug'] = ContentAddon::slugify((string) $data['title']);
                    }

                    $data = ContentAddon::applyDataHook($hooks, 'before_create', $data);

                    try {
                        $id = $repo->create($table, $data);
                        ContentAddon::fireHook($hooks, 'after_create', $id, $data);
                        $flash->add('success', 'Created successfully.');
                    } catch (\Throwable $e) {
                        $flash->add('danger', 'Error: ' . $e->getMessage());
                    }

                    return $res->withHeader('Location', $contentBase)->withStatus(302);
                });

                $g->get('/{id}/edit', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                    array $args,
                ) use ($container, $title, $slug, $table, $fields, $grid, $contentBase, $uploadUrl): ResponseInterface {
                    $repo  = $container->get(ContentRepository::class);
                    $flash = $container->get(Flash::class);
                    $id    = (int) ($args['id'] ?? 0);
                    $item  = $repo->findOne($table, $id);

                    if ($item === null) {
                        $flash->add('danger', 'Record not found.');
                        return $res->withHeader('Location', $contentBase)->withStatus(302);
                    }

                    $view = $container->get('view');
                    return $view->render($res, '@content/form.twig', [
                        'title'        => 'Edit ' . $title,
                        'slug'         => $slug,
                        'fields'       => $fields,
                        'grid'         => $grid,
                        'item'         => $item,
                        'form_action'  => $contentBase . '/' . $id,
                        'content_base' => $contentBase,
                        'upload_url'   => $uploadUrl,
                        'breadcrumbs'  => [
                            ['label' => $title, 'url' => $contentBase],
                            ['label' => 'Edit'],
                        ],
                    ]);
                });

                $g->post('/{id}', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                    array $args,
                ) use ($container, $slug, $table, $fields, $hooks, $contentBase): ResponseInterface {
                    $repo     = $container->get(ContentRepository::class);
                    $flash    = $container->get(Flash::class);
                    $id       = (int) ($args['id'] ?? 0);
                    $existing = $repo->findOne($table, $id);

                    if ($existing === null) {
                        $flash->add('danger', 'Record not found.');
                        return $res->withHeader('Location', $contentBase)->withStatus(302);
                    }

                    $post = (array) $req->getParsedBody();
                    $data = ContentAddon::extractData($fields, $post);
                    $data = ContentAddon::applyDataHook($hooks, 'before_update', $data, $existing);

                    try {
                        $repo->update($table, $id, $data);
                        ContentAddon::fireHook($hooks, 'after_update', $id, $data);
                        $flash->add('success', 'Saved.');
                    } catch (\Throwable $e) {
                        $flash->add('danger', 'Error: ' . $e->getMessage());
                    }

                    return $res->withHeader('Location', $contentBase)->withStatus(302);
                });

                $g->post('/{id}/delete', function (
                    ServerRequestInterface $req,
                    ResponseInterface $res,
                    array $args,
                ) use ($container, $table, $hooks, $contentBase): ResponseInterface {
                    $repo     = $container->get(ContentRepository::class);
                    $flash    = $container->get(Flash::class);
                    $id       = (int) ($args['id'] ?? 0);
                    $existing = $repo->findOne($table, $id);

                    if ($existing !== null) {
                        ContentAddon::fireHook($hooks, 'before_delete', $id, $existing);
                    }

                    try {
                        $repo->delete($table, $id);
                        ContentAddon::fireHook($hooks, 'after_delete', $id);
                        $flash->add('warning', 'Deleted.');
                    } catch (\Throwable $e) {
                        $flash->add('danger', 'Error: ' . $e->getMessage());
                    }

                    return $res->withHeader('Location', $contentBase)->withStatus(302);
                });
            }
        )->add(new RoleMiddleware($container, ['admin'], $dashboardPrefix))
         ->add(CsrfMiddleware::class)
         ->add(AuthMiddleware::class);
    }

    /**
     * Call a before_* hook that returns modified data.
     *
     * When no hook is registered for the given name, returns $data unchanged.
     *
     * @param  array<string,callable> $hooks
     * @param  string                 $name  Hook name (e.g. 'before_create').
     * @param  array<string,mixed>    $data  Data to pass as the first argument.
     * @param  mixed                  ...$extra Additional arguments forwarded to the hook.
     * @return array<string,mixed>
     */
    public static function applyDataHook(array $hooks, string $name, array $data, mixed ...$extra): array
    {
        if (isset($hooks[$name]) && \is_callable($hooks[$name])) {
            return ($hooks[$name])($data, ...$extra);
        }
        return $data;
    }

    /**
     * Call an after_* hook that returns void.
     *
     * Does nothing when no hook is registered for the given name.
     *
     * @param  array<string,callable> $hooks
     * @param  string                 $name Hook name (e.g. 'after_create').
     * @param  mixed                  ...$args Arguments forwarded to the hook.
     * @return void
     */
    public static function fireHook(array $hooks, string $name, mixed ...$args): void
    {
        if (isset($hooks[$name]) && \is_callable($hooks[$name])) {
            ($hooks[$name])(...$args);
        }
    }

    /**
     * Extract and sanitize field values from a POST body.
     *
     * Toggle fields are coerced to 0/1 (unchecked checkbox sends nothing).
     * Number fields are cast to float or null when empty.
     * Date fields are kept as string or null when empty.
     * All other fields are cast to string.
     *
     * @param array<string,mixed> $fields Field definitions.
     * @param array<string,mixed> $post   Raw POST data ($_POST / getParsedBody()).
     * @return array<string,mixed>
     */
    public static function extractData(array $fields, array $post): array
    {
        $data = [];
        foreach ($fields as $name => $def) {
            $type = (string) ($def['type'] ?? 'text');
            if ($type === 'toggle') {
                $data[$name] = isset($post[$name]) && $post[$name] !== '0' ? 1 : 0;
            } elseif ($type === 'number') {
                $raw         = $post[$name] ?? '';
                $data[$name] = $raw !== '' ? (float) $raw : null;
            } elseif ($type === 'date') {
                $raw         = $post[$name] ?? '';
                $data[$name] = $raw !== '' ? $raw : null;
            } else {
                $data[$name] = (string) ($post[$name] ?? '');
            }
        }
        return $data;
    }

    /**
     * Generate a URL-safe slug from an arbitrary string.
     *
     * Lowercases, replaces non-alphanumeric runs with hyphens, and trims edge hyphens.
     *
     * @param  string $value Raw input string.
     * @return string        URL-safe slug.
     */
    public static function slugify(string $value): string
    {
        $slug = \strtolower(\trim($value));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return \trim($slug, '-');
    }

    /**
     * Determine a sensible default set of list columns from field definitions.
     *
     * Picks up to three fields whose types render well in a table (text, select,
     * date, number, toggle). Falls back to all field names when none match.
     *
     * @param  array<string,mixed> $fields Field definitions.
     * @return list<string>
     */
    private static function defaultListColumns(array $fields): array
    {
        $tableTypes = ['text', 'select', 'date', 'number', 'toggle'];
        $cols       = [];
        foreach ($fields as $name => $def) {
            if (\in_array($def['type'] ?? 'text', $tableTypes, true)) {
                $cols[] = $name;
                if (\count($cols) >= 3) {
                    break;
                }
            }
        }
        return $cols ?: \array_keys($fields);
    }
}
