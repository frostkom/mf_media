<?php
/*
Plugin Name: MF Media
Plugin URI: https://github.com/frostkom/mf_media
Description: 
Version: 5.6.6
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_media
Domain Path: /lang

Depends: Meta Box, MF Base
GitHub Plugin URI: frostkom/mf_media
*/

include_once("include/classes.php");
include_once("include/functions.php");

$obj_media = new mf_media();

add_action('cron_base', 'activate_media', mt_rand(1, 10));

add_action('init', 'init_media');
add_action('init', 'init_callback_media', 100);

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_media');
	register_uninstall_hook(__FILE__, 'uninstall_media');

	add_action('admin_init', 'settings_media');
	add_action('admin_init', array($obj_media, 'admin_init'), 0);

	add_action('admin_menu', 'menu_media');
	add_filter('upload_mimes', 'upload_mimes_media');

	add_action('wp_handle_upload_prefilter', array($obj_media, 'upload_filter'));

	add_action('rwmb_meta_boxes', 'meta_boxes_media');

	add_filter('manage_mf_media_allowed_posts_columns', 'column_header_media_allowed', 5);
	add_action('manage_mf_media_allowed_posts_custom_column', 'column_cell_media_allowed', 5, 2);

	add_filter('filter_on_category', 'filter_on_category_media');

	add_action('admin_footer', array($obj_media, 'print_media_templates'), 0);

	add_action('wp_ajax_query-attachments', 'ajax_attachments_media', 0);
	//add_action('admin_enqueue_scripts', 'enqueue_scripts_media');

	add_filter('attachment_fields_to_edit', 'attachment_edit_media', 10, 2);
	add_action('attachment_fields_to_save', 'attachment_save_media', null, 2);

	add_filter('manage_media_columns', 'column_header_media', 5);
	add_action('manage_media_custom_column', 'column_cell_media', 5, 2);

	load_plugin_textdomain('lang_media', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

function activate_media()
{
	global $wpdb;

	if(get_option('setting_media_activate_categories') == 'yes')
	{
		require_plugin("meta-box/meta-box.php", "Meta Box");

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

		/* This is not needed anymore since Theme Core handles this */
		/*$arr_data = array();
		get_post_children(array('post_type' => 'mf_media_allowed'), $arr_data);

		if(count($arr_data) == 0)
		{
			$obj_media = new mf_media();

			$post_data = array(
				'post_type' => 'mf_media_allowed',
				'post_status' => 'publish',
				'post_title' => __("Default", 'lang_media'),
			);

			$post_id = wp_insert_post($post_data);

			if($post_id > 0)
			{
				update_post_meta($post_id, $obj_media->meta_prefix.'action', 'allow');

				$arr_mimes = array('svg', 'eot', 'ttf', 'woff');

				foreach($arr_mimes as $mime)
				{
					//update_post_meta() the Meta Box way
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->postmeta." SET post_id = '%d', meta_key = %s, meta_value = %s", $post_id, $obj_media->meta_prefix.'types', $mime));
				}
			}
		}*/

		//Migrate from option to DB
		/*if(IS_ADMIN)
		{
			$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'attachment'");

			foreach($result as $r)
			{
				$arr_categories = get_post_meta($r->ID, 'mf_mc_category', false);

				foreach($arr_categories as $key => $value)
				{
					$wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2category WHERE fileID = '%d' AND categoryID = '%d' LIMIT 0, 1", $r->ID, $value));

					if($wpdb->num_rows == 0)
					{
						$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."media2category SET fileID = '%d', categoryID = '%d'", $r->ID, $value));
					}
				}
			}
		}*/

		replace_user_meta(array('old' => 'mf_mc_current_media_category', 'new' => 'meta_current_media_category'));
	}
}

function uninstall_media()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_show_admin_menu'),
		'meta' => array('meta_current_media_category'),
		'post_types' => array('mf_media_allowed'),
		'tables' => array('media2category', 'media2role'),
	));
}