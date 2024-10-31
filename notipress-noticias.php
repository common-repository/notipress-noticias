<?php
/**
 * @package ntpmx
 */
/*
Plugin Name: NotiPress Noticias
Plugin URI: https://notipress.mx/ntpmx
Description: Con sede en Ciudad de México, la agencia de noticias NotiPress genera contenidos informativos de política, negocios, tecnología, economía, internacionales y más. También cuenta con un banco fotográfico de uso para los medios de comunicación.
Version: 1.4.5
Author: NotiPress
Author URI: https://notipress.mx
License: GPLv2 or later
Text Domain: ntpmx
*/


if (!defined('ABSPATH')) exit;

define('NTPMX_VERSION','1.4.5');
define('NTPMX_MINIMUM_WP_VERSION','5.0');
define('NTPMX_PLUGIN_DIR',plugin_dir_path( __FILE__ ));


if (is_admin()){
    require_once(NTPMX_PLUGIN_DIR . 'inc/ntpmx.class.php');
    $ntp = new ntpmx_api();
    register_activation_hook(__FILE__,[$ntp,'ntpmx_activation']);
    $ntp->init();
    $plugin = plugin_basename(__FILE__);
    add_filter("plugin_action_links_$plugin", 'ntpmx_settings_page');

}
function ntpmx_settings_page($links){
    $url = esc_url( add_query_arg('page','ntpmx_settings',get_admin_url() . 'admin.php'));
    $settings_link = "<a href='$url'>Configuración</a>";
    array_push($links,$settings_link);
    return $links;
}