<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Twig;

use Agit\IntlBundle\Service\LocaleConfigService;
use Agit\IntlBundle\Service\LocaleService;
use Agit\LocaleDataBundle\Entity\LanguageRepository;
use Agit\PageBundle\Service\PageService;
use Collator;
use Twig_SimpleFunction;

class PageContentExtension extends \Twig_Extension
{
    private $pageService;

    private $localeService;

    private $localeConfigService;

    private $languageRepository;

    public function __construct(PageService $pageService, LocaleService $localeService, LocaleConfigService $localeConfigService, LanguageRepository $languageRepository = null)
    {
        $this->pageService = $pageService;
        $this->localeService = $localeService;
        $this->localeConfigService = $localeConfigService;
        $this->languageRepository = $languageRepository;
    }

    public function getName()
    {
        return "agit.page.pagecontent";
    }

    public function getFunctions()
    {
        return [
            new Twig_SimpleFunction("createUrl", [$this, "createUrl"], ["is_safe" => ["all"]]),
            new Twig_SimpleFunction("getPageLocaleUrls", [$this, "getPageLocaleUrls"], ["needs_context" => true, "is_safe" => ["all"]])
        ];
    }

    // returns the canonical path of the given path
    public function createUrl($vPath)
    {
        return $this->pageService->createUrl($vPath, $this->localeService->getLocale());
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

                    $list[$locale] = [
                        "url"       => $url,
                        "name"      => $name,
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

    private function sortList($list)
    {
        $collator = new Collator($this->localeService->getLocale());
        $collator->asort($list);

        return $list;
    }
}
