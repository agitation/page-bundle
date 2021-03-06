<?php
declare(strict_types=1);

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

use Twig_Environment;
use Twig_Node;
use Twig_Source;

trait PageConfigExtractorTrait
{
    protected function getConfigFromTemplate($pagePath)
    {
        $source = new Twig_Source(file_get_contents($pagePath), $pagePath, $pagePath);
        $tokenStream = $this->getTwig()->tokenize($source);
        $rootNode = $this->getTwig()->parse($tokenStream);

        return $this->findConfigInNode($rootNode);
    }

    protected function findConfigInNode(Twig_Node $node)
    {
        $config = [];

        foreach ($node->getIterator() as $childNode)
        {
            if ($childNode instanceof Twig_Node)
            {
                if ($childNode instanceof PageConfigNode)
                {
                    $config += $childNode->getConfigValues();
                }

                $config += $this->findConfigInNode($childNode);
            }
        }

        return $config;
    }

    /**
     * @return Twig_Environment instance of Twig
     */
    abstract protected function getTwig();
}
