<?php

namespace Oro\Bundle\ConsentBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\OrderedMigrationInterface;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Adds CustomerUser.acceptedConsents and ConsentAcceptance.customerUser Many-To-One bidirectional relation
 * Copies existing ids from customer_user_id to customerUser_id (generated by ExtendExtension)
 * Must be executed first - before customer_user_id gets deleted
 */
class AddCustomerUserRelation implements Migration, ExtendExtensionAwareInterface, OrderedMigrationInterface
{
    /** @var ExtendExtension */
    private $extendExtension;

    /**
     * {@inheritdoc}
     */
    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->addConsentAcceptanceCustomerUserRelation($schema);
        $this->updateNewCustomerUserIdFieldWithExistingValues($queries);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 1;
    }

    /**
     * @param Schema $schema
     */
    private function addConsentAcceptanceCustomerUserRelation(Schema $schema): void
    {
        $table = $schema->getTable('oro_customer_user');
        $targetTable = $schema->getTable('oro_consent_acceptance');

        $this->extendExtension->addManyToOneRelation(
            $schema,
            $targetTable,
            'customerUser',
            $table,
            'id',
            [
                ExtendOptionsManager::MODE_OPTION => ConfigModel::MODE_READONLY,
                'extend' => [
                    'is_extend' => true,
                    'owner' => ExtendScope::OWNER_CUSTOM,
                    'without_default' => true,
                    'on_delete' => 'CASCADE',
                ],
                'datagrid' => ['is_visible' => false],
                'form' => ['is_enabled' => false],
                'view' => ['is_displayable' => false],
                'merge' => ['display' => false]
            ]
        );

        $this->extendExtension->addManyToOneInverseRelation(
            $schema,
            $targetTable,
            'customerUser',
            $table,
            'acceptedConsents',
            ['id'],
            ['id'],
            ['id'],
            [
                ExtendOptionsManager::MODE_OPTION => ConfigModel::MODE_READONLY,
                'extend' => [
                    'is_extend' => true,
                    'owner' => ExtendScope::OWNER_CUSTOM,
                    'without_default' => true,
                    'cascade' => ['persist'],
                    'on_delete' => 'CASCADE',
                    'orphanRemoval' => true,
                    'fetch' => ClassMetadataInfo::FETCH_LAZY
                ],
                'datagrid' => ['is_visible' => false],
                'form' => ['is_enabled' => false],
                'view' => ['is_displayable' => false],
                'merge' => ['display' => false]
            ]
        );
    }

    /**
     * @param QueryBag $queries
     */
    private function updateNewCustomerUserIdFieldWithExistingValues(QueryBag $queries)
    {
        $queries->addQuery('UPDATE oro_consent_acceptance SET customerUser_id=customer_user_id');
    }
}