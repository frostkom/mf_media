<?php

if(!defined('ABSPATH'))
{
	header("Content-Type: application/json");

	$folder = str_replace("/wp-content/plugins/mf_media/include/api", "/", dirname(__FILE__));

	require_once($folder."wp-load.php");
}

if(function_exists('is_plugin_active') && is_plugin_active('mf_cache/index.php'))
{
	$obj_cache = new mf_cache();
	$obj_cache->fetch_request();
	$obj_cache->get_or_set_file_content(array('suffix' => 'json'));
}

$json_output = array(
	'success' => false,
);

$type = check_var('type', 'char');
$arr_input = explode("/", $type);

$type_action = $arr_input[0];
$type_action_type = isset($arr_input[1]) ? $arr_input[1] : '';
$type_class = isset($arr_input[2]) ? $arr_input[2] : '';

switch($type_action)
{
	case 'files2sync':
		$json_output['success'] = true;
		$json_output['files'] = array();

		$setting_media_files2sync = get_option('setting_media_files2sync', array());

		if(count($setting_media_files2sync) > 0)
		{
			list($upload_path, $upload_url) = get_uploads_folder('', false);

			foreach($setting_media_files2sync as $post_id)
			{
				$file_url = get_post_meta($post_id, '_wp_attached_file', true);
				$arr_file_url = explode("/", $file_url, 3);

				//$file_full_size_path = get_attached_file($post->ID);

				$json_output['files'][] = array(
					'name' => $arr_file_url[2],
					'title' => get_post_title($post_id),
					'image_alt' => get_post_meta($post_id, '_wp_attachment_image_alt', true),
					'url' => $upload_url.$file_url,
					'type' => mf_get_post_content($post_id, 'post_mime_type'),
					'modified' => mf_get_post_content($post_id, 'post_modified'),
				);
			}
		}
	break;
}

echo json_encode($json_output);