<?php
declare(strict_types=1);
/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Controller;

use Agit\IntlBundle\Tool\Translate;
use Agit\IntlBundle\Service\LocaleService;

use Agit\PageBundle\Event\PageRequestEvent;
use Agit\PageBundle\Service\PageService;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CatchallController extends Controller
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
    * @var PageService
    */
    private $pageService;

    /**
    * @var LocaleService
    */
    private $localeService;

    public function __construct(EventDispatcherInterface $eventDispatcher, PageService $pageService, LocaleService $localeService)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageService = $pageService;
        $this->localeService = $localeService;
    }

    public function dispatcherAction(Request $request)
    {
        $reqDetails = $this->load($request);
        $pageDetails = null;
        $response = null;

        if (isset($reqDetails['canonical']) && $request->getPathInfo() !== $reqDetails['canonical'])
        {
            parse_str((string)$request->getQueryString(), $query);
            $redirectUrl = $this->pageService->createUrl($reqDetails['canonical'], '', $query);
            $response = $this->createRedirectResponse($redirectUrl);
        }
        else
        {
            $pageDetails = $this->pageService->loadPage($reqDetails['vPath']);
            $response = $this->createResponse($pageDetails, $reqDetails);
        }

        $this->eventDispatcher->dispatch(
            'agit.page.request',
            new PageRequestEvent($request, $response, $reqDetails, $pageDetails)
        );

        return $response;
    }

    public function exceptionAction(Request $request, FlattenException $exception)
    {
        $status = $exception->getStatusCode();
        $debug = $this->getParameter('kernel.debug');
        $trace = $debug && $status >= 500 ? print_r($exception->getTrace(), true) : '';

        $message = ($status && $status < 500) || $debug
            ? $exception->getMessage()
            : Translate::t('Sorry, there has been an internal error. The administrators have been notified and will fix this as soon as possible.');

        if ($debug)
        {
            $message . sprintf(' in %s:%d', $exception->getMessage(), $exception->getTrace()[0]['file'], $exception->getTrace()[0]['line']);
        }

        if ($this->pageService->pageExists('_exception'))
        {
            $pageDetails = $this->pageService->getPage('_exception');
            $reqDetails = $this->load($request);
            $response = $this->createResponse($pageDetails, $reqDetails, ['message' => $message, 'trace' => $trace]);
        }
        else
        {
            $response = new Response("$message\n\n$trace");
            $this->setCommonHeaders($response, $status);
            $response->headers->set('Content-Type', 'text/plain; charset=utf-8', true);
        }

        return $response;
    }

    private function load($request)
    {
        // we’ll try to provide error messages in the UA’s language until the real locale is set
        $this->localeService->setLocale($this->localeService->getUserLocale());

        $reqDetails = $this->pageService->parseRequest($request->getPathInfo());

        // now set real locale as per request
        $this->localeService->setLocale($reqDetails['locale']);

        return $reqDetails;
    }

    private function createResponse($pageDetails, $reqDetails, $extraVariables = [])
    {
        $variables = [
            'locale' => $reqDetails['locale'],
            'vPath' => $pageDetails['vPath']
        ] + $extraVariables;

        if (isset($reqDetails['localeUrls']) && isset($reqDetails['localeUrls'][$reqDetails['locale']]))
        {
            $variables['localeUrls'] = $reqDetails['localeUrls'];
            $variables['canonicalUrl'] = $reqDetails['localeUrls'][$reqDetails['locale']];
        }

        $response = $this->render($pageDetails['template'], $variables);
        $this->setCommonHeaders($response, $pageDetails['status']);

        return $response;
    }

    private function createRedirectResponse($url, $status = 301)
    {
        $response = new Response(sprintf("<a href='%s'>%s</a>", htmlentities($url), 'Click here to continue.'));
        $this->setCommonHeaders($response, $status);
        $response->headers->set('Location', $url);

        return $response;
    }

    private function setCommonHeaders(Response $response, $status = 200)
    {
        $response->setStatusCode($status);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate, max-age=0', true);
        $response->headers->set('Pragma', 'no-store', true);
        $response->headers->set('X-Content-Type-Options', 'nosniff', true);
    }
}
