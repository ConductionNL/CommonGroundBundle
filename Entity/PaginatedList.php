<?php

namespace Conduction\CommonGroundBundle\Entity;



/**
 * An list with pagination functionality
 *
 * This entity represents a list of commonground resources extend with basic funtionality for paginiation
 *
 * @author Ruben van der Linde <ruben@conduction.nl>
 *
 * @category Entity
 *
 * @license EUPL <https://github.com/ConductionNL/productenendienstencatalogus/blob/master/LICENSE.md>
 *
 */
class PaginatedList implements ArrayAccess
{
    /**
     * @var array The items in this list
     *
     */
    public $results;

    /**
     * @var integer The amount of pages in this list
     *
     * @example 5
     *
     */
    private $pages;

    /**
     * @var integer The current page of the list
     *
     * @example 1
     *
     */
    private $page;

    /**
     * @var integer The total amount of results (so not just the ones in the current list)
     *
     * @example 5
     *
     */
    private $amount;

    /**
     * @var integer The start number of the current results
     *
     * @example 5
     *
     */
    private $offset;

    /**
     * @var integer The current limit of this list e.g. the amount of results shown on a page
     *
     * @example 5
     *
     */
    private $limit;

    /**
     * @var array The query used to create this list
     *
     */
    private $query;

    /*
     * Populate the object with an result from the commonground service
     */
    public function load(array $result, ?array $query): self
    {
        // PLAIN JSON
        if(array_key_exists("results", $result)) $this->results = $result["results"];
        if(array_key_exists("amount", $result)) $this->amount = $result["amount"];

        // JSON-LD
        if(array_key_exists("hydra:member", $result)) $this->results = $result["hydra:member"];
        if(array_key_exists("hydra:totalItems", $result)) $this->amount = $result["hydra:totalItems"];

        // JSON-HAL
        if(array_key_exists("_embedded", $result)) $this->results = $result["_embedded"];
        if(array_key_exists("totalItems", $result)) $this->amount = $result["totalItems"];
        if(array_key_exists("itemsPerPage", $result)) $this->limit = $result["itemsPerPage"];

        // Of cource we might not get all the data we need from the source api, so we might need to do some calculations our selfs
        if(!$this->amount) $this->amount = count($this->results );
        if(!$this->limit) $this->limit = $this->results;
        if(!$this->pages) $this->pages = (int) $this->amount / $this->limit;

        // de offset is iets trickyer
        if(!$this->page && $this->offset) $this->page = $this->offset/$this->limit;
        if(!$this->offset && $this->page) $this->offset =  ($this->page - 1) * $this->limit;
        if(!$this->offset && !$this->page) $this->offset =  0;
        if(!$this->page) $this->page =  1;

        // And then we might want to pass the query
        if($query) $this->query = $query;

        return $this;
    }

    /*
     * We need some to array magic
     */
    public function offsetSet($key, $value): self
    {
        if (is_null($key)) {
            $this->results[] = $value;
        } else {
            $this->results[$key] = $value;
        }

        return $this;
    }

    public function offsetExists($key)
    {
        return isset($this->results[$key]);
    }

    public function offsetUnset($key): self
    {
        unset($this->results[$key]);
    }

    public function offsetGet($key)
    {
        if (! isset($this->results[$key])) {
            return null;
        }

        return $this->results[$key];
    }
}
