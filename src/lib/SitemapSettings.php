<?php
namespace today\gqlextend\lib;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Throwable;

/**
 * Resolves an element's sitemap settings for GraphQL.
 *
 * Headless front ends build their own sitemaps, because they serve URLs the CMS
 * knows nothing about (paginated archives, modal routes). They still need the
 * CMS's answer per element: is this in the sitemap, and with what
 * changefreq/priority.
 *
 * The field is registered on every site so the schema is the same everywhere,
 * but how it resolves depends on the setup:
 *
 *  - With SEOmatic, `SeomaticSitemapSettings` reproduces the decision SEOmatic's
 *    own sitemap generator would make, including per-entry SEO field overrides.
 *  - Without it, the answer comes from Craft alone: the element is included when
 *    it is enabled for the site and has a URI, unless the site defines its own
 *    `noindex` field and has it switched on.
 *
 * SEOmatic is referenced only from `SeomaticSitemapSettings`, which is loaded
 * solely once its presence is confirmed — so nothing here tries to autoload a
 * class that isn't installed.
 */
class SitemapSettings
{
    const TYPE_NAME = 'SitemapSettings';

    /**
     * @var bool|null Memoised result of the SEOmatic availability check.
     */
    private static $seomaticAvailable = null;

    /**
     * The GraphQL type returned by the `sitemapSettings` field.
     */
    static function getType ()
    {
        $existing = GqlEntityRegistry::getEntity(SitemapSettings::TYPE_NAME);

        if ($existing) {
            return $existing;
        }

        $type = new ObjectType([
            'name' => SitemapSettings::TYPE_NAME,
            'description' => "An element's resolved sitemap settings.",
            'fields' => [
                'include' => [
                    'name' => 'include',
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Whether this element belongs in the sitemap.',
                ],
                'changeFreq' => [
                    'name' => 'changeFreq',
                    'type' => Type::string(),
                    'description' => 'The sitemap changefreq, if one is set. Always null without SEOmatic.',
                ],
                'priority' => [
                    'name' => 'priority',
                    'type' => Type::float(),
                    'description' => 'The sitemap priority, if one is set. Always null without SEOmatic.',
                ],
            ]
        ]);

        // `createEntity` registers the type with the TypeLoader itself.
        return GqlEntityRegistry::createEntity(SitemapSettings::TYPE_NAME, $type);
    }

    /**
     * Resolve the settings for an element.
     */
    static function resolve ($element)
    {
        if (!$element instanceof Element) {
            return SitemapSettings::excluded();
        }

        // Gate on status before anything else. SEOmatic's own generator never
        // sees a non-live element because its element query filters them out,
        // but this resolver runs on whatever the GraphQL query returned — and a
        // caller passing a `status` argument could hand us a disabled, pending
        // or expired element, none of which belong in a sitemap.
        if (!SitemapSettings::isLive($element)) {
            return SitemapSettings::excluded();
        }

        if (SitemapSettings::seomaticAvailable()) {
            try {
                return SeomaticSitemapSettings::resolve($element);
            } catch (Throwable $e) {
                // Fall through to the Craft-only answer rather than failing the
                // whole query. Worth logging: it means SEOmatic is installed but
                // its API didn't behave as expected, so changefreq/priority and
                // any per-entry sitemap overrides are being silently ignored.
                Craft::error(
                    sprintf(
                        'Could not resolve SEOmatic sitemap settings for element %s: %s',
                        $element->id,
                        $e->getMessage()
                    ),
                    __METHOD__
                );
            }
        }

        return SitemapSettings::fromCraft($element);
    }

    /**
     * Whether SEOmatic is installed and enabled.
     */
    static function seomaticAvailable ()
    {
        if (SitemapSettings::$seomaticAvailable === null) {
            SitemapSettings::$seomaticAvailable = class_exists('nystudio107\seomatic\Seomatic')
                && \nystudio107\seomatic\Seomatic::$plugin !== null;
        }

        return SitemapSettings::$seomaticAvailable;
    }

    /**
     * Whether the element is publicly live.
     *
     * Entries report `live` and carry post/expiry dates; categories and other
     * element types just report `enabled`.
     */
    private static function isLive (Element $element)
    {
        return in_array(
            $element->getStatus(),
            [Element::STATUS_ENABLED, Entry::STATUS_LIVE],
            true
        );
    }

    /**
     * Work the inclusion out from Craft alone.
     *
     * Sites without SEOmatic often carry their own `noindex` lightswitch — the
     * same field the SEO fallback in this plugin reads — so honour it when the
     * element's layout has one.
     */
    private static function fromCraft (Element $element)
    {
        $noindex = $element->__isset('noindex') && $element->getFieldValue('noindex');

        $include = $element->uri !== null && !$noindex;

        return [
            'include' => $include,
            'changeFreq' => null,
            'priority' => null,
        ];
    }

    private static function excluded ()
    {
        return [
            'include' => false,
            'changeFreq' => null,
            'priority' => null,
        ];
    }
}
