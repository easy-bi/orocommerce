<?php

namespace Oro\Bundle\ProductBundle\EventListener;

use Oro\Bundle\FormBundle\Event\FormHandler\FormProcessEvent;
use Oro\Bundle\FormBundle\Form\Type\EntityIdentifierType;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\RelatedItem\Helper\RelatedItemConfigHelper;
use Oro\Bundle\UIBundle\Event\BeforeListRenderEvent;
use Oro\Bundle\UIBundle\View\ScrollData;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Adds related product information (tabs, grids, forms) to the product edit page.
 */
class RelatedItemsProductEditListener
{
    const RELATED_ITEMS_ID = 'relatedItems';

    /** @var int */
    const BLOCK_PRIORITY = 10;

    /** @var TranslatorInterface */
    private $translator;

    /** @var RelatedItemConfigHelper */
    private $relatedItemConfigHelper;

    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;
    /**
     * @param TranslatorInterface               $translator
     * @param RelatedItemConfigHelper           $relatedItemConfigHelper
     * @param AuthorizationCheckerInterface     $authorizationChecker
     */
    public function __construct(
        TranslatorInterface $translator,
        RelatedItemConfigHelper $relatedItemConfigHelper,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->translator = $translator;
        $this->relatedItemConfigHelper = $relatedItemConfigHelper;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @param string $name
     * @return \Oro\Bundle\ProductBundle\RelatedItem\AbstractRelatedItemConfigProvider
     */
    protected function getConfigProvider($name)
    {
        return $this->relatedItemConfigHelper->getConfigProvider($name);
    }

    /**
     * @param BeforeListRenderEvent $event
     */
    public function onProductEdit(BeforeListRenderEvent $event)
    {
        $twigEnv = $event->getEnvironment();
        $tabs = [];
        $grids = [];

        if ($this->getConfigProvider('related_products')->isEnabled()
            && $this->authorizationChecker->isGranted('oro_related_products_edit')
        ) {
            $tabs[] = [
                'id' => 'related-products-block',
                'label' => $this->translator->trans('oro.product.tabs.relatedProducts')
            ];
            $grids[] = $this->getRelatedProductsEditBlock($event, $twigEnv);
        }

        if ($this->getConfigProvider('upsell_products')->isEnabled()
            && $this->authorizationChecker->isGranted('oro_upsell_products_edit')
        ) {
            $tabs[] = [
                'id' => 'upsell-products-block',
                'label' => $this->translator->trans('oro.product.tabs.upsellProducts')
            ];
            $grids[] = $this->getUpsellProductsEdidBlock($event, $twigEnv);
        }

        if (count($tabs) > 1) {
            $grids = array_merge([$this->renderTabs($twigEnv, $tabs)], $grids);
        }

        if (count($grids) > 0) {
            $this->addEditPageBlock($event->getScrollData(), $grids);
        }
    }

    /**
     * @param FormProcessEvent $event
     */
    public function onFormDataSet(FormProcessEvent $event)
    {
        if ($this->authorizationChecker->isGranted('oro_related_products_edit')) {
            $event->getForm()->add(
                'appendRelated',
                EntityIdentifierType::class,
                [
                    'class' => Product::class,
                    'required' => false,
                    'mapped' => false,
                    'multiple' => true,
                ]
            );
            $event->getForm()->add(
                'removeRelated',
                EntityIdentifierType::class,
                [
                    'class' => Product::class,
                    'required' => false,
                    'mapped' => false,
                    'multiple' => true,
                ]
            );
        } else {
            $event->getForm()->remove('appendRelated');
            $event->getForm()->remove('removeRelated');
        }

        if ($this->authorizationChecker->isGranted('oro_upsell_products_edit')) {
            $event->getForm()->add(
                'appendUpsell',
                EntityIdentifierType::class,
                [
                    'class' => Product::class,
                    'required' => false,
                    'mapped' => false,
                    'multiple' => true,
                ]
            );
            $event->getForm()->add(
                'removeUpsell',
                EntityIdentifierType::class,
                [
                    'class' => Product::class,
                    'required' => false,
                    'mapped' => false,
                    'multiple' => true,
                ]
            );
        } else {
            $event->getForm()->remove('appendUpsell');
            $event->getForm()->remove('removeUpsell');
        }
    }

    /**
     * @param ScrollData $scrollData
     * @param string[] $htmlBlocks
     */
    private function addEditPageBlock(ScrollData $scrollData, array $htmlBlocks)
    {
        $scrollData->addNamedBlock(
            self::RELATED_ITEMS_ID,
            $this->translator->trans($this->relatedItemConfigHelper->getRelatedItemsTranslationKey()),
            self::BLOCK_PRIORITY
        );

        $subBlock = $scrollData->addSubBlock(self::RELATED_ITEMS_ID);
        $scrollData->addSubBlockData(
            self::RELATED_ITEMS_ID,
            $subBlock,
            implode('', $htmlBlocks),
            'relatedItems'
        );
    }

    /**
     * @param BeforeListRenderEvent $event
     * @param Environment $twigEnv
     * @return string
     */
    private function getRelatedProductsEditBlock(BeforeListRenderEvent $event, Environment $twigEnv)
    {
        return $twigEnv->render(
            '@OroProduct/Product/RelatedItems/relatedProducts.html.twig',
            [
                'form' => $event->getFormView(),
                'entity' => $event->getEntity(),
                'relatedProductsLimit' => $this->getConfigProvider('related_products')->getLimit()
            ]
        );
    }

    /**
     * @param BeforeListRenderEvent $event
     * @param Environment $twigEnv
     * @return string
     */
    private function getUpsellProductsEdidBlock(BeforeListRenderEvent $event, Environment $twigEnv)
    {
        return $twigEnv->render(
            '@OroProduct/Product/RelatedItems/upsellProducts.html.twig',
            [
                'form' => $event->getFormView(),
                'entity' => $event->getEntity(),
                'upsellProductsLimit' => $this->getConfigProvider('upsell_products')->getLimit(),
            ]
        );
    }

    /**
     * @param Environment $twigEnv
     * @param array $tabs
     * @return string
     */
    private function renderTabs(Environment $twigEnv, array $tabs)
    {
        return $twigEnv->render(
            '@OroProduct/Product/RelatedItems/tabs.html.twig',
            [
                'relatedItemsTabsItems' => $tabs
            ]
        );
    }
}
