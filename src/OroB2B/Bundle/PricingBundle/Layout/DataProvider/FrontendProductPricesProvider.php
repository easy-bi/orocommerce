<?php

namespace OroB2B\Bundle\PricingBundle\Layout\DataProvider;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

use OroB2B\Bundle\PricingBundle\Entity\CombinedProductPrice;
use OroB2B\Bundle\PricingBundle\Entity\ProductPrice;
use OroB2B\Bundle\PricingBundle\Entity\Repository\ProductPriceRepository;
use OroB2B\Bundle\PricingBundle\Model\PriceListRequestHandler;
use OroB2B\Bundle\PricingBundle\Manager\UserCurrencyManager;
use OroB2B\Bundle\ProductBundle\Entity\Product;

class FrontendProductPricesProvider
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @var PriceListRequestHandler
     */
    protected $priceListRequestHandler;

    /**
     * @var UserCurrencyManager
     */
    protected $userCurrencyManager;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param PriceListRequestHandler $priceListRequestHandler
     * @param UserCurrencyManager $userCurrencyManager
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        PriceListRequestHandler $priceListRequestHandler,
        UserCurrencyManager $userCurrencyManager
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->priceListRequestHandler = $priceListRequestHandler;
        $this->userCurrencyManager = $userCurrencyManager;
    }

    /**
     * @param Product $product
     * @return ProductPrice[]
     */
    public function getByProduct(Product $product)
    {
        if (!$product) {
            return null;
        }

        $this->setProductsPrices([$product]);

        return $this->data[$product->getId()];
    }

    public function getByProducts($products)
    {
        $this->setProductsPrices($products);
        $productsUnits = [];

        foreach ($products as $product) {
            $productId = $product->getId();
            if ($this->data[$productId]) {
                $productsUnits[$productId] = $this->data[$productId];
            }
        }

        return $productsUnits;
    }

    protected function setProductsPrices($products)
    {
        $products = array_filter($products, function ($product) {
            return !array_key_exists($product->getId(), $this->data);
        });
        if (!$products) {
            return;
        }

        $priceList = $this->priceListRequestHandler->getPriceListByAccount();
        $productsIds = array_map(function ($product) {
            return $product->getId();
        }, $products);

        /** @var ProductPriceRepository $priceRepository */
        $priceRepository = $this->doctrineHelper->getEntityRepository('OroB2BPricingBundle:CombinedProductPrice');
        $prices = $priceRepository->findByPriceListIdAndProductIds(
            $priceList->getId(),
            $productsIds,
            true,
            $this->userCurrencyManager->getUserCurrency(),
            null,
            [
                'unit' => 'ASC',
                'currency' => 'DESC',
                'quantity' => 'ASC',
            ]
        );

        $productsPrices = [];

        foreach ($prices as $price) {
            $productsPrices[$price->getProduct()->getId()][] = $price;
        }

        foreach ($products as $product) {
            $unitPrecisions = $product->getUnitPrecisions();

            $unitsToSell = [];
            foreach ($unitPrecisions as $unitPrecision) {
                $unitsToSell[$unitPrecision->getUnit()->getCode()] = $unitPrecision->isSell();
            }

            $this->data[$product->getId()] = array_filter(
                isset($productsPrices[$product->getId()]) ? $productsPrices[$product->getId()] : [],
                function (CombinedProductPrice $price) use ($unitsToSell) {
                    return !empty($unitsToSell[$price->getProductUnitCode()]);
                }
            );
        }
    }
}
