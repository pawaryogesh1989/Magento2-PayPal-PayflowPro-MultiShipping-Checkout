<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Clarion\Payflowpro\Gateway;

class PayFlowRun
{

    public function Initialise_Curl()
    {
        if (!function_exists('curl_init')) {
            $this->debugData(
                ['request' => "Clarion PayflowPro", 'exception' => "Curl not enabled. Please enable curl"]
            );
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
    }

    public function postRequest($request, $gatewayurl)
    {

        $this->Initialise_Curl();

        $params = $request->toArray();
        $partialurl = $this->_prepareString($params);
        $response = $this->_processPostRequest($partialurl, $gatewayurl);

        return $response;
    }

    public function _prepareString($params)
    {
        $paramList = [];

        foreach ($params as $index => $value) {
            $paramList[] = $index . "[" . strlen($value) . "]=" . $value;
        }
        
        $apiStr = implode("&", $paramList);

        return $apiStr;
    }

    public function _processPostRequest($gatewaydata, $gatewayurl)
    {

        $curl = curl_init($gatewayurl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $gatewaydata);

        $result = curl_exec($curl);

        return $result;
    }

    public function _formatResponse($str)
    {

        $response = explode("&", $str);
        $responsearray = [];

        foreach ($response as $key => $value) {
            $finalvalue = explode("=", $value);
            $responsearray[$finalvalue[0]] = $finalvalue[1];
        }

        return $responsearray;
    }
}
