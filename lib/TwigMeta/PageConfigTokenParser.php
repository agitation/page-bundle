<?php
declare(strict_types=1);
/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

use Agit\PageBundle\Exception\InvalidConfigurationException;
use Twig_Token;
use Twig_TokenParser;

class PageConfigTokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $config = [];

        $tokenStream = $this->parser->getStream();
        $tokenStream->expect(Twig_Token::PUNCTUATION_TYPE)->getValue();
        $field = $tokenStream->expect(Twig_Token::NAME_TYPE)->getValue();
        $current = $tokenStream->getCurrent();

        if ($field === 'status')
        {
            $value = $tokenStream->expect(Twig_Token::NUMBER_TYPE)->getValue();
        }
        elseif ($field === 'capability' || $field === 'attr')
        {
            $value = $tokenStream->expect(Twig_Token::STRING_TYPE)->getValue();
        }
        elseif ($field === 'name')
        {
            $value = ($current->getType() === Twig_Token::STRING_TYPE)
                ? $tokenStream->expect(Twig_Token::STRING_TYPE)->getValue()
                : $this->parser->getExpressionParser()->parseExpression();
        }
        elseif ($field === 'virtual')
        {
            $value = true;
        }
        else
        {
            throw new InvalidConfigurationException(sprintf('Unknown token for %s in line %s.', $field, $token->getLine()));
        }

        $tokenStream->expect(Twig_Token::BLOCK_END_TYPE);

        $config[$field] = $value;

        return new PageConfigNode($config, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'agit';
    }
}
