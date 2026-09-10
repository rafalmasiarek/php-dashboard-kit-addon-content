<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitContent\Twig;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides the content_render Twig filter for rendering richtext fields.
 *
 * Uses league/commonmark with:
 *   - HTML passthrough ('html_input' => 'allow') so raw HTML is preserved.
 *   - AttributesExtension so {.class #id} syntax adds CSS classes to elements.
 *
 * Usage in templates: {{ page.body|content_render|raw }}
 *
 * @package rafalmasiarek\DashboardKitContent\Twig
 */
final class ContentExtension extends AbstractExtension
{
    /** @var MarkdownConverter|null Lazily-initialized converter instance. */
    private ?MarkdownConverter $converter = null;

    /**
     * Return the Twig filters provided by this extension.
     *
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('content_render', [$this, 'render']),
        ];
    }

    /**
     * Convert a richtext field value to HTML.
     *
     * Supports Markdown syntax, raw HTML blocks, and {.class} attribute notation.
     * Returns an empty string for null or empty input.
     *
     * @param  string|null $content Raw richtext content from the database.
     * @return string               Rendered HTML.
     */
    public function render(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        if ($this->converter === null) {
            $env = new Environment([
                'html_input'         => 'allow',
                'allow_unsafe_links' => true,
            ]);
            $env->addExtension(new CommonMarkCoreExtension());
            $env->addExtension(new AttributesExtension());
            $this->converter = new MarkdownConverter($env);
        }

        return $this->converter->convert($content)->getContent();
    }
}
