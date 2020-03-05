<?php
/**
 * Copyright 2018 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 * TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Vipps\Payment\Controller\Payment;

use Magento\Framework\{Controller\Result\Json,
    Controller\ResultFactory,
    Controller\ResultInterface,
    App\Action\Context,
    App\Action\Action,
    Exception\LocalizedException,
    Exception\NoSuchEntityException,
    App\ResponseInterface,
    Session\SessionManagerInterface};
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Vipps\Payment\{Api\CommandManagerInterface, Gateway\Request\Initiate\InitiateBuilderInterface, Model\Method\Vipps};
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Psr\Log\LoggerInterface;

/**
 * Class InitRegular
 * @package Vipps\Payment\Controller\Payment
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class InitRegular extends Action
{
    /**
     * @var CommandManagerInterface
     */
    private $commandManager;

    /**
     * @var CheckoutSession|SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var CustomerSession|SessionManagerInterface
     */
    private $customerSession;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * InitRegular constructor.
     *
     * @param Context $context
     * @param CommandManagerInterface $commandManager
     * @param SessionManagerInterface $checkoutSession
     * @param SessionManagerInterface $customerSession
     * @param CheckoutHelper $checkoutHelper
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CommandManagerInterface $commandManager,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        CheckoutHelper $checkoutHelper,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandManager = $commandManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var Json $response */
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                throw new LocalizedException(__('Could not initiate the payment. Please, reload the page.'));
            }

            // init Vipps payment and retrieve redirect url
            $responseData = $this->initiatePayment($quote);

            $quote->getPayment()
                ->setAdditionalInformation(Vipps::METHOD_TYPE_KEY, Vipps::METHOD_TYPE_REGULAR_CHECKOUT);
            $this->cartRepository->save($quote);

            $response->setData($responseData);
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getMessage());
            $response->setData(['message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $response->setData([
                'message' => __('An error occurred during request to Vipps. Please try again later.')
            ]);
        }

        return $response;
    }

    /**
     * Initiate payment on Vipps side
     *
     * @param CartInterface|Quote $quote
     *
     * @return \Magento\Payment\Gateway\Command\ResultInterface|null
     */
    private function initiatePayment(CartInterface $quote)
    {
        $responseData = $this->commandManager->initiatePayment(
            $quote->getPayment(),
            [
                'amount' => $quote->getGrandTotal(),
                InitiateBuilderInterface::PAYMENT_TYPE_KEY => InitiateBuilderInterface::PAYMENT_TYPE_REGULAR_PAYMENT
            ]
        );

        return $responseData;
    }
}