<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use Conduction\CommonGroundBundle\Event\CommonGroundEvents;
use Conduction\CommonGroundBundle\Event\CommongroundUpdateEvent;
use Conduction\CommonGroundBundle\Service\IrcService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IrcSubscriber implements EventSubscriberInterface
{
    private $ircService;

    public function __construct(IrcService $ircService)
    {
        $this->ircService = $ircService;
    }

    public static function getSubscribedEvents()
    {
        return [
            CommonGroundEvents::UPDATE  => 'update',
            CommonGroundEvents::CREATE  => 'create',
            CommonGroundEvents::CREATED => 'created',
        ];
    }

    // Our resource might reqoure aditional resources to be created, so lets look into that
    public function update(CommongroundUpdateEvent $event)
    {
        // Lets make sure that we are dealing with a Request resource from the vrc
        $resource = $event->getResource();
        $url = $event->getUrl();
        if (!$url || !is_array($url) || $url['component'] != 'irc' || $url['type'] != 'assents') {
            return;
        }

        // Lets see if we need to do anything with the resource
        $resource = $event->getResource();
        $resource = $this->ircService->scanResource($resource);
        $event->setResource($resource);

        return $event;
    }

    public function create(CommongroundUpdateEvent $event)
    {
        $resource = $event->getResource();
        $url = $event->getUrl();
        if (!$url || !is_array($url) || $url['component'] != 'irc' || $url['type'] != 'assents') {
            return false;
        }

        $event->setResource($this->ircService->scanResource($resource));

        return $event;
    }

    public function created(CommongroundUpdateEvent $event)
    {
        $resource = $event->getResource();
        if (!array_key_exists('@type', $resource) || $resource['@type'] != 'Assent') {
            return;
        }

        $resource = $this->ircService->setForwardUrl($resource);

        $event->setResource($resource);

        return $event;
    }
}
