<?php

namespace Conduction\CommonGroundBundle\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\SerializerInterface;

class FieldsAndExtendSubscriber implements EventSubscriberInterface
{
    private $params;
    private $serializer;
    private $propertyAccessor;
    private $em;
    private $commonGroundService;

    public function __construct(ParameterBagInterface $params, SerializerInterface $serializer, EntityManagerInterface $em, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->serializer = $serializer;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->em = $em;
        $this->commonGroundService = $commonGroundService;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['FilterFields', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function FilterFields(ViewEvent $event)
    {
        $result = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        $route = $event->getRequest()->attributes->get('_route');

        $fields = $event->getRequest()->query->get('fields');
        $extends = $event->getRequest()->query->get('extend');
        $contentType = $event->getRequest()->headers->get('accept');
        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
        }
        // Only do somthing if fields is query supplied
        if ((!$fields && !$extends) || $method != 'GET') {
            return $result;
        }
        $authorization = $event->getRequest()->headers->get('Authorization');

        $this->commonGroundService->setHeader('Authorization', $authorization);
        // Lets set a return content type
        switch ($contentType) {
            case 'application/json':
                $renderType = 'json';
                break;
            case 'application/ld+json':
                $renderType = 'jsonld';
                break;
            case 'application/hal+json':
                $renderType = 'jsonhal';
                break;
            default:
                $contentType = 'application/ld+json';
                $renderType = 'jsonld';
        }

        $json = $this->serializer->serialize(
            $result,
            $renderType,
            ['enable_max_depth' => true]
        );

        $array = json_decode($json, true);
        if (!key_exists('id', $array)) {
            foreach ($array as $key=> $resource) {
                $array[$key] = $resource = $this->getExtends($extends, $resource);

                if ($fields != [] && $fields != '') {
                    $array[$key] = $this->getFields($fields, $resource);
                }
            }

            switch ($renderType) {
                case 'jsonld':
                    if(count($array) > 0)
                        $response['@context'] = $array[0]['@context'];
                    $response['@id'] = $event->getRequest()->getPathInfo();
                    $response['@type'] = 'hydra:Collection';
                    $response['hydra:member'] = array_values($array);
                    $response['hydra:totalItems'] = count($array);
                    break;
                default:
                    $response['_embedded']['item'] = $array;
                    $response['totalItems'] = count($array);
                    $response['itemsPerPage'] = 30;
                    $links = [];
                    foreach ($array as $key=>$resource) {
                        array_push($links, json_decode($json, true)[$key]['_links']['self']);
                    }
                    $response['_links'] = $response['_links'] = [
                        'self' => ['href'=>$event->getRequest()->getRequestUri()],
                        'item' => $links,
                    ];
                    break;
            }
        } else {
            $array = $this->getExtends($extends, $array);
            if ($fields != [] && $fields != '') {
                $array = $this->getFields($fields, $array);
            }
            $response = $array;
        }

        if ($fields != [] && $fields != '') {
            // now we need to overide the normal subscriber
            $json = $this->serializer->serialize(
                $response,
                $renderType,
                ['enable_max_depth' => true,
                    'attributes'    => $fields, ]
            );
        } else {
            $json = $this->serializer->serialize(
                $response,
                $renderType,
                ['enable_max_depth' => true]
            );
        }
        // Creating a response
        $response = new Response(
            $json,
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );
        $this->commonGroundService->setHeader('Authorization', $this->params->get('app_application_key'));
        $event->setResponse($response);
    }

    public function getExtends($extends, $array)
    {
        if (!is_array($extends)) {
            $extends = explode(',', $extends);
        }
        foreach ($extends as $extend) {
            $extend = explode('.', $extend);
            $array = $this->recursiveExtend($array, $extend);
        }

        return $array;
    }

    public function getFields($fields, $array)
    {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        // Its possible to nest fields for filterins
        foreach ($fields as $key=>$field) {
            $field = explode('.', $field);
            unset($fields[$key]);
            $field = $this->recursiveField($field);

            $fields = $this->array_merge_recursive_ex($fields, $field);
        }
//            die;
        // Overwrite maxdepth for extended properties
        // we always need to return an id and links (in order not to break stuff)
        if (!in_array('id', $fields)) {
            $fields[] = 'id';
        }
        if (!in_array('@id', $fields)) {
            $fields[] = '@id';
        }
        if (!in_array('@type', $fields)) {
            $fields[] = '@type';
        }
        if (!in_array('@context', $fields)) {
            $fields[] = '@context';
        }

        return $this->selectFields($array, $fields);
    }

    public function recursiveExtend(array $resource, array $extend)
    {
        $sub = array_shift($extend);
        if (
            array_key_exists($sub, $resource) &&
            is_array($resource[$sub])
        ) {
            $resource[$sub] = $this->recursiveExtend($resource[$sub], $extend);
        } elseif (
            array_key_exists($sub, $resource) &&
            filter_var($resource[$sub], FILTER_VALIDATE_URL) &&
            $value = $this->commonGroundService->isResource($resource[$sub])
        ) {
            $resource[$sub] = $value;
        }

        return $resource;
    }

    public function recursiveField(array $field)
    {
        $sub = array_shift($field);

        if ($field == null) {
            return [$sub];
        } else {
            return [$sub=>$this->recursiveField($field)];
        }
    }

    public function selectFields(array $resource, array $fields)
    {
        $returnArray = [];
        foreach ($fields as $key=>$field) {
            if (!is_array($field) && array_key_exists($field, $resource)) {
                $returnArray[$field] = $resource[$field];
            } elseif (array_key_exists($key, $resource) && $resource[$key] && is_array($resource[$key])) {
                if (!array_key_exists($key, $returnArray)) {
                    $returnArray[$key] = $this->selectFields($resource[$key], $field);
                } else {
                    $returnArray[$key] = array_merge($returnArray[$key], $this->selectFields($resource[$key], $field));
                }
            }
        }

        return $returnArray;
    }

    public function array_merge_recursive_ex(array $array1, array $array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_ex($merged[$key], $value);
            } elseif (is_numeric($key)) {
                if (!in_array($value, $merged)) {
                    $merged[] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
