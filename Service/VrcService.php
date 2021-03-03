<?php

// Conduction/CommonGroundBundle/Service/VrcService.php

namespace Conduction\CommonGroundBundle\Service;

use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/*
 * The VRC Service handels logic reqoured to properly connect with the vrc component
 *
 */
class VrcService
{
    private $commonGroundService;
    private $flash;
    private $camundaService;

    public function __construct(
        CommonGroundService $commonGroundService,
        FlashBagInterface $flash,
        CamundaService $camundaService
    ) {
        $this->commonGroundService = $commonGroundService;
        $this->flash = $flash;
        $this->camundaService = $camundaService;
    }

    /*
     * Remove interdependend properties
     *
     * @param array $request The request before removal dependecies
     * @return array The request afther removal of dependecies
     */
    public function clearDependencies(?array $request)
    {
        // We want to ignore the cache here
        $request = $this->commonGroundService->getResource(['component' => 'vrc', 'type' => 'requests', 'id'=>$request['id']], [], true);
        // Lets first see if we can grap an requested type and if it has stages
        if (!$requestType = $this->commonGroundService->getResource($request['requestType'])) {
            return $request;
        }
        $dependencyArray = [];
        foreach ($requestType['properties'] as $property) {
            $dependencies = [];
            foreach ($property['query'] as $key => $value) {
                if (strstr($value, 'request')) {
                    $value = substr($value, strrpos($value, '.') + 1);
                    array_push($dependencies, $value);
                } else {
                    array_push($dependencies, $value);
                }
                $dependencyArray[$property['name']] = $dependencies;
            }
        }
        foreach ($request['properties'] as $key => $value) {
            if (array_key_exists($key, $dependencyArray)) {
                $dependencies = $dependencyArray[$key];
                foreach ($dependencies as $dependency) {
                    if (!array_key_exists($dependency, $request['properties'])) {
                        unset($request['properties'][$key]);
                    }
                }
            }
        }

        return $request;
    }

