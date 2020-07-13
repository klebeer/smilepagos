<?php


namespace smile\payment\smile\payment;


use PrestaShop\PrestaShop\Adapter\Configuration;
use smile\payment\model\DataFastRequest;
use smile\payment\model\Environment;

class Config
{

    public function getDataFastRequest(): DataFastRequest
    {
        $request = new DataFastRequest();
        if (Configuration::get('DATA_FAST_DEV')) {
            $url = Environment::TEST;
            $request->setTestMode(true);
        } else {
            $request->setTestMode(false);
            $url = Environment::PRODUCTION;
        }

        $request->setUrlRequest($url);
        $request->setBearerToken(Configuration::get('DATA_FAST_BEARER_TOKEN'));
        $request->setEntityId(Configuration::get('DATA_FAST_ENTITY_ID'));
        $request->setMid(Configuration::get('DATA_FAST_MID'));
        $request->setTid(Configuration::get('DATA_FAST_TID'));
        $request->setRisk(Configuration::get('DATA_FAST_RISK'));

        $request->setCheckoutScript($url . 'paymentWidgets.js?checkoutId=');
        $request->setResourcePathUri($url . 'checkouts/{id}/payment');

        return $request;
    }

}