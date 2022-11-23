<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use Conduction\CommonGroundBundle\Event\CommonGroundEvents;
use Conduction\CommonGroundBundle\Event\CommongroundUpdateEvent;
use Conduction\CommonGroundBundle\Service\VrcService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class VrcSubscriber implements EventSubscriberInterface
{
    private $vrcService;

    public function __construct(VrcService $vrcService)
    {
        $this->vrcService = $vrcService;
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
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();
        $url = $event->getUrl();
        if (!$url || !is_array($url) || $url['component'] != 'vrc' || $url['type'] != 'requests') {
            return;
        }

//        $this->vrcService->clearDependencies($resource);
        $resource = $this->vrcService->createCommongroundResources($resource);
        $event->setResource($resource);

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
        $resource = $event->getResource();
        $url = $event->getUrl();

        if (!$url || !is_array($url) || $url['component'] != 'vrc' || $url['type'] != 'requests') {
            return;
        }

        $resource = $this->vrcService->createCommongroundResources($resource);
//        $this->vrcService->clearDependencies($resource);
        $event->setResource($resource);

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function updated(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();
        if (!array_key_exists('@type', $resource) || $resource['@type'] != 'Request') {
            return;
        }

        $this->vrcService->checkEvents($resource);
        $this->vrcService->checkOffers($resource);
        $event->setResource($resource);

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function create(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        $resource = $event->getResource();
        $url = $event->getUrl();
        if (!$url || !is_array($url) || $url['component'] != 'vrc' || $url['type'] != 'requests') {
            return;
        }

//        $this->vrcService->clearDependencies($resource);
        $resource = $this->vrcService->createCommongroundResources($resource);
        $event->setResource($resource);

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function created(CommongroundUpdateEvent $event)
    {
        $resource = $event->getResource();

        if (!array_key_exists('@type', $resource) || $resource['@type'] != 'Request') {
            return;
        }

        $this->vrcService->checkEvents($resource);
        $this->vrcService->checkOffers($resource);

        $event->setResource($resource);

        return $event;
    }
}
