<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Service;

use Agit\IntlBundle\Service\LocaleConfigService;
use Agit\IntlBundle\Service\LocaleService;
use Agit\LocaleDataBundle\Entity\LanguageRepository;
use Collator;
use Twig_Extension;
use Twig_SimpleFunction;
use Agit\UserBundle\Service\UserService;

class NavigationExtension extends Twig_Extension
{
    private $pages;

    private $cache = [];

    /**
     * @var PageService
     */
    private $pageService;

    /**
     * @var LocaleService
     */
    private $localeService;

    /**
     * @var LocaleConfigService
     */
    private $localeConfigService;

    /**
     * @var LanguageRepository
     */
    private $languageRepository;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(
        PageService $pageService,
        LocaleService $localeService,
        LocaleConfigService $localeConfigService,
        LanguageRepository $languageRepository = null,
        UserService $userService = null
    )
    {
        $this->pageService = $pageService;
        $this->localeService = $localeService;
        $this->localeConfigService = $localeConfigService;
        $this->languageRepository = $languageRepository;
        $this->userService = $userService;
    }

    public function getName()
    {
        return "agit.page";
    }

    public function getFunctions()
    {
        return [
            new Twig_SimpleFunction("getPageName", [$this, "getPageName"], ["needs_context" => true, "is_safe" => ["all"]]),

            new Twig_SimpleFunction("getPageTree", [$this, "getPageTree"]),

            new Twig_SimpleFunction("getPageUrls", [$this, "getPageUrls"]),
            new Twig_SimpleFunction("getPageLocaleUrls", [$this, "getPageLocaleUrls"], ["needs_context" => true, "is_safe" => ["all"]]),

            new Twig_SimpleFunction("createUrl", [$this, "createUrl"], ["needs_context" => true, "is_safe" => ["all"]]),
            new Twig_SimpleFunction("breadcrumb", [$this, "breadcrumb"],  ["needs_context" => true]),

            new Twig_SimpleFunction("hasPrev", [$this, "hasPrev"],  ["needs_context" => true]),
            new Twig_SimpleFunction("hasNext", [$this, "hasNext"],  ["needs_context" => true]),
            new Twig_SimpleFunction("getPrev", [$this, "getPrev"],  ["needs_context" => true]),
            new Twig_SimpleFunction("getNext", [$this, "getNext"],  ["needs_context" => true])
        ];
    }

    public function getPageName($context)
    {
        $page = $this->pageService->getPage($context["vPath"]);

        return $page["names"][$context["locale"]];
    }

    public function getPageTree($base)
    {
        $tree = $this->pageService->getTree($base);

        return $this->sortTree($tree);
    }

    public function getPageUrls($base, $onlyAccessible = true)
    {
        $tree = $this->getPageTree($base);
        return $this->getPages($tree, $onlyAccessible, $this->localeService->getLocale());
    }

    public function breadcrumb($context, $base, $withLinks = false)
    {
        $locale = $context["locale"];
        $breadcrumb = [];
        $path = "";
        $pathParts = preg_split("|/+|", $context["vPath"], null, PREG_SPLIT_NO_EMPTY);

        foreach ($pathParts as $pathPart) {
            $path .= "/$pathPart";

            if (strpos($path, $base) !== 0) {
                continue;
            }

            $page = $this->pageService->getPage($path);

            $url = $this->pageService->createUrl($page["vPath"], $locale);
            $name = isset($page["names"][$locale]) ? $page["names"][$locale] : $page["name"];

            $breadcrumb[] = $withLinks
                ? sprintf("<a href='%s'>%s</a>", $url, $name)
                : $name;
        }

        return implode(" › ", $breadcrumb);
    }

    public function hasPrev($context)
    {
        return (bool) $this->getPrevNext($context["vPath"], -1);
    }

    public function getPrev($context)
    {
        return $this->getPrevNext($context["vPath"], -1);
    }

    public function hasNext($context)
    {
        return (bool) $this->getPrevNext($context["vPath"], 1);
    }

    public function getNext($context)
    {
        return $this->getPrevNext($context["vPath"], 1);
    }

