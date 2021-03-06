<?php

// src/Service/BRPService.php

namespace Conduction\CommonGroundBundle\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ApplicationService
{
    private $params;
    private $cache;
    private $session;
    private $flashBagInterface;
    private $request;
    private $commonGroundService;
    private $requestService;

    public function __construct(
        ParameterBagInterface $params,
        CacheInterface $cache,
        SessionInterface $session,
        FlashBagInterface $flash,
        RequestStack $requestStack,
        CommonGroundService $commonGroundService,
        RequestService $requestService
    ) {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->flash = $flash;
        $this->request = $requestStack->getCurrentRequest();
        $this->commonGroundService = $commonGroundService;
        $this->requestService = $requestService;
    }

    /*
     * Get a single resource from a common ground componant
     */
    public function getVariables()
    {
        $variables = [];

        // Lets handle the loading of a product is we have one
        $resource = $this->request->get('resource');
        if ($resource || $resource = $this->request->query->get('resource')) {
            /*@todo dit zou de commonground service moeten zijn */
            $variables['resource'] = $this->commonGroundService->getResource($resource);
        }

        // Lets handle a posible login
        $bsn = $this->request->get('bsn');
        if ($bsn || $bsn = $this->request->query->get('bsn')) {
            $user = $this->commonGroundService->getResource(['component'=>'brp', 'type'=>'ingeschrevenpersonen', 'id'=>$bsn]);
            $this->session->set('user', $user);
        }
        $variables['user'] = $this->session->get('user');

        // @todo iets met organisaties en applicaties
        if ($organization = $this->request->get('organization')) {
            $organization = $this->commonGroundService->getResource($organization);
            $this->session->set('organization', $organization);
        }

        // lets default
        elseif (!$this->session->get('organization')) {
            /*@todo param bag interface */
            //$organization = $this->commonGroundService->getResource(["component"=>"wrc","type"=>"organizations","id"=>$this->params->get('app_organization')]);
            //$organization = $this->commonGroundService->getResource('http://wrc.huwelijksplanner.online/organizations/68b64145-0740-46df-a65a-9d3259c2fec8');
            $this->session->set('organization', $organization);
            //$this->session->set('organization', 0000);
        }
        $variables['organization'] = $this->session->get('organization');

        // application
        $application = $this->request->get('application');
        if ($application || $application = $this->request->query->get('application')) {
            $application = $this->commonGroundService->getResource($application);
            $this->session->set('application', $application);
        }
        // lets default
        elseif (!$this->session->get('application')) {
            /*@todo param bag interface */
            $organization = $this->commonGroundService->getResource(['component'=>'wrc', 'type'=>'applications', 'id'=>$this->params->get('app_id')]);
            //$application = $this->commonGroundService->getResource('http://wrc.huwelijksplanner.online/applications/536bfb73-63a5-4719-b535-d835607b88b2');
            $this->session->set('application', $application);
        }
        $variables['application'] = $this->session->get('application');

        // Let handle posible request creation
        if ($requestType = $this->request->get('requestType')) {
            $requestParent = $this->request->get('requestParent');

            $requestType = $this->commonGroundService->getResource($requestType);
            $request = $this->requestService->createFromRequestType($requestType, $requestParent);

            // Validate current reqoust type

            $requestType = $this->requestService->checkRequestType($request, $requestType);

            $this->session->set('requestType', $requestType);
            if ($request != null) {
                $this->session->set('request', $request);
                /* @todo translation */
                $this->flash->add('success', 'Verzoek voor '.$requestType['name'].' opgestart');
            } else {
                $this->flash->add('failure', 'Kon geen verzoek voor '.$requestType['name'].' opstarten, omdat er al een verzoek voor '.$requestType['name'].' actief is');
            }
        }

        $variables['requestType'] = $this->session->get('requestType');

        return $variables;
    }
}
