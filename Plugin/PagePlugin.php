<?php
/**
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/AgitPageBundle
 * @author     Alex Günsche <http://www.agitsol.com/>
 * @copyright  2012-2015 AGITsol GmbH
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Plugin;

use Agit\BaseBundle\Exception\InternalErrorException;
use Agit\BaseBundle\Pluggable\ServiceAwarePluginTrait;
use Agit\BaseBundle\Pluggable\ServiceAwarePluginInterface;
use Agit\PageBundle\TwigMeta\PageConfigNode;
use Agit\BaseBundle\Pluggable\Depends;
use Agit\BaseBundle\Pluggable\Cache\CacheEntry;
use Agit\BaseBundle\Pluggable\Cache\CachePlugin;
use Agit\BaseBundle\Pluggable\Cache\CachePluginInterface;


/**
 * @CachePlugin(tag="agit.pages")
 * @Depends({"@kernel", "@agit.common.filecollector", "@twig"})
 *
 * This plugin walks through *all* bundles and searches the Resources/views directory
 * for compatible pages.
 *
 * NOTE: Bundles can override other bundles's pages. The order is determined by the
 * bundle loading order in AppKernel.
 */
final class PagePlugin implements CachePluginInterface, ServiceAwarePluginInterface
{
    use ServiceAwarePluginTrait;

    private $availableTypes = ['page' => 'Page', 'special' => 'Special'];

    private $entryList = [];

    public function load()
    {
        $fileCollector = $this->getService('agit.common.filecollector');

        foreach ($this->getService("kernel")->getBundles() as $alias => $bundle)
        {
            foreach ($this->availableTypes as $type => $subdir)
            {
                $extension = "html.twig";
                $basePath = $fileCollector->resolve("$alias:Resources:views:$subdir");

                foreach ($fileCollector->collect($basePath, $extension) as $pagePath)
                {
                    $cacheEntry = new CacheEntry();
                    $data = $this->getData($type, $subdir, $basePath, $pagePath, $extension);

                    $cacheEntry->setId($data['vPath']);
                    $cacheEntry->setData($data);
                    $this->entryList[] = $cacheEntry;
                }
            }
        }
    }

    public function nextCacheEntry()
    {
        return array_shift($this->entryList);
    }

    protected function getData($type, $subdir, $basePath, $pagePath, $extension)
    {
        $page = str_replace(["$basePath/", ".$extension"], '', $pagePath);

        $data = [
            'type'      => $type,
            'vPath'     => ($type === 'page') ? $this->pageToVirtualPath($page) : '_' . basename($page),
            'template'  => $this->pathToTemplateName($basePath, $page, $extension),
            'order'     => $this->getOrderPosition($page)
        ];

        $config = $this->getConfigFromTemplate($pagePath);

        if (!isset($config['capability']))
            throw new InternalErrorException("Template {$data['template']} does not define capabilities.");

        $data['caps'] = (string)$config['capability'];

        $data['pageId'] = $this->makePageId($data['vPath']); // NOTE: The page ID is unique only within its page set.
        $data['status'] = isset($config['status']) ? (int)$config['status'] : 200;

        $twigTemplate = $this->getService('twig')->loadTemplate($data['template']);
        $hasParent = (bool)$twigTemplate->getParent([]);
        $data['isVirtual'] = !$hasParent; // a rather simple convention, but should be ok for our scenarios
        $data['name'] = $twigTemplate->renderBlock('title', []);

        if ($data['isVirtual'])
            unset($data['template'], $data['pageId']);

        return $data;
    }

    protected function pageToVirtualPath($page)
    {
        $parts = preg_split('|/+|', $page, null, PREG_SPLIT_NO_EMPTY);

        $parts = array_map(function($part) {
            // if the first part is numeric, it is for ordering and must be chopped off
            return preg_replace('|^\d{1,3}\.|', '', $part);
        }, $parts);

        $parts = array_filter($parts, function($part) {
            return ($part !== 'index' && $part !== '');
        });

        return '/' . implode('/', $parts);
    }

    protected function pathToTemplateName($basePath, $page, $extension)
    {
        return "$basePath/$page.$extension";
    }

    protected function getOrderPosition($page)
    {
        $pos = 0;
        $parts = preg_split('|/+|', $page, null, PREG_SPLIT_NO_EMPTY);

        if (count($parts))
        {
            $last = array_pop($parts);

            // when it's an index page, then the order must be determined via the parent directory.
            if ($last === 'index' && count($parts))
                $last = array_pop($parts);

            if (preg_match('|^(\d{1,3})\.|', $last, $matches) && is_array($matches) && isset($matches[1]))
                $pos = (int)$matches[1];
        }

        return $pos;
    }

    private function makePageId($vPath)
    {
        $pageFilename = '';
        $pathParts = explode('/', trim($vPath, '/_'));
        $pageFilename .= array_shift($pathParts);
        $pathParts = array_map('ucfirst', $pathParts);
        $pageFilename .= implode('', $pathParts);

        if ($pageFilename === '')
            $pageFilename = 'index';

        return $pageFilename;
    }

    private function getConfigFromTemplate($pagePath)
    {
        $tokenStream = $this->getService('twig')->tokenize(file_get_contents($pagePath));
        $rootNode = $this->getService('twig')->parse($tokenStream);

        return $this->findConfigInNode($rootNode);
    }

    private function findConfigInNode($node)
    {
        $config = [];

        foreach ($node->getIterator() as $childNode)
        {
            if ($childNode instanceof \Twig_Node)
            {
                if ($childNode instanceof PageConfigNode)
                    $config += $childNode->getConfigValues();

                $config += $this->findConfigInNode($childNode);
            }
        }

        return $config;
    }
}
