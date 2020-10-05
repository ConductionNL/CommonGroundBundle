<?php

// Conduction/CommonGroundBundle/Service/IrcService.php

namespace Conduction\CommonGroundBundle\Service;

class IrcService
{
    private $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function scanResource(array $resource)
    {
        // Lets see if we need to create a contact for the contact
        if (!empty($resource['contact']) && !empty($resource['contact']['@id'])) {
            $contact = $this->commonGroundService->saveResource($resource['contact'], ['component'=>'cc', 'type'=>'people']);
            if (is_array($contact) && key_exists('@id', $contact)) {
                $resource['contact'] = $contact['@id'];
            }
        }

        // Lets see if we need to create a contact for the requester

        if (array_key_exists('requester', $resource) && is_array($resource['requester']) && !array_key_exists('@id', $resource['requester'])) {
            $contact = $this->commonGroundService->saveResource($resource['requester'], ['component'=>'cc', 'type'=>'people']);
            if (is_array($contact) && key_exists('@id', $contact)) {
                $resource['requester'] = $contact['@id'];
            }
        }

        return $resource;
    }
}
