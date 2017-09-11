<?php
declare(strict_types=1);
/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Exception;

use Agit\BaseBundle\Exception\PublicException;

/**
 * A page or form was requested which does not exist.
 */
class NotFoundException extends PublicException
{
    protected $statusCode = 404;
}
