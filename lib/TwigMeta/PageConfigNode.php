<?php
declare(strict_types=1);

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

class PageConfigNode extends \Twig_Node
{
    private $config;

    public function __construct(array $config, $lineno = 0, $tag = null)
    {
        $this->config = $config;
        parent::__construct([], $config, $lineno, $tag);
    }

    public function getConfigValues()
    {
        return $this->config;
    }

    public function getNodeTag()
    {
        return 'agit';
    }
}
