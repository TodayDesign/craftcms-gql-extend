<?php
namespace today\gqlextend;

use today\gqlextend\lib\GqlExtendGraphql;

class GqlExtend extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

        GqlExtendGraphql::init();
    }
}
