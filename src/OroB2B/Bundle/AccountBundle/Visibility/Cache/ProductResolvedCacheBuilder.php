<?php

namespace OroB2B\Bundle\AccountBundle\Visibility\Cache;

use OroB2B\Bundle\AccountBundle\Entity\Visibility\ProductVisibility;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseProductVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\ProductVisibilityResolved;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

use Oro\Bundle\EntityBundle\ORM\InsertFromSelectQueryExecutor;

use OroB2B\Bundle\CatalogBundle\Entity\Category;
use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\WebsiteBundle\Entity\Website;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\ProductVisibility;
use OroB2B\Bundle\AccountBundle\Entity\VisibilityResolved\BaseProductVisibilityResolved;
use OroB2B\Bundle\AccountBundle\Visibility\Calculator\CategoryVisibilityResolver;
use OroB2B\Bundle\AccountBundle\Entity\Repository\ProductVisibilityResolvedRepository;

class ProductResolvedCacheBuilder extends AbstractCacheBuilder
{
    const VISIBLE = 'visible';
    const HIDDEN = 'hidden';
    /**
     * @param ProductVisibility $productVisibility
     * @var InsertFromSelectQueryExecutor
     */
    protected $insertFromSelectExecutor;

    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var string
     */
    protected $cacheClass;

    /**
     * @var CategoryVisibilityResolver
     */
    protected $categoryVisibilityResolver;

    /**
     * ProductResolvedCacheBuilder constructor.
     * @param RegistryInterface $doctrine
     * @param InsertFromSelectQueryExecutor $executor
     * @param CategoryVisibilityResolver $categoryVisibilityResolver
     * @param string $cacheClass
     */
    public function __construct(
        RegistryInterface $doctrine,
        InsertFromSelectQueryExecutor $executor,
        CategoryVisibilityResolver $categoryVisibilityResolver,
        $cacheClass
    ) {
        $this->doctrine = $doctrine;
        $this->insertFromSelectExecutor = $executor;
        $this->cacheClass = $cacheClass;
        $this->categoryVisibilityResolver = $categoryVisibilityResolver;
    }


    /**
     * {@inheritdoc}
     */
    public function resolveVisibilitySettings($productVisibility)
    {
        $product = $productVisibility->getProduct();
        $website = $productVisibility->getWebsite();

        $selectedVisibility = $productVisibility->getVisibility();

        $em = $this->registry->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved');
        $productVisibilityResolved = $em
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\ProductVisibilityResolved')
            ->findByPrimaryKey($product, $website);

        if (!$productVisibilityResolved && $selectedVisibility !== ProductVisibility::CONFIG) {
            $productVisibilityResolved = new ProductVisibilityResolved($website, $product);
            $em->persist($productVisibilityResolved);
        }

        if ($selectedVisibility === ProductVisibility::CATEGORY) {
            $category = $this->registry
                ->getManagerForClass('OroB2BCatalogBundle:Category')
                ->getRepository('OroB2BCatalogBundle:Category')
                ->findOneByProduct($product);

            if ($category) {
                $productVisibilityResolved->setSourceProductVisibility(null);
                $productVisibilityResolved->setVisibility(
                    $this->convertVisibility($this->categoryVisibilityResolver->isCategoryVisible($category))
                );
                $productVisibilityResolved->setSource(BaseProductVisibilityResolved::SOURCE_CATEGORY);
                $productVisibilityResolved->setCategoryId($category->getId());
            } else {
                $this->resolveConfigValue($productVisibilityResolved);
            }
        } elseif ($selectedVisibility === ProductVisibility::CONFIG) {
            if ($productVisibilityResolved) {
                $em->remove($productVisibilityResolved);
            }
        } else {
            $this->resolveStaticValues($productVisibilityResolved, $productVisibility, $selectedVisibility);
        }

        // set calculated visibility to account resolved values
        if ($productVisibilityResolved && $selectedVisibility !== ProductVisibility::CONFIG) {
            $visibility = $productVisibilityResolved->getVisibility();
        } else {
            $visibility = $this->getVisibilityFromConfig();
        }
        $this->registry->getManagerForClass('OroB2BAccountBundle:VisibilityResolved\AccountProductVisibilityResolved')
            ->getRepository('OroB2BAccountBundle:VisibilityResolved\AccountProductVisibilityResolved')
            ->updateCurrentProductRelatedEntities($website, $product, $visibility);
    }

    /**
     * {@inheritdoc}
     */
    public function isVisibilitySettingsSupported($visibilitySettings)
    {
        return $visibilitySettings instanceof ProductVisibility;
    }

    /**
     * {@inheritdoc}
     */
    public function updateResolvedVisibilityByCategory(Category $category)
    {
        // TODO: Implement updateResolvedVisibilityByCategory() method.
    }

    /**
     * {@inheritdoc}
     */
    public function updateProductResolvedVisibility(Product $product)
    {
        // TODO: Implement updateProductResolvedVisibility() method.
    }

    /**
     * {@inheritdoc}
     */
    public function buildCache(Website $website = null)
    {
        $this->getManager()->beginTransaction();
        try {
            $this->getRepository()->clearTable();

            $categoriesGrouped = $this->getCategories();
            $this->getRepository()->insertByCategory(
                $this->insertFromSelectExecutor,
                BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
                $categoriesGrouped[self::VISIBLE]
            );
            $this->getRepository()->insertByCategory(
                $this->insertFromSelectExecutor,
                BaseProductVisibilityResolved::VISIBILITY_HIDDEN,
                $categoriesGrouped[self::HIDDEN]
            );

            $this->getRepository()->deleteByVisibility(ProductVisibility::CONFIG);
            $this->getRepository()->updateFromBaseTable(
                BaseProductVisibilityResolved::VISIBILITY_VISIBLE,
                ProductVisibility::VISIBLE
            );
            $this->getRepository()->updateFromBaseTable(
                BaseProductVisibilityResolved::VISIBILITY_HIDDEN,
                ProductVisibility::HIDDEN
            );
            $this->getManager()->commit();
        } catch (\Exception $exception) {
            $this->getManager()->rollback();
            throw $exception;
        }
    }

    /**
     * @return array
     */
    protected function getCategories()
    {
        // temporary
        /** @var Category[] $categories */
        $categories = $this->doctrine->getManagerForClass('OroB2BCatalogBundle:Category')
            ->getRepository('OroB2BCatalogBundle:Category')
            ->createQueryBuilder('category')
            ->select('partial category.{id}')
            ->getQuery()
            ->getResult();

        $categoriesGrouped = [self::VISIBLE => [], self::HIDDEN => []];

        foreach ($categories as $category) {
            if ($this->categoryVisibilityResolver->isCategoryVisible($category)) {
                $categoriesGrouped[self::VISIBLE][] = $category->getId();
            } else {
                $categoriesGrouped[self::HIDDEN][] = $category->getId();
            }
        }

        return $categoriesGrouped;
    }

    /**
     * @return ProductVisibilityResolvedRepository
     */
    protected function getRepository()
    {
        return $this->getManager()->getRepository($this->cacheClass);
    }

    /**
     * @return EntityManagerInterface|null
     */
    protected function getManager()
    {
        return $this->doctrine->getManagerForClass($this->cacheClass);
    }
}
