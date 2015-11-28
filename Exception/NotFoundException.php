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
use Agit\CommonBundle\Exception\ExceptionCode;

/**
 * @ExceptionCode("7.0")
 *
 * A page or form was requested which does not exist.
 */
class NotFoundException extends AgitException { }