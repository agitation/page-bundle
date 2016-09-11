<?php

/*
 * @package    agitation/page-bundle
 * @link       http://github.com/agitation/page-bundle
 * @author     Alexander Günsche
 * @license    http://opensource.org/licenses/MIT
 */

namespace Agit\PageBundle\TwigMeta;

class PageConfigTokenParser extends \Twig_TokenParser
{
    public function parse(\Twig_Token $token)
    {
        $config = [];

        $tokenStream = $this->parser->getStream();
        $dot = $tokenStream->expect(\Twig_Token::PUNCTUATION_TYPE)->getValue();
        $field = $tokenStream->expect(\Twig_Token::NAME_TYPE)->getValue();
        $value = $tokenStream->expect(\Twig_Token::STRING_TYPE)->getValue();
        $tokenStream->expect(\Twig_Token::BLOCK_END_TYPE);

        $config[$field] = $value;

        return new PageConfigNode($config, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'agit';
    }
}
