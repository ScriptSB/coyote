<?php

namespace Coyote\Services\Parser\Factories;

use Coyote\Repositories\Contracts\PageRepositoryInterface;
use Coyote\Repositories\Contracts\UserRepositoryInterface;
use Coyote\Repositories\Contracts\WordRepositoryInterface;
use Coyote\Services\Parser\Container;
use Coyote\Services\Parser\Parsers\Censore;
use Coyote\Services\Parser\Parsers\Geshi;
use Coyote\Services\Parser\Parsers\Link;
use Coyote\Services\Parser\Parsers\Markdown;
use Coyote\Services\Parser\Parsers\Purifier;
use Coyote\Services\Parser\Parsers\Smilies;

class PostFactory extends AbstractFactory
{
    /**
     * Parse post
     *
     * @param string $text
     * @return string
     */
    public function parse(string $text) : string
    {
        start_measure('parsing', 'Parsing post...');

        $isInCache = $this->isInCache($text);
        if ($isInCache) {
            $text = $this->getFromCache($text);
        }

        if (!$isInCache || $this->isSmiliesAllowed()) {
            $parser = new Container();

            if (!$isInCache) {
                $text = $this->cache($text, function () use ($parser) {
                    $parser->attach((new Markdown($this->app[UserRepositoryInterface::class]))->setBreaksEnabled(true));
                    $parser->attach(new Purifier());
                    $parser->attach(new Link($this->app[PageRepositoryInterface::class], $this->request->getHost()));
                    $parser->attach(new Censore($this->app[WordRepositoryInterface::class]));
                    $parser->attach(new Geshi());

                    return $parser;
                });
            }

            if ($this->isSmiliesAllowed()) {
                $parser->attach(new Smilies());
                $text = $parser->parse($text);
            }
        }
        stop_measure('parsing');

        return $text;
    }
}
