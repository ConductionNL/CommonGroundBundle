<?php

// Conduction/CommonGroundBundle/Event/ResourceUpdateEvent.php

namespace Conduction\CommonGroundBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * The commonground.resource.update is dispatched each time before an commonground resource is updated.
 */
class CommongroundUpdateEvent extends Event
{
    public const NAME = 'commonground.update';

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
