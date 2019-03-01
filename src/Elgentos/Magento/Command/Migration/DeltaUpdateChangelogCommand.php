<?php

namespace Elgentos\Magento\Command\Migration;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use \Varien_Db_Adapter_Pdo_Mysql as Mysql;

class DeltaUpdateChangelogCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('m2-migration:delta-update-changelog')
            ->setDescription('Update changelog tables with new entities for M2 delta migration')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Magento 2 path (relative)')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Magento 2 database host')
            ->addOption('dbname', null, InputOption::VALUE_OPTIONAL, 'Magento 2 database name')
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, 'Magento 2 database username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Magento 2 database password')
            ->addOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Magento 2 database prefix');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if (!$this->initMagento()) return;

        $m1DbResource = \Mage::getModel('core/resource');
        /** @var \Varien_Db_Adapter_Mysqli $m1DbObject */
        $m1DbObject = $m1DbResource->getConnection('core_write');


        $m2Db = [
                'prefix' => $input->getOption('prefix'),
                'host' => $input->getOption('host'),
                'dbname' => $input->getOption('dbname'),
                'username' => $input->getOption('username'),
                'password' => ($input->getOption('password')?:'')
        ];

        $dialog = $this->getHelperSet()->get('dialog');

        $m2Path = $input->getOption('path');
        if (!$m2Path && !$input->getOption('host')) {
            $m2Path = $dialog->ask($output,
                    '<question>Path to M2 (leave empty to fill out database info manually)</question>: ', null);
        }

        if ($m2Path) {
            $m2Path = rtrim($m2Path, DIRECTORY_SEPARATOR);
            $envPath = $m2Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'env.php';
            if (file_exists($envPath)) {
                $dbInfo = include($envPath);
                $m2Db = [
                    'prefix' => $dbInfo['db']['table_prefix'],
                    'host' => $dbInfo['db']['connection']['default']['host'],
                    'dbname' => $dbInfo['db']['connection']['default']['dbname'],
                    'username' => $dbInfo['db']['connection']['default']['username'],
                    'password' => $dbInfo['db']['connection']['default']['password'],
                ];
            } else {
                $output->writeln('<error>No env file found at ' . $envPath . '; please fill out info manually.</error>');
                $m2Path = false;
            }
        }

        if (! $m2Path) {
            foreach ($m2Db as $key => $value) {
                if (null !== $m2Db[$key]) continue;

                $m2Db[$key] = $dialog->ask($output,
                    '<question>Magento 2 database ' . $key . ' ?</question>: ', null);
            }
        }

        $m2Db['initStatements'] = 'SET NAMES utf8';
        $m2Db['model'] = 'mysql4';
        $m2Db['type'] = 'pdo_mysql';
        $m2Db['pdo_type'] = '';
        $m2Db['active'] = '1';

        // Set up M2 connection
        $config = \Mage::getConfig();
        $config->setNode('global/resources/m2_database/connection/host', $m2Db['host']);
        $config->setNode('global/resources/m2_database/connection/username', $m2Db['username']);
        $config->setNode('global/resources/m2_database/connection/password', $m2Db['password']);
        $config->setNode('global/resources/m2_database/connection/dbname', $m2Db['dbname']);
        $config->setNode('global/resources/m2_database/connection/initStatements', $m2Db['initStatements']);
        $config->setNode('global/resources/m2_database/connection/model', $m2Db['model']);
        $config->setNode('global/resources/m2_database/connection/type', $m2Db['type']);
        $config->setNode('global/resources/m2_database/connection/pdo_type', $m2Db['pdo_type']);
        $config->setNode('global/resources/m2_database/connection/active', $m2Db['active']);
        $config->setNode('global/resources/m2db_write/connection/use', 'm2_database');

        /** @var \Varien_Db_Adapter_Mysqli $m2DbObject */
        $m2DbObject = $m1DbResource->getConnection('m2db_write');

        $tables = $this->getClTableList();

        foreach ($tables as $table) {
            // If no specific m2name is set, name is thesame as M1
            if (!isset($table['m2name'])) {
                $table['m2name'] = $table['name'];
            }

            // If a prefix for M2 is set, prepend it to the table name
            if ($m2Db['prefix']) {
                $table['m2name'] = $m2Db['prefix'] . $table['m2name'];
            }

            // Fetch correct table name for M1 with possible prefix
            $table['name'] = $m1DbResource->getTableName($table['name']);
            $table['destination'] = $m1DbResource->getTableName($table['destination']);

            $output->writeln('<comment>Processing Magento 1 table ' . $table['name'] . ' / Magento 2 table '. $table['m2name'] . '</comment>');

            // Clear changelog table before processing to avoid duplicate entry warnings
            $m1DbObject->delete($table['destination']);

            // Fetch all rows from the M2 database to see which ones we already have
            $existingRows = $m2DbObject->fetchCol($m2DbObject->select()->from($table['m2name'], $table['id']));

            // Start building the query for M1 to fetch only new rows by using a NOT IN query
            $operationExpr = new \Zend_Db_Expr('"INSERT" as operation');
            $newRowsQuery = $m1DbObject->select()->from($table['name'], array($table['id'], $operationExpr));

            if (count($existingRows)) {
                $newRowsQuery->where($table['id'] . ' NOT IN (' . implode(',', $existingRows) . ')');
            }

            $newRows =$m1DbObject->fetchCol($newRowsQuery);

            if (count($newRows)) {
                $output->writeln('<info>' . count($newRows) . ' entities were found for ' . $table['name'] . '</info>');
                // Then, insert rows into the CL tables
                $fields = array($table['id'], 'operation');
                $m1DbObject->insertBatchFromSelect($newRowsQuery, $table['destination'], $fields, Mysql::INSERT_IGNORE);
            } else {
                $output->writeln('<info>No new entities found for ' . $table['name'] . '</info>');
            }
        }
    }

    private function getClTableList()
    {
        $tables = array(
	    array(
	         'name' => 'cataloginventory_stock_item',
	         'id' => 'item_id',
	         'destination' => 'm2_cl_cataloginventory_stock_item'
	     ),
 		array(
	         'name' => 'catalog_compare_item',
	         'id' => 'catalog_compare_item_id',
	         'destination' => 'm2_cl_catalog_compare_item'
	     ),
  		array(
	         'name' => 'customer_address_entity',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_customer_address_entity'
	     ),
  		array(
	         'name' => 'customer_address_entity_datetime',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_address_entity_datetime'
	     ),
  		array(
	         'name' => 'customer_address_entity_decimal',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_address_entity_decimal'
	     ),
  		array(
	         'name' => 'customer_address_entity_int',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_address_entity_int'
	     ),
  		array(
	         'name' => 'customer_address_entity_text',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_address_entity_text'
	     ),
  		array(
	         'name' => 'customer_address_entity_varchar',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_address_entity_varchar'
	     ),
  		array(
	         'name' => 'customer_entity',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_customer_entity'
	     ),
  		array(
	         'name' => 'customer_entity_datetime',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_entity_datetime'
	     ),
  		array(
	         'name' => 'customer_entity_decimal',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_entity_decimal'
	     ),
  		array(
	         'name' => 'customer_entity_int',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_entity_int'
	     ),
  		array(
	         'name' => 'customer_entity_text',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_entity_text'
	     ),
  		array(
	         'name' => 'customer_entity_varchar',
	         'id' => 'value_id',
	         'destination' => 'm2_cl_customer_entity_varchar'
	     ),
  		array(
	         'name' => 'downloadable_link_purchased',
	         'id' => 'purchased_id',
	         'destination' => 'm2_cl_downloadable_link_purchased'
	     ),
  		array(
	         'name' => 'downloadable_link_purchased_item',
	         'id' => 'item_id',
	         'destination' => 'm2_cl_downloadable_link_purchased_item'
	     ),
  		array(
	         'name' => 'eav_entity_store',
	         'id' => 'entity_store_id',
	         'destination' => 'm2_cl_eav_entity_store'
	     ),
  		array(
	         'name' => 'gift_message',
	         'id' => 'gift_message_id',
	         'destination' => 'm2_cl_gift_message'
	     ),
  		array(
	         'name' => 'log_visitor',
	         'm2name' => 'customer_visitor',
	         'id' => 'visitor_id',
	         'destination' => 'm2_cl_log_visitor'
	     ),
  		array(
	         'name' => 'newsletter_subscriber',
	         'id' => 'subscriber_id',
	         'destination' => 'm2_cl_newsletter_subscriber'
	     ),
  		array(
	         'name' => 'rating_option_vote',
	         'id' => 'vote_id',
	         'destination' => 'm2_cl_rating_option_vote'
	     ),
  		array(
	         'name' => 'rating_option_vote_aggregated',
	         'id' => 'primary_id',
	         'destination' => 'm2_cl_rating_option_vote_aggregated'
	     ),
  		array(
	         'name' => 'report_compared_product_index',
	         'id' => 'index_id',
	         'destination' => 'm2_cl_report_compared_product_index'
	     ),
  		array(
	         'name' => 'report_event',
	         'id' => 'event_id',
	         'destination' => 'm2_cl_report_event'
	     ),
  		array(
	         'name' => 'report_viewed_product_index',
	         'id' => 'index_id',
	         'destination' => 'm2_cl_report_viewed_product_index'
	     ),
  		array(
	         'name' => 'review',
	         'id' => 'review_id',
	         'destination' => 'm2_cl_review'
	     ),
  		array(
	         'name' => 'review_detail',
	         'id' => 'detail_id',
	         'destination' => 'm2_cl_review_detail'
	     ),
  		array(
	         'name' => 'review_entity_summary',
	         'id' => 'primary_id',
	         'destination' => 'm2_cl_review_entity_summary'
	     ),
  		array(
	         'name' => 'review_store',
	         'id' => 'review_id',
	         'destination' => 'm2_cl_review_store'
	     ),
  		array(
	         'name' => 'sales_flat_creditmemo',
	         'm2name' => 'sales_creditmemo',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_creditmemo'
	     ),
  		array(
	         'name' => 'sales_flat_creditmemo_grid',
	         'm2name' => 'sales_creditmemo_grid',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_creditmemo_grid'
	     ),
  		array(
	         'name' => 'sales_flat_creditmemo_item',
	         'm2name' => 'sales_creditmemo_item',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_creditmemo_item'
	     ),
  		array(
	         'name' => 'sales_flat_invoice',
	         'm2name' => 'sales_invoice',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_invoice'
	     ),
  		array(
	         'name' => 'sales_flat_invoice_grid',
	         'm2name' => 'sales_invoice_grid',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_invoice_grid'
	     ),
  		array(
	         'name' => 'sales_flat_invoice_item',
	         'm2name' => 'sales_invoice_item',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_invoice_item'
	     ),
            array(
                'name' => 'sales_flat_order',
                'm2name' => 'sales_order',
                'id' => 'entity_id',
                'destination' => 'm2_cl_sales_flat_order'
            ),
  		array(
	         'name' => 'sales_flat_order_address',
	         'm2name' => 'sales_order_address',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_order_address'
	     ),
  		array(
	         'name' => 'sales_flat_order_grid',
	         'm2name' => 'sales_order_grid',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_order_grid'
	     ),
  		array(
	         'name' => 'sales_flat_order_item',
	         'm2name' => 'sales_order_item',
	         'id' => 'item_id',
	         'destination' => 'm2_cl_sales_flat_order_item'
	     )
  		,
  		array(
	         'name' => 'sales_flat_order_payment',
	         'm2name' => 'sales_order_payment',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_order_payment'
	     ),
  		array(
	         'name' => 'sales_flat_order_status_history',
	         'm2name' => 'sales_order_status_history',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_order_status_history'
	     ),
  		array(
	         'name' => 'sales_flat_quote',
	         'm2name' => 'quote',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_quote'
	     ),
  		array(
	         'name' => 'sales_flat_quote_address',
	         'm2name' => 'quote_address',
	         'id' => 'address_id',
	         'destination' => 'm2_cl_sales_flat_quote_address'
	     ),
  		array(
	         'name' => 'sales_flat_quote_address_item',
	         'm2name' => 'quote_address_item',
	         'id' => 'address_item_id',
	         'destination' => 'm2_cl_sales_flat_quote_address_item'
	     ),
  		array(
	         'name' => 'sales_flat_quote_item',
	         'm2name' => 'quote_item',
	         'id' => 'item_id',
	         'destination' => 'm2_cl_sales_flat_quote_item'
	     ),
  		array(
	         'name' => 'sales_flat_quote_item_option',
	         'm2name' => 'quote_item_option',
	         'id' => 'option_id',
	         'destination' => 'm2_cl_sales_flat_quote_item_option'
	     ),
  		array(
	         'name' => 'sales_flat_quote_payment',
	         'm2name' => 'quote_payment',
	         'id' => 'payment_id',
	         'destination' => 'm2_cl_sales_flat_quote_payment'
	     ),
  		array(
	         'name' => 'sales_flat_quote_shipping_rate',
	         'm2name' => 'quote_shipping_rate',
	         'id' => 'rate_id',
	         'destination' => 'm2_cl_sales_flat_quote_shipping_rate'
	     ),
  		array(
	         'name' => 'sales_flat_shipment',
	         'm2name' => 'sales_shipment',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_shipment'
	     ),
  		array(
	         'name' => 'sales_flat_shipment_grid',
	         'm2name' => 'sales_shipment_grid',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_shipment_grid'
	     ),
  		array(
	         'name' => 'sales_flat_shipment_item',
	         'm2name' => 'sales_shipment_item',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_shipment_item'
	     ),
  		array(
	         'name' => 'sales_flat_shipment_track',
	         'm2name' => 'sales_shipment_track',
	         'id' => 'entity_id',
	         'destination' => 'm2_cl_sales_flat_shipment_track'
	     ),
  		array(
	         'name' => 'sales_order_tax',
	         'id' => 'tax_id',
	         'destination' => 'm2_cl_sales_order_tax'
	     ),
  		array(
	         'name' => 'sales_order_tax_item',
	         'id' => 'tax_item_id',
	         'destination' => 'm2_cl_sales_order_tax_item'
	     ),
  		array(
	         'name' => 'wishlist',
	         'id' => 'wishlist_id',
	         'destination' => 'm2_cl_wishlist'
	     ),
  		array(
	         'name' => 'wishlist_item',
	         'id' => 'wishlist_item_id',
	         'destination' => 'm2_cl_wishlist_item'
	     ),
  		array(
	         'name' => 'wishlist_item_option',
	         'id' => 'option_id',
	         'destination' => 'm2_cl_wishlist_item_option'
	     )
        );

        return $tables;
    }
}
