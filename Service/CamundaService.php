<?php

// src/Service/HuwelijkService.php

namespace Conduction\CommonGroundBundle\Service;

class CamundaService
{
    private $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /*
     * Get a single resource from a common ground componant
     *
     * @param array $request The request being send to camunda
     * @param array The camunda proces started
     */
    public function proccesFromRequest(?array $request)
    {
        // Getting the request type
        $requestType = $this->commonGroundService->getResource($request['requestType']);

        // Declare on behalve on authentication
        $services = [
            'vtc'=> ['jwt'=>'Bearer '.$this->commonGroundService->getJwtToken('vtc')],
            'vrc'=> ['jwt'=>'Bearer '.$this->commonGroundService->getJwtToken('vrc')],
        ];

        // Build eigenschapen
        $eigenschappen = [];
        foreach ($request['properties'] as $key => $value) {
            $eigenschappen[] = ['naam'=>$key, 'waarde'=>$value];
        }

        $variables = [
            'services'       => ['type'=>'json', 'value'=> json_encode($services)],
            'eigenschappen'  => ['type'=>'json', 'value'=> json_encode($eigenschappen)],
            'zaaktype'       => ['type'=>'String', 'value'=> $requestType['caseType']],
            'organisatieRSIN'=> ['type'=>'String', 'value'=> $request['organization']],
        ];

        // Build the post
        $post = ['withVariablesInReturn'=>true, 'variables'=>$variables];

        return $this->commonGroundService->createResource($post, ['component'=>'be', 'type'=>'/process-definition/key/'.$requestType['camundaProces'].'/submit-form']);
    }
}
