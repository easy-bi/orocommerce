<?php

namespace Oro\Bundle\CheckoutBundle\Tests\Unit\Provider;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\Repository\CheckoutRepository;
use Oro\Bundle\CheckoutBundle\Factory\CheckoutPaymentContextFactory;
use Oro\Bundle\CheckoutBundle\Layout\DataProvider\CheckoutPaymentContextProvider;
use Oro\Bundle\CheckoutBundle\Provider\PaymentMethodProvider;
use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProvider as PaymentBundleMethodProvider;

class PaymentMethodProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var PaymentMethodProvider */
    protected $provider;

    /** @var CheckoutPaymentContextFactory|\PHPUnit\Framework\MockObject\MockObject */
    protected $contextFactory;

    /** @var CheckoutRepository|\PHPUnit\Framework\MockObject\MockObject */
    protected $repository;

    /** @var PaymentBundleMethodProvider|\PHPUnit\Framework\MockObject\MockObject */
    protected $paymentBundleMethodProvider;

    /** @var PaymentTransaction */
    protected $transaction;

    /** @var CheckoutPaymentContextProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $checkoutPaymentContextProvider;

    public function setUp()
    {
        $this->contextFactory = $this->createMock(CheckoutPaymentContextFactory::class);
        $this->checkoutPaymentContextProvider = $this->createMock(CheckoutPaymentContextProvider::class);
        $this->repository = $this->createMock(CheckoutRepository::class);
        $this->paymentBundleMethodProvider = $this->createMock(PaymentBundleMethodProvider::class);

        $this->transaction = new PaymentTransaction();

        $this->provider = new PaymentMethodProvider(
            $this->contextFactory,
            $this->repository,
            $this->paymentBundleMethodProvider
        );
    }

    public function testGetApplicablePaymentMethodsWithoutCheckoutId()
    {
        $this->transaction->setTransactionOptions(['checkoutId' => null]);
        $this->paymentBundleMethodProvider->expects($this->never())->method('getApplicablePaymentMethods');

        $this->assertNull($this->provider->getApplicablePaymentMethods($this->transaction));
    }

    public function testGetApplicablePaymentMethodsWithoutCheckout()
    {
        $this->transaction->setTransactionOptions(['checkoutId' => 123]);
        $this->repository->expects($this->once())->method('find')->with(123)->willReturn(null);
        $this->paymentBundleMethodProvider->expects($this->never())->method('getApplicablePaymentMethods');

        $this->assertNull($this->provider->getApplicablePaymentMethods($this->transaction));
    }

    public function testGetApplicablePaymentMethodsWithoutContextWhenNoContextProvider(): void
    {
        $this->transaction->setTransactionOptions(['checkoutId' => 123]);
        $checkout = new Checkout();
        $this->repository->expects($this->once())->method('find')->with(123)->willReturn($checkout);
        $this->contextFactory->expects($this->once())->method('create')->with($checkout)->willReturn(null);
        $this->paymentBundleMethodProvider->expects($this->never())->method('getApplicablePaymentMethods');

        $this->assertNull($this->provider->getApplicablePaymentMethods($this->transaction));
    }

    public function testGetApplicablePaymentMethodsWithoutContext()
    {
        $this->transaction->setTransactionOptions(['checkoutId' => 123]);
        $checkout = new Checkout();
        $this->repository->expects($this->once())->method('find')->with(123)->willReturn($checkout);
        $this->checkoutPaymentContextProvider->expects($this->once())
            ->method('getContext')->with($checkout)->willReturn(null);
        $this->paymentBundleMethodProvider->expects($this->never())->method('getApplicablePaymentMethods');

        $this->provider->setCheckoutPaymentContextProvider($this->checkoutPaymentContextProvider);
        $this->assertNull($this->provider->getApplicablePaymentMethods($this->transaction));
    }

    public function testGetApplicablePaymentMethodsWhenNoContextProvider(): void
    {
        $this->transaction->setTransactionOptions(['checkoutId' => 123]);
        $checkout = new Checkout();
        $this->repository->expects($this->once())->method('find')->with(123)->willReturn($checkout);
        $paymentContext = $this->createMock(PaymentContextInterface::class);
        $this->contextFactory->expects($this->once())->method('create')->with($checkout)->willReturn($paymentContext);
        $paymentMethodInterfaces = [$this->createMock(PaymentMethodInterface::class)];
        $this->paymentBundleMethodProvider->expects($this->once())
            ->method('getApplicablePaymentMethods')
            ->with($paymentContext)
            ->willReturn($paymentMethodInterfaces);

        $this->assertSame($paymentMethodInterfaces, $this->provider->getApplicablePaymentMethods($this->transaction));
    }

    public function testGetApplicablePaymentMethods(): void
    {
        $this->transaction->setTransactionOptions(['checkoutId' => 123]);
        $checkout = new Checkout();
        $this->repository->expects($this->once())->method('find')->with(123)->willReturn($checkout);
        $paymentContext = $this->createMock(PaymentContextInterface::class);
        $this->checkoutPaymentContextProvider->expects($this->once())
            ->method('getContext')->with($checkout)->willReturn($paymentContext);
        $paymentMethodInterfaces = [$this->createMock(PaymentMethodInterface::class)];
        $this->paymentBundleMethodProvider->expects($this->once())
            ->method('getApplicablePaymentMethods')
            ->with($paymentContext)
            ->willReturn($paymentMethodInterfaces);

        $this->provider->setCheckoutPaymentContextProvider($this->checkoutPaymentContextProvider);
        $this->assertSame($paymentMethodInterfaces, $this->provider->getApplicablePaymentMethods($this->transaction));
    }
}
