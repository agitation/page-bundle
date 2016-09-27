<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Service;

use Agit\BaseBundle\Exception\InternalErrorException;
use Agit\BaseBundle\Service\FileCollector;
use Agit\IntlBundle\Service\LocaleService;
use Agit\PageBundle\TwigMeta\PageConfigNode;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Twig_Environment;
use Twig_Node;

/**
 * This service walks through *all* bundles and searches the Resources/views
 * directory for compatible pages.
 *
 * NOTE: Bundles can override other bundles’s pages. The priority is determined
 * by the bundle loading order in AppKernel, later definitions override earlier
 * ones.
 */
final class PageCollector implements CacheWarmerInterface
{
    const FILE_EXTENSION = "html.twig";

    private $availableTypes = ["page" => "Page", "special" => "Special"];

    private $cache;

    private $kernel;

    private $fileCollector;

    private $localeService;

    private $twig;

    private $cacheKey;

    private $defaultLocale;

    private $availableLocales;

    public function __construct(Cache $cache, Kernel $kernel, FileCollector $fileCollector, LocaleService $localeService, Twig_Environment $twig, $cacheKey)
    {
        $this->cache = $cache;
        $this->kernel = $kernel;
        $this->fileCollector = $fileCollector;
        $this->localeService = $localeService;
        $this->twig = $twig;
        $this->cacheKey = $cacheKey;
        $this->defaultLocale = $localeService->getDefaultLocale();
        $this->availableLocales = $localeService->getAvailableLocales();
    }

    /**
     * Warms up the cache, required by CacheWarmerInterface.
     */
    public function warmUp($cacheDir)
    {
        $this->collect();
    }

    /**
     * required by CacheWarmerInterface.
     */
    public function isOptional()
    {
        return true;
    }

    public function collect()
    {
        $pages = [];
        $viewsPaths = [];

        foreach ($this->kernel->getBundles() as $alias => $bundle) {
            $viewsPaths[] = $this->fileCollector->resolve("$alias:Resources:views");
        }

        $viewsPaths[] = $this->kernel->getRootDir() . "/Resources/views";
        $viewsPaths = array_filter($viewsPaths);

        foreach ($viewsPaths as $viewsPath) {
            foreach ($this->availableTypes as $type => $subdir) {
                $path = "$viewsPath/$subdir";

                if (! is_dir($path)) {
                    continue;
                }

                foreach ($this->fileCollector->collect($path, self::FILE_EXTENSION) as $pagePath) {
                    $data = $this->getData($type, $subdir, $path, $pagePath, self::FILE_EXTENSION);
                    $pages[$data["vPath"]] = $data;
                }
            }
        }

        $this->cache->save($this->cacheKey, $pages);
    }

    protected function getData($type, $subdir, $basePath, $pagePath, $extension)
    {
        $page = str_replace(["$basePath/", ".$extension"], "", $pagePath);

        $data = [
            "type"      => $type,
            "vPath"     => ($type === "page") ? $this->pageToVirtualPath($page) : "_" . basename($page),
            "template"  => $this->pathToTemplateName($basePath, $page, $extension),
            "order"     => $this->getOrderPosition($page)
        ];

        $config = $this->getConfigFromTemplate($pagePath);

        if (! isset($config["capability"])) {
            throw new InternalErrorException("Template {$data["template"]} does not define capabilities.");
        }

        $data["caps"] = (string) $config["capability"];

        $data["pageId"] = $this->makePageId($data["vPath"]); // NOTE: The page ID is unique only within its page set.
        $data["status"] = isset($config["status"]) ? (int) $config["status"] : 200;

        $twigTemplate = $this->twig->loadTemplate($data["template"]);
        $hasParent = (bool) $twigTemplate->getParent([]);
        $data["isVirtual"] = ! $hasParent; // a rather simple convention, but should be ok for our scenarios
        $data["names"] = []; // i18n

        foreach ($this->availableLocales as $locale) {
            $this->localeService->setLocale($locale);
            $data["names"][$locale] = $twigTemplate->renderBlock("name", []);
        }

        $this->localeService->setLocale($this->defaultLocale);
        $data["name"] = $twigTemplate->renderBlock("name", []);

        if ($data["isVirtual"]) {
            unset($data["template"], $data["pageId"]);
        }

        return $data;
    }

    protected function pageToVirtualPath($page)
    {
        $parts = preg_split("|/+|", $page, null, PREG_SPLIT_NO_EMPTY);

        $parts = array_map(function ($part) {
            // if the first part is numeric, it is for ordering and must be chopped off
            return preg_replace("|^\d{1,3}\.|", "", $part);
        }, $parts);

        $parts = array_filter($parts, function ($part) {
            return $part !== "index" && $part !== "";
        });

        return "/" . implode("/", $parts);
    }

    protected function pathToTemplateName($basePath, $page, $extension)
    {
        return "$basePath/$page.$extension";
    }

    protected function getOrderPosition($page)
    {
        $pos = 0;
        $parts = preg_split("|/+|", $page, null, PREG_SPLIT_NO_EMPTY);

        if (count($parts)) {
            $last = array_pop($parts);

            // when it"s an index page, then the order must be determined via the parent directory.
            if ($last === "index" && count($parts)) {
                $last = array_pop($parts);
            }

            if (preg_match("|^(\d{1,3})\.|", $last, $matches) && is_array($matches) && isset($matches[1])) {
                $pos = (int) $matches[1];
            }
        }

        return $pos;
    }

    private function makePageId($vPath)
    {
        $pageFilename = "";
        $pathParts = explode("/", trim($vPath, "/_"));
        $pageFilename .= array_shift($pathParts);
        $pathParts = array_map("ucfirst", $pathParts);
        $pageFilename .= implode("", $pathParts);

        if ($pageFilename === "") {
            $pageFilename = "index";
        }

        return $pageFilename;
    }

    private function getConfigFromTemplate($pagePath)
    {
        $tokenStream = $this->twig->tokenize(file_get_contents($pagePath));
        $rootNode = $this->twig->parse($tokenStream);

        return $this->findConfigInNode($rootNode);
    }

    private function findConfigInNode($node)
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
}
