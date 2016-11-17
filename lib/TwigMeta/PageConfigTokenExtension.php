<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

use Twig_Extension;

class PageConfigTokenExtension extends Twig_Extension
{
    public function getTokenParsers()
    {
        return [new PageConfigTokenParser()];
    }

    public function getName()
    {
        return "agit.page.meta";
    }
}
