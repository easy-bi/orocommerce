<?php

namespace Oro\Bundle\ShoppingListBundle\Entity\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Oro\Bundle\BatchBundle\ORM\Query\ResultIterator\IdentifierHydrator;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\SecurityBundle\Acl\BasicPermission;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;

/**
 * Entity repository for Shopping List Line Item entity
 */
class LineItemRepository extends EntityRepository
{
    /**
     * Find line item with the same product and unit
     *
     * @param LineItem $lineItem
     *
     * @return LineItem
     */
    public function findDuplicate(LineItem $lineItem)
    {
        $qb = $this->createQueryBuilder('li');
        $qb->where('li.product = :product')
            ->andWhere('li.unit = :unit')
            ->andWhere('li.shoppingList = :shoppingList')
            ->setParameter('product', $lineItem->getProduct())
            ->setParameter('unit', $lineItem->getUnit())
            ->setParameter('shoppingList', $lineItem->getShoppingList())
            ->addOrderBy($qb->expr()->asc('li.id'));

        if ($lineItem->getId()) {
            $qb
                ->andWhere('li.id != :currentId')
                ->setParameter('currentId', $lineItem->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param AclHelper $aclHelper
     * @param array|Product $products
     * @return LineItem[]
     */
    public function getProductItemsWithShoppingListNames(AclHelper $aclHelper, $products)
    {
        $qb = $this->createQueryBuilder('li');
        $qb->select('li, shoppingList')
            ->join('li.shoppingList', 'shoppingList')
            ->join('li.product', 'product')
            ->leftJoin('product.parentVariantLinks', 'parentVariantLinks')
            ->andWhere('product IN (:products)')
            ->orWhere('li.parentProduct IN (:products)')
            ->orWhere('parentVariantLinks.parentProduct IN (:products)')
            ->setParameter('products', $products)
            ->addOrderBy($qb->expr()->asc('li.id'));

        return $aclHelper->apply($qb, BasicPermission::EDIT)->getResult();
    }

    /**
     * @param ShoppingList $shoppingList
     * @return array|LineItem[]
     */
    public function getItemsWithProductByShoppingList(ShoppingList $shoppingList)
    {
        $qb = $this->createQueryBuilder('li');
        $qb->select('li, product, names')
            ->join('li.product', 'product')
            ->join('product.names', 'names')
            ->where('li.shoppingList = :shoppingList')
            ->setParameter('shoppingList', $shoppingList)
            ->addOrderBy($qb->expr()->asc('li.id'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param ShoppingList $shoppingList
     * @param Product[] $products
     * @return array|LineItem[]
     */
    public function getItemsByShoppingListAndProducts(ShoppingList $shoppingList, $products)
    {
        $qb = $this->createQueryBuilder('li');
        $qb->select('li')
            ->where('li.shoppingList = :shoppingList', $qb->expr()->in('li.product', ':product'))
            ->setParameter('shoppingList', $shoppingList)
            ->setParameter('product', $products)
            ->addOrderBy($qb->expr()->asc('li.id'));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Product $product
     * @param CustomerUser $customerUser
     * @return array|LineItem[]
     */
    public function getOneProductLineItemsWithShoppingListNames(Product $product, CustomerUser $customerUser)
    {
        $qb = $this->createQueryBuilder('li');
        $qb->select('li, shoppingList')
            ->join('li.shoppingList', 'shoppingList')
            ->andWhere('li.product = :product')
            ->andWhere('li.customerUser = :customerUser')
            ->setParameter('product', $product)
            ->setParameter('customerUser', $customerUser)
            ->addOrderBy($qb->expr()->asc('li.id'));

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns array where Shopping List id is a key and array of last added products is a value
     *
     * Example:
     * [
     *   74 => [
     *     ['name' => '220 Lumen Rechargeable Headlamp'],
     *     ['name' => 'Credit Card Pin Pad Reader']
     *   ]
     * ]
     *
     * @param ShoppingList[]    $shoppingLists
     * @param int               $productCount
     * @param Localization|null $localization
     *
     * @return array
     */
    public function getLastProductsGroupedByShoppingList(
        array $shoppingLists,
        $productCount,
        Localization $localization = null
    ) {
        if (!$shoppingLists) {
            return [];
        }

        $qb = $this->createQueryBuilder('line_item');
        $query = $qb
            ->select('COALESCE(parent_product.id, product.id) as main_product_id')
            ->innerJoin('line_item.product', 'product')
            ->leftJoin('line_item.parentProduct', 'parent_product')
            ->andWhere($qb->expr()->eq('line_item.shoppingList', ':shopping_list'))
            ->addGroupBy('main_product_id')
            ->addOrderBy($qb->expr()->desc($qb->expr()->max('line_item.id')))
            ->setMaxResults($productCount)
            ->getQuery();

        $productsIdsByShoppingList = [];
        foreach ($shoppingLists as $shoppingList) {
            $shoppingListId = $shoppingList->getId();

            $productsIdsByShoppingList[$shoppingListId] = \array_column(
                $query->execute(['shopping_list' => $shoppingListId], AbstractQuery::HYDRATE_ARRAY),
                'main_product_id'
            );
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('product', 'names')
            ->from(Product::class, 'product', 'product.id')
            ->innerJoin('product.names', 'names')
            ->where($qb->expr()->in('product', ':product_ids'))
            ->setParameter('product_ids', \array_merge(...\array_values($productsIdsByShoppingList)));

        /** @var Product[] $products */
        $products = $qb->getQuery()->getResult();

        $result = [];
        foreach ($productsIdsByShoppingList as $shoppingListId => $productsIds) {
            foreach ($productsIds as $productId) {
                if (!isset($products[$productId])) {
                    continue;
                }

                $result[$shoppingListId][] = [
                    'name' => $products[$productId]->getName($localization)->getString(),
                ];
            }
        }

        return $result;
    }

    /**
     * @param ShoppingList $shoppingList
     * @param array $allowedInventoryStatuses
     */
    public function deleteItemsByShoppingListAndInventoryStatuses(
        ShoppingList $shoppingList,
        array $allowedInventoryStatuses
    ) {
        $lineItemsQB = $this->createQueryBuilder('li');
        $lineItemsQB->select('li.id')
            ->join('li.product', 'p')
            ->where($lineItemsQB->expr()->notIn('IDENTITY(p.inventory_status)', ':allowedInventoryStatuses'))
            ->andWhere($lineItemsQB->expr()->eq('li.shoppingList', ':shoppingList'))
            ->setParameter('shoppingList', $shoppingList)
            ->setParameter('allowedInventoryStatuses', $allowedInventoryStatuses);
        $query = $lineItemsQB->getQuery();

        $identifierHydrationMode = 'IdentifierHydrator';
        $query
            ->getEntityManager()
            ->getConfiguration()
            ->addCustomHydrationMode($identifierHydrationMode, IdentifierHydrator::class);
        $ids = $query->getResult($identifierHydrationMode);

        $deleteQb = $this->getEntityManager()->createQueryBuilder();
        $deleteQb->delete()
            ->from($this->getEntityName(), 'li')
            ->where($deleteQb->expr()->in('li.id', ':ids'));
        $deleteQb->getQuery()->execute(['ids' => $ids]);
    }

    /**
     * @param ShoppingList $shoppingList
     */
    public function deleteDisabledItemsByShoppingList(ShoppingList $shoppingList)
    {
        $whereQb = $this->createQueryBuilder('l');
        $whereQb->select('l.id')
            ->join('l.product', 'p')
            ->where($whereQb->expr()->eq('p.status', ':status'))
            ->andWhere($whereQb->expr()->eq('l.shoppingList', ':shoppingList'))
            ->setParameter('status', Product::STATUS_DISABLED)
            ->setParameter('shoppingList', $shoppingList);
        $whereQuery = $whereQb->getQuery();

        $identifierHydrationMode = 'IdentifierHydrator';
        $whereQuery
            ->getEntityManager()
            ->getConfiguration()
            ->addCustomHydrationMode($identifierHydrationMode, IdentifierHydrator::class);
        $ids = $whereQuery->getResult($identifierHydrationMode);

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->delete()
            ->from($this->getEntityName(), 'li')
            ->where($qb->expr()->in('li.id', ':ids'))
            ->getQuery()->execute(['ids' => $ids]);
    }

    /**
     * @param ShoppingList $shoppingList
     * @param array $allowedInventoryStatuses
     */
    public function deleteNotAllowedLineItemsFromShoppingList(
        ShoppingList $shoppingList,
        array $allowedInventoryStatuses
    ): void {
        $lineItemsQb = $this->createQueryBuilder('line_item');
        $lineItemsQuery = $lineItemsQb
            ->select('line_item.id')
            ->innerJoin('line_item.product', 'product')
            ->where(
                $lineItemsQb->expr()->orX(
                    $lineItemsQb->expr()->notIn('IDENTITY(product.inventory_status)', ':allowedInventoryStatuses'),
                    $lineItemsQb->expr()->eq('product.status', ':status')
                ),
                $lineItemsQb->expr()->eq('line_item.shoppingList', ':shoppingList')
            )
            ->setParameter('allowedInventoryStatuses', $allowedInventoryStatuses)
            ->setParameter('status', Product::STATUS_DISABLED)
            ->setParameter('shoppingList', $shoppingList)
            ->getQuery();

        $identifierHydrationMode = 'IdentifierHydrator';

        $lineItemsQuery->getEntityManager()
            ->getConfiguration()
            ->addCustomHydrationMode($identifierHydrationMode, IdentifierHydrator::class);

        $ids = $lineItemsQuery->getResult($identifierHydrationMode);
        if ($ids) {
            $deleteQb = $this->getEntityManager()->createQueryBuilder();
            $deleteQb->delete()
                ->from($this->getEntityName(), 'line_item')
                ->where($deleteQb->expr()->in('line_item.id', ':ids'))
                ->getQuery()
                ->execute(['ids' => $ids]);
        }
    }
}