    /**
     * This function checks if a subresource only contains empty values.
     *
     * @param $value mixed The subresource to be checked
     *
     * @return bool If the subresource contains values
     */
    public function checkIfEmpty($value)
    {
        if (is_array($value)) {
            $booleans = [];
            foreach ($value as $sub) {
                $booleans[] = $this->checkIfEmpty($sub);
            }
            if (in_array(false, $booleans)) {
                return false;
            } else {
                return true;
            }
        } elseif ($value !== null && $value !== '') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * This function translates nested objects on a request to commonground resources
     *
     * @param array $request The request before stage completion checks
     * @return array The resourceType afther stage completion checks
     */
    public function createCommongroundResources($request)
    {
        // If we don't have any properties then there is no need to create resources
        if (!array_key_exists('properties', $request) || $request['properties'] === null) {
            return $request;
        }

        foreach ($request['properties'] as $key => $value) {

            // We currently support both name and uuid based keying of properties
            if (filter_var($key, FILTER_VALIDATE_URL)) {
                $property = $this->commonGroundService->getResource($key);
            } else {
                $property = $this->getPropertyByName($key, $request);
            }

            // lets check if the component is a commonground resource
            if (is_array($value) && array_key_exists('iri', $property) && ($property['format'] == 'url' || $property['format'] == 'uri') && $component = explode('/', $property['iri'])) {
                //&& count($component) == 2

                // Lets support arrays
                if ($property['type'] == 'array') {
                    foreach ($value as $propertyKey => $propertyValue) {
                        if ($this->checkIfEmpty($propertyValue)) {
                            unset($request['properties'][$key][$propertyKey]);
                        } elseif (is_array($propertyValue) || !$this->commonGroundService->isResource($propertyValue)) {
                            $createdResource = $this->commonGroundService->saveResource($propertyValue, ['component' => $component[0], 'type' => $component[1]]);
                            if (is_array($createdResource) && key_exists('@id', $createdResource)) {
                                $request['properties'][$key][$propertyKey] = $createdResource['@id'];
                            }
                        }
                    }
                } else {
                    if ($this->checkIfEmpty($value)) {
                        unset($request['properties'][$key]);
                    } elseif (is_array($value) || !$this->commonGroundService->isResource($value)) {
                        $createdResource = $this->commonGroundService->saveResource($value, ['component' => $component[0], 'type' => $component[1]]);
                        if (is_array($createdResource) && key_exists('@id', $createdResource)) {
                            $request['properties'][$key] = $createdResource['@id'];
                        }
                    }
                }
            }
        }

        return $request;
    }

    /*
     * This function tests if a order should be created
     *
     * @param array $request The request before stage completion checks
     * @return array The resourceType afther stage completion checks
     */
    public function checkEvents(array $request)
    {
        if (!array_key_exists('properties', $request)) {
            return $request;
        }

        // Let make a list of al the calanders that need an event
        $requestCalendars = [];

        // Lets create a start date  for  this request
        // Oke we need to try to figure out  a date for this request
        if (array_key_exists('datum', $request['properties'])) {
            $startDate = (new \DateTime($request['properties']['datum']));
        } elseif (array_key_exists('date', $request['properties'])) {
            $startDate = (new \DateTime($request['properties']['date']));
        } else {
            $startDate = new \DateTime();
        }

        // Lets default the startdate to the enddate
        $endDate = $startDate;

        // Lets walk trough al te properties and see if any has a calendar or duration
        foreach ($request['properties'] as $key => $value) {

            // We currently support both name and uuid based keying of properties
            if (filter_var($key, FILTER_VALIDATE_URL)) {
                $property = $this->commonGroundService->getResource($key);
            } else {
                $property = $this->getPropertyByName($key, $request);
            }

            // Falback for unfindable properties
            if (!is_array($property)) {
                continue;
            }

            if (
                array_key_exists('iri', $property) &&
                $property['format'] == 'uri' &&
                $component = explode('/', $property['iri'])
            ) { //count($component) == 2

                $propertyArray = [];
                // Bit wierd but for offers we use nested values
                if ($property['iri'] == 'pdc/offer') {
                    $offers = [];
                    if ($property['type'] == 'array') {
                        $offers = array_merge($offers, $value);
                    } else {
                        $offers[] = $value;
                    }
                    foreach ($offers as $offer) {
                        $offer = $this->commonGroundService->getResource($offer);
                        foreach ($offer['products'] as $product) {
                            $propertyArray[] = $product['@id'];
                        }
                    }
                } // Normal
                elseif ($property['type'] == 'array') {
                    $propertyArray = array_merge($propertyArray, $value);
                } else {
                    $propertyArray[] = $value;
                }

                foreach ($propertyArray as $propertyValue) {
                    $propertyValue = $this->commonGroundService->getResource($propertyValue);
                    if (array_key_exists('duration', $propertyValue) && $propertyValue['duration']) {
                        $endDate->add(new \DateInterval($propertyValue['duration']));
                    }
                    if (array_key_exists('calendar', $propertyValue) && $propertyValue['calendar']) {
                        $requestCalendars[] = $propertyValue['calendar'];
                    }
                }
            }
        }

        // If there are no offers do not nothing
        if (empty($requestCalendars)) {
            return $requestCalendars;
        }

        /*
         * If we have submitters we want to create a calanders for the submitters (if they dont have one already)
         */
        if (array_key_exists('submitters', $request)) {
            foreach ($request['submitters'] as $submitter) {
                // We only create calenders for validated submitters
                if (!array_key_exists('brp', $submitter)) {
                    continue;
                }

                $calendars = $this->commonGroundService->getResourceList(['component' => 'arc', 'type' => 'calendars'], ['resource' => $submitter['brp']])['hydra:member'];
                if (count($calendars) > 0) {
                    $calendar = $calendars[0];
                } else {
                    // Make a user calendar
                    $calendar = [];
                    $calendar['resource'] = $submitter['brp'];
                    $calendar['name'] = $this->commonGroundService->getResource($submitter['brp'])['burgerservicenummer'];
                    /* @todo nope nope nope dit moet een naam zijn */
                    $calendar['organization'] = $request['organization'];
                    $calendar['timeZone'] = 'CET';
                    $calendar = $this->commonGroundService->saveResource($calendar, ['component' => 'arc', 'type' => 'calendars']);
                }
                $requestCalendars[] = $calendar['@id'];
            }
        }

        /*
         * Lets loop trough al the calendars and create or update the nececery events
         */
        foreach ($requestCalendars as $calendar) {
            // create or update  event
            $events = $this->commonGroundService->getResourceList(['component' => 'arc', 'type' => 'events'], ['calendar.id' =>$calendar, 'resource' => $request['@id']])['hydra:member'];

            if (count($events) > 0) {
                $event = $events[0];
                $event['startDate'] = $startDate->format('Y-m-d H:i:s');
                $event['endDate'] = $endDate->format('Y-m-d H:i:s');
                unset($event['calendar']);
                $event = $this->commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);
            } else {
                $requestType = $this->commonGroundService->getResource($request['requestType']);
                $event = [];
                $event['name'] = $request['reference'];
                $event['description'] = $requestType['name'];
                $event['organization'] = $request['organization'];
                $event['resource'] = $request['@id'];
                $event['calendar'] = $calendar;
                $event['startDate'] = $startDate->format('Y-m-d H:i:s');
                $event['endDate'] = $endDate->format('Y-m-d H:i:s');
                $event['priority'] = 1;
                $event = $this->commonGroundService->saveResource($event, ['component' => 'arc', 'type' => 'events']);
            }
        }

        return $request;
    }

