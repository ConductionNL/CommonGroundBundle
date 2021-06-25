<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

class ResourceSubscriber implements EventSubscriberInterface
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;
    private CommonGroundService $commonGroundService;
    private Inflector $inflector;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, SerializerInterface $serializer, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->em = $em;
        $this->serializer = $serializer;
        $this->commonGroundService = $commonGroundService;
        $this->inflector = InflectorFactory::create()->build();
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['notify', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function notify(ViewEvent $event)
    {
        $method = $event->getRequest()->getMethod();
        $result = $event->getControllerResult();
        $route = $event->getRequest()->attributes->get('_route');

        if ($result && is_object($result)) {
            $type = explode('\\', get_class($result));
            $type = $this->inflector->pluralize($this->inflector->tableize(end($type)));
        } else {
            $properties = array_slice(explode('/', $event->getRequest()->getPathInfo()), -2);
            //@TODO: make dynamic for BRP etc.
            $type = $properties[0];
            $id = $properties[1];
        }

        // Only do somthing if we are on te log route and the entity is logable
        if ($this->params->get('app_notification') == 'true') {
            $notification = [];
            $notification['topic'] = "{$this->params->get('app_name')}/$type";
            switch ($method) {
                case 'POST':
                    $notification['action'] = 'Create';
                    break;
                case 'PUT':
                    $notification['action'] = 'Update';
                    break;
                case 'DELETE':
                    $notification['action'] = 'Delete';
                    break;
                default:
                    return;
            }

            if ($result) {
                $notification['resource'] = "{$this->params->get('app_url')}/$type/{$result->getId()}";
            } else {
                $notification['resource'] = "{$this->params->get('app_url')}/$type/$id";
            }

            $this->commonGroundService->createResource($notification, ['component' => 'nrc', 'type' => 'notifications'], false, true, false);
        }
    }
}
