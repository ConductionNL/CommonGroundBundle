<?php

// Conduction/CommonGroundBundle/Service/VrcService.php

namespace Conduction\CommonGroundBundle\Service;

/*
 * The VRC Service handels logic reqoured to properly connect with the vrc component
 *
 */
class VrcService
{
    private $commonGroundService;
    private $camundaService;

    public function __construct(CommonGroundService $commonGroundService, CamundaService $camundaService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->camundaService = $camundaService;
    }

    /*
     * Validates a resource with optional commonground and component specific logic
     *
     * @param array $resource The resource before enrichment
     * @param array The resource afther enrichment
     */
    public function onSave(?array $resource)
    {
        // Lets get the request type
        if (array_key_exists('requestType', $resource)) {
            $requestType = $this->commonGroundService->getResource($resource['requestType']);
        }

        // Lets get the process type
        if (array_key_exists('processType', $resource)) {
            $processType = $this->commonGroundService->getResource($resource['processType']);
        }

        // We need to loop trough the properties, and see if items need to be created
        if (array_key_exists('properties', $resource)) {
            foreach ($resource['properties'] as $property) {
            }
        }

        // Lets see if we need to create assents for the submitters
        if (array_key_exists('submitters', $resource)) {
            foreach ($resource['submitters'] as $submitter) {
            }
        }

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
        // If the request has Zaak properties we need to trigger those
        if (array_key_exists('caseType', $requestType) && !array_key_exists('cases', $resource)) {
            /* @todo create a case */
        }

        // If the request has Camunda requests we need to trigger those
        if (array_key_exists('camundaProces', $requestType) && !array_key_exists('processes', $resource)) {
            /* @todo start a camunda procces */
            $procces = $this->camundaService->proccesFromRequest($resource);
            $resource['processes'] = [$procces];
        }
    }

    /*
     * Aditional logic triggerd afther a Request has been newly created
     *
     * @param array $resource The resource before enrichment
     * @return array The resource afther enrichment
     */
    public function onUpdated(?array $resource)
    {
        // Lets first see if we can grap an requested type
        if (!$requestType = $this->commonGroundService->getResource($resource['requestType'])) {
            return;
        }

        // If the request has Zaak properties we need to trigger those
        if (array_key_exists('caseType', $requestType) && !array_key_exists('cases', $resource) && !array_key_exists('camundaProces', $requestType)) {
            /* @todo create a case */
            $case = null;
            $resource['cases'] = [$case];
        }

        // If the request has Camunda requests we need to trigger those
        if (array_key_exists('camundaProces', $requestType)) { //&& (!key_exists('processes', $resource) || count($resource['processes']) == 0))
            /* @todo start a camunda procces */

            $resource = $this->startProcess($resource, $requestType['camundaProces'], $requestType['caseType']);

            var_dump($this->getTasks($resource));
            die;
        }

        return $resource;
    }

    /*
     * Start a Cammunda procces from e resource
     *
     * @param array $resource The resource before enrichment
     * @param string $proccess The uuid of the proccess to start
     * @param string $caseType The uuid of the casetype to start
     * @return array The resource afther enrichment
     */
    public function startProcess(?array $resource, ?string $proccess, ?string $caseType)
    {
        // Lets first see if we can grap an requested type
        if (!$requestType = $this->commonGroundService->getResource($resource['requestType'])) {
            return false;
        }

        $properties = [];

        // Declare on behalve on authentication
        $services = [
            'ztc'=> ['jwt'=>'Bearer '.$this->commonGroundService->getJwtToken('ztc')],
            'zrc'=> ['jwt'=>'Bearer '.$this->commonGroundService->getJwtToken('zrc')],
        ];

        $formvariables = $this->commonGroundService->getResource(['component'=>'be', 'type'=>'process-definition/key/'.$proccess.'/form-variables']);

        // Transfer the  default properties
        foreach ($formvariables as $key => $value) {
            $properties[] = ['naam'=> $key, 'waarde'=>$value['value']];
        }

        // Transfer te request properties
        foreach ($resource['properties'] as $key => $value) {
            $properties[] = ['naam'=> $key, 'waarde'=> $value];
        }

        $variables = [
            'services'       => ['type'=>'json', 'value'=> json_encode($services)],
            'eigenschappen'  => ['type'=>'json', 'value'=> json_encode($properties)],
            'zaaktype'       => ['type'=>'String', 'value'=> 'https://openzaak.utrechtproeftuin.nl/catalogi/api/v1/zaaktypen/'.$caseType],
            'organisatieRSIN'=> ['type'=>'String', 'value'=> $this->commonGroundService->getResource($resource['organization'])['rsin']],
        ];

        // Build the post
        $post = ['withVariablesInReturn'=>true, 'variables'=>$variables];

        $procces = $this->commonGroundService->createResource($post, ['component'=>'be', 'type'=>'process-definition/key/'.$requestType['camundaProces'].'/submit-form']);
        $resource['processes'] = [$procces['links'][0]['href']];

        /* @todo dit is  natuurlijk but lellijk en moet eigenlijk worden upgepakt in een onCreate hook */
        unset($resource['submitters']);
        unset($resource['children']);
        unset($resource['parent']);

        return $resource = $this->commonGroundService->saveResource($resource, ['component'=>'vrc', 'type'=>'requests']);
    }

    /*
     * Get Camunda tasks for a given request
     *
     * @param array $resource The resource before enrichment
     * @return array The resource afther enrichment
     */
    public function getTasks(?array $resource)
    {
        $tasks = [];

        // Lets see if we have procceses tied to this request
        if (!array_key_exists('processes', $resource) || !is_array($resource['processes'])) {
            return $tasks;
        }

        // Lets get the tasks for each procces atached to this request
        foreach ($resource['processes'] as $process) {
            //$processTasks = $this->commonGroundService->getResourceList(['component'=>'be','type'=>'task'],['processInstanceId'=> $this->commonGroundService->getUuidFromUrl($process)]);
            $processTasks = $this->commonGroundService->getResourceList(['component'=>'be', 'type'=>'task'], ['processInstanceId'=> '0a3d56dd-9345-11ea-ae32-0e13a3f6559d']);
            // Lets get the form elements
            foreach ($processTasks as $key=>$value) {
                $processTasks[$key]['form'] = $this->getTaskForm($value['id']);
            }
            $tasks = array_merge($tasks, $processTasks);
        }

        return $tasks;
    }

    /*
     * Gets a form for a given task
     *
     * @param string $taskId The task uuid
     * @return string The xhtml form
     */
    public function getTaskForm(?string $taskId)
    {
        return $this->commonGroundService->getResource(['component'=>'be', 'type'=>'task', 'id'=> $taskId.'/rendered-form', 'accept'=>'application/xhtml+xml']);
    }

    /*
     * Gets a requestType from a request and validates stage completion
     *
     * @param array $request The request before stage completion checks
     * @return array The resourceType afther stage completion checks
     */
    public function checkProperties(?array $request)
    {
        foreach ($request['properties'] as $property) {
        }

        return $request;
    }

    /*
     * Checks the property of a request against the requestType to determine if it is valid
     *
     * @param array $request The request to wichs the property belongs
     * @param string $property The key of the property to checks
     * @return boolean Whether or not a property is valid to its requestTypes
     */
    public function checkProperty(?array $request, $propertys)
    {
        // Lets first see if we can grap an requested type and if it has stages
        if (!$requestType = $this->commonGroundService->getResource($resource['requestType']) || !array_key_exists('stages', $requestType)) {
            return;
        }

        return $requestType;
    }
}
