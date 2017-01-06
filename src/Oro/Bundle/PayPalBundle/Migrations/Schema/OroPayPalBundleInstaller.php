<?php

namespace Oro\Bundle\PayPalBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedSqlMigrationQuery;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroPayPalBundleInstaller implements Installation
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_1';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->migratePayPalSettings($schema, $queries);
        $this->createOroPaypalAllowedCcTypesTable($schema);
        $this->createOroPaypalCcPaymentActionTable($schema);
        $this->createOroPaypalCreditCardLblTable($schema);
        $this->createOroPaypalCreditCardShrtLblTable($schema);
        $this->createOroPaypalCreditCardTypesTable($schema);
        $this->createOroPaypalEcPaymentActionTable($schema);
        $this->createOroPaypalXprssChktLblTable($schema);
        $this->createOroPaypalXprssChktShrtLblTable($schema);
        $this->addOroPaypalAllowedCcTypesForeignKeys($schema);
        $this->addOroPaypalCcPaymentActionForeignKeys($schema);
        $this->addOroPaypalCreditCardLblForeignKeys($schema);
        $this->addOroPaypalCreditCardShrtLblForeignKeys($schema);
        $this->addOroPaypalEcPaymentActionForeignKeys($schema);
        $this->addOroPaypalXprssChktLblForeignKeys($schema);
        $this->addOroPaypalXprssChktShrtLblForeignKeys($schema);
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     */
    protected function migratePayPalSettings(Schema $schema, QueryBag $queries)
    {
        // PayPal Payments Pro
        $this->migrateSetting($queries, 'paypal_payments_pro_label');
        $this->migrateSetting($queries, 'paypal_payments_pro_short_label');
        $this->migrateSetting($queries, 'paypal_payments_pro_allowed_cc_types');
        $this->migrateSetting($queries, 'paypal_payments_pro_partner');
        $this->migrateSetting($queries, 'paypal_payments_pro_user');
        $this->migrateSetting($queries, 'paypal_payments_pro_vendor');
        $this->migrateSetting($queries, 'paypal_payments_pro_password');
        $this->migrateSetting($queries, 'paypal_payments_pro_payment_action');
        $this->migrateSetting($queries, 'paypal_payments_pro_test_mode');
        $this->migrateSetting($queries, 'paypal_payments_pro_use_proxy');
        $this->migrateSetting($queries, 'paypal_payments_pro_proxy_host');
        $this->migrateSetting($queries, 'paypal_payments_pro_proxy_port');
        $this->migrateSetting($queries, 'paypal_payments_pro_debug_mode');
        $this->migrateSetting($queries, 'paypal_payments_pro_enable_ssl_verification');
        $this->migrateSetting($queries, 'paypal_payments_pro_require_cvv');
        $this->migrateSetting($queries, 'paypal_payments_pro_zero_amount_authorization');
        $this->migrateSetting($queries, 'paypal_payments_pro_authorization_for_required_amount');
        $this->migrateSetting($queries, 'paypal_payments_pro_allowed_currencies');
        $this->migrateSetting($queries, 'paypal_payments_pro_express_checkout_enabled');
        $this->migrateSetting($queries, 'paypal_payments_pro_express_checkout_label');
        $this->migrateSetting($queries, 'paypal_payments_pro_express_checkout_short_label');
        $this->migrateSetting($queries, 'paypal_payments_pro_express_checkout_sort_order');
        $this->migrateSetting($queries, 'paypal_payments_pro_express_checkout_payment_action');

        // Payflow Gateway
        $this->migrateSetting($queries, 'payflow_gateway_label');
        $this->migrateSetting($queries, 'payflow_gateway_short_label');
        $this->migrateSetting($queries, 'payflow_gateway_allowed_cc_types');
        $this->migrateSetting($queries, 'payflow_gateway_partner');
        $this->migrateSetting($queries, 'payflow_gateway_user');
        $this->migrateSetting($queries, 'payflow_gateway_vendor');
        $this->migrateSetting($queries, 'payflow_gateway_password');
        $this->migrateSetting($queries, 'payflow_gateway_payment_action');
        $this->migrateSetting($queries, 'payflow_gateway_test_mode');
        $this->migrateSetting($queries, 'payflow_gateway_use_proxy');
        $this->migrateSetting($queries, 'payflow_gateway_proxy_host');
        $this->migrateSetting($queries, 'payflow_gateway_proxy_port');
        $this->migrateSetting($queries, 'payflow_gateway_debug_mode');
        $this->migrateSetting($queries, 'payflow_gateway_enable_ssl_verification');
        $this->migrateSetting($queries, 'payflow_gateway_require_cvv');
        $this->migrateSetting($queries, 'payflow_gateway_zero_amount_authorization');
        $this->migrateSetting($queries, 'payflow_gateway_authorization_for_required_amount');
        $this->migrateSetting($queries, 'payflow_gateway_allowed_currencies');

        // Payflow Express Checkout
        $this->migrateSetting($queries, 'payflow_express_checkout_label');
        $this->migrateSetting($queries, 'payflow_express_checkout_short_label');
        $this->migrateSetting($queries, 'payflow_express_checkout_payment_action');
    }

    /**
     * @param QueryBag $queries
     * @param string $name
     */
    protected function migrateSetting(QueryBag $queries, $name)
    {
        $queries->addQuery(new ParametrizedSqlMigrationQuery(
            'UPDATE oro_config_value SET section = :new_section WHERE name = :name AND section = :old_section',
            [
                'name' => $name,
                'new_section' => 'oro_paypal',
                'old_section' => 'orob2b_payment'
            ]
        ));
    }

    /**
     * Create oro_paypal_allowed_cc_types table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalAllowedCcTypesTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_allowed_cc_types');
        $table->addColumn('pp_settings_id', 'integer', []);
        $table->addColumn('cc_id', 'integer', []);
        $table->setPrimaryKey(['pp_settings_id', 'cc_id']);
        $table->addIndex(['pp_settings_id'], 'IDX_EBCFE954FDDC5A1F', []);
        $table->addIndex(['cc_id'], 'IDX_EBCFE954A823BE4F', []);
    }

    /**
     * Create oro_paypal_cc_payment_action table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalCcPaymentActionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_cc_payment_action');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('settings_id', 'integer', ['notnull' => false]);
        $table->addColumn('label', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['settings_id'], 'IDX_24D6481759949888', []);
    }

    /**
     * Create oro_paypal_credit_card_lbl table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalCreditCardLblTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_credit_card_lbl');
        $table->addColumn('transport_id', 'integer', []);
        $table->addColumn('localized_value_id', 'integer', []);
        $table->setPrimaryKey(['transport_id', 'localized_value_id']);
        $table->addUniqueIndex(['localized_value_id'], 'UNIQ_92E5B87EEB576E89');
        $table->addIndex(['transport_id'], 'IDX_92E5B87E9909C13F', []);
    }

    /**
     * Create oro_paypal_credit_card_sh_lbl table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalCreditCardShrtLblTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_credit_card_sh_lbl');
        $table->addColumn('transport_id', 'integer', []);
        $table->addColumn('localized_value_id', 'integer', []);
        $table->setPrimaryKey(['transport_id', 'localized_value_id']);
        $table->addUniqueIndex(['localized_value_id'], 'UNIQ_415165DEEB576E89');
        $table->addIndex(['transport_id'], 'IDX_415165DE9909C13F', []);
    }

    /**
     * Create oro_paypal_credit_card_types table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalCreditCardTypesTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_credit_card_types');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('label', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_paypal_ec_payment_action table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalEcPaymentActionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_ec_payment_action');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('settings_id', 'integer', ['notnull' => false]);
        $table->addColumn('label', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['settings_id'], 'IDX_83E2FF1F59949888', []);
    }

    /**
     * Create oro_paypal_xprss_chkt_lbl table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalXprssChktLblTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_xprss_chkt_lbl');
        $table->addColumn('transport_id', 'integer', []);
        $table->addColumn('localized_value_id', 'integer', []);
        $table->setPrimaryKey(['transport_id', 'localized_value_id']);
        $table->addUniqueIndex(['localized_value_id'], 'UNIQ_386D1FC6EB576E89');
        $table->addIndex(['transport_id'], 'IDX_386D1FC69909C13F', []);
    }

    /**
     * Create oro_paypal_xprss_chkt_shrt_lbl table
     *
     * @param Schema $schema
     */
    protected function createOroPaypalXprssChktShrtLblTable(Schema $schema)
    {
        $table = $schema->createTable('oro_paypal_xprss_chkt_shrt_lbl');
        $table->addColumn('transport_id', 'integer', []);
        $table->addColumn('localized_value_id', 'integer', []);
        $table->setPrimaryKey(['transport_id', 'localized_value_id']);
        $table->addUniqueIndex(['localized_value_id'], 'UNIQ_A9419ECEB576E89');
        $table->addIndex(['transport_id'], 'IDX_A9419EC9909C13F', []);
    }

    /**
     * Add oro_paypal_allowed_cc_types foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalAllowedCcTypesForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_allowed_cc_types');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_paypal_credit_card_types'),
            ['cc_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['pp_settings_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_cc_payment_action foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalCcPaymentActionForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_cc_payment_action');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['settings_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_credit_card_lbl foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalCreditCardLblForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_credit_card_lbl');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['transport_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_fallback_localization_val'),
            ['localized_value_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_credit_card_sh_lbl foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalCreditCardShrtLblForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_credit_card_sh_lbl');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['transport_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_fallback_localization_val'),
            ['localized_value_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_ec_payment_action foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalEcPaymentActionForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_ec_payment_action');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['settings_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_xprss_chkt_lbl foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalXprssChktLblForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_xprss_chkt_lbl');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['transport_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_fallback_localization_val'),
            ['localized_value_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }

    /**
     * Add oro_paypal_xprss_chkt_shrt_lbl foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroPaypalXprssChktShrtLblForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_paypal_xprss_chkt_shrt_lbl');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_integration_transport'),
            ['transport_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_fallback_localization_val'),
            ['localized_value_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null]
        );
    }
}
