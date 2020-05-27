<?php

// Conduction/CommonGroundBundle/Event/ResourceSaveEvent.php

namespace Conduction\CommonGroundBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The commonground.resource.save event is dispatched each time before an commonground resource is saved.
 */
class ResourceSaveEvent extends Event
{
    public const NAME = 'commonground.resource.save';

    protected $resource;
    protected $component;

    public function __construct(?array $resource, ?array $component)
    {
        $this->resource = $resource;
        $this->component = $component;
    }

    public function setResource(?array $resource)
    {
        $this->resource = $resource;

        return $this;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setComponent(?array $component)
    {
        $this->component = $component;

        return $this;
    }

    public function getComponent()
    {
        return $this->component;
    }
}
