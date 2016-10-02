<?php

namespace Agit\PageBundle\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\Event;

class PageRequestEvent extends Event
{
    private $request;

    private $response;

    private $pageDetails;

    private $reqDetails;

    public function __construct(Request $request, Response $response, $pageDetails, $reqDetails)
    {
        $this->request = $request;
        $this->response = $response;
        $this->pageDetails = $pageDetails;
        $this->reqDetails = $reqDetails;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getPageDetails()
    {
        return $this->pageDetails;
    }

    public function getReqDetails()
    {
        return $this->reqDetails;
    }
}
