# craftcms-gql-extend
A CraftCMS plugin to extend the GraphQL scheme for entries with additional fields.

Fields are added to both `EntryInterface` and `CategoryInterface`.

## `sitemapSettings`

For headless front ends that build their own `sitemap.xml` — they serve URLs Craft
knows nothing about (paginated archives, modal routes), so they need the CMS's
answer per element rather than a ready-made sitemap.

```graphql
{
  entries(uri: ["NOT", null]) {
    uri
    dateUpdated
    sitemapSettings {
      include      # Boolean! — does this element belong in the sitemap
      changeFreq   # String   — null without SEOmatic
      priority     # Float    — null without SEOmatic
    }
  }
}
```

The field is registered on every install, so the schema doesn't change shape
between projects. How it resolves depends on the setup:

**With SEOmatic** it reproduces the decision SEOmatic's own sitemap generator
would make, following the precedence in `nystudio107\seomatic\helpers\Sitemap`:
the element's meta bundle (the entry type's where one is configured, otherwise the
section's), with per-element SEO field settings layered over it. An element is
excluded when it is disabled, has no URI, has `robots` set to `noindex`/`none`, or
has sitemap inclusion switched off.

`changeFreq` and `priority` are only editable per entry once the SEO field's
**Sitemap** tab is enabled (field settings → Sitemap). Enabling that tab does not
change existing output: unset per-entry values are filtered out, so entries keep
inheriting their section settings until an editor overrides them.

**Without SEOmatic** the answer comes from Craft alone — included when the element
is enabled for the site and has a URI, unless the layout has its own `noindex`
lightswitch and it is switched on. `changeFreq` and `priority` are `null`.

If SEOmatic is installed but its API does not respond as expected, resolution logs
the error and falls back to the Craft-only answer rather than failing the query.
