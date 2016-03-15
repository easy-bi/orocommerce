<?php

namespace OroB2B\Bundle\OrderBundle\Tests\Unit\Form\Type;

use OroB2B\Bundle\OrderBundle\Entity\OrderDiscount;
use OroB2B\Bundle\OrderBundle\Form\Type\OrderDiscountItemType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderDiscountItemTypeTest extends FormIntegrationTestCase
{
    /**
     * @var OrderDiscountItemType
     */
    protected $formType;

    protected function setUp()
    {
        parent::setUp();

        $this->formType = new OrderDiscountItemType();
        $this->formType->setDataClass('OroB2B\Bundle\OrderBundle\Entity\OrderDiscount');
    }

    public function testGetName()
    {
        $this->assertEquals(OrderDiscountItemType::NAME, $this->formType->getName());
    }

    public function testBuildView()
    {
        $view = new FormView();
        /** @var FormInterface|\PHPUnit_Framework_MockObject_MockObject $form */
        $form = $this->getMock('Symfony\Component\Form\FormInterface');

        $options = [
            'currency' => 'USD',
            'page_component' => 'test',
            'page_component_options' => ['test_option'],
        ];

        $expectedVars = [
            'page_component' => 'test',
            'page_component_options' => ['test_option', 'currency' => 'USD'],

        ];

        $this->formType->buildView($view, $form, $options);
        foreach ($expectedVars as $key => $val) {
            $this->assertArrayHasKey($key, $view->vars);
            $this->assertEquals($val, $view->vars[$key]);
        }
    }

    public function testConfigureOptions()
    {
        $expectedOptions = [
            'currency' => 'USD',
            'data_class' => 'OroB2B\Bundle\OrderBundle\Entity\OrderDiscount',
            'intention' => 'order_discount_item',
            'page_component' => 'oroui/js/app/components/view-component',
            'page_component_options' => [
                'view' => 'orob2border/js/app/views/discount-item-view',
                'percentTypeValue' => OrderDiscount::TYPE_PERCENT,
            ],
        ];
        $resolver = new OptionsResolver();
        $resolver->setDefault('currency', 'USD');
        $this->formType->configureOptions($resolver);

        $resolvedOptions = $resolver->resolve();
        foreach ($resolver->getDefinedOptions() as $option) {
            $this->assertArrayHasKey($option, $expectedOptions);
            $this->assertArrayHasKey($option, $resolvedOptions);
            $this->assertEquals($expectedOptions[$option], $resolvedOptions[$option]);
        }
    }

    public function testSubmit()
    {

        $data = new OrderDiscount();

        $form = $this->factory->create($this->formType, $data, ['currency' => 'USD']);

        $submittedData = [
            'value' => '10',
            'percent' => '10',
            'amount' => '9.99',
            'type' => OrderDiscount::TYPE_PERCENT,
            'description' => 'some test description'
        ];

        $expectedData = new OrderDiscount();
        $expectedData->setAmount('9.99')
            ->setDescription('some test description')
            ->setPercent('10')
            ->setType(OrderDiscount::TYPE_PERCENT);

        $form->submit($submittedData);
        $this->assertTrue($form->isValid());
        $this->assertEquals($expectedData, $form->getData());
    }
}
