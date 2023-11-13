<?php
namespace today\gqlextend\lib;

use Craft;
use yii\base\Event;
use markhuot\CraftQL\Types\VolumeInterface;
use craft\elements\Entry;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\gql\TypeManager;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ObjectType;

class GqlExtendGraphql
{
    public static function init()
    {
        GqlExtendGraphql::addTypes();
        GqlExtendGraphql::addFields();
    }

    static function getImageType ()
    {
        $typeName = 'Image';

        if (GqlEntityRegistry::getEntity($typeName)) {
            return GqlEntityRegistry::getEntity($typeName);
        }

        $ImageType = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'src' => [ 'type' => Type::string() ],
                'srcset' => [ 'type' => Type::string() ],
                'alt' => [ 'type' => Type::string() ],
                'position' => [ 'type' => Type::string() ],
                'width' => [ 'type' => Type::int() ],
                'height' => [ 'type' => Type::int() ]
            ]
        ]);

        GqlEntityRegistry::createEntity($typeName, $ImageType);
        TypeLoader::registerType($typeName, function () use ($ImageType) { return $ImageType ;});
    }

    static function getSocialType ()
    {
        $typeName = 'Social';

        if (GqlEntityRegistry::getEntity($typeName)) {
            return GqlEntityRegistry::getEntity($typeName);
        }

        $SocialType = new ObjectType([
            'name' => 'Social',
            'fields' => [
                'title' => [ 'type' => Type::string() ],
                'description' => [ 'type' => Type::string() ],
                'image' => [ 'type' => GqlExtendGraphql::getImageType() ],
            ]
        ]);

        GqlEntityRegistry::createEntity($typeName, $SocialType);
        TypeLoader::registerType($typeName, function () use ($SocialType) { return $SocialType ;});
    }

    static function getSEOType ()
    {
        $typeName = 'SEO';

        if (GqlEntityRegistry::getEntity($typeName)) {
            return GqlEntityRegistry::getEntity($typeName);
        }

        $SEOType = new ObjectType([
            'name' => 'SEO',
            'fields' => [
                'title' => [ 'type' => Type::string() ],
                'description' => [ 'type' => Type::string() ],
                'keywords' => [ 'type' => Type::string() ],
                'social' => [ 'type' => GqlExtendGraphql::getSocialType() ],
                'noindex' => [ 'type' => Type::boolean() ],
                'nofollow' => [ 'type' => Type::boolean() ],
            ]
        ]);

        GqlEntityRegistry::createEntity($typeName, $SEOType);
        TypeLoader::registerType($typeName, function () use ($SEOType) { return $SEOType ;});
    }

    static function getBreadcrumbType ()
    {
        $typeName = 'Breadcrumb';

        if (GqlEntityRegistry::getEntity($typeName)) {
            return GqlEntityRegistry::getEntity($typeName);
        }

        $BreadcrumbType = new ObjectType([
            'name' => 'Breadcrumb',
            'fields' => [
                'title' => [ 'type' => Type::string() ],
                'href' => [ 'type' => Type::string() ],
            ]
        ]);

        GqlEntityRegistry::createEntity($typeName, $BreadcrumbType);
        TypeLoader::registerType($BreadcrumbType, function () use ($BreadcrumbType) { return $BreadcrumbType ;});
    }

    static function getRelatedType ()
    {
        $typeName = 'Related';

        if (GqlEntityRegistry::getEntity($typeName)) {
            return GqlEntityRegistry::getEntity($typeName);
        }

        $RelatedType = new ObjectType([
            'name' => 'Related',
            'fields' => [
                'title' => [ 'type' => Type::string() ],
                'href' => [ 'type' => Type::string() ],
                'id' => [ 'type' => Type::int() ],
            ]
        ]);

        GqlEntityRegistry::createEntity($typeName, $RelatedType);
        TypeLoader::registerType($typeName, function () use ($RelatedType) { return $RelatedType ;});
    }

    static function addTypes ()
    {
        GqlExtendGraphql::getImageType();
        GqlExtendGraphql::getSocialType();
        GqlExtendGraphql::getSEOType();
        GqlExtendGraphql::getBreadcrumbType();
        GqlExtendGraphql::getRelatedType();
    }

    static function addFields ()
    {
        /*
        * @event DefineGqlTypeFields The event that is triggered when GraphQL type fields are being prepared.
        * Plugins can use this event to add, remove or modify fields on a given GraphQL type.
        */
        Event::on(TypeManager::class, TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS, function(DefineGqlTypeFieldsEvent $event) {

            // Extend entry and category interface
            if ($event->typeName == 'EntryInterface' || $event->typeName == 'CategoryInterface') {

                // Add a relative link to be used in nuxt app
                $event->fields['href'] = [
                    'name' => 'href',
                    'type' => Type::string(),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        return $source->uri === '__home__' ? '/' : '/' . $source->uri . GqlExtendGraphql::getTrailingSlash();
                    }
                ];

                // Add a link text to be used when linking to this entry
                $event->fields['linkText'] = [
                    'name' => 'linkText',
                    'type' => Type::string(),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        return $source->__isset('linkText') ? $source->getFieldValue('linkText') : '';
                    }
                ];

                // Add a link description to be used when linking to this entry
                $event->fields['linkDescription'] = [
                    'name' => 'linkDescription',
                    'type' => Type::string(),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        return $source->__isset('linkDescription') ? $source->getFieldValue('linkDescription') : '';
                    }
                ];

                $event->fields['thumbnail'] = [
                    'name' => 'thumbnail',
                    'type' => GqlExtendGraphql::getImageType(),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        $asset = $source->__isset('featuredImage') && $source->getFieldValue('featuredImage') ? $source->getFieldValue('featuredImage')->one() : false;

                        if (!$asset) {
                            return null;
                        }

                        $alt = $asset && $asset->__isset('altText') ? $asset->altText : ($asset ? $asset->title : '');
                        $src = $asset ? $asset->url : '';
                        $srcset = '';
                        $optimizedImages = $asset && $asset->__isset('optimizedImages') ? $asset->optimizedImages : false;

                        if ($optimizedImages) {
                            $srcset = array();

                            foreach ($optimizedImages->optimizedImageUrls as $key=>$value) {
                                array_push($srcset, $value . ' ' . $key . 'w');
                            };

                            $srcset = implode(', ', $srcset);
                        }

                        return array(
                            'src' =>  $src,
                            'srcset' => $srcset,
                            'alt' => $alt,
                            'position' => ($asset->focalPoint['x'] * 100) . '% ' . ($asset->focalPoint['y'] * 100) . '%',
                            'width' => $asset->width,
                            'height' => $asset->height
                        );
                    }
                ];


                // Add breadcrumbs
                $event->fields['breadcrumbs'] = [
                    'name' => 'breadcrumbs',
                    'type' => Type::listOf(GqlExtendGraphql::getBreadcrumbType()),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        $breadcrumbs = GqlExtendGraphql::addBreadcrumb($source);

                        // Add home page
                        array_push($breadcrumbs, array(
                            'title' => 'Home',
                            'href' => '/'
                        ));

                        return array_reverse($breadcrumbs);
                    }
                ];

                // Add related entries
                $event->fields['related'] = [
                    'name' => 'related',
                    'type' => Type::listOf(GqlExtendGraphql::getRelatedType()),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        // Get entries related doing a db query
                        $related = Entry::find()->relatedTo(['targetElement' => $source])->all();

                        // Map related entries
                        return array_map(function ($entry) {
                            return array(
                                'title' => $entry->title,
                                'href' => '/' . $entry->uri . GqlExtendGraphql::getTrailingSlash(),
                                'id' => $entry->id
                            );
                        }, $related);
                    }
                ];
            }
        });
    }

    static function addBreadcrumb($entry, $breadcrumbs = array()) {
        array_push($breadcrumbs, array(
            'title' => $entry->title,
            'href' => '/' . $entry->uri . GqlExtendGraphql::getTrailingSlash()
        ));

        $parent = $entry->getParent();

        if ($parent) {
            return GqlExtendGraphql::addBreadcrumb($parent, $breadcrumbs);
        }

        return $breadcrumbs;
    }

    static function getTrailingSlash() {
        if (Craft::$app->getConfig()->getGeneral()->addTrailingSlashesToUrls) {
            return '/';
        }

        return '';
    }
}
