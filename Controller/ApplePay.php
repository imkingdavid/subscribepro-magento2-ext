<?php

namespace Swarming\SubscribePro\Controller;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Swarming\SubscribePro\Api\ApplePayInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * @codeCoverageIgnore
 */
class ApplePay implements ApplePayInterface, CsrfAwareActionInterface
{

    /**
     * @var \Swarming\SubscribePro\Helper\ApplePay
     */
    protected $applePayHelper;

    /**
     * @var JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param \Swarming\SubscribePro\Helper\ApplePay $applePayHelper
     * @param JsonFactory $jsonResultFactory
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Swarming\SubscribePro\Helper\ApplePay $applePayHelper,
        JsonFactory $jsonResultFactory,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        LoggerInterface $logger
    ) {
        $this->applePayHelper = $applePayHelper;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onShippingContactSelected()
    {
        $result = $this->jsonResultFactory->create();
        try {
            if (!$this->request->getPostValue('shippingContact')) {
                throw new BadRequestException('Request missing shippingContact');
            }

            // Send over shipping destination
            $this->applePayHelper->setApplePayShippingContactOnQuote($this->request->getPostValue('shippingContact'));

            // Set initial shipping method/rate on the quote if none is already set
            // This gets overridden later in onShippingMethodSelected()
            $applePayShippingMethods = $this->applePayHelper->getApplePayShippingMethods();
            if (!strlen($this->applePayHelper->getQuote()->getShippingAddress()->getShippingMethod())) {
                if (count($applePayShippingMethods)) {
                    $this->applePayHelper->setApplePayShippingMethodOnQuote();
                }
            }

            $response = [
                'newShippingMethods' => $applePayShippingMethods,
                'newTotal' => $this->applePayHelper->getApplePayTotal(),
                'newLineItems' => $this->applePayHelper->getApplePayLineItems(),
            ];
        } catch (BadRequestException $e) {
            $response = [
                'error' => $e->getMessage(),
            ];
            $result->setHttpResponseCode(400);
        }

        $result->setJsonData(json_encode($response));
        $result->setHeader('Content-Type', 'application/json');

        return $result;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onShippingMethodSelected()
    {
        $result = $this->jsonResultFactory->create();
        try {
            if (!$this->request->getPostValue('shippingMethod')) {
                throw new BadRequestException('Request missing shippingMethod');
            }

            $this->applePayHelper->setApplePayShippingMethodOnQuote($this->request->getPostValue('shippingMethod'));

            $response = [
                'newTotal' => $this->applePayHelper->getApplePayTotal(),
                'newLineItems' => $this->applePayHelper->getApplePayLineItems(),
            ];
        } catch (BadRequestException $e) {
            $response = [
                'error' => $e->getMessage(),
            ];
            $result->setHttpResponseCode(400);
        }

        $result->setJsonData(json_encode($response));
        $result->setHeader('Content-Type', 'application/json');

        return $result;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onPaymentAuthorized()
    {
        $result = $this->jsonResultFactory->create();
        try {
            if (!$this->request->getPostValue('payment')) {
                throw new BadRequestException('Request missing payment');
            }

            $this->applePayHelper->setApplePayPaymentOnQuote($this->request->getPostValue('payment'));
            // TODO : DO we need this or can we just call QuoteManagement::placeOrder()?
            $this->applePayHelper->placeOrder();

            $response = [
                'redirectUrl' => $this->urlBuilder->getUrl('checkout/onepage/success'),
            ];
        } catch (BadRequestException $e) {
            $response = [
                'error' => $e->getMessage(),
            ];
            $result->setHttpResponseCode(400);
        }

        $result->setJsonData(json_encode($response));
        $result->setHeader('Content-Type', 'application/json');

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
