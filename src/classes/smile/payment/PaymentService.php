<?php


namespace smile\payment;


use Httpful\Exception\ConnectionErrorException;
use Httpful\Request;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use smile\payment\model\Constants;
use smile\payment\model\CustomerInfo;
use smile\payment\model\DataFastRequest;
use smile\payment\smile\payment\model\CustomParamsBuilder;
use smile\payment\smile\payment\model\Payment;
use smile\payment\smile\payment\model\PaymentResponse;

class PaymentService
{


    public function requestCheckoutId(Payment $payment): string
    {
        $checkoutId = "";

        try {
            $dataFastRequest = $payment->getRequest();

            $checkOutUri = $dataFastRequest->getUrlRequest() . "checkouts";
            $auth = $this->getAuth($dataFastRequest);

            $initBody = $this->buildInitialBody($payment);
            $itemsBody = $this->addItemsToBody($payment);
            $customParamBody = $this->getCustomParams($payment);
            $riskBody = $this->getRiskParams($payment);

            $body = array_merge($initBody, $itemsBody, $customParamBody, $riskBody);

            if ($payment->getRequest()->isTestMode()) {
                $body['testMode'] = 'EXTERNAL';
            }

            //$this->getLogger()->debug("URL  " . $checkOutUri);


            $body = http_build_query($body);

            //$this->getLogger()->debug("Body request-->  " . $body);


            $response = Request::post($checkOutUri)
                ->sendsForm()
                ->strictSSL(!$payment->getRequest()->isTestMode())
                ->addHeaders(array(
                    'Authorization' => $auth
                ))
                ->body($body)
                ->send();

            $responseBody = $response->body;

            //$this->getLogger()->debug("Response Body", (array)$responseBody);

            $resultCode = $responseBody->result->code;

            if ($resultCode == "000.200.100") {
                $checkoutId = $responseBody->id;
            }

        } catch (ConnectionErrorException $e) {
            $this->getLogger()->error("Error trying to get checkoutId.", $e->getTrace());
        }
        return $checkoutId;
    }


    /**
     * @param DataFastRequest $dataFastRequest
     * @return string
     */
    private function getAuth(DataFastRequest $dataFastRequest): string
    {
        return "Bearer " . $dataFastRequest->getBearerToken();
    }

    /**
     * @param Payment $payment
     * @return array
     */
    protected function buildInitialBody(Payment $payment): array
    {
        $amount = $payment->getAmount();
        $customer = $payment->getCustomerInfo();
        $dataFastRequest = $payment->getRequest();
        return [
            'entityId' => $dataFastRequest->getEntityId(),
            'amount' => $this->toDecimalNumber($amount->getTotal()),
            'currency' => DataFastRequest::CURRENCY,
            'paymentType' => DataFastRequest::PAYMENT_TYPE,
            'customer.givenName' => $customer->getGivenName(),
            'customer.middleName' => $customer->getMiddleName(),
            'customer.surname' => $customer->getSurname(),
            'customer.ip' => $customer->getIp(),
            'customer.phone' => $customer->getPhoneNumber(),
            'customer.merchantCustomerId' => $customer->getMerchantCustomerId(),
            'merchantTransactionId' => $customer->getMerchantTransactionId(),
            'customer.email' => $customer->getEmail(),
            'customer.identificationDocType' => CustomerInfo::identificationDocType,
            'customer.identificationDocId' => $customer->getIdentificationDocId(),
            'shipping.street1' => $customer->getShippingAddress(),
            'billing.street1' => $customer->getShippingAddress(),
            'shipping.country' => $customer->getCountry(),
            'billing.country' => $customer->getCountry(),
        ];
    }

    private function toDecimalNumber(float $number): string
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * @param Payment $payment
     * @return array
     */
    protected function addItemsToBody(Payment $payment): array
    {
        $body = [];
        $productCount = 0;
        foreach ($payment->getProductInfo() as $allProducts) {
            foreach ($allProducts as $key => $product) {
                $body['cart.items[' . $productCount . '].name'] = $product->getName();
                $body['cart.items[' . $productCount . '].description'] = strip_tags($product->getDescription());
                $body['cart.items[' . $productCount . '].price'] = $this->toDecimalNumber($product->getPrice());
                $body['cart.items[' . $productCount . '].quantity'] = $product->getQuantity();
                $productCount++;
            }
        }
        return $body;
    }

    private function getCustomParams(Payment $payment): array
    {
        $request = $payment->getRequest();
        $amount = $payment->getAmount();
        $customParamBuilder = new CustomParamsBuilder();
        $customParamsKey = 'customParameters[' . $request->getMid() . "_" . $request->getTid() . ']';

        return [$customParamsKey => $customParamBuilder->buildCustomParams($amount)];
    }

    private function getRiskParams(Payment $payment): array
    {
        $request = $payment->getRequest();
        return ['risk.parameters[USER_DATA2]' => $request->getRisk()];
    }

    /**
     * @return Logger
     */
    private function getLogger(): Logger
    {
        $logger = new Logger('PaymentService');
        $logger->pushHandler(new StreamHandler(Constants::LOGGER_FILE, Logger::DEBUG));
        return $logger;
    }

    public function processPayment(DataFastRequest $dataFastRequest): PaymentResponse
    {

        try {
            $resourcePathUri = $dataFastRequest->getResourcePathUri() . "?entityId=" . $dataFastRequest->getEntityId();

            //$this->getLogger()->debug("URI en resourcePathUri  -> " . $resourcePathUri);
            $auth = $this->getAuth($dataFastRequest);

            $response = Request::get($resourcePathUri)
                ->addHeaders(array(
                    'Authorization' => $auth
                ))
                ->send();
            $responseBody = $response->body;
            //$this->getLogger()->debug("Response Resource path", (array)$responseBody);

            return PaymentResponse::fromJson($responseBody);

        } catch (ConnectionErrorException $e) {
            $this->getLogger()->error("Error trying to get checkoutId.", $e->getTrace());
            return PaymentResponse::paymentError();
        }

    }
}