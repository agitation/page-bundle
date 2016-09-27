<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Service;

use Agit\BaseBundle\Exception\InternalErrorException;
use Agit\BaseBundle\Service\UrlService;
use Agit\IntlBundle\Service\LocaleService;
use Agit\IntlBundle\Service\LocaleConfigService;
use Agit\UserBundle\Service\UserService;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpFoundation\Response;

// NOTE: The language which comes first in %agit.intl.locales% is the one that
// will show when a page is called without language suffix. Modify the order of
// locales in %agit.intl.locales% in order to set a different language as default.

class PageService
{
    private $pages;

    private $urlService;

    private $primaryLocale;

    private $currentLocale;

    private $activeLocales;

    private $userService;

    public function __construct(Cache $cache, UrlService $urlService, LocaleService $localeService, LocaleConfigService $localeConfigService, $cacheKey, UserService $userService = null)
    {
        $this->pages = $cache->fetch($cacheKey) ?: [];
        $this->urlService = $urlService;
        $this->currentLocale = $localeService->getLocale();
        $this->activeLocales = $localeConfigService->getActiveLocales();
        $this->primaryLocale = reset($this->activeLocales);
        $this->userService = $userService;
    }

    public function parseRequest($request)
    {
        $reqParts = preg_split("|/+|", $request, null, PREG_SPLIT_NO_EMPTY);
        $lang = end($reqParts);
        $locale = $this->getLocaleFromLangId($lang);
        if ($locale) {
            array_pop($reqParts);
        }

        $vPath = "/" . implode("/", $reqParts);
        $pageType = "page";

        if (! $this->isPage($vPath)) {
            $pageType = "special";
            $vPath = "_notfound";
        }

        if (! $locale || ! in_array($locale, $this->activeLocales)) {
            $locale = $this->primaryLocale;
        }

        $reqDetails = [
            "request" => $request,
            "vPath"   => $vPath,
            "locale"  => $locale
        ];

        if ($pageType !== "special") {
            $reqDetails["localeUrls"] = [];
            foreach ($this->activeLocales as $activeLocale) {
                $reqDetails["localeUrls"][$activeLocale] = $this->createUrl($vPath, $activeLocale);
            }

            $reqDetails["canonical"] = parse_url($reqDetails["localeUrls"][$locale], PHP_URL_PATH);
        }

        return $reqDetails;
    }

    public function createUrl($vPath, $locale = null, array $params = [])
    {
        $parts = [];
        $hash = "";

        if (strpos($vPath, "#") !== false) {
            $hash = strstr($vPath, "#");
            $vPath = strstr($vPath, "#", true);
        }

        $vPath = trim($vPath, "/");

        if ($vPath) {
            $parts[] = $vPath;
        }

        if ($locale === null) {
            $locale = $this->currentLocale;
        }

        if ($locale !== $this->primaryLocale && in_array($locale, $this->activeLocales)) {
            $parts[] = substr($locale, 0, 2);
        }

        return $this->urlService->createAppUrl(implode("/", $parts), $params) . $hash;
    }

    public function createRedirectResponse($url, $status = 302)
    {
        $response = new Response(sprintf("<a href='%s'>%s</a>", htmlentities($url), "Click here to continue."), $status);
        $response->headers->set("Location", $url);
        $response->headers->set("Cache-Control", "no-cache, must-revalidate, max-age=0", true);
        $response->headers->set("Pragma", "no-store", true);

        return $response;
    }

    public function loadPage($vPath)
    {
        if (! $this->isPage($vPath)) {
            $page = $this->getPage("_notfound");
        } else {
            $page = $this->getPage($vPath);

            if ($page["isVirtual"]) {
                $page = $this->getPage("_notfound");
            } elseif ($page["caps"]) {
                if (! $this->userService || ! $this->userService->getCurrentUser()) {
                    $page = $this->getPage("_unauthorized");
                } elseif (! $this->userService->currentUserCan($page["caps"])) {
                    $page = $this->getPage("_forbidden");
                }
            }
        }

        return $page;
    }

    public function isPage($vPath)
    {
        return isset($this->pages[$vPath]);
    }

    public function getPage($vPath)
    {
        if (! $this->isPage($vPath)) {
            throw new InternalErrorException("Page `$vPath` does not exist.");
        }

        return $this->pages[$vPath];
    }

    public function getPages()
    {
        return $this->pages;
    }

    // creates a hierachical representation
    public function getTree($prefix)
    {
        return $this->createTree($this->pages, $prefix);
    }

    private function getLocaleFromLangId($string)
    {
        $locale = null;

        if (is_string($string) && strlen($string) === 2) {
            foreach ($this->activeLocales as $activeLocale) {
                if (substr($activeLocale, 0, 2) === $string) {
                    $locale = $activeLocale;
                    break;
                }
            }
        }

        return $locale;
    }

    // NOTE: If the $prefix ends with a slash, the "root" page will be omitted, otherwise included.
    private function createTree($pages, $prefix = "/")
    {
        ksort($pages);
        $tree = [];

        foreach ($pages as $vPath => $details) {
            if ($vPath[0] === "_" || ($prefix && strpos($vPath, $prefix) !== 0)) {
                continue;
            }

            $vPathParts = array_filter(explode("/", trim(substr($vPath, strlen($prefix)), "/")));
            $totalDepth = count($vPathParts);
            $curDepth = 0;

            // root index page
            if (count($vPathParts) === 0) {
                $tree[""] = ["data" => $details, "children" => []];
            } else {
                $curBranch = &$tree;

                foreach ($vPathParts as $part) {
                    ++$curDepth;

                    if (! isset($curBranch[$part])) {
                        $curBranch[$part] = [];
                    }

                    if ($curDepth === $totalDepth) {
                        $curBranch[$part]["data"] = $details;
                    } else {
                        if (! isset($curBranch[$part]["children"])) {
                            $curBranch[$part]["children"] = [];
                        }

                        $curBranch = &$curBranch[$part]["children"];
                    }
                }
            }
        }

        return $tree;
    }
}