    /*
     * This function tests if a order should be created
     *
     * @param array $request The request before stage completion checks
     * @return array The resourceType afther stage completion checks
     */
    public function checkOffers(array $request)
    {
        if (!array_key_exists('properties', $request)) {
            return $request;
        }

        // Let make a list of al the calanders that need an event
        $requestOffers = [];

        // Lets walk trough al te properties and see if any has a calendar or duration
        foreach ($request['properties'] as $key => $value) {

            // We currently support both name and uuid based keying of properties
            if (filter_var($key, FILTER_VALIDATE_URL)) {
                $property = $this->commonGroundService->getResource($key);
            } else {
                $property = $this->getPropertyByName($key, $request);
            }

            // Falback for unfindable properties
            if (!is_array($property)) {
                continue;
            }

            if (
                array_key_exists('iri', $property) &&
                (
                    $property['format'] == 'uri' ||
                    $property['format'] == 'url'
                ) &&
                $property['iri'] == 'pdc/offer' &&
                $component = explode('/', $property['iri'])
            ) { //count($component) == 2
                // Lets support arrays of iri's
                if ($property['type'] == 'array') {
                    $requestOffers = array_merge($requestOffers, $value);
                } else {
                    $requestOffers[] = $value;
                }
            }
        }

        // If there are no offers do not nothing
        if (empty($requestOffers)) {
            return $request;
        }

        /*
         * Lets see if the request already has an associated order
         */
        if (!array_key_exists('order', $request) || !$request['order']) {
            $order = [];

            $order['name'] = $request['reference'];
            $order['description'] = $request['reference'];
            $order['organization'] = $request['organization'];
            $order['resources'] = [$request['@id']];

            // Determining the custommer
            if (array_key_exists('submitters', $request) && !empty($request['submitters'])) {
                // Lets go trought the options here
                if (array_key_exists('brp', $request['submitters'][0])) {
                    $order['customer'] = $request['submitters'][0]['brp'];
                } else {
                    $order['customer'] = $request['submitters'][0]['person'];
                }
            } else {
                /* @todo elseif to use the user */
                $order['customer'] = $request['organization'];
            }

            $order = $this->commonGroundService->saveResource($order, ['component' => 'orc', 'type' => 'orders']);

            // We have a new order so will need to write the order to the request
            $request['order'] = $order['@id'];

            unset($request['submitters']);
            unset($request['children']);
            unset($request['parent']);
            $request = $this->commonGroundService->saveResource($request, ['component' => 'vrc', 'type' => 'requests'], true, false);
        } else {
            $order = $this->commonGroundService->getResource($request['order'], [], true);
        }
        $orderIRI = "/orders/{$order['id']}";

        /*
         * Lets loop trough al the calendars and create or update the nececery events
         */

        $requestItems = [];

        foreach ($requestOffers as $offer) {
            $offer = $this->commonGroundService->getResource($offer);
            // or in the mean time just replace the whole offer array
            $orderItem = [];
            $orderItem['offer'] = $offer['@id'];

            if (array_key_exists('name', $offer)) {
                $orderItem['name'] = $offer['name'];
            }
            if (array_key_exists('description', $offer)) {
                $orderItem['description'] = $offer['description'];
            }
            if (array_key_exists('price', $offer)) {
                $orderItem['price'] = (string) $offer['price'];
            }
            if (array_key_exists('priceCurrency', $offer)) {
                $orderItem['priceCurrency'] = $offer['priceCurrency'];
            }

            $orderItem['quantity'] = 1;
            $orderItem['order'] = $orderIRI;
            $requestItems[$offer['@id']] = $orderItem;
        }

        // Lets see if the offers is already in the order
        $orderItems = [];
        if (array_key_exists('items', $order)) {
            foreach ($order['items'] as $orderItem) {
                // Needs to be deleted
                if (!array_key_exists($orderItem['offer'], $requestItems)) {
                    $this->commonGroundService->deleteResource($orderItem);
                    continue;
                }
                // Als we need to keep it
                $orderItems[$orderItem['offer']] = $orderItem;
            }
        }

        // else add it
        foreach ($requestItems as $requestItemId => $requestItem) {
            //let see if we already have it
            if (array_key_exists($requestItemId, $orderItems)) {
                continue;
            }
            // We need to add it
            $orderItem = $this->commonGroundService->saveResource($requestItem, ['component' => 'orc', 'type' => 'order_items']);
        }

        // Lets see if we need to make an invoice
        /* @todo dit zou een losse service moeten zijn */
        if (
            array_key_exists('status', $request) &&
            in_array($request['status'], ['submitted', 'in progress', 'progressed']) &&
            array_key_exists('order', $request) &&
            $request['order']
        ) {
            $invoices = $this->commonGroundService->getResourceList(['component' => 'bc', 'type' => 'invoices'], ['order'=>$request['order']])['hydra:member'];
            if (count($invoices) > 0) {
                $invoice = $invoices[0];
            } else {
                //$post = ['url'=>$request['order']];
                //$invoice = $this->commonGroundService->saveResource($post, ['component' => 'bc', 'type' => 'order']);
            }
        }

        // We have changed some stuff about the order and its value so lets force a cache reload

        $order = $this->commonGroundService->getResource($request['order'], [], true);
        //$this->commonGroundService->clearFromsCash($order);

        return $request;
    }

