<?php

class DHLCapabilityAndQuoteHandler extends DHLXmlPiManager {

    function queryCapability($options) {
        // retrive xml from view
        $this->_xml = $this->retrieveXmlFromView('capabilityRequest', $options);

        // make request & parse
        $response = simplexml_load_string($this->sendCallPI());
        
        // return null on order not found
        if (!$response)
            return null;
        elseif(isset($response->Response) && isset($response->Response->Status) && $response->Response->Status->ActionStatus == 'Error')
            return null;
        elseif(isset($response->Response) && isset($response->Response->Note) && isset($response->Response->Note))
            return null;

        $dhlCapabilityResponse = new DHLCapabilityResponse;

        // add services
        $srvs = $response->GetCapabilityResponse->Srvs->Srv;
        foreach ($srvs as $srv) {
            $srv = $srv->MrkSrv;

            $service = new DHLService();
            $service->localProductCode = (string) $srv->LocalProductCode;
            $service->productShortName = (string) $srv->ProductShortName;
            $service->localProductName = (string) $srv->LocalProductName;
            $service->networkTypeCode = (string) $srv->NetworkTypeCode;
            $service->pOfferedCustAgreement = (string) $srv->POfferedCustAgreement;
            $service->transInd = (string) $srv->TransInd;

            $dhlCapabilityResponse->services[] = $service;
        }

        return $dhlCapabilityResponse;
    }

}

?>
