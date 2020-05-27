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
    public function save(CommongroundUpdateEvent $event)
    {
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function saved(CommongroundUpdateEvent $event)
    {
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function delete(CommongroundUpdateEvent $event)
    {
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function deleted(CommongroundUpdateEvent $event)
    {
        var_dump($event->getResource());
        die;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function update(CommongroundUpdateEvent $event)
    {
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function updated(CommongroundUpdateEvent $event)
    {
        // Lets make sure we only triger on requests resources
        /* @todo lets also check for a vrc component */
        if ($event->getResource()['@type'] != 'Request') {
            return;
        }

        $resource = $this->vrcService->onUpdated($event->getResource());
        $event->setResource($resource);

        return $event;
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function create(CommongroundUpdateEvent $event)
    {
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function created(CommongroundUpdateEvent $event)
    {
    }
}
