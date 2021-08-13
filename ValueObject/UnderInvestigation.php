<?php

namespace Conduction\CommonGroundBundle\ValueObject;

class UnderInvestigation implements \Serializable, \JsonSerializable
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

    public function jsonSerialize()
    {
        return  array_merge($this->properties, ['datumAanvangOnderzoek' => $this->date]);
    }

    public function serialize()
    {
        return json_encode($this->jsonSerialize());
    }

    public function unserialize($serialized)
    {
        $normalized = json_decode($serialized, true);
        $this->date = $normalized['datumAanvangOnderzoek'];
        unset($normalized['datumAanvangOnderzoek']);
        $this->properties = $normalized;
    }
}
