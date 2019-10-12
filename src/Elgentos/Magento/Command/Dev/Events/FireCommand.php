<?php

namespace Elgentos\Magento\Command\Dev\Events;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FireCommand extends AbstractMagentoCommand
{
    private static $staticEvents = array(
        'add_synchronize_message',
        'admin_permissions_role_prepare_save',
        'admin_session_user_login_failed',
        'admin_session_user_login_success',
        'admin_system_config_changed_section_currency',
        'admin_system_config_changed_section_currency_before_reinit',
        'admin_system_config_section_save_after',
        'admin_user_authenticate_after',
        'admin_user_authenticate_before',
        'adminhtml_block_eav_attribute_edit_form_init',
        'adminhtml_block_html_before',
        'adminhtml_block_html_before',
        'adminhtml_block_promo_widget_chooser_prepare_collection',
        'adminhtml_block_salesrule_actions_prepareform',
        'adminhtml_block_system_config_init_tab_sections_before',
        'adminhtml_cache_flush_all',
        'adminhtml_cache_flush_system',
        'adminhtml_cache_refresh_type',
        'adminhtml_catalog_category_edit_prepare_form',
        'adminhtml_catalog_category_tabs',
        'adminhtml_catalog_category_tree_can_add_root_category',
        'adminhtml_catalog_category_tree_can_add_sub_category',
        'adminhtml_catalog_category_tree_is_moveable',
        'adminhtml_catalog_product_attribute_edit_prepare_form',
        'adminhtml_catalog_product_attribute_set_main_html_before',
        'adminhtml_catalog_product_attribute_set_toolbar_main_html_before',
        'adminhtml_catalog_product_edit_element_types',
        'adminhtml_catalog_product_edit_element_types',
        'adminhtml_catalog_product_edit_prepare_form',
        'adminhtml_catalog_product_edit_prepare_form',
        'adminhtml_catalog_product_edit_tab_attributes_create_html_before',
        'adminhtml_catalog_product_form_prepare_excluded_field_list',
        'adminhtml_catalog_product_grid_prepare_massaction',
        'adminhtml_cms_page_edit_tab_content_prepare_form',
        'adminhtml_cms_page_edit_tab_design_prepare_form',
        'adminhtml_cms_page_edit_tab_main_prepare_form',
        'adminhtml_cms_page_edit_tab_meta_prepare_form',
        'adminhtml_cmspage_on_delete',
        'adminhtml_cmspage_on_delete',
        'adminhtml_controller_action_predispatch_start',
        'adminhtml_controller_catalogrule_prepare_save',
        'adminhtml_controller_salesrule_prepare_save',
        'adminhtml_customer_orders_add_action_renderer',
        'adminhtml_customer_prepare_save',
        'adminhtml_customer_save_after',
        'adminhtml_product_attribute_types',
        'adminhtml_product_attribute_types',
        'adminhtml_promo_catalog_edit_tab_main_prepare_form',
        'adminhtml_promo_quote_edit_tab_coupons_form_prepare_form',
        'adminhtml_promo_quote_edit_tab_main_prepare_form',
        'adminhtml_sales_order_create_process_data',
        'adminhtml_sales_order_create_process_data_before',
        'adminhtml_sales_order_creditmemo_register_before',
        'adminhtml_store_edit_form_prepare_form',
        'adminhtml_system_config_advanced_disableoutput_render_before',
        'adminhtml_widget_container_html_before',
        'adminhtml_widget_grid_filter_collection',
        'api_user_authenticated',
        'api_user_html_before',
        'application_clean_cache',
        'before_save_message_queue',
        'bundle_product_view_config',
        'catalog_block_product_list_collection',
        'catalog_category_change_products',
        'catalog_category_flat_loadnodes_before',
        'catalog_category_prepare_save',
        'catalog_category_tree_init_inactive_category_ids',
        'catalog_category_tree_init_inactive_category_ids',
        'catalog_category_tree_move_after',
        'catalog_category_tree_move_before',
        'catalog_category_tree_move_before',
        'catalog_controller_category_delete',
        'catalog_controller_category_init_after',
        'catalog_controller_category_init_before',
        'catalog_controller_product_delete',
        'catalog_controller_product_init',
        'catalog_controller_product_init_before',
        'catalog_controller_product_view',
        'catalog_helper_output_construct',
        'catalog_model_product_duplicate',
        'catalog_prepare_price_select',
        'catalog_prepare_price_select',
        'catalog_prepare_price_select',
        'catalog_product_attribute_update_before',
        'catalog_product_collection_apply_limitations_after',
        'catalog_product_collection_before_add_count_to_categories',
        'catalog_product_collection_load_after',
        'catalog_product_collection_load_before',
        'catalog_product_compare_add_product',
        'catalog_product_compare_item_collection_clear',
        'catalog_product_compare_remove_product',
        'catalog_product_edit_action',
        'catalog_product_edit_form_render_recurring',
        'catalog_product_flat_prepare_columns',
        'catalog_product_flat_prepare_indexes',
        'catalog_product_flat_rebuild',
        'catalog_product_flat_update_product',
        'catalog_product_gallery_prepare_layout',
        'catalog_product_gallery_upload_image_after',
        'catalog_product_get_final_price',
        'catalog_product_get_final_price',
        'catalog_product_get_final_price',
        'catalog_product_get_final_price',
        'catalog_product_import_finish_before',
        'catalog_product_is_salable_after',
        'catalog_product_is_salable_before',
        'catalog_product_media_add_image',
        'catalog_product_media_save_before',
        'catalog_product_new_action',
        'catalog_product_prepare_index_select',
        'catalog_product_prepare_index_select',
        'catalog_product_prepare_index_select',
        'catalog_product_prepare_save',
        'catalog_product_prepare_save',
        'catalog_product_status_update',
        'catalog_product_to_website_change',
        'catalog_product_type_configurable_price',
        'catalog_product_type_configurable_price',
        'catalog_product_type_grouped_price',
        'catalog_product_upsell',
        'catalog_product_view_config',
        'catalog_product_website_update',
        'catalog_product_website_update_before',
        'catalogindex_get_minimal_price',
        'catalogindex_plain_reindex_after',
        'catalogindex_prepare_price_select',
        'catalogindex_prepare_price_select',
        'catalogindex_prepare_price_select',
        'catalogindex_prepare_price_select',
        'catalogindex_prepare_price_select',
        'catalogindex_prepare_price_select',
        'catalogrule_after_apply',
        'catalogrule_before_apply',
        'catalogsearch_index_process_complete',
        'catalogsearch_index_process_start',
        'catalogsearch_reset_search_result',
        'category_move',
        'category_prepare_ajax_response',
        'catelogsearch_searchable_attributes_load_after',
        'checkout_allow_guest',
        'checkout_cart_add_product_complete',
        'checkout_cart_add_product_complete',
        'checkout_cart_product_add_after',
        'checkout_cart_product_update_after',
        'checkout_cart_save_after',
        'checkout_cart_save_before',
        'checkout_cart_update_item_complete',
        'checkout_cart_update_items_after',
        'checkout_cart_update_items_before',
        'checkout_controller_multishipping_shipping_post',
        'checkout_controller_onepage_save_shipping_method',
        'checkout_controller_onepage_save_shipping_method',
        'checkout_controller_onepage_save_shipping_method',
        'checkout_controller_onepage_save_shipping_method',
        'checkout_controller_onepage_save_shipping_method',
        'checkout_multishipping_controller_success_action',
        'checkout_multishipping_refund_all',
        'checkout_onepage_controller_success_action',
        'checkout_quote_destroy',
        'checkout_quote_init',
        'checkout_submit_all_after',
        'checkout_submit_all_after',
        'checkout_submit_all_after',
        'checkout_submit_all_after',
        'checkout_submit_all_after',
        'checkout_type_multishipping_create_orders_single',
        'checkout_type_multishipping_set_shipping_items',
        'checkout_type_onepage_save_order',
        'checkout_type_onepage_save_order_after',
        'checkout_type_onepage_save_order_after',
        'clean_catalog_images_cache_after',
        'clean_media_cache_after',
        'clear_expired_quotes_before',
        'cms_controller_router_match_before',
        'cms_page_get_available_statuses',
        'cms_page_prepare_save',
        'cms_page_render',
        'cms_wysiwyg_config_prepare',
        'cms_wysiwyg_images_static_urls_allowed',
        'controller_action_layout_generate_blocks_after',
        'controller_action_layout_generate_blocks_before',
        'controller_action_layout_generate_xml_before',
        'controller_action_layout_load_before',
        'controller_action_layout_render_before',
        'controller_action_nocookies',
        'controller_action_noroute',
        'controller_action_postdispatch',
        'controller_action_postdispatch_adminhtml',
        'controller_action_predispatch',
        'controller_front_init_before',
        'controller_front_init_routers',
        'controller_front_send_response_after',
        'controller_front_send_response_before',
        'controller_response_redirect',
        'core_block_abstract_prepare_layout_before',
        'core_block_abstract_to_html_after',
        'core_block_abstract_to_html_before',
        'core_clean_cache',
        'core_collection_abstract_load_after',
        'core_collection_abstract_load_before',
        'core_layout_block_create_after',
        'core_layout_update_updates_get_after',
        'core_locale_set_locale',
        'core_session_abstract_add_message',
        'core_session_abstract_clear_messages',
        'currency_display_options_forming',
        'custom_quote_process',
        'customer_address_format',
        'customer_customer_authenticated',
        'customer_login',
        'customer_logout',
        'customer_register_success',
        'customer_registration_is_allowed',
        'customer_session_init',
        'eav_collection_abstract_load_before',
        'enterprise_giftcardaccount_add',
        'gift_options_prepare_items',
        'google_checkout_discount_item_price',
        'googlecheckout_block_link_html_before',
        'googlecheckout_checkout_before',
        'googlecheckout_create_order_before',
        'googlecheckout_save_order_after',
        'http_response_send_before',
        'index_process_change_status',
        'load_customer_quote_before',
        'log_log_clean_after',
        'log_log_clean_before',
        'log_visitor_collection_load_before',
        'model_config_data_save_before',
        'model_delete_after',
        'model_delete_before',
        'model_delete_commit_after',
        'model_load_after',
        'model_load_before',
        'model_save_after',
        'model_save_before',
        'model_save_commit_after',
        'on_view_report',
        'order_cancel_after',
        'page_block_html_topmenu_gethtml_after',
        'page_block_html_topmenu_gethtml_before',
        'payment_form_block_to_html_before',
        'payment_info_block_prepare_specific_information',
        'payment_method_is_active',
        'paypal_prepare_line_items',
        'permissions_user_html_before',
        'persistent_session_expired',
        'persistent_session_expired',
        'poll_vote_add',
        'prepare_catalog_product_collection_prices',
        'prepare_catalog_product_collection_prices',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_index_select',
        'prepare_catalog_product_price_index_table',
        'prepare_catalog_product_price_index_table',
        'product_option_renderer_init',
        'resource_get_tablename',
        'review_controller_product_init',
        'review_controller_product_init',
        'review_controller_product_init_before',
        'review_controller_product_init_before',
        'review_review_collection_load_before',
        'rss_catalog_category_xml_callback',
        'rss_catalog_new_xml_callback',
        'rss_catalog_notify_stock_collection_select',
        'rss_catalog_review_collection_select',
        'rss_catalog_special_xml_callback',
        'rss_catalog_tagged_item_xml_callback',
        'rss_order_new_collection_select',
        'rss_wishlist_xml_callback',
        'rule_environment_collect',
        'sales_convert_order_item_to_quote_item',
        'sales_convert_order_to_quote',
        'sales_convert_order_to_quote',
        'sales_convert_quote_address_to_order',
        'sales_convert_quote_address_to_order_address',
        'sales_convert_quote_item_to_order_item',
        'sales_convert_quote_payment_to_order_payment',
        'sales_convert_quote_to_order',
        'sales_model_service_quote_submit_after',
        'sales_model_service_quote_submit_failure',
        'sales_model_service_quote_submit_success',
        'sales_order_creditmemo_cancel',
        'sales_order_creditmemo_refund',
        'sales_order_invoice_cancel',
        'sales_order_invoice_pay',
        'sales_order_invoice_register',
        'sales_order_item_cancel',
        'sales_order_payment_cancel',
        'sales_order_payment_cancel_creditmemo',
        'sales_order_payment_cancel_invoice',
        'sales_order_payment_capture',
        'sales_order_payment_pay',
        'sales_order_payment_place_end',
        'sales_order_payment_place_start',
        'sales_order_payment_refund',
        'sales_order_payment_void',
        'sales_order_place_before',
        'sales_quote_add_item',
        'sales_quote_address_discount_item',
        'sales_quote_address_discount_item',
        'sales_quote_address_discount_item',
        'sales_quote_address_discount_item',
        'sales_quote_config_get_product_attributes',
        'sales_quote_item_collection_products_after_load',
        'sales_quote_item_qty_set_after',
        'sales_quote_item_set_product',
        'sales_quote_product_add_after',
        'sales_quote_remove_item',
        'sales_sale_collection_query_before',
        'salesrule_rule_condition_combine',
        'salesrule_rule_get_coupon_types',
        'salesrule_validator_process',
        'sendfriend_product',
        'store_delete',
        'store_group_save',
        'tax_rate_data_fetch',
        'tax_settings_change_after',
        'tax_settings_change_after',
        'tax_settings_change_after',
        'tax_settings_change_after',
        'tax_settings_change_after',
        'visitor_init',
        'wishlist_add_item',
        'wishlist_add_product',
        'wishlist_add_product',
        'wishlist_item_collection_products_after_load',
        'wishlist_items_renewed',
        'wishlist_product_add_after',
        'wishlist_share',
        'wishlist_update_item',
        'Other....'
    );
    
