<?php

namespace Conduction\CommonGroundBundle\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface as CacheInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class MessageService
{
    private $params;
    private $cache;
    private $client;
    private $session;
    private $commonGroundService;

    public function __construct(ParameterBagInterface $params, CacheInterface $cache, SessionInterface $session, CommonGroundService $commonGroundService)
    {
        $this->params = $params;
        $this->cash = $cache;
        $this->session = $session;
        $this->commonGroundService = $commonGroundService;
    }

    public function createMessage($contact, $data, $template)
    {
        $message = [];
        $message['sender'] = 'https://cc.huwelijksplanner.online/organizations/95c3da92-b7d3-4ea0-b6d4-3bc24944e622'; //@TODO: organisatie in WRC uitlezen
        $message['reciever'] = $contact['@id'];
        $message['service'] = '/services/a8b29815-7fdd-45a1-9951-aab9462b4457';
        $message['content'] = $template;
        $message['data'] = $data;
        $message['data']['contact'] = $contact;
        $message['status'] = 'queued';
//        var_dump($message);
//        die;

        $message = $this->commonGroundService->createResource($message, 'https://bs.huwelijksplanner.online/messages');

        return $message;
    }
}
