<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Conduction\CommonGroundBundle\Service\NLXLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class ResourceSubscriber implements EventSubscriberInterface
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;
    private CommonGroundService $commonGroundService;

        public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, SerializerInterface $serializer, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->em = $em;
        $this->serializer = $serializer;
        $this->commonGroundService = $commonGroundService;
    }

        public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['notify', EventPriorities::PRE_VALIDATE],
        ];
    }

        public function notify(ViewEvent $event)
    {
        $method = $event->getRequest()->getMethod();
        $result = $event->getControllerResult();
        $route = $event->getRequest()->attributes->get('_route');

        // Only do somthing if we are on te log route and the entity is logable
        if($this->params->get('app_notification') == 'true'){
            $notification = [];
            $notification['topic'] = $this->params->get('app_name');
            switch ($method){
                case 'POST':
                    $notification['action'] = 'Create';
                    $notification['resource'] = "{$event->getRequest()->getUri()}/{$result->getId()}";
                    break;
                case 'PUT':
                    $notification['action'] = 'Update';
                    $notification['resource'] = $event->getRequest()->getUri();
                    break;
                case 'DELETE':
                    $notification['action'] = 'Delete';
                    $notification['resource'] = $event->getRequest()->getUri();
                    break;
                default:
                    return;
            }

            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications']);

        }
    }
}
