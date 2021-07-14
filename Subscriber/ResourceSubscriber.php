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
    private ParameterBagInterface $parameterBag;
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;
    private CommonGroundService $commonGroundService;
    private Inflector $inflector;

    public function __construct(ParameterBagInterface $parameterBag, EntityManagerInterface $em, SerializerInterface $serializer, CommonGroundService $commonGroundService)
    {
        $this->parameterBag = $parameterBag;
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
        $components = $this->parameterBag->get('components');

        if ($result && is_object($result)) {
            $type = explode('\\', get_class($result));
            $type = $this->inflector->pluralize($this->inflector->tableize(end($type)));
        } else {
            $properties = array_slice(explode('/', $event->getRequest()->getPathInfo()), -2);
            //@TODO: make dynamic for BRP etc.
            $type = $properties[0];
            $id = $properties[1];
        }
        if(key_exists('notificatiecomponent', $components)){
            $notificationComponent = 'notificatiecomponent';
        } elseif(key_exists('notification-component', $components)){
            $notificationComponent = 'notification-component';
        }elseif(key_exists('notification-regitration-component', $components)){
            $notificationComponent = 'notification-registration-component';
        }elseif(key_exists('nrc', $components)){
            $notificationComponent = 'nrc';
        }

        // Only do somthing if we are on te log route and the entity is logable
        $notification = [];
        $notification['topic'] = "{$this->parameterBag->get('app_name')}/$type";
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
            $notification['resource'] = "{$this->parameterBag->get('app_url')}/$type/{$result->getId()}";
        } else {
            $notification['resource'] = "{$this->parameterBag->get('app_url')}/$type/$id";
        }

        $this->commonGroundService->createResource($notification, ['component' => $notificationComponent, 'type' => 'notifications'], false, true, false);

    }
}
