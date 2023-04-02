<?php

namespace Blacklight\processing\adult;

use voku\helper\HtmlDomParser;

abstract class AdultMovies
{
    protected HtmlDomParser $_html;

    protected string $_title;

    protected string $_directUrl;

    /**
     * AdultMovies constructor.
     */
    public function __construct()
    {
        $this->_html = new HtmlDomParser();
    }

    /**
     * @return array|mixed
     */
    abstract protected function productInfo(bool $extras = false): mixed;

    abstract protected function covers(): mixed;

    abstract protected function synopsis(): mixed;

    abstract protected function cast(): mixed;

    abstract protected function genres(): mixed;

    abstract public function processSite(string $movie): mixed;

    abstract protected function trailers(): mixed;

    /**
     * Gets all information.
     *
     * @return array|false
     */
    public function getAll(): bool|array
    {
        $results = [];
        if ($this->_directUrl !== null) {
            $results['title'] = $this->_title;
            $results['directurl'] = $this->_directUrl;
        }

        $dummy = $this->synopsis();
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }

        $dummy = $this->productInfo(true);
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }

        $dummy = $this->cast();
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }

        $dummy = $this->genres();
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }

        $dummy = $this->covers();
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }

        $dummy = $this->trailers();
        if (\is_array($dummy)) {
            $results = array_merge($results, $dummy);
        }
        if (empty($results)) {
            return false;
        }

        return $results;
    }
}