    private function getPrevNext($vPath, $offset)
    {
        $return = null;

        if (isset($this->cache[$vPath]) && isset($this->cache[$vPath][$offset])) {
            $return = $this->cache[$vPath][$offset];
        } else {
            $dir = dirname($vPath) . "/";

            if (is_null($this->pages)) {
                $this->pages = $this->pageService->getPages();
            }

            $pages = array_filter($this->pages, function ($page) use ($dir) {
                return strpos($page["vPath"], $dir) === 0;
            });

            if (isset($pages[$vPath])) {
                uasort($pages, function ($page1, $page2) {
                    return $page1["order"] - $page2["order"];
                });

                $pagesIdx = array_keys($pages);
                $idxPages = array_flip($pagesIdx);
                $myIdx = $idxPages[$vPath];
                $return = isset($pagesIdx[$myIdx + $offset]) ? $pages[$pagesIdx[$myIdx + $offset]] : null;
            }

            if (! isset($this->cache[$vPath])) {
                $this->cache[$vPath] = [];
            }

            $this->cache[$vPath][$offset] = $return;
        }

        return $return;
    }

    private function sortTree(array $tree)
    {
        // NOTE: workaround for weird PHP bug with empty string keys
        if (isset($tree[""])) {
            $tree["index"] = $tree[""];
            unset($tree[""]);
        }

        uasort($tree, function ($branch1, $branch2) {
            $o1 = isset($branch1["data"]) ? $branch1["data"]["order"] : 100000;
            $o2 = isset($branch2["data"]) ? $branch2["data"]["order"] : 100000;
            $diff = $o1 - $o2;

            if ($diff <= -1) {
                return -1;
            } elseif ($diff >= 1) {
                return 1;
            } else {
                return 0;
            }
        });

        foreach ($tree as $k => $branch) {
            if (isset($branch["children"])) {
                $branch["children"] = $this->sortTree($branch["children"]);
                $tree[$k] = $branch;
            }
        }

        return $tree;
    }

    private function getPages(array $tree, $onlyAccessible, $locale)
    {
        $pages = [];

        foreach ($tree as $key => $value) {
            if (
                ! isset($value["data"]) || (
                    $onlyAccessible && $value["data"]["caps"] && (
                        !$this->userService || !$this->userService->currentUserCan($value["data"]["caps"])
                    )
                )
            ) {
                continue;
            }

            $name = isset($value["data"]["names"][$locale]) ? $value["data"]["names"][$locale] : $value["data"]["name"];

            if (isset($value["children"]) && count($value["children"])) {
                $children = $this->getPages($value["children"], $onlyAccessible, $locale);

                if (count($children)) {
                    $pages[$value["data"]["vPath"]] = [
                        "name"     => $name,
                        "attr"     => $value["data"]["attr"],
                        "children" => $children
                    ];
                }
            } elseif (! $value["data"]["virtual"]) {
                $pages[$value["data"]["vPath"]] = [
                    "name" => $name,
                    "attr" => $value["data"]["attr"]
                ];
            }
        }

        return $pages;
    }

    // returns the canonical path of the given path
    public function createUrl($context, $vPath, $locale = null)
    {
        if (! $vPath) {
            $vPath = $context["vPath"];
        }

        return $this->pageService->createUrl($vPath, $locale ?: $this->localeService->getLocale());
    }

    public function getPageLocaleUrls($context)
    {
        $list = [];

        if (isset($context["localeUrls"]) && $this->languageRepository) {
            $localeList = $this->localeConfigService->getActiveLocales();
            $languageCountryMap = [];

            foreach ($localeList as $localeCode) {
                if (strlen($localeCode) !== 5 || $localeCode[2] !== "_") {
                    continue;
                }

                $langCode = substr($localeCode, 0, 2);
                $countryCode = substr($localeCode, 3);

                if (! isset($languageCountryMap[$langCode])) {
                    $languageCountryMap[$langCode] = [];
                }

                $languageCountryMap[$langCode][] = $countryCode;
            }

            $langCodes = array_map(
                function ($locale) { return substr($locale, 0, 2); },
                array_keys($context["localeUrls"])
            );

            $this->languageRepository->findBy(["id" => $langCodes]); // preloading, to reduce no. of DB queries

            foreach ($context["localeUrls"] as $locale => $url) {
                $lang = substr($locale, 0, 2);
                $country = substr($locale, 3);
                $language = $this->languageRepository->find($lang);

                if ($language) {
                    $name = $language->getLocalName();

                    if (count($languageCountryMap[$lang]) > 1) {
                        $name .= " ($country)";
                    }

                    $list[$lang] = [
                        "id"        => $lang,
                        "url"       => $url,
                        "name"      => $name,
                        "isDefault" => $locale === $this->localeService->getDefaultLocale(),
                        "isCurrent" => $locale === $this->localeService->getLocale()
                    ];
                }
            }

            $collator = new Collator($this->localeService->getLocale());
            usort($list, function ($elem1, $elem2) use ($collator) {
                return $collator->compare($elem1["name"], $elem2["name"]);
            });
        }

        return $list;
    }
}
