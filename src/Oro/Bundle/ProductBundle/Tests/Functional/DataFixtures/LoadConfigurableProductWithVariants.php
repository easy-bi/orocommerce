<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Schema\OroFrontendTestFrameworkBundleInstaller;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Entity\ProductVariantLink;
use Oro\Bundle\ProductBundle\Migrations\Data\ORM\LoadProductDefaultAttributeFamilyData;
use Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\AbstractFixture;
use Oro\Bundle\UserBundle\DataFixtures\UserUtilityTrait;
use Oro\Bundle\WebsiteSearchBundle\Event\ReindexationRequestEvent;

class LoadConfigurableProductWithVariants extends AbstractFixture implements DependentFixtureInterface
{
    use UserUtilityTrait;

    const CONFIGURABLE_SKU = 'PARENTCONFIG';
    const FIRST_VARIANT_SKU = 'FIRSTVARIANT';
    const SECOND_VARIANT_SKU = 'SECONDVARIANT';

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadProductUnits::class,
            LoadVariantFields::class,
            LoadProductMultiEnumValues::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $configurableProduct = $this->createProduct($manager, self::CONFIGURABLE_SKU, Product::TYPE_CONFIGURABLE);
        $this->setReference(self::CONFIGURABLE_SKU, $configurableProduct);

        $firstVariant = $this->createProduct(
            $manager,
            self::FIRST_VARIANT_SKU,
            Product::TYPE_SIMPLE,
            'Good',
            ['first', 'second']
        );
        $firstLink = new ProductVariantLink($configurableProduct, $firstVariant);
        $configurableProduct->addVariantLink($firstLink);
        $manager->persist($firstLink);
        $this->setReference(self::FIRST_VARIANT_SKU, $firstVariant);

        $secondVariant = $this->createProduct(
            $manager,
            self::SECOND_VARIANT_SKU,
            Product::TYPE_SIMPLE,
            'Better',
            ['second', 'third']
        );
        $secondLink = new ProductVariantLink($configurableProduct, $secondVariant);
        $configurableProduct->addVariantLink($secondLink);
        $manager->persist($secondLink);
        $this->setReference(self::SECOND_VARIANT_SKU, $secondVariant);

        $manager->flush();

        $this->container->get('event_dispatcher')->dispatch(
            ReindexationRequestEvent::EVENT_NAME,
            new ReindexationRequestEvent([Product::class], [], [], false)
        );
    }

    /**
     * @param ObjectManager $manager
     * @param string $sku
     * @param string $type
     * @param null|string $variantName
     * @param array $multiEnumCodes
     * @return Product
     */
    protected function createProduct(
        ObjectManager $manager,
        $sku,
        $type,
        $variantName = null,
        $multiEnumCodes = []
    ) {
        /** @var EntityManager $manager */
        $user = $this->getFirstUser($manager);
        $businessUnit = $user->getOwner();
        $organization = $user->getOrganization();

        $unit = $this->getReference(LoadProductUnits::BOX);

        $familyRepository = $manager->getRepository(AttributeFamily::class);
        $defaultProductFamily = $familyRepository
            ->findOneBy(['code' => LoadProductDefaultAttributeFamilyData::DEFAULT_FAMILY_CODE]);

        $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName('prod_inventory_status');
        /** @var AbstractEnumValue $inventoryStatus */
        $inventoryStatus = $manager->getRepository($inventoryStatusClassName)->find('in_stock');

        $unitPrecision = new ProductUnitPrecision();
        $unitPrecision->setUnit($unit)
            ->setPrecision(0)
            ->setConversionRate(1)
            ->setSell(true);

        $product = new Product();
        $product
            ->setSku($sku)
            ->setOwner($businessUnit)
            ->setOrganization($organization)
            ->setAttributeFamily($defaultProductFamily)
            ->setInventoryStatus($inventoryStatus)
            ->setStatus(Product::STATUS_ENABLED)
            ->setPrimaryUnitPrecision($unitPrecision)
            ->setType($type);

        $defaultName = new LocalizedFallbackValue();
        $defaultName->setString($sku);
        $product->addName($defaultName);

        if ($variantName) {
            $variantClassName = ExtendHelper::buildEnumValueClassName(
                OroFrontendTestFrameworkBundleInstaller::VARIANT_FIELD_CODE
            );
            $variantEnumRepository = $manager->getRepository($variantClassName);
            $variantEnum = $variantEnumRepository->findOneBy(['name' => $variantName]);
            $product->setTestVariantField($variantEnum);
        }

        if ($multiEnumCodes) {
            $multiEnumClassName = ExtendHelper::buildEnumValueClassName(
                OroFrontendTestFrameworkBundleInstaller::MULTIENUM_FIELD_CODE
            );
            $multiEnumRepository = $manager->getRepository($multiEnumClassName);
            foreach ($multiEnumCodes as $code) {
                $multiEnumValue = $multiEnumRepository->find($code);
                $product->addMultienumField($multiEnumValue);
            }
        }

        $manager->persist($product);

        return $product;
    }
}
