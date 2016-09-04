<?php
/**
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/AgitPageBundle
 * @author     Alex Günsche <http://www.agitsol.com/>
 * @copyright  2012-2015 AGITsol GmbH
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Twig;

use Agit\BaseBundle\Exception\InternalErrorException;
use Agit\BaseBundle\Service\LocaleService;
use Agit\LocaleDataBundle\Entity\LanguageRepository;
use Agit\PageBundle\Service\PageService;

class PageNavigationExtension extends \Twig_Extension
{
    private $pageService;

    private $pages;

    private $cache = [];

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }

    public function getName()
    {
        return "agit.page.navigation";
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction("getPageTree", [$this, "getPageTree"]),
            new \Twig_SimpleFunction("hasPrev", [$this, "hasPrev"],  ["needs_context" => true]),
            new \Twig_SimpleFunction("hasNext", [$this, "hasNext"],  ["needs_context" => true]),
            new \Twig_SimpleFunction("getPrev", [$this, "getPrev"],  ["needs_context" => true]),
            new \Twig_SimpleFunction("getNext", [$this, "getNext"],  ["needs_context" => true])
        ];
    }

    public function getPageTree($base)
    {
        $tree = $this->pageService->getTree($base);
        $this->sortTree($tree);
        return $tree;
    }

    public function hasPrev($context)
    {
        return (bool)$this->getPrevNext($context["vPath"], -1);
    }

    public function getPrev($context)
    {
        return $this->getPrevNext($context["vPath"], -1);
    }

    public function hasNext($context)
    {
        return (bool)$this->getPrevNext($context["vPath"], 1);
    }

    public function getNext($context)
    {
        return $this->getPrevNext($context["vPath"], 1);
    }

    private function getPrevNext($vPath, $offset)
    {
        $return = null;

        if (isset($this->cache[$vPath]) && isset($this->cache[$vPath][$offset]))
        {
            $return = $this->cache[$vPath][$offset];
        }
        else
        {
            $dir = dirname($vPath) . "/";

            if (is_null($this->pages))
                $this->pages = $this->pageService->getPages();

            $pages = array_filter($this->pages, function($page) use ($dir){
                return (strpos($page["vPath"], $dir) === 0);
            });

            if (isset($pages[$vPath]))
            {
                uasort($pages, function($page1, $page2) {
                    return $page1["order"] - $page2["order"];
                });

                $pagesIdx = array_keys($pages);
                $idxPages = array_flip($pagesIdx);
                $myIdx = $idxPages[$vPath];
                $return = isset($pagesIdx[$myIdx + $offset]) ? $pages[$pagesIdx[$myIdx + $offset]] : null;
            }

            if (!isset($this->cache[$vPath]))
                $this->cache[$vPath] = [];

            $this->cache[$vPath][$offset] = $return;
        }

        return $return;
    }

    private function sortTree(array &$tree)
    {
        // NOTE: workaround for weird PHP bug with empty string keys
        if (isset($tree[""]))
        {
            $tree["index"] = $tree[""];
            unset($tree[""]);
        }

        uasort($tree, function($branch1, $branch2){

            if (!isset($branch1["data"]))
                $diff = 1;
            elseif (!isset($branch2["data"]))
                $diff = -1;
            else
                $diff = $branch1["data"]["order"] - $branch2["data"]["order"];

            if ($diff === 0)    return 0;
            elseif ($diff > 1)  return 1;
            else                return -1;
        });

        foreach ($tree as &$branch)
            if (isset($branch["children"]))
                $this->sortTree($branch["children"]);
    }
}
