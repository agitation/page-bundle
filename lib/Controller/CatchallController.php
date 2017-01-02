<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Controller;

use Agit\IntlBundle\Tool\Translate;
use Agit\PageBundle\Event\PageRequestEvent;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CatchallController extends Controller
{
    public function dispatcherAction(Request $request)
    {
        $pageService = $this->get("agit.page");
        $reqDetails = $this->load($request);
        $pageDetails = null;
        $response = null;

        if (isset($reqDetails["canonical"]) && $request->getPathInfo() !== $reqDetails["canonical"]) {
            parse_str($request->getQueryString(), $query);
            $redirectUrl = $pageService->createUrl($reqDetails["canonical"], "", $query);
            $response = $pageService->createRedirectResponse($redirectUrl);
        } else {
            $pageDetails = $pageService->loadPage($reqDetails["vPath"]);
            $response = $this->createResponse($pageDetails, $reqDetails);
        }

        $this->setCommonHeaders($response, $pageDetails["status"]);

        $this->get("event_dispatcher")->dispatch(
            "agit.page.request",
            new PageRequestEvent($request, $response, $reqDetails, $pageDetails)
        );

        return $response;
    }

    public function exceptionAction(Request $request, FlattenException $exception, $format = "html")
    {
        $status = $exception->getStatusCode();
        $message = ($status && $status < 500) || $this->getParameter("kernel.debug")
            ? $exception->getMessage()
            : Translate::t("Sorry, there has been an internal error. The administrators have been notified and will fix this as soon as possible.");

        try {
            if ($format === "html") {
                $reqDetails = $this->load($request);
                $pageDetails = $this->get("agit.page")->getPage("_exception");
                $response = $this->createResponse($pageDetails, $reqDetails, ["message" => $message]);
            } else {
                $response = new Response($message);
            }
        } catch (Exception $e) {
            $response = $this->render("AgitPageBundle:Special:exception.html.twig", [
                "locale"  => "en_GB",
                "message" => $message
            ]);
        }

        $this->setCommonHeaders($response, $status);

        return $response;
    }

    private function load($request)
    {
        $localeService = $this->get("agit.intl.locale");

        // we’ll try to provide error messages in the UA’s language until the real locale is set
        $localeService->setLocale($localeService->getUserLocale());

        $reqDetails = $this->get("agit.page")->parseRequest($request->getPathInfo());

        // now set real locale as per request
        $localeService->setLocale($reqDetails["locale"]);

        return $reqDetails;
    }

    private function createResponse($pageDetails, $reqDetails, $extraVariables = [])
    {
        $variables = [
            "locale" => $reqDetails["locale"],
            "vPath"  => $reqDetails["vPath"]
        ] + $extraVariables;

        if (isset($reqDetails["localeUrls"]) && isset($reqDetails["localeUrls"][$reqDetails["locale"]])) {
            $variables["localeUrls"] = $reqDetails["localeUrls"];
            $variables["canonicalUrl"] = $reqDetails["localeUrls"][$reqDetails["locale"]];
        }

        return $this->render($pageDetails["template"], $variables);
    }

    private function createRedirectResponse($url, $status = 301)
    {
        $response = new Response(sprintf("<a href='%s'>%s</a>", htmlentities($url), "Click here to continue."), $status);
        $response->headers->set("Location", $url);
        $response->headers->set("Cache-Control", "no-cache, must-revalidate, max-age=0", true);
        $response->headers->set("Pragma", "no-store", true);

        return $response;
    }

    private function setCommonHeaders(Response $response, $status = 200)
    {
        $response->setStatusCode($status);
        $response->headers->set("X-Frame-Options", "SAMEORIGIN");
        $response->headers->set("Cache-Control", "no-cache, must-revalidate, max-age=0", true);
        $response->headers->set("Pragma", "no-store", true);
        $response->headers->set("X-Content-Type-Options", "nosniff", true);
    }
}
