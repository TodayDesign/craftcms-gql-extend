<?php
namespace today\gqlextend;

use yii\base\Event;
use markhuot\CraftQL\Types\VolumeInterface;

class GqlExtend extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

		$this->addFields();
    }

    function addFields ()
    {
        Event::on(\markhuot\CraftQL\Types\EntryInterface::class,\markhuot\CraftQL\Events\AlterSchemaFields::EVENT, function (\markhuot\CraftQL\Events\AlterSchemaFields $event) {
            $field = $event->sender;

            // Add a relative link to be used in app
            $event->schema->addStringField('href')
                ->resolve(function ($root, $args) {
                    return $root->uri === '__home__' ? '/' : '/' . $root->uri . '/';
                });

            // Resolve link text
            $event->schema->addStringField('linkText')
                ->resolve(function ($root, $args) {
                    return $root->__isset('linkText') ? $root->getFieldValue('linkText') : '';
                });

            // Resolve link description
            $event->schema->addStringField('linkDescription')
                ->resolve(function ($root, $args) {
                    return $root->__isset('linkDescription') ? $root->getFieldValue('linkDescription') : '';
                });

            $breadcrumbType = $event->schema->createObjectType('BreadcrumbItem');
            $breadcrumbType->addStringField('title');
            $breadcrumbType->addStringField('href');

            $event->schema->addField('breadcrumbs')
                ->lists()
                ->type($breadcrumbType)
                // ->description('Array of entries to show the current position within the site structure.')
                // ->values(['title' => 'Title of the entry', 'href' => 'Relative link of the entry'])
                ->resolve(function ($root, $args) {
                    // Build the breadcrumb trail
                    $breadcrumbs = GqlExtend::addBreadcrumb($root);

                    // Add home page
                    array_push($breadcrumbs, array(
                        'title' => 'Home',
                        'href' => '/'
                    ));

                    return array_reverse($breadcrumbs);
                });

            $imageType = $event->schema->createObjectType('Image');
            $imageType->addStringField('src');
            $imageType->addStringField('srcset');
            $imageType->addStringField('alt');
            $imageType->addStringField('position');

            $socialType = $event->schema->createObjectType('Social');
            $socialType->addStringField('title');
            $socialType->addStringField('description');
            $socialType->addField('image')->type($imageType);

            $seoType = $event->schema->createObjectType('SEO');
            $seoType->addStringField('title');
            $seoType->addStringField('description');
            $seoType->addStringField('keywords');
            $seoType->addField('social')->type($socialType);
            $seoType->addBooleanField('noindex');
            $seoType->addBooleanField('nofollow');

            // Add featured image
            $event->schema->addField('thumbnail')
                ->type($imageType)
                ->resolve(function ($root, $args) {
                    $asset = $root->__isset('featuredImage') && $root->getFieldValue('featuredImage') ? $root->getFieldValue('featuredImage')->one() : false;

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
                });

            // Add SEO
            $event->schema->addField('seo')
                ->type($seoType)
                ->resolve(function ($root, $args) {
                    $title = $root->__isset('seoTitle') && $root->getFieldValue('seoTitle') ? $root->getFieldValue('seoTitle') : $root->title;
                    $introduction = $root->__isset('introduction') ? $root->getFieldValue('introduction') : '';
                    $description = $root->__isset('seoDescription') && $root->getFieldValue('seoDescription') ? $root->getFieldValue('seoDescription') : $introduction;
                    $asset = $root->__isset('featuredImage') && $root->getFieldValue('featuredImage') ? $root->getFieldValue('featuredImage')->one() : false;
                    $image = null;

                    // Add site name
                    $title .= " | " . $root->getSite()->name;

                    if ($asset) {
                        $image = array(
                            'alt' => $asset->title,
                            'src' => $asset->url
                        );
                    };

                    return array(
                        'title' => $title,
                        'description' => $description,
                        'keywords' => $root->__isset('seoKeywords') ? $root->getFieldValue('seoKeywords') : '',
                        'social' => array(
                            'title' => $title,
                            'description' => $description,
                            'image' => $image
                        ),
                        'noindex' => $root->__isset('noindex') ? $root->getFieldValue('noindex') : false,
                        'nofollow' => $root->__isset('nofollow') ? $root->getFieldValue('nofollow') : false,
                    );
                });
        });
    }

    static function addBreadcrumb($entry, $breadcrumbs = array()) {
        array_push($breadcrumbs, array(
            'title' => $entry->title,
            'href' => '/' . $entry->uri . '/'
        ));

        $parent = $entry->getParent();

        if ($parent) {
            return GqlExtend::addBreadcrumb($parent, $breadcrumbs);
        }

        return $breadcrumbs;
    }
}
