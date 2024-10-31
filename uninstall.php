<?php

delete_option('ntpmx_product');
delete_option('ntpmx_api');
delete_option('ntpmx_version');
delete_option('ntpmx_date_install');
delete_option('ntpmx_date_update');
delete_option('ntpmx_date_activation');
delete_option('ntpmx_minutes');
delete_option('ntpmx_show_rows');
delete_option('ntpmx_last_check');
delete_option('ntpmx_new_content');
delete_option('ntpmx_rich_text');
delete_option('ntpmx_links');
delete_option('ntpmx_source');
delete_option('ntpmx_customer_id');
delete_option('ntpmx_sku');
delete_option('ntpmx_sku_name');


global $wpdb;
$table_name = $wpdb->prefix."ntpmx_contents";
$sql = "DROP TABLE IF EXISTS " . $table_name . ";";
$wpdb->query($sql);
wp_clear_scheduled_hook( 'ntpmx_cron' );

