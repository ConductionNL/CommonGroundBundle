<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use Conduction\CommonGroundBundle\Event\CommonGroundEvents;
use Conduction\CommonGroundBundle\Event\CommongroundUpdateEvent;
use Conduction\CommonGroundBundle\Service\VsbeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class VsbeSubscriber implements EventSubscriberInterface
{
    private $vsbeService;

    public function __construct(VsbeService $vsbeService)
    {
        $this->vsbeService = $vsbeService;
    }

    public static function getSubscribedEvents()
    {
        return [
            CommonGroundEvents::SAVE     => 'save',
            CommonGroundEvents::SAVED    => 'saved',
            CommonGroundEvents::DELETE   => 'delete',
            CommonGroundEvents::DELETED  => 'deleted',
            CommonGroundEvents::CREATE   => 'create',
            CommonGroundEvents::CREATED  => 'created',
            CommonGroundEvents::UPDATE   => 'update',
            CommonGroundEvents::UPDATED  => 'updated',
            CommonGroundEvents::RESOURCE => 'resource',
            CommonGroundEvents::LIST     => 'list',
            //KernelEvents::VIEW => ['onCreate', ResourceCreateEvent::NAME],
            //ResourceCreateEvent::NAME => 'onCreate',
            //ResourceCreatedEvent::NAME => 'onCreated',
            //ResourceSaveEvent::NAME => 'onSave',
        ];
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function resource(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function list(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function save(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function saved(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function delete(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function deleted(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function update(CommongroundUpdateEvent $event)
    {
        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function updated(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();

        $resource = $this->vsbeService->onUpdated($event->getResource());
        $event->setResource($resource);

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function create(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function created(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();

        $resource = $this->vsbeService->onCreated($event->getResource());
        $event->setResource($resource);

        return $event;
    }
}
