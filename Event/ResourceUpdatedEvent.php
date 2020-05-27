<?php

// Conduction/CommonGroundBundle/Event/ResourceUpdatedEvent.php

namespace Conduction\CommonGroundBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The commonground.resource.updated event is dispatched each time afther an commonground resource is updated.
 */
class ResourceUpdatedEvent extends Event
{
    public const NAME = 'commonground.resource.updated';

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
