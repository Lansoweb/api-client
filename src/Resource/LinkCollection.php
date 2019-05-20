<?php

namespace Los\ApiClient\Resource;

use Psr\Link\LinkInterface;

use function array_filter;
use function in_array;

trait LinkCollection
{
    /**
     * @var LinkInterface[]
     */
    private $links = [];

    /**
     * {@inheritDoc}
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * {@inheritDoc}
     */
    public function getLinksByRel($rel)
    {
        return array_filter($this->links, function (LinkInterface $link) use ($rel) {
            $rels = $link->getRels();
            return in_array($rel, $rels, true);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function withLink(LinkInterface $link)
    {
        if (in_array($link, $this->links, true)) {
            return $this;
        }

        $new = clone $this;
        $new->links[] = $link;
        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutLink(LinkInterface $link)
    {
        if (! in_array($link, $this->links, true)) {
            return $this;
        }

        $new = clone $this;
        $new->links = array_filter($this->links, function (LinkInterface $compare) use ($link) {
            return $link !== $compare;
        });
        return $new;
    }
}
