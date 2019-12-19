<?php
/*
Plugin Name: MF Media
Plugin URI: https://github.com/frostkom/mf_media
Description: 
Version: 5.8.14
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_media
Domain Path: /lang

Depends: Meta Box, MF Base
GitHub Plugin URI: frostkom/mf_media
*/

include_once("include/classes.php");

$obj_media = new mf_media();

add_action('cron_base', 'activate_media', mt_rand(1, 10));
add_action('cron_base', array($obj_media, 'cron_base'), mt_rand(1, 10));

add_action('cron_sync', array($obj_media, 'cron_sync'));
add_filter('api_sync', array($obj_media, 'api_sync'), 10, 2);

add_action('init', array($obj_media, 'init'));
add_action('init', array($obj_media, 'init_callback'), 100);

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_media');
	register_uninstall_hook(__FILE__, 'uninstall_media');

	add_action('admin_init', array($obj_media, 'settings_media'));
	add_action('admin_init', array($obj_media, 'admin_init'), 0);

	add_action('admin_menu', array($obj_media, 'admin_menu'));
	add_filter('upload_mimes', array($obj_media, 'upload_mimes'));

	add_action('wp_handle_upload_prefilter', array($obj_media, 'wp_handle_upload_prefilter'));

	add_filter('hidden_meta_boxes', array($obj_media, 'hidden_meta_boxes'), 10, 2);
	add_action('rwmb_meta_boxes', array($obj_media, 'rwmb_meta_boxes'));

	add_action('restrict_manage_posts', array($obj_media, 'restrict_manage_posts'));
	add_action('pre_get_posts', array($obj_media, 'pre_get_posts'));

	add_filter('manage_media_columns', array($obj_media, 'column_header'), 5);
	add_action('manage_media_custom_column', array($obj_media, 'column_cell'), 5, 2);

	add_filter('manage_'.$obj_media->post_type_allowed.'_posts_columns', array($obj_media, 'column_header'), 5);
	add_action('manage_'.$obj_media->post_type_allowed.'_posts_custom_column', array($obj_media, 'column_cell'), 5, 2);

	add_filter('filter_on_category', array($obj_media, 'filter_on_category'), 10, 2);

	add_action('admin_footer', array($obj_media, 'print_media_templates'), 0);

	add_action('wp_ajax_query-attachments', array($obj_media, 'ajax_attachments'), 0);

	add_filter('attachment_fields_to_edit', array($obj_media, 'attachment_fields_to_edit'), 10, 2);
	add_action('attachment_fields_to_save', array($obj_media, 'attachment_fields_to_save'), null, 2);

	add_filter('count_shortcode_button', array($obj_media, 'count_shortcode_button'));
	add_filter('get_shortcode_output', array($obj_media, 'get_shortcode_output'));
}

add_filter('init_base_admin', array($obj_media, 'init_base_admin'));
add_filter('filter_is_file_used', array($obj_media, 'filter_is_file_used'));

add_shortcode('mf_media_category', array($obj_media, 'shortcode_media_category'));

load_plugin_textdomain('lang_media', false, dirname(plugin_basename(__FILE__)).'/lang/');

function activate_media()
{
	global $wpdb;

	require_plugin("meta-box/meta-box.php", "Meta Box");

	if(get_option('setting_media_activate_categories') == 'yes')
	{
		$default_charset = DB_CHARSET != '' ? DB_CHARSET : "utf8";

		$arr_add_index = array();

		$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."media2category (
			fileID INT UNSIGNED NOT NULL DEFAULT '0',
			categoryID INT UNSIGNED NOT NULL DEFAULT '0',
			KEY fileID (fileID),
			KEY categoryID (categoryID)
		) DEFAULT CHARSET=".$default_charset);

		$arr_add_index[$wpdb->prefix."media2category"] = array(
			'fileID' => "ALTER TABLE [table] ADD INDEX [column] ([column])",
			'categoryID' => "ALTER TABLE [table] ADD INDEX [column] ([column])",
		);

		$wpdb->query("CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."media2role (
			fileID INT UNSIGNED NOT NULL DEFAULT '0',
			roleKey VARCHAR(100) DEFAULT NULL,
			KEY fileID (fileID)
		) DEFAULT CHARSET=".$default_charset);

		$arr_add_index[$wpdb->prefix."media2role"] = array(
			'fileID' => "ALTER TABLE [table] ADD INDEX [column] ([column])",
		);

		add_index($arr_add_index);
	}

	replace_option(array('old' => 'setting_show_admin_menu', 'new' => 'setting_media_display_categories_in_menu'));
}

function uninstall_media()
{
	global $obj_media;

	mf_uninstall_plugin(array(
		'options' => array('setting_media_sanitize_files', 'setting_media_activate_categories', 'setting_media_activate_is_file_used', 'setting_media_display_categories_in_menu', 'setting_media_files2sync'),
		'meta' => array('meta_current_media_category'),
		'post_types' => array($obj_media->post_type_allowed),
		'tables' => array('media2category', 'media2role'),
	));
}