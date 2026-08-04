<?php
namespace today\gqlextend\lib;

use Craft;
use craft\base\Element;
use nystudio107\seomatic\fields\SeoSettings;
use nystudio107\seomatic\helpers\ArrayHelper;
use nystudio107\seomatic\helpers\Field as FieldHelper;
use nystudio107\seomatic\models\MetaBundle;
use nystudio107\seomatic\Seomatic;

/**
 * Reproduces the decision SEOmatic's own sitemap generator makes for an element.
 *
 * SEOmatic's `SeoSettings` field doesn't implement `getContentGqlType()`, so the
 * SEO field is only queryable as an opaque string and its sitemap settings can't
 * be read over GraphQL. This resolves them server-side instead.
 *
 * The precedence follows `nystudio107\seomatic\helpers\Sitemap`: start from the
 * element's meta bundle — `getContentMetaBundleForElement()` already resolves the
 * entry type's bundle where one exists, falling back to the section's — then
 * layer on any per-element SEO field settings.
 *
 * IMPORTANT: this class references SEOmatic classes directly, so it must only be
 * loaded once SEOmatic's presence is confirmed. Enter through
 * `SitemapSettings::resolve()`, never call it directly.
 */
class SeomaticSitemapSettings
{
    /**
     * @return array{include: bool, changeFreq: string|null, priority: float|null}
     */
    static function resolve (Element $element)
    {
        $metaBundle = Seomatic::$plugin->metaBundles->getContentMetaBundleForElement($element);

        if ($metaBundle === null) {
            // No bundle to consult — let the caller fall back to Craft.
            throw new \RuntimeException('No SEOmatic meta bundle for element ' . $element->id);
        }

        // The meta bundles service caches its bundles, so never mutate them in
        // place: the next element of the same type would inherit this element's
        // per-entry overrides.
        $sitemapVars = clone $metaBundle->metaSitemapVars;
        $globalVars = clone $metaBundle->metaGlobalVars;

        SeomaticSitemapSettings::applyFieldSettings($element, $sitemapVars, $globalVars);

        $robots = $globalVars->robots;
        $robotsEnabled = empty($robots) || !in_array($robots, ['none', 'noindex'], true);

        $include = $element->uri !== null
            && $element->getEnabledForSite($metaBundle->sourceSiteId)
            && (bool)$sitemapVars->sitemapUrls
            && $robotsEnabled;

        if (!$include) {
            return [
                'include' => false,
                'changeFreq' => null,
                'priority' => null,
            ];
        }

        return [
            'include' => true,
            'changeFreq' => SeomaticSitemapSettings::nullIfBlank($sitemapVars->sitemapChangeFreq),
            'priority' => SeomaticSitemapSettings::floatOrNull($sitemapVars->sitemapPriority),
        ];
    }

    /**
     * Layer any per-element SEO field settings over the bundle's, the way
     * `Sitemap::combineFieldSettings()` does.
     *
     * @param mixed $sitemapVars nystudio107\seomatic\models\MetaSitemapVars
     * @param mixed $globalVars nystudio107\seomatic\models\MetaGlobalVars
     */
    private static function applyFieldSettings (Element $element, $sitemapVars, $globalVars)
    {
        $fieldHandles = FieldHelper::fieldsOfTypeFromElement(
            $element,
            FieldHelper::SEO_SETTINGS_CLASS_KEY,
            true
        );

        foreach ($fieldHandles as $fieldHandle) {
            $fieldMetaBundle = $element->$fieldHandle ?? null;

            if (!$fieldMetaBundle instanceof MetaBundle) {
                continue;
            }

            $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);

            if (!$field instanceof SeoSettings) {
                continue;
            }

            // Sitemap settings only count when editors can actually see the tab.
            if ($field->sitemapTabEnabled) {
                $attributes = $fieldMetaBundle->metaSitemapVars->getAttributes();

                // Anything explicitly marked as inherited has to keep coming
                // from the bundle, so drop it before overriding.
                $inherited = array_keys(ArrayHelper::remove($attributes, 'inherited', []));

                $attributes = array_intersect_key(
                    $attributes,
                    array_flip((array)$field->sitemapEnabledFields)
                );

                // Unset values arrive as null/'' and must not clobber the
                // bundle, but a deliberate `false` has to survive.
                $attributes = array_filter($attributes, [ArrayHelper::class, 'preserveBools']);

                foreach ($inherited as $inheritedAttribute) {
                    unset($attributes[$inheritedAttribute]);
                }

                $sitemapVars->setAttributes($attributes, false);
            }

            // Global vars are read for `robots` regardless of the sitemap tab,
            // since a noindex element is never in the sitemap.
            $attributes = $fieldMetaBundle->metaGlobalVars->getAttributes();
            $attributes = array_filter($attributes, [ArrayHelper::class, 'preserveBools']);
            $globalVars->setAttributes($attributes, false);
        }
    }

    private static function nullIfBlank ($value)
    {
        return $value === null || $value === '' ? null : (string)$value;
    }

    private static function floatOrNull ($value)
    {
        return $value === null || $value === '' || !is_numeric($value) ? null : (float)$value;
    }
}
