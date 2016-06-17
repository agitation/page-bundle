<?php
/**
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/AgitPageBundle
 * @author     Alex Günsche <http://www.agitsol.com/>
 * @copyright  2012-2015 AGITsol GmbH
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Exception;

use Agit\CommonBundle\Exception\AgitException;

/**
 * A page or form was requested which the current user is not allowed to access.
 */
class UnauthorizedException extends AgitException
{
    protected $httpStatus = 403;
}
