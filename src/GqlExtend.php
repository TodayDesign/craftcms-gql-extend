<?php
namespace today\gqlextend;

use today\gqlextend\lib\GqlExtendCraftql;
use today\gqlextend\lib\GqlExtendGraphql;

class GqlExtend extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

        GqlExtendGraphql::init();

        if (\Craft::$app->plugins->isPluginInstalled('markhuot/craftql')) {
            GqlExtendCraftql::init();
        }
    }
}
