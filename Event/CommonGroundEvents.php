<?php

// Conduction/CommonGroundBundle/Event/ResourceCreatedEvent.php

namespace Conduction\CommonGroundBundle\Event;

/**
 * TheCommonGroundEvents holds hookable events triggerd from the commonground service.
 */
class CommonGroundEvents
{
    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceEvent")
     */
    public const RESOURCE = 'commonground.resource.resource';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceListEvent")
     */
    public const LIST = 'commonground.resource.list';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceCreateEvent")
     */
    public const CREATE = 'commonground.resource.create';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceCreatedEvent")
     */
    public const CREATED = 'commonground.resource.created';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\CommongroundUpdateEvent")
     */
    public const UPDATE = 'commonground.update';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceUpdatedEvent")
     */
    public const UPDATED = 'commonground.resource.updated';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceSaveEvent")
     */
    public const SAVE = 'commonground.resource.save';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceSavedEvent")
     */
    public const SAVED = 'commonground.resource.saved';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceDeleteEvent")
     */
    public const DELETE = 'commonground.resource.delete';

    /**
     * Called directly before the Lorem Ipsum API data is returned.
     *
     * Listeners have the opportunity to change that data.
     *
     * @Event("Conduction\CommonGroundBundle\Event\ResourceDeletedEvent")
     */
    public const DELETED = 'commonground.resource.deleted';
}
