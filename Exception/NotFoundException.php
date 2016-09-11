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
 * A page or form was requested which does not exist.
 */
class NotFoundException extends AgitException
{
    protected $httpStatus = 404;
}
