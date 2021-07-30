<?php

namespace Conduction\CommonGroundBundle\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerService
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Gets content type from an event, and sets a default when the content type is not supported.
     *
     * @param RequestEvent $event The event triggered by the request
     *
     * @return string The content type from the request, defaulted to application/json when not supported
     */
    public function getContentType(RequestEvent $event): string
    {
        $contentType = $event->getRequest()->headers->get('accept');
        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
        }
        if (
            $contentType != 'application/json' &&
            $contentType != 'application/ld+json' &&
            $contentType != 'application/hal+json'
        ) {
            $contentType = 'application/json';
        }

        return $contentType;
    }

    /**
     * Returns the render type for the content type provided.
     *
     * @param string $contentType The content type to find the proper render type for
     *
     * @return string The render type belonging to the content type specified
     */
    public function getRenderType(string $contentType): string
    {
        switch ($contentType) {
            case 'application/json':
                return 'json';
                break;
            case 'application/ld+json':
                return 'jsonld';
                break;
            case 'application/hal+json':
                return 'jsonhal';
                break;
            default:
                return 'json';
        }
    }

    /**
     * Serialises an object into the requested render type.
     *
     * @param object     $result     The object to be serialised
     * @param string     $renderType The render type to render in
     * @param array|null $attributes Attributes to serialize
     *
     * @return string The resulting response string
     */
    public function serialize(object $result, string $renderType, ?array $attributes): string
    {
        $options = ['enable_max_depth'=> true];
        $attributes ? $options['attributes'] = $attributes : null;

        return $this->serializer->serialize(
            $result,
            $renderType,
            $options,
        );
    }

    /**
     * Creates the response for the response string.
     *
     * @param string $response    The response string to include
     * @param string $contentType The content type of the response string
     *
     * @return Response The HTTP response created
     */
    public function createResponse(string $response, string $contentType): Response
    {
        // Creating a response
        $response = new Response(
            $response,
            Response::HTTP_OK,
            ['content-type' => $contentType],
        );

        return $response;
    }

    /**
     * Sets a HTTP response for an object to serialize.
     *
     * @param object     $result     The object to serialize
     * @param RequestEvent  $event      The request event
     * @param array|null $attributes Attributes to serialize
     */
    public function setResponse(object $result, RequestEvent $event, ?array $attributes = null): void
    {
        $contentType = $this->getContentType($event);
        $renderType = $this->getRenderType($contentType);
        $response = $this->serialize($result, $renderType, $attributes);
        $event->setResponse($this->createResponse($response, $contentType));
    }
}
