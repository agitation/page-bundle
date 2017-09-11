<?php
declare(strict_types=1);
/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PageRequestEvent extends Event
{
    private $request;

    private $response;

    private $reqDetails;

    private $pageDetails;

    public function __construct(Request $request, Response $response, $reqDetails, $pageDetails)
    {
        $this->request = $request;
        $this->response = $response;
        $this->reqDetails = $reqDetails;
        $this->pageDetails = $pageDetails;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getReqDetails()
    {
        return $this->reqDetails;
    }

    public function getPageDetails()
    {
        return $this->pageDetails;
    }
}
