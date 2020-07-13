<?php


namespace smile\payment\smile\payment\model;


use smile\payment\model\Amount;
use smile\payment\model\CustomerInfo;
use smile\payment\model\DataFastRequest;


class Payment
{

    private $request;
    private $amount;
    private $productInfo;
    private $customerInfo;
    private $shipping;


    /**
     * @return DatafastRequest
     */
    public function getRequest(): DatafastRequest
    {
        return $this->request;
    }

    /**
     * @param DatafastRequest $request
     */
    public function setRequest(DatafastRequest $request): void
    {
        $this->request = $request;
    }

    /**
     * @return Amount
     */
    public function getAmount(): Amount
    {
        return $this->amount;
    }

    /**
     * @param Amount $amount
     */
    public function setAmount(Amount $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return array
     */
    public function getProductInfo(): array
    {
        return $this->productInfo;
    }

    /**
     * @param array $productInfo
     */
    public function setProductInfo(array $productInfo): void
    {
        $this->productInfo = $productInfo;
    }

    /**
     * @return CustomerInfo
     */
    public function getCustomerInfo(): CustomerInfo
    {
        return $this->customerInfo;
    }

    /**
     * @param CustomerInfo $customerInfo
     */
    public function setCustomerInfo(CustomerInfo $customerInfo): void
    {
        $this->customerInfo = $customerInfo;
    }

    /**
     * @return Shipping
     */
    public function getShipping(): Shipping
    {
        return $this->shipping;
    }

    /**
     * @param Shipping $shipping
     */
    public function setShipping(Shipping $shipping): void
    {
        $this->shipping = $shipping;
    }


}