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
use Agit\PageBundle\Exception\InvalidConfigurationException;
use Agit\PageBundle\TwigMeta\PageConfigNode;
use Agit\PageBundle\TwigMeta\PageConfigExtractorTrait;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Twig_Compiler;
use Twig_Environment;
use Twig_Node;
use Twig_Node_Expression_Function;

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
    use PageConfigExtractorTrait;

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
            throw new InternalErrorException(sprintf("Page `%s` does not define capabilities.", $data["template"]));
        }

        $data["caps"] = $config["capability"];
        $data["status"] = isset($config["status"]) ? $config["status"] : 200;

        $twigTemplate = $this->twig->loadTemplate($data["template"]);
        $hasParent = (bool) $twigTemplate->getParent([]);
        $data["virtual"] = isset($config["virtual"]);

        if (! isset($config["name"])) {
            throw new InvalidConfigurationException(sprintf("Page `%s` is missing the `agit.name` tag.", $data["template"]));
        }

        $data["names"] = $this->getNames($config["name"]);
        $data["name"] = $data["names"][$this->defaultLocale];

        if ($data["virtual"]) {
            unset($data["template"]);
        }

        return $data;
    }

    protected function getNames($nameNode)
    {
        $names = [];

        if ($nameNode instanceof Twig_Node_Expression_Function) {
            $function = $this->twig->getFunction($nameNode->getAttribute("name"));
            $callable = $function->getCallable();
            $args = [];

            foreach ($nameNode->getNode("arguments") as $argNode) {
                $args[] = $argNode->getAttribute("value");
            }

            foreach ($this->availableLocales as $locale) {
                $this->localeService->setLocale($locale);
                $names[$locale] = call_user_func_array($callable, $args);
            }
        } elseif (is_string($nameNode)) {
            foreach ($this->availableLocales as $locale) {
                $names[$locale] = $nameNode;
            }
        } else {
            throw new InvalidConfigurationException("The value for `agit.name` must be either a string or a function expression.");
        }

        $this->localeService->setLocale($this->defaultLocale);

        return $names;
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

    // needed by PageConfigExtractorTrait
    protected function getTwig()
    {
        return $this->twig;
    }
}
