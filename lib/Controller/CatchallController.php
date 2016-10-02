<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Controller;

use Agit\PageBundle\Event\PageRequestEvent;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class CatchallController extends Controller
{
    public function dispatcherAction(Request $request, $path)
    {
        $path = "/$path"; // for consistency
        $response = null;

        $pageService = $this->get('agit.page');
        $localeService = $this->get('agit.intl.locale');

        // we'll try to provide error messages in the UA's language until the real locale is set
        $localeService->setLocale($localeService->getUserLocale());

        $reqDetails = $pageService->parseRequest($path);

        // now set the real locale as requested via URL
        $localeService->setLocale($reqDetails['locale']);

        if (isset($reqDetails['canonical']) && $path !== $reqDetails['canonical']) {
            parse_str($request->getQueryString(), $query);
            $redirectUrl = $pageService->createUrl($reqDetails['canonical'], '', $query);
            $response = $pageService->createRedirectResponse($redirectUrl, 301);
        } else {
            $pageDetails = $pageService->loadPage($reqDetails['vPath']);
            $response = $this->createResponse($pageDetails, $reqDetails);
        }

        $this->get("event_dispatcher")->dispatch(
            "agit.page.request",
            new PageRequestEvent($request, $response, $pageDetails, $reqDetails)
        );

        return $response;
    }

    private function createResponse($pageDetails, $reqDetails)
    {
        $variables = [
            'pageId' => $pageDetails['pageId'],
            'locale' => $reqDetails['locale'],
            'vPath'  => $reqDetails['vPath']
        ];

        if (isset($reqDetails['localeUrls']) && isset($reqDetails['localeUrls'][$reqDetails['locale']])) {
            $variables['localeUrls'] = $reqDetails['localeUrls'];
            $variables['canonicalUrl'] = $reqDetails['localeUrls'][$reqDetails['locale']];
        }

        $response = $this->render($pageDetails['template'], $variables);

        $response->headers->set("X-Frame-Options", "SAMEORIGIN");
        $response->headers->set("Cache-Control", "no-cache, must-revalidate, max-age=0", true);
        $response->headers->set("Pragma", "no-store", true);
        $response->headers->set("X-Content-Type-Options", "nosniff", true);
        $response->setStatusCode($pageDetails['status']);

        return $response;
    }
}
