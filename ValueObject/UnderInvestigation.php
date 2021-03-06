<?php

namespace Conduction\CommonGroundBundle\ValueObject;

class UnderInvestigation
{
    public $properties;
    public $date;

    /**
     * @param array  $properties
     * @param string $date
     */
    public function __construct($properties, $date)
    {
        $this->properties = $properties;
        $this->date = $date;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }
}