    /*
     * Checks the property of a request against the requestType to determine if it is valid
     *
     * @param array $request The request to wichs the property belongs
     * @param string $property The key of the property to checks
     * @return boolean Whether or not a property is valid to its requestTypes
     */
    public function checkProperty(?array $request, $property, $stageNumber)
    {
        $result = ['value'=>null, 'valid'=>true, 'messages'=>[]];

        $currentStage['orderNumber'] = null;

        if (isset($request['currentStage'])) {
            if (filter_var($request['currentStage'], FILTER_VALIDATE_URL)) {
                $currentStage = $this->commonGroundService->getResource($request['currentStage']);
            } else {
                $currentStage = $this->commonGroundService->getResourceList(['component' => 'ptc', 'type' => 'stages'], ['name' => ucfirst($request['currentStage'])])['hydra:member'];
                if (isset($currentStage[0])) {
                    $currentStage = $currentStage[0];
                }
            }
        }

        // Lets see if the property is requered and unset, in wich case we do not need to do more validation
        if ((!array_key_exists($property['name'], $request['properties'])) && key_exists('orderNumber', $currentStage) && $stageNumber >= $currentStage['orderNumber']) {
            $result['messages'] = ['value is required'];
            $result['valid'] = false;

            return $result;
        } elseif ((!array_key_exists($property['name'], $request['properties'])) && $property['required']) {
            $result['messages'] = ['value is required'];
            $result['valid'] = false;

            return $result;
        }
        // If we don't have a validation further checking has no point
        elseif (!array_key_exists($property['name'], $request['properties'])) {
            $result['messages'] = ['value is empty'];

            return $result;
        }

        $result['value'] = $request['properties'][$property['name']];

        // Now we could hit multiple problems, so lets turn de message in an array

        // Type validation
        if ($property['type']) {
            switch ($property['type']) {
                case 'string':

                    if (!is_string($property['type'])) {
                        $result['messages'][] = 'value should be a string';
                        $result['valid'] = false;
                    }

                    if ($property['maxLength'] && strlen($property['value']) > (int) $property['maxLength']) {
                        $result['messages'][] = 'value should be longer then'.$property['maxLength'];
                        $result['valid'] = false;
                    }
                    if ($property['minLength'] && strlen($property['value']) < (int) $property['minLength']) {
                        $result['messages'][] = 'value should be shorter then'.$property['minLength'];
                        $result['valid'] = false;
                    }
                    if ($property['pattern']) {
                    }

                    // Format is only validated in combination with type string
                    if ($property['format']) {
                        switch ($property['format']) {
                            case 'url':
                                break;
                            case 'date':
                                if ($property['minDate']) {
                                }
                                if ($property['maxDate']) {
                                }

                                break;
                            default:
                                $result['message'][] = 'property format '.$property['format'].' is not supported';
                        }
                    }

                    break;
                case 'integer':

                    if ($property['multipleOf']) {
                    }
                    if ($property['maximum'] && $property['exclusiveMaximum']) {
                    }
                    if ($property['maximum'] && !$property['exclusiveMaximum']) {
                    }
                    if ($property['minimum'] && $property['exclusiveMinimum']) {
                    }
                    if ($property['minimum'] && !$property['exclusiveMinimum']) {
                    }
                    break;
                case 'boolean':

                    break;
                case 'number':

                    break;
                case 'array':

                    if (!is_array($property['type'])) {
                        $result['messages'][] = 'value should be a string';
                        $result['valid'] = false;
                    } else {
                        if ($property['maxItems'] && count($result['value']) > (int) $property['maxItems']) {
                            $result['messages'][] = 'There should be no more then '.$property['maxItems'].' items';
                            $result['valid'] = false;
                        }
                        if ($property['minItems'] && count($result['value']) < (int) $property['minItems']) {
                            $result['messages'][] = 'There should be no less then '.$property['minItems'].' items';
                            $result['valid'] = false;
                        }
                        if ($property['uniqueItems']) {
                        }
                    }

                    break;
                default:
                    $result['message'][] = 'property type '.$property['type'].' is not supported';
            }
        }

        // Lets look for requered values
        if ($property['enum']) {
            if (!is_array($property['enum'])) {
                $property['enum'] = explode(',', $property['enum']);
            }
            if (!in_array($result['value'], $property['enum'])) {
                $result['messages'][] = 'There the value should be one of '.implode(',', $property['enum']);
                $result['valid'] = false;
            }
        }

        if ($property['availableFrom']) {
            $date = new DateTime($property['availableFrom']);
            $now = new DateTime();

            if ($date > $now) {
                $result['messages'][] = 'This property is not yet available';
                $result['valid'] = false;
            }
        }

        if ($property['availableUntil']) {
            $date = new DateTime($property['availableFrom']);
            $now = new DateTime();

            if ($date < $now) {
                $result['messages'][] = 'This property is no longer available';
                $result['valid'] = false;
            }
        }

        if ($property['readOnly'] && $request['value']) {
            $result['messages'][] = 'This property is read only';
            $result['valid'] = false;
        }

        if ($property['iri']) {
        }

        // format validation to be implemented
        /*
                "query": [],
                "additionalItems": null,
                "maxProperties": null,
                "minProperties": null,
                "properties": null,
                "additionalProperties": null,
                "object": null,
                "nullable": null,
                "discriminator": null,
                "xml": null,

            */

        // format validation noy to bu suporterd
        /*
         *  "deprecated": null
         *  "writeOnly": null,
         */

        return $result;
    }

    /*
     * This functions supports backwards compatibilty by getting a property on a by name basis
     *
     * @param string $property The name of the property to get
     * @param array $request The request to wichs the property belongs
     * @return array The requested property
     */
    public function getPropertyByName(?string $name, ?array $request)
    {
        /* @tod would we like to support requests as strings here? */

        // Lets first see if we can grap an requested type
        if (!array_key_exists('requestType', $request) || !$requestType = $this->commonGroundService->getResource($request['requestType'])) {
            /* @to error handling */
            return false;
        }

        $properties = [];

        // lets then index the request type properties by name
        foreach ($requestType['properties'] as $property) {
            $properties[$property['name']] = $property;
        }

        // Lets then see if we can grap the property by name
        if (array_key_exists($name, $properties)) {
            return $properties[$name];
        }

        // Lets default to false
        return false;
    }
}
