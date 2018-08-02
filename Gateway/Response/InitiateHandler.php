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
namespace Vipps\Payment\Gateway\Response;

use Magento\Customer\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\{Data\PaymentDataObjectInterface, Response\HandlerInterface};
use Magento\Quote\{
    Api\CartRepositoryInterface, Model\Quote, Model\Quote\Payment
};
use Vipps\Payment\Gateway\Request\SubjectReader;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Checkout\Helper\Data as CheckoutHelper;

/**
 * Class InitiateHandler
 * @package Vipps\Payment\Gateway\Response
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class InitiateHandler implements HandlerInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var SessionManagerInterface|Session
     */
    private $customerSession;

    /**
     * InitiateHandler constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param SubjectReader $subjectReader
     * @param CheckoutHelper $checkoutHelper
     * @param SessionManagerInterface $customerSession
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        SubjectReader $subjectReader,
        CheckoutHelper $checkoutHelper,
        SessionManagerInterface $customerSession
    ) {
        $this->cartRepository = $cartRepository;
        $this->subjectReader = $subjectReader;
        $this->checkoutHelper = $checkoutHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $handlingSubject
     * @param array $responseBody
     *
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $responseBody) //@codingStandardsIgnoreLine
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $quote = $payment->getQuote();

        if (!$quote->getCheckoutMethod()) {
            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            } elseif ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
        $quote->setIsActive(false);

        $this->cartRepository->save($quote);
    }
}