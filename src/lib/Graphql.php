<?php
namespace today\gqlextend\lib;

use yii\base\Event;
use markhuot\CraftQL\Types\VolumeInterface;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ObjectType;

class GqlExtendGraphql
{
    public static function init()
    {
		  GqlExtendGraphql::addFields();
    }

    static function addFields ()
    {
        /*
        * @event DefineGqlTypeFields The event that is triggered when GraphQL type fields are being prepared.
        * Plugins can use this event to add, remove or modify fields on a given GraphQL type.
        */
        Event::on(TypeManager::class, TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS, function(DefineGqlTypeFieldsEvent $event) {

            // Extend entry and category interface
            if ($event->typeName == 'EntryInterface' || $event->typeName == 'Interface') {

                // Add a relative link to be used in nuxt app
                $event->fields['href'] = [
                    'name' => 'href',
                    'type' => Type::string(),
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        return $source->uri === '__home__' ? '/' : '/' . $source->uri . '/';
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

                $ImageType = new ObjectType([
                    'name' => 'Image',
                    'fields' => [
                        'src' => [ 'type' => Type::string() ],
                        'srcset' => [ 'type' => Type::string() ],
                        'alt' => [ 'type' => Type::string() ],
                        'position' => [ 'type' => Type::string() ]
                    ]
                ]);

                $event->fields['thumbnail'] = [
                    'name' => 'thumbnail',
                    'type' => $ImageType,
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        $asset = $source->__isset('featuredImage') && $source->getFieldValue('featuredImage') ? $source->getFieldValue('featuredImage')->first() : false;

                        if (!$asset) {
                            return null;
                        }

                        $alt = $asset ? $asset->title : '';
                        $src = $asset ? $asset->url : '';
                        $srcset = '';
                        $optimizedImages = $asset ? $asset->optimizedImages : false;

                        if ($optimizedImages) {
                            $srcset = array();

                            foreach ($optimizedImages->optimizedImageUrls as $key=>$value) {
                                array_push($srcset, $value . ' ' . $key . 'w');
                            };

                            $srcset = implode($srcset, ', ');
                        }

                        return array(
                            'src' =>  $src,
                            'srcset' => $srcset,
                            'alt' => $asset->title,
                            'position' => ($asset->focalPoint['x'] * 100) . '% ' . ($asset->focalPoint['y'] * 100) . '%'
                        );
                    }
                ];

                $BreadcrumbType = new ObjectType([
                    'name' => 'Breadcrumb',
                    'fields' => [
                        'title' => [ 'type' => Type::string() ],
                        'href' => [ 'type' => Type::string() ],
                    ]
                ]);

                // Add breadcrumbs
                $event->fields['breadcrumbs'] = [
                    'name' => 'breadcrumbs',
                    'type' => Type::listOf($BreadcrumbType),
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

                $SocialType = new ObjectType([
                    'name' => 'Social',
                    'fields' => [
                        'title' => [ 'type' => Type::string() ],
                        'description' => [ 'type' => Type::string() ],
                        'image' => [ 'type' => $ImageType ],
                    ]
                ]);

                $SEOType = new ObjectType([
                    'name' => 'SEO',
                    'fields' => [
                        'title' => [ 'type' => Type::string() ],
                        'description' => [ 'type' => Type::string() ],
                        'keywords' => [ 'type' => Type::string() ],
                        'social' => [ 'type' => $SocialType ],
                        'noindex' => [ 'type' => Type::boolean() ],
                        'nofollow' => [ 'type' => Type::boolean() ],
                    ]
                ]);

                // Add SEO
                $event->fields['seo'] = [
                    'name' => 'seo',
                    'type' => $SEOType,
                    'resolve' => function ($source, array $arguments, $context, ResolveInfo $resolveInfo) {
                        $title = $source->__isset('seoTitle') && $source->getFieldValue('seoTitle') ? $source->getFieldValue('seoTitle') : $source->title;
                        $description = $source->__isset('seoDescription') && $source->getFieldValue('seoDescription') ? $source->getFieldValue('seoDescription') : ( $source->__isset('linkText') ? $source->getFieldValue('linkText') : '');
                        $asset = $source->__isset('featuredImage') && $source->getFieldValue('featuredImage') ? $source->getFieldValue('featuredImage')->first() : false;
                        $image = null;

                        // Add site name
                        $title .= " | " . $source->getSite()->name;

                        if ($asset) {
                            $image = array(
                                'alt' => $asset->title,
                                'src' => $asset->url
                            );
                        };

                        return array(
                            'title' => $title,
                            'description' => $description,
                            'keywords' => $source->__isset('seoKeywords') ? $source->getFieldValue('seoKeywords') : '',
                            'social' => array(
                                'title' => $title,
                                'description' => $description,
                                'image' => $image
                            ),
                            'noindex' => $source->__isset('noindex') ? $root->getFieldValue('noindex') : false,
                            'nofollow' => $source->__isset('nofollow') ? $root->getFieldValue('nofollow') : false,
                        );
                    }
                ];
            }
        });
    }

    static function addBreadcrumb($entry, $breadcrumbs = array()) {
        array_push($breadcrumbs, array(
            'title' => $entry->title,
            'href' => '/' . $entry->uri . '/'
        ));

        $parent = $entry->getParent();

        if ($parent) {
            return GqlExtendGraphql::addBreadcrumb($parent, $breadcrumbs);
        }

        return $breadcrumbs;
    }
}
