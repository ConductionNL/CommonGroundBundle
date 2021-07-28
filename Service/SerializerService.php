<?php


namespace Conduction\CommonGroundBundle\Service;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerService
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Gets content type from an event, and sets a default when the content type is not supported
     * @param ViewEvent $event The event triggered by the request
     * @return string The content type from the request, defaulted to application/json when not supported
     */
    public function getContentType(ViewEvent $event): string
    {
        $contentType = $event->getRequest()->headers->get('accept');
        if (!$contentType) {
            $contentType = $event->getRequest()->headers->get('Accept');
        }
        if(
            $contentType != 'application/json' &&
            $contentType != 'application/ld+json' &&
            $contentType != 'application/hal+json'
        ){
            $contentType = 'application/json';
        }
        return $contentType;
    }

    /**
     * Returns the render type for the content type provided
     * @param string $contentType The content type to find the proper render type for
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
     * Serialises an object into the requested render type
     * @param Object $result The object to be serialised
     * @param string $renderType The render type to render in
     * @return string The resulting response string
     */
    public function serialize(Object $result, string $renderType): string
    {
        return $this->serializer->serialize(
            $result,
            $renderType,
            ['enable_max_depth'=> true]
        );
    }

    /**
     * Creates the response for the response string
     * @param string $response The response string to include
     * @param string $contentType The content type of the response string
     * @return Response The HTTP response created
     */
    public function createResponse(string $response, string $contentType): Response
    {
        // Creating a response
        $response = new Response(
            $response,
            Response::HTTP_OK,
            ['content-type' => $contentType]
        );

        return $response;
    }

    /**
     * Sets a HTTP response for an object to serialize
     * @param Object $result The object to serialize
     * @param ViewEvent $event The request event
     */
    public function setResponse(Object $result, ViewEvent $event): void
    {
        $contentType = $this->getContentType($event);
        $renderType = $this->getRenderType($contentType);
        $response = $this->serialize($result, $renderType);
        $event->setResponse($this->createResponse($response, $contentType));
    }
}