    protected function configure()
    {
        $this
          ->setName('dev:events:fire')
          ->setDescription('Fire an event through Magento\'s event/observer system [elgentos]')
          ->addOption('event', 'e', InputOption::VALUE_REQUIRED, 'Which event do you want to run?', null)
          ->addOption('parameters', 'p', InputOption::VALUE_REQUIRED, 'Do you want to add parameters?', null)
      ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $dialog = $this->getHelper('dialog');
            
            $event = $input->getOption('event');
            if (!$event) {
                $eventIndex = $dialog->select(
                    $output,
                    'Select event to fire',
                    self::$staticEvents,
                    0
                );
                
                $chosenEvent = self::$staticEvents[$eventIndex];
                
                if ($chosenEvent == 'Other....') {
                    $event = $dialog->ask($output, '<question>Which event do you want to fire?</question> ', null);
                } else {
                    $event = $chosenEvent;
                }
            }
            
            $parameters = array();
            $parameterString = $input->getOption('parameters');
            if ($parameterString) {
                $parameterStringParts = explode(';', $parameterString);
                foreach ($parameterStringParts as $parameterStringPart) {
                    list($name, $value) = explode('::', $parameterStringPart);
                    $parameters[$name] = $value;
                }
            } else {
                $parameterQuestion = 'Do you want to add a parameter?';
                while ($next = $dialog->askConfirmation($output, '<question>' . $parameterQuestion . '</question> <comment>[y]</comment> ', true)) {
                    $parameterName = $dialog->ask($output, '<question>Parameter name: </question>');
                    $parameterValue = $dialog->ask($output, '<question>Parameter value: </question>');
                    $parameters[$parameterName] = $parameterValue;
                    $parameterQuestion = 'Do you want to add another parameter?';
                }
            }
            
            // Populate parameters with models
            if (count($parameters)) {
                foreach ($parameters as $name=>$value) {
                    if (stripos($value, ':')!==false) {
                        list($model, $id) = explode(':', $value);
                        $objectModel = \Mage::getModel($model);
                        if ($objectModel) {
                            $object = $objectModel->load($id);
                            if ($object->getId()) {
                                $parameters[$name] = $object;
                            }
                        }
                    }
                }
            }
            
            try {
                if ($event) {
                    if (count($parameters)) {
                        \Mage::dispatchEvent($event, $parameters);
                    } else {
                        \Mage::dispatchEvent($event);
                    }
                    if (count($parameters)) {
                        $output->writeln('<info>Event ' . $event . ' has been fired with parameters; </info>');
                        foreach ($parameters as $key=>$value) {
                            if (!is_object($value)) {
                                $output->writeln('<info> - ' . $key . ': ' . $value . '</info>');
                            } else {
                                $output->writeln('<info> - object ' . $key . ': ' . get_class($value) . ' ID ' . $value->getId() . '</info>');
                            }
                        }
                    } else {
                        $output->writeln('<info>Event ' . $event . ' has been fired</info>');
                    }
                }
            } catch (Exception $e) {
                echo 'Error; ' . $e->getMessage() . PHP_EOL;
            }
        }
    }
}
