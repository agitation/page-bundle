<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

use Twig_Node;
use Twig_Environment;

trait PageConfigExtractorTrait
{
    protected function getConfigFromTemplate($pagePath)
    {
        $tokenStream = $this->getTwig()->tokenize(file_get_contents($pagePath));
        $rootNode = $this->getTwig()->parse($tokenStream);

        return $this->findConfigInNode($rootNode);
    }

    protected function findConfigInNode(Twig_Node $node)
    {
        $config = [];

        foreach ($node->getIterator() as $childNode) {
            if ($childNode instanceof Twig_Node) {
                if ($childNode instanceof PageConfigNode) {
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
