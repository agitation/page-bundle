<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Exception;

use Agit\BaseBundle\Exception\AgitException;

/**
 * A page or form was requested which the current user is not allowed to access.
 */
class UnauthorizedException extends AgitException
{
    protected $httpStatus = 403;
}
