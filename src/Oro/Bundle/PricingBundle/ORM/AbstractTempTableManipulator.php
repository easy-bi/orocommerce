<?php

namespace Oro\Bundle\PricingBundle\ORM;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\ORM\NativeQueryExecutorHelper;
use Oro\Bundle\PricingBundle\ORM\Walker\PriceShardOutputResultModifier;
use Oro\Bundle\PricingBundle\ORM\Walker\TempTableOutputResultModifier;
use Oro\Bundle\PricingBundle\Sharding\ShardManager;
use Oro\Component\DoctrineUtils\ORM\QueryBuilderUtil;

/**
 * Manipulate temp tables created by a given entity table.
 */
abstract class AbstractTempTableManipulator implements TempTableManipulatorInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var NativeQueryExecutorHelper
     */
    protected $helper;

    /**
     * @var ShardQueryExecutorNativeSqlInterface
     */
    protected $queryExecutor;

    /**
     * @var ShardManager
     */
    protected $shardManager;

    /**
     * @param ManagerRegistry $registry
     * @param NativeQueryExecutorHelper $helper
     * @param ShardQueryExecutorNativeSqlInterface $queryExecutor
     * @param ShardManager $shardManager
     */
    public function __construct(
        ManagerRegistry $registry,
        NativeQueryExecutorHelper $helper,
        ShardQueryExecutorNativeSqlInterface $queryExecutor,
        ShardManager $shardManager
    ) {
        $this->registry = $registry;
        $this->helper = $helper;
        $this->queryExecutor = $queryExecutor;
        $this->shardManager = $shardManager;
    }

    /**
     * {@inheritDoc}
     */
    public function setInsertSelectExecutor(ShardQueryExecutorNativeSqlInterface $queryExecutor)
    {
        $this->queryExecutor = $queryExecutor;
    }

    /**
     * {@inheritDoc}
     */
    public function dropTempTableForEntity(string $className, $identifier)
    {
        $this->registry->getConnection()->executeQuery(sprintf(
            'DROP TABLE %s',
            $this->getTempTableNameForEntity($className, $identifier)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function truncateTempTableForEntity(string $className, $identifier)
    {
        $this->registry->getConnection()->executeQuery(sprintf(
            'TRUNCATE %s',
            $this->getTempTableNameForEntity($className, $identifier)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getTempTableNameForEntity(string $className, $identifier): string
    {
        QueryBuilderUtil::checkIdentifier($identifier);

        return $this->getTableNameForEntity($className) . '_tmp_' . $identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function getTableNameForEntity(string $className): string
    {
        return $this->helper->getTableName($className);
    }

    /**
     * {@inheritDoc}
     */
    public function moveDataFromTemplateTableToEntityTable(string $className, $identifier, array $fields)
    {
        $columns = $this->helper->getColumns($className, $fields);

        $this->queryExecutor->executeNative(
            $this->helper->getTableName($className),
            $className,
            sprintf(
                'SELECT %s FROM %s',
                implode(',', $columns),
                $this->getTempTableNameForEntity($className, $identifier)
            ),
            $fields
        );

        $this->truncateTempTableForEntity($className, $identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function insertData(
        string $insertToTableName,
        string $className,
        $identifier,
        array $fields,
        QueryBuilder $qb,
        bool $applyOnDuplicateKeyUpdate = true,
        ?array $tempTableAliases = []
    ) {
        $selectQuery = $qb->getQuery();
        [$params, $types] = $this->helper->processParameterMappings($selectQuery);
        $selectQuery->useQueryCache(false);
        $selectQuery->setHint(PriceShardOutputResultModifier::ORO_PRICING_SHARD_MANAGER, $this->shardManager);

        if ($tempTableAliases) {
            $selectQuery->setHint(TempTableOutputResultModifier::ORO_TEMP_TABLE_ALIASES, $tempTableAliases);
        }

        $this->queryExecutor->executeNative(
            $insertToTableName,
            $className,
            $selectQuery->getSQL(),
            $fields,
            $params,
            $types,
            $applyOnDuplicateKeyUpdate
        );
    }
}
