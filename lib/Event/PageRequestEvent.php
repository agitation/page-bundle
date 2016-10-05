<?php

namespace Agit\PageBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\Event;

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
