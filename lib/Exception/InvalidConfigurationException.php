<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\Exception;

use Agit\BaseBundle\Exception\InternalErrorException;

/**
 * A page is misconfigured due to missing or invalid tokens.
 */
class InvalidConfigurationException extends InternalErrorException
{
}
