<?php


use PHPUnit\Framework\TestCase;
use smile\payment\model\Amount;
use smile\payment\smile\payment\model\CustomParamsBuilder;

include_once "../src/classes/smile/payment/model/CustomParamsBuilder.php";
include_once "../src/classes/smile/payment/model/Amount.php";

class CustomParamsTest extends TestCase
{

    public function testCustomPad1()
    {

        $amount = new Amount();
        $amount->setTotal(3.12);
        $amount->setSubtotalIVA(1);
        $amount->setIvaRate(12);
        $amount->setSubtotalIVA0(2);
        $customParams = new CustomParamsBuilder();
        $customPad = $customParams->buildCustomParams($amount);

        $this->assertSame("0081003007010391000401200000000001205100817913101052012000000000200053012000000000100", $customPad);


    }


    public function testCustomPad2()
    {

        $amount = new Amount();
        $amount->setTotal(104.5);
        $amount->setSubtotalIVA(93.31);
        $amount->setIvaRate(12);
        $amount->setSubtotalIVA0(0);
        $customParams = new CustomParamsBuilder();

        $customPad = $customParams->buildCustomParams($amount);
        $this->assertSame("0081003007010391000401200000000111905100817913101052012000000000000053012000000009331", $customPad);

    }
}
