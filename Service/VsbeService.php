<?php

// Conduction/CommonGroundBundle/Service/VrcService.php

namespace Conduction\CommonGroundBundle\Service;

/*
 * The VRC Service handels logic reqoured to properly connect with the vrc component
 *
 */
class VsbeService
{
    private $commonGroundService;

    public function __construct(
        CommonGroundService $commonGroundService
    ) {
        $this->commonGroundService = $commonGroundService;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onResource(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onList(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onSave(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onSaved(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onDelete(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onDeleted(?array $resource)
    {
        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onUpdate(?array $resource)
    {
        return $resource;
    }

    /*
         * Aditional logic triggerd afther a Request has been newly created
         *
         * @param array $resource The resource before enrichment
         * @return array The resource afther enrichment
         */
    public function onUpdated(?array $resource)
    {
        // Run the request through the very small business engine
        if ($this->commonGroundService->getComponentHealth('vsbe')) {
            $vsbeResource = [];
            $vsbeResource['object'] = $resource['@id'];
            $vsbeResource['action'] = 'UPDATE';
            exec(dirname(__FILE__, 5)."/bin/console app:vsbe:start {$vsbeResource['object']} {$vsbeResource['action']} > /dev/null &");
        }

        return $resource;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onCreate(?array $resource)
    {
        $resource = $this->clearDefaults($resource);

        return $resource;
    }

    /*
     * Aditional logic triggerd afther a Request has been newly created
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onCreated(?array $resource)
    {

        // Run the request through the very small business engine
        if ($this->commonGroundService->getComponentHealth('vsbe')) {
            $vsbeResource = [];
            $vsbeResource['object'] = $resource['@id'];
            $vsbeResource['action'] = 'CREATE';
            exec(dirname(__FILE__, 5)."/bin/console app:vsbe:start {$vsbeResource['object']} {$vsbeResource['action']} > /dev/null &");
        }

        return $resource;
    }
}
