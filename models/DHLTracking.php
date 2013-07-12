<?php
class DHLTracking {
    
    const PI_URL = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';
    const PI_URL_TEST = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet';
    
    var $inTestMode;
    
    //
    var $userId = "911comprep";
    var $passwd = "DiiC08pR3p";
    
    var $_errors = array();
    var $errorFail = false;
    var $_xml = null;
    var $_result = null;
    var $_xmlEnd = "\n";    
    
    public $proxy = false;
    public $proxyAuth = false;
    public $useProxy = false;

    function __construct($inTestMode = true) {
        $this->inTestMode = $inTestMode;
    }

    public function setAuth($userid = NULL, $passwd = NULL) {
        $this->userId = $userid;
        $this->passwd = $passwd;
    }

    public function setProxyInfo($proxy, $proxyAuth, $use = true) {
        $this->useProxy = $use;
        $this->proxy = $proxy;
        $this->proxyAuth = $proxyAuth;
    }

    public function getErrors() {
        //
        return ($this->_errors);
    }

    function single($airbill) {
        //
        $this->_xml = "";
        $this->_xml .= "<?xml version = '1.0' encoding = 'UTF-8'?>" . $this->_xmlEnd;
        $this->_xml .= "<req:KnownTrackingRequest xmlns:req='http://www.dhl.com' ";
        $this->_xml .= "		xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' ";
        $this->_xml .= "		xsi:schemaLocation='http://www.dhl.com TrackingRequestKnown.xsd'>" . $this->_xmlEnd;
        $this->_xml .= "<Request>" . $this->_xmlEnd;
        $this->_xml .= "<ServiceHeader>" . $this->_xmlEnd;
        $this->_xml .= "<MessageTime>" . date("c") . "</MessageTime>" . $this->_xmlEnd;
        $this->_xml .= "<MessageReference>1234567890123456789012345678</MessageReference>" . $this->_xmlEnd;
        $this->_xml .= "<SiteID>" . $this->_PIuserid . "</SiteID>" . $this->_xmlEnd;
        $this->_xml .= "<Password>" . $this->_PIpwd . "</Password>" . $this->_xmlEnd;
        $this->_xml .= "</ServiceHeader>" . $this->_xmlEnd;
        $this->_xml .= "</Request>" . $this->_xmlEnd;
        $this->_xml .= "<LanguageCode>en</LanguageCode>" . $this->_xmlEnd;
        $this->_xml .= "<AWBNumber>" . $airbill . "</AWBNumber>" . $this->_xmlEnd;
        $this->_xml .= "<LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>" . $this->_xmlEnd;
        $this->_xml .= "</req:KnownTrackingRequest>" . $this->_xmlEnd;
        
        // make request
        $abi = simplexml_load_string($this->sendCallPI());
        
        // return null on order not found
        if($abi->AWBInfo->Status->ActionStatus == 'No Shipments Found')
            return null;
        
        $awb = new AWBInfo;        
        // data
        $awb->number = (string) $abi->AWBInfo->AWBNumber;
        $awb->status = (string) $abi->AWBInfo->Status->ActionStatus;
        $awb->originServiceArea = (string) $abi->AWBInfo->ShipmentInfo->OriginServiceArea->Description;
        $awb->destinationServiceArea = (string) $abi->AWBInfo->ShipmentInfo->DestinationServiceArea->Description;
        $awb->shipper = new Shipper(
            (string) $abi->AWBInfo->ShipmentInfo->ShipperName,
            (string) $abi->AWBInfo->ShipmentInfo->ShipperReference->ReferenceID,
            (string) $abi->AWBInfo->ShipmentInfo->Shipper->City,
            (string) $abi->AWBInfo->ShipmentInfo->Shipper->DivisionCode,
            (string) $abi->AWBInfo->ShipmentInfo->Shipper->PostalCode,
            (string) $abi->AWBInfo->ShipmentInfo->Shipper->CountryCode
        );
        $awb->consignee = new Consignee(
            (string) $abi->AWBInfo->ShipmentInfo->ConsigneeName,
            (string) $abi->AWBInfo->ShipmentInfo->Consignee->City,
            (string) $abi->AWBInfo->ShipmentInfo->Consignee->PostalCode,
            (string) $abi->AWBInfo->ShipmentInfo->Consignee->CountryCode
        );
        $awb->shipperAccountNumber = (string) $abi->AWBInfo->ShipmentInfo->ShipperAccountNumber;
        $awb->pieces = (string) $abi->AWBInfo->ShipmentInfo->Pieces;
        $awb->weight = (string) $abi->AWBInfo->ShipmentInfo->Weight;
        $awb->weightUnit = (string) $abi->AWBInfo->ShipmentInfo->WeightUnit;
        $awb->weightUnit = (string) $abi->AWBInfo->ShipmentInfo->WeightUnit;
        $awb->globalProductCode = (string) $abi->AWBInfo->ShipmentInfo->GlobalProductCode;
        $awb->shipmentDesc = (string) $abi->AWBInfo->ShipmentInfo->ShipmentDesc;
        $awb->dlvyNotificationFlag = (string) $abi->AWBInfo->ShipmentInfo->DlvyNotificationFlag;
        
        // events
        $events = $abi->AWBInfo->ShipmentInfo->ShipmentEvent;
        if(count($events) > 0){
            foreach($events as $e){
                $event = new ShipmentEvent(
                        (string) $e->Date, 
                        (string) $e->Time, 
                        (string) $e->ServiceEvent->Description, 
                        (string) $e->Signatory, 
                        (string) $e->ServiceArea->Description
                );
                
                $awb->addEvent($event);
            }
        }
        
        return $awb;
    }
    
    private function logError($loc = "", $msg = "", $fail = false) {
        //
        $tmp = array(
            'location' => $loc,
            'message' => $msg,
            'stop' => ((bool) $fail ? "Yes" : "No"),
            'time' => microtime(true)
        );
        if ((bool) $fail) {
            $this->errorFail = true;
        }
        $this->_errors[] = $tmp;
        $tmp = NULL;
    }

    private function sendCallPI() {
        if (!$ch = curl_init()) {
            $this->logError("Send >> Curl", $msg = "Curl is not initialized", true);
            return false;
        } else {
            if (!$this->errorFail) {

                $use_url = ($this->_PImode == "test" ? $this->_PItesturl : $this->_PIurl);
                curl_setopt($ch, CURLOPT_URL, $use_url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_xml);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // for proxy
                if($this->useProxy){
                    curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyAuth);
                }
                $this->_result = curl_exec($ch);
                if (curl_error($ch) != "") {
                    $this->logError("Send >> Curl", $msg = "Error with Curl installation: " . curl_error($ch), true);
                    return false;
                } else {
                    curl_close($ch);
                    return $this->_result;
                }
            }
        }
    }
}

?>