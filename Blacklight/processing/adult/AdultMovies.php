<?php

namespace Blacklight\processing\adult;

use voku\helper\HtmlDomParser;

abstract class AdultMovies
{
    /**
     * @var \voku\helper\HtmlDomParser
     */
    protected HtmlDomParser $_html;

    /**
     * @var string
     */
    protected string $_title;

    /**
     * @var string
     */
    protected string $_directUrl;

    /**
     * AdultMovies constructor.
     */
    public function __construct()
    {
        $this->_html = new HtmlDomParser();
    }

    /**
     * @param bool $extras
     * @return array|mixed
     */
    abstract protected function productInfo(bool $extras = false): mixed;

    /**
     * @return mixed
     */
    abstract protected function covers(): mixed;

    /**
     * @return mixed
     */
    abstract protected function synopsis(): mixed;

    /**
     * @return mixed
     */
    abstract protected function cast(): mixed;

    /**
     * @return mixed
     */
    abstract protected function genres(): mixed;

    /**
     * @param string $movie
     * @return mixed
     */
    abstract public function processSite(string $movie): mixed;

    /**
     * @return mixed
     */
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
