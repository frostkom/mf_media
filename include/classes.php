<?php

class mf_media
{
	var $categories = array();
	var $default_tab = 0;
	var $post_type_allowed = 'mf_media_allowed';
	var $meta_prefix = 'mf_media_';
	var $check_if_file_is_used_start = false;
	var $check_if_file_is_used_logged = false;

	function __construct(){}

	function get_media_roles($post_id)
	{
		global $wpdb;

		$array = array();

		$result = $wpdb->get_results($wpdb->prepare("SELECT roleKey FROM ".$wpdb->prefix."media2role WHERE fileID = '%d'", $post_id));

		foreach($result as $r)
		{
			$array[] = $r->roleKey;
		}

		return $array;
	}

	function get_media_categories($post_id)
	{
		global $wpdb;

		$array = array();

		$result = $wpdb->get_results($wpdb->prepare("SELECT categoryID FROM ".$wpdb->prefix."media2category WHERE fileID = '%d'", $post_id));

		foreach($result as $r)
		{
			$array[] = $r->categoryID;
		}

		return $array;
	}

	function get_taxonomy($data)
	{
		global $wpdb;

		if(!isset($data['parent'])){	$data['parent'] = 0;}

		$result = $wpdb->get_results($wpdb->prepare("SELECT term_id, name FROM ".$wpdb->terms." INNER JOIN ".$wpdb->term_taxonomy." USING (term_id) WHERE taxonomy = %s AND parent = '%d' ORDER BY name ASC", $data['taxonomy'], $data['parent']));

		return $result;
	}

	function update_count_callback_media_category_media()
	{
		global $wpdb;

		$taxonomy = 'category';

		$result = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id, MAX(total) AS total FROM ((
			SELECT tt.term_taxonomy_id, COUNT(*) AS total FROM ".$wpdb->term_relationships." tr, ".$wpdb->term_taxonomy." tt WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s GROUP BY tt.term_taxonomy_id
		) UNION ALL (
			SELECT term_taxonomy_id, 0 AS total FROM ".$wpdb->term_taxonomy." WHERE taxonomy = %s
		)) AS unioncount GROUP BY term_taxonomy_id", $taxonomy, $taxonomy));

		// update all count values from taxonomy
		foreach($result as $r)
		{
			$intCategoryID = $r->term_taxonomy_id;

			$tax_count = $r->total;

			if(get_option('setting_media_activate_categories') == 'yes')
			{
				$tax_count += $wpdb->get_var($wpdb->prepare("SELECT COUNT(categoryID) FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $intCategoryID));
			}

			$wpdb->update($wpdb->term_taxonomy, array('count' => $tax_count), array('term_taxonomy_id' => $intCategoryID));
		}
	}

	function check_if_file_is_used($post_id)
	{
		if($this->check_if_file_is_used_start == false)
		{
			$this->check_if_file_is_used_start = date("Y-m-d H:i:s");
		}

		list($upload_path, $upload_url) = get_uploads_folder();

		$is_used = false;

		$file_path = str_replace(get_site_url(), "", wp_get_attachment_url($post_id));
		$file_path = str_replace(array("http://", "https://"), "", $file_path);
		$file_path = str_replace(str_replace(array("http://", "https://"), "", $upload_url), "", $file_path);

		$file_thumb_path = $file_path = str_replace("/wp-content/uploads/", "", $file_path);

		if(wp_attachment_is_image($post_id))
		{
			$file_name_temp = $file_name_orig = basename($file_path);

			if(substr_count($file_name_orig, ".") > 1)
			{
				$file_name_temp = "";

				$arr_file_name = explode(".", $file_name_orig);
				$count_temp = count($arr_file_name);

				for($i = 0; $i < $count_temp; $i++)
				{
					if($file_name_temp != '')
					{
						if($i == ($count_temp - 1))
						{
							$file_name_temp .= "%.";
						}

						else
						{
							$file_name_temp .= ".";
						}
					}

					$file_name_temp .= $arr_file_name[$i];
				}

				//do_log("There were several dots in the filename (".$file_name_orig." -> ".$file_name_temp.")");
			}

			else
			{
				$file_name_temp = str_replace(".", "%.", $file_name_orig);
			}

			$file_thumb_path = str_replace($file_name_orig, $file_name_temp, $file_path);
		}

		$arr_used = array(
			'id' => $post_id,
			'file_url' => $file_path,
			'file_thumb_url' => $file_thumb_path,
			'amount' => 0,
			'example' => '',
		);

		$arr_used = apply_filters('filter_is_file_used', $arr_used);

		if($arr_used['amount'] > 0)
		{
			update_post_meta($post_id, $this->meta_prefix.'used_amount', $arr_used['amount']);

			$is_used = true;
		}

		else
		{
			delete_post_meta($post_id, $this->meta_prefix.'used_amount');

			$post = get_post($post_id);
			$post_date = date("Y-m-d", strtotime($post->post_date));
			$post_date_limit = date("Y-m-d", strtotime("-5 year"));

			if($this->check_if_file_is_used_logged == false && $post_date < $post_date_limit)
			{
				$post_title = get_post_title($post_id);

				do_log(sprintf("%sThe file%s (%s) is not in use and is old (%s)", "<a href='".admin_url("upload.php?mode=list&s=".$post_title)."'>", "</a>", "<a href='".admin_url("post.php?post=".$post_id."&action=edit")."'>".($post_title != '' ? $post_title : "<em>".__("Unknown", 'lang_media')."</em>")." <i class='fa fa-wrench'></i></a>", $post_date));

				$this->check_if_file_is_used_logged = true;
			}
		}

		update_post_meta($post_id, $this->meta_prefix.'used_example', $arr_used['example']);
		update_post_meta($post_id, $this->meta_prefix.'used_updated', date("Y-m-d H:i:s"));

		return $is_used;
	}

	function cron_base()
	{
		global $wpdb;

		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			/* Look for duplicates */
			#######################################
			$result = $wpdb->get_results($wpdb->prepare("SELECT post_title, COUNT(post_title) AS post_title_count FROM ".$wpdb->posts." INNER JOIN ".$wpdb->postmeta." ON ".$wpdb->posts.".ID = ".$wpdb->postmeta.".post_id AND meta_key = %s WHERE post_type = %s AND post_status != %s AND post_title != '' GROUP BY post_title ORDER BY post_title_count DESC LIMIT 0, 1", '_wp_attachment_metadata', 'attachment', 'trash'));

			foreach($result as $r)
			{
				$post_title = $r->post_title;
				$post_title_count = $r->post_title_count;

				$result_files = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." INNER JOIN ".$wpdb->postmeta." ON ".$wpdb->posts.".ID = ".$wpdb->postmeta.".post_id AND meta_key = %s WHERE post_type = %s AND post_status != %s AND post_title = %s LIMIT 0, 5", '_wp_attachment_metadata', 'attachment', 'trash', $post_title));

				$arr_attachment_metadata_temp = array(
					'height' => 0,
					'width' => 0,
					'filesize' => 0,
				);

				foreach($result_files as $r)
				{
					$post_id = $r->ID;

					//$arr_attachment_metadata = get_post_meta($post_id, '_wp_attachment_metadata', true);
					$arr_attachment_metadata = wp_get_attachment_metadata($post_id);

					$arr_attachment_metadata['id'] = $post_id;

					if(!isset($arr_attachment_metadata['height']))
					{
						$arr_attachment_metadata['height'] = 0;
					}

					if(!isset($arr_attachment_metadata['width']))
					{
						$arr_attachment_metadata['width'] = 0;
					}

					if(!isset($arr_attachment_metadata['filesize']))
					{
						$file = get_attached_file($post_id);

						if(file_exists($file))
						{
							$arr_attachment_metadata['filesize'] = filesize($file);
						}

						else
						{
							$arr_attachment_metadata['filesize'] = 0;
						}
					}

					//do_log(sprintf("%d had the value %s", $post_id, str_replace(array("\n", "\r"), "", var_export($arr_attachment_metadata, true))));

					if($arr_attachment_metadata['height'] == $arr_attachment_metadata_temp['height'] && $arr_attachment_metadata['width'] == $arr_attachment_metadata_temp['width'] && $arr_attachment_metadata['filesize'] == $arr_attachment_metadata_temp['filesize'])
					{
						do_log("<a href='".admin_url("upload.php?mode=list&s=".$post_title)."'>".sprintf("There were multiple files called %s with the same proportions %s and size %s", $post_title, $arr_attachment_metadata['height']."x".$arr_attachment_metadata['width'], size_format($arr_attachment_metadata['filesize']))."</a> (<a href='".admin_url("post.php?post=".$arr_attachment_metadata['id']."&action=edit")."'>#".$arr_attachment_metadata['id']."</a> & <a href='".admin_url("post.php?post=".$arr_attachment_metadata_temp['id']."&action=edit")."'>".$arr_attachment_metadata_temp['id']."</a>)");

						break;
					}

					$arr_attachment_metadata_temp = $arr_attachment_metadata;
				}

				//do_log(sprintf("There are %d files called %s", $post_title_count, $post_title));
			}
			#######################################

			/* Check which files are used */
			#######################################
			if(get_site_option('setting_media_activate_is_file_used') == 'yes')
			{
				$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." LEFT JOIN ".$wpdb->postmeta." ON ".$wpdb->posts.".ID = ".$wpdb->postmeta.".post_id AND meta_key = %s WHERE post_type = %s AND (meta_value IS null OR meta_value < DATE_SUB(NOW(), INTERVAL 1 WEEK)) ORDER BY meta_value ASC LIMIT 0, 20", $this->meta_prefix.'used_updated', 'attachment'));

				foreach($result as $r)
				{
					$post_id = $r->ID;

					$this->check_if_file_is_used($post_id);
				}
			}

			else
			{
				delete_post_meta_by_key($this->meta_prefix.'used_amount');
				delete_post_meta_by_key($this->meta_prefix.'used_example');
				delete_post_meta_by_key($this->meta_prefix.'used_updated');
			}
			#######################################

			/* Look for special characters in file names */
			#######################################
			/*if(get_site_option('setting_media_sanitize_files') == 'yes')
			{
				$debug_mode = false;

				list($upload_path, $upload_url) = get_uploads_folder();
				$upload_url = $upload_url; //get_site_url().

				$arr_exclude = $arr_include = array();
				//$arr_exclude[] = "é";	$arr_include[] = "e"; // Does not work
				$arr_exclude[] = "Ã©";	$arr_include[] = "e";
				$arr_exclude[] = "Ã¨";	$arr_include[] = "e";
				//$arr_exclude[] = "ê";	$arr_include[] = "e"; // Does not work
				$arr_exclude[] = "Ã«";	$arr_include[] = "e";
				//$arr_exclude[] = "ü";	$arr_include[] = "u"; // Does not work
				$arr_exclude[] = "Ã¼";	$arr_include[] = "u";
				$arr_exclude[] = "å";	$arr_include[] = "a";
				$arr_exclude[] = "Ã¥";	$arr_include[] = "a";
				$arr_exclude[] = "Å";	$arr_include[] = "A";
				$arr_exclude[] = "Ã…";	$arr_include[] = "A";
				$arr_exclude[] = "ä";	$arr_include[] = "a";
				$arr_exclude[] = "Ã¤";	$arr_include[] = "a";
				$arr_exclude[] = "Ä";	$arr_include[] = "A";
				$arr_exclude[] = "Ã„";	$arr_include[] = "A";
				$arr_exclude[] = "ö";	$arr_include[] = "o";
				$arr_exclude[] = "Ã¶";	$arr_include[] = "o";
				$arr_exclude[] = "Ö";	$arr_include[] = "O";
				$arr_exclude[] = "Ã–";	$arr_include[] = "O";
				//$arr_exclude[] = "´";	$arr_include[] = ""; // Does not work
				//$arr_exclude[] = "Â´";	$arr_include[] = ""; // Does not work

				$query_where = "";

				$count_temp = count($arr_exclude);

				$query_where .= " AND (";

					for($i = 0; $i < $count_temp; $i++)
					{
						$query_where .= ($i > 0 ? " OR " : "")."guid LIKE '%".utf8_encode($arr_exclude[$i])."%'";
					}

				$query_where .= ")";

				$query_select = "SELECT ID, post_title, guid FROM ".$wpdb->posts." WHERE post_type = 'attachment'".$query_where; //." ORDER BY ID ASC LIMIT 0, 5"
				$result = $wpdb->get_results($query_select);

				foreach($result as $r)
				{
					$post_id = $r->ID;
					$post_title = $r->post_title;
					$post_guid = $r->guid;

					//$file_url = get_permalink($post_id);
					$file_url = wp_get_attachment_url($post_id);
					//$file_path = str_replace(array("http://", "https://"), "", $file_url);
					$file_path_old = str_replace($upload_url, $upload_path, $file_url);
					$file_path_new = str_replace($arr_exclude, $arr_include, $file_path_old);

					// Change GUID for attachments
					############################
					$query_update = $wpdb->prepare("UPDATE ".$wpdb->posts." SET guid = %s WHERE ID = '%d'", str_replace($arr_exclude, $arr_include, $post_guid), $post_id); // AND guid = %s //, $post_guid

					if($debug_mode)
					{
						do_log("Replace GUID: ".$query_update); //".$query_select." -> 
					}

					else
					{
						$wpdb->query($query_update);

						if($wpdb->rows_affected > 0)
						{
							// Success
						}

						else
						{
							do_log("Replace GUID did NOT work: ".$query_update);
						}
					}
					############################

					// Change URL in posts etc.
					// Replace $arr_exclude[$i] -> $arr_include[$i] in all tables used in filter_is_file_used()
					############################
					$query_update = $wpdb->prepare("UPDATE ".$wpdb->postmeta." SET meta_value = %s WHERE post_id = '%d'", str_replace($arr_exclude, $arr_include, $post_guid), $post_id); // AND meta_value = %s //, $post_guid

					if($debug_mode)
					{
						do_log("Replace meta value: ".$query_update); //".$query_select." -> 
					}

					else
					{
						$wpdb->query($query_update);

						if($wpdb->rows_affected > 0)
						{
							// Success
						}

						else
						{
							do_log("Replace meta value did NOT work: ".$query_update);
						}
					}
					############################

					// Change file names
					############################
					if($debug_mode)
					{
						do_log("Replace file name: ".$file_path_old." -> ".$file_path_new); //".$file_url." -> ".$upload_url." -> ".$upload_path." -> 
					}

					else
					{
						if(file_exists($file_path_old) && copy($file_path_old, $file_path_new))
						{
							// Success
						}

						else
						{
							do_log("Replace file name did NOT work: ".$file_path_old." -> ".$file_path_new); //".$file_url." -> ".$upload_url." -> ".$upload_path." -> 
						}
					}
					############################
				}
			}*/
			#######################################
		}

		$obj_cron->end();
	}

	function cron_sync($json)
	{
		global $wpdb;

		if(count($json['files']) > 0)
		{
			//do_log("Media -> cron_sync: ".var_export($json['files'], true));

			list($upload_path, $upload_url) = get_uploads_folder('', false);
			$file_base_path = $upload_path.date("Y")."/".date("m")."/";

			foreach($json['files'] as $file)
			{
				list($content, $headers) = get_url_content(array(
					'url' => $file['url'],
					'catch_head' => true,
				));

				$log_message = "The file could not be found (".$file['url'].")";

				switch($headers['http_code'])
				{
					case 200:
						$content_md5 = md5($content);

						$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." INNER JOIN ".$wpdb->postmeta." ON ".$wpdb->posts.".ID = ".$wpdb->postmeta.".post_id WHERE post_type = %s AND post_title = %s AND ".$wpdb->postmeta.".meta_key = %s", 'attachment', $file['name'], $this->meta_prefix.'synced_file'));
						$already_exists = ($post_id > 0);
						$file_content_updated = false;

						$file_path = $file_base_path.$file['name'];

						if($already_exists)
						{
							$synced_file = update_post_meta($post_id, $this->meta_prefix.'synced_file', true);

							if($synced_file != $content_md5)
							{
								$file_content_updated = true;
							}
						}

						else
						{
							$file_content_updated = true;
						}

						if($file_content_updated)
						{
							$savefile = fopen($file_path, 'w');
							fwrite($savefile, $content);
							fclose($savefile);
						}

						if($already_exists)
						{
							$post_data = array(
								'ID' => $post_id,
								'post_modified' => $file['modified'],
								'meta_input' => array(
									'_wp_attachment_image_alt' => $file['image_alt'],
								),
							);

							wp_update_post($post_data);
						}

						else
						{
							$post_data = array(
								'post_mime_type' => $file['type'],
								'post_title' => $file['name'],
								'post_content' => '',
								'post_status' => 'inherit',
								'post_modified' => $file['modified'],
								'meta_input' => array(
									'_wp_attachment_image_alt' => $file['image_alt'],
								),
							);

							$post_id = wp_insert_attachment($post_data, $file_path);
						}

						if($file_content_updated)
						{
							$file_full_size_path = get_attached_file($post_id);

							$attach_data = wp_generate_attachment_metadata($post_id, $file_full_size_path);
							wp_update_attachment_metadata($post_id, $attach_data);

							update_post_meta($post_id, $this->meta_prefix.'synced_file', $content_md5);
						}

						do_log($log_message, 'trash');
					break;

					default:
						do_log($log_message);
					break;
				}
			}
		}
	}

	function api_sync($json_output, $data = array())
	{
		$json_output['files'] = array();

		$setting_media_files2sync = get_option_or_default('setting_media_files2sync', array());

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

		return $json_output;
	}

	function init()
	{
		load_plugin_textdomain('lang_media', false, str_replace("/include", "", dirname(plugin_basename(__FILE__)))."/lang/");

		$labels = array(
			'name' => _x(__("Types", 'lang_media'), 'post type general name'),
			'singular_name' => _x(__("Type", 'lang_media'), 'post type singular name'),
			'menu_name' => __("Allowed Types", 'lang_media'),
		);

		$args = array(
			'labels' => $labels,
			'public' => false, // Previously true but changed to hide in sitemap.xml
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_nav_menus' => false,
			'exclude_from_search' => true,
			'supports' => array('title'),
			'hierarchical' => false,
			'has_archive' => false,
		);

		register_post_type($this->post_type_allowed, $args);

		register_taxonomy_for_object_type('category', 'attachment');
	}

	function init_callback()
	{
		global $wp_taxonomies;

		$taxonomy = 'category';

		if(!taxonomy_exists($taxonomy))
		{
			return false;
		}

		$new_arg = &$wp_taxonomies[$taxonomy]->update_count_callback;
		$new_arg = array($this, 'update_count_callback_media_category_media');
	}

	function settings_media()
	{
		$options_area = __FUNCTION__;

		add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

		$arr_settings = array();

		if(IS_SUPER_ADMIN)
		{
			$arr_settings['setting_media_sanitize_files'] = __("Sanitize Filenames", 'lang_media');
		}

		$arr_settings['setting_media_activate_categories'] = __("Activate Categories", 'lang_media');

		if(IS_SUPER_ADMIN)
		{
			$arr_settings['setting_media_activate_is_file_used'] = __("Activate Is File Used", 'lang_media');
		}

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			$arr_settings['setting_media_display_categories_in_menu'] = __("Display Categories in Menu", 'lang_media');
		}

		$arr_settings['setting_media_resize_original_image'] = __("Resize Original Image", 'lang_media');

		$option_sync_sites = get_option('option_sync_sites', array());

		if(count($option_sync_sites) > 0)
		{
			$arr_settings['setting_media_files2sync'] = __("Files to Sync", 'lang_media');
		}

		show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
	}

	function settings_media_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Media", 'lang_media'));
	}

	function setting_media_sanitize_files_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key, 'yes'));

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => __("This will remove special characters from file names and URLs", 'lang_media')));
	}

	function setting_media_activate_categories_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => __("This will add the possibility to connect categories and restrict roles to every file in the Media Library", 'lang_media')));
	}

	function setting_media_activate_is_file_used_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key, 'no'));

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => __("This will add an extra column in the Media Library to check which files are being used and where", 'lang_media')));

		if($option == 'no')
		{
			delete_post_meta_by_key($this->meta_prefix.'used_amount');
			delete_post_meta_by_key($this->meta_prefix.'used_example');
			delete_post_meta_by_key($this->meta_prefix.'used_updated');
		}
	}

	function setting_media_display_categories_in_menu_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function setting_media_resize_original_image_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'yes');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => __("This will remove the original image if it is larger than the largest resized size", 'lang_media')));
	}

	function setting_media_files2sync_callback()
	{
		global $wpdb;

		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		//echo get_media_library(array('name' => $setting_key, 'value' => $option, 'multiple' => true, 'description' => __("These files will be downloaded and/or updated to child sites, if there are any", 'lang_media')));

		$arr_data = array();
		get_post_children(array('add_choose_here' => false, 'post_type' => 'attachment'), $arr_data);

		echo show_select(array('data' => $arr_data, 'name' => $setting_key."[]", 'value' => $option, 'description' => __("These files will be downloaded and/or updated to child sites, if there are any", 'lang_media')));
	}

	function admin_init()
	{
		global $pagenow;

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			$do_enqueue = false;

			switch($pagenow)
			{
				case 'upload.php':
					$do_enqueue = true;
				break;

				case 'admin.php':
					if(substr(check_var('page'), 0, 9) == 'int_page_')
					{
						$do_enqueue = true;
					}
				break;

				case 'post.php':
					$post_id = check_var('post');

					if($post_id > 0)
					{
						if(get_post_type($post_id) == 'attachment')
						{
							$do_enqueue = true;
						}
					}
				break;
			}

			if($do_enqueue == true)
			{
				$plugin_include_url = plugin_dir_url(__FILE__);
				$plugin_version = get_plugin_version(__FILE__);

				mf_enqueue_style('style_media', $plugin_include_url."style.css", $plugin_version);
				mf_enqueue_style('style_media_wp', $plugin_include_url."style_wp.css", $plugin_version);

				/*$taxonomy = 'category';

				$this->get_categories();

				$attachment_terms = $this->get_categories_options();

				$current_media_category = get_user_meta(get_current_user_id(), 'meta_current_media_category', true);

				mf_enqueue_script('script_media_taxonomy', $plugin_include_url."script_taxonomy.js", array(
					'taxonomy' => $taxonomy,
					'list_title' => "-- "." --",
					'term_list' => "[".$attachment_terms."]",
					'terms_test' => get_terms($taxonomy, array('hide_empty' => false)),
					'current_media_category' => $current_media_category
				), $plugin_version);*/
			}

			else if($pagenow == 'admin.php' && check_var('page') == 'mf_media/list/index.php')
			{
				$plugin_base_include_url = plugins_url()."/mf_base/include";
				$plugin_version = get_plugin_version(__FILE__);

				mf_enqueue_script('script_base_settings', $plugin_base_include_url."/script_settings.js", array('default_tab' => $this->default_tab, 'settings_page' => false), $plugin_version);
			}
		}
	}

	function admin_menu()
	{
		$menu_root = 'mf_media/';
		$menu_start = $menu_root.'list/index.php';
		$menu_capability = 'read';

		if(get_option('setting_media_display_categories_in_menu') == 'yes')
		{
			$menu_title = __("Files", 'lang_media');

			add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-admin-media', 11);

			if(IS_EDITOR)
			{
				$menu_title = __("Settings", 'lang_media');
				add_submenu_page($menu_start, $menu_title, $menu_title, $menu_capability, admin_url("options-general.php?page=settings_mf_base#settings_media"));
			}
		}

		if(IS_ADMINISTRATOR)
		{
			$menu_title = __("Allowed Types", 'lang_media');

			add_submenu_page("upload.php", $menu_title, $menu_title, $menu_capability, "edit.php?post_type=".$this->post_type_allowed);
		}
	}

	function upload_mimes($existing_mimes = array())
	{
		global $wpdb;

		$arr_types = $this->get_media_types(array('type' => 'mime'));

		$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s", $this->post_type_allowed, 'publish'));

		foreach($result as $r)
		{
			$post_id = $r->ID;

			$post_role = get_post_meta($post_id, $this->meta_prefix.'role', false);
			$post_role_count = count($post_role);

			if($post_role_count == 0 || in_array(get_current_user_role(), $post_role))
			{
				$post_action = get_post_meta($post_id, $this->meta_prefix.'action', true);
				$post_types = get_post_meta($post_id, $this->meta_prefix.'types', false);

				switch($post_action)
				{
					case 'allow':
						if(count($post_types) > 0)
						{
							foreach($post_types as $post_type)
							{
								$existing_mimes[$post_type] = $arr_types[$post_type];
							}
						}

						else if($post_role_count == 1 && in_array('administrator', $post_role))
						{
							if(!defined('ALLOW_UNFILTERED_UPLOADS'))
							{
								define('ALLOW_UNFILTERED_UPLOADS', true);
							}
						}
					break;

					case 'allow_only_these':
						$existing_mimes = array();

						foreach($post_types as $post_type)
						{
							$existing_mimes[$post_type] = $arr_types[$post_type];
						}
					break;

					case 'disallow':
						foreach($post_types as $post_type)
						{
							unset($existing_mimes[$post_type]);
						}
					break;
				}
			}
		}

		return $existing_mimes;
	}

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_media'] = array(
			/*'setting_media_sanitize_files' => array(
				'type' => 'bool',
				'global' => true,
				'icon' => "fas fa-broom",
				'name' => __("Media", 'lang_media')." - ".__("Sanitize Filenames", 'lang_media'),
			),*/
			'setting_media_activate_categories' => array(
				'type' => 'bool',
				'global' => false,
				'icon' => "fas fa-network-wired",
				'name' => __("Media", 'lang_media')." - ".__("Activate Categories", 'lang_media'),
			),
			/*'setting_media_activate_is_file_used' => array(
				'type' => 'bool',
				'global' => true,
				'icon' => "fas fa-search",
				'name' => __("Media", 'lang_media')." - ".__("Activate Is File Used", 'lang_media'),
			),*/
			'setting_media_display_categories_in_menu' => array(
				'type' => 'bool',
				'global' => false,
				'icon' => "fas fa-bars",
				'name' => __("Media", 'lang_media')." - ".__("Display Categories in Menu", 'lang_media'),
			),
			'setting_media_files2sync' => array(
				'type' => 'posts',
				'global' => false,
				'icon' => "fas fa-sync",
				'name' => __("Media", 'lang_media')." - ".__("Files to Sync", 'lang_media'),
			),
		);

		return $arr_settings;
	}

	function wp_handle_upload_prefilter($file)
	{
		if(get_site_option('setting_media_sanitize_files') == 'yes')
		{
			$file_suffix = get_file_suffix($file['name']);

			$file['name'] = sanitize_title(preg_replace("/.".$file_suffix."$/", '', $file['name']));

			if(strlen($file['name']) > 95)
			{
				$file['name'] = substr($file['name'], 0, 95);
			}

			$file['name'] .= ".".$file_suffix;
		}

		return $file;
	}

	function wp_generate_attachment_metadata($image_data) 
	{
		if(get_option('setting_media_resize_original_image', 'yes') == 'yes')
		{
			if(isset($image_data['sizes']['2048x2048']))
			{
				$image_size = '2048x2048';
			}

			else
			{
				$image_size = 'large';
			}

			if(isset($image_data['sizes'][$image_size]))
			{
				$upload_dir = wp_upload_dir();

				if(isset($image_data['original_image']))
				{
					$uploaded_image_location = $upload_dir['path']."/".$image_data['original_image'];
					$large_image_location = $upload_dir['path']."/".$image_data['sizes'][$image_size]['file'];

					if(file_exists($uploaded_image_location))
					{
						// Delete the uploaded image
						unlink($uploaded_image_location);

						// Copy the large image
						copy($large_image_location, $uploaded_image_location);

						// Update image metadata and return them
						$image_data['width'] = $image_data['sizes'][$image_size]['width'];
						$image_data['height'] = $image_data['sizes'][$image_size]['height'];
						unset($image_data['sizes'][$image_size]);
					}
				}

				else if(isset($image_data['file']))
				{
					$uploaded_image_location = $upload_dir['path']."/".$image_data['file'];
					$large_image_location = $upload_dir['path']."/".$image_data['sizes'][$image_size]['file'];

					if(file_exists($uploaded_image_location))
					{
						// Delete the uploaded image
						unlink($uploaded_image_location);
						//do_log("wp_generate_attachment_metadata() Delete: ".$uploaded_image_location);

						// Copy the large image
						copy($large_image_location, $uploaded_image_location);
						//do_log("wp_generate_attachment_metadata() Copy: ".$large_image_location." -> ".$uploaded_image_location);
					}

					// Update image metadata and return them
					/*$image_data['width'] = $image_data['sizes'][$image_size]['width'];
					$image_data['height'] = $image_data['sizes'][$image_size]['height'];
					unset($image_data['sizes'][$image_size]);*/
				}

				else
				{
					do_log("wp_generate_attachment_metadata() Error: No Original Image (".var_export($image_data, true).")");
				}
			}
		}

		return $image_data;
	}

	function hidden_meta_boxes($hidden, $screen)
	{
		if($screen->id == 'attachment')
		{
			$hidden = array_merge($hidden, array('categorydiv'));
		}

		return $hidden;
	}

	function rwmb_meta_boxes($meta_boxes)
	{
		$arr_actions = $this->get_media_actions();

		$arr_roles = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

		$arr_types = $this->get_media_types(array('type' => 'name'));

		$meta_boxes[] = array(
			'id' => $this->meta_prefix.'settings',
			'title' => __("Settings", 'lang_media'),
			'post_types' => array($this->post_type_allowed),
			//'context' => 'side',
			'priority' => 'low',
			'fields' => array(
				array(
					'name' => __("Action", 'lang_media'),
					'id' => $this->meta_prefix.'action',
					'type' => 'select',
					'options' => $arr_actions,
				),
				array(
					'name' => __("Role", 'lang_media'),
					'id' => $this->meta_prefix.'role',
					'type' => 'select3',
					'options' => $arr_roles,
					'multiple' => true,
					'attributes' => array(
						'size' => get_select_size(array('count' => count($arr_roles))),
					),
				),
				array(
					'name' => __("Type", 'lang_media'),
					'id' => $this->meta_prefix.'types',
					'type' => 'select3',
					'options' => $arr_types,
					'multiple' => true,
					'attributes' => array(
						'size' => get_select_size(array('count' => count($arr_types))),
					),
				),
			)
		);

		// For some reason it is not saved in postmeta table...
		/*$meta_boxes[] = array(
			'id' => $this->meta_prefix.'settings',
			'title' => __("Settings", 'lang_media'),
			'post_types' => array('attachment'),
			'context' => 'side',
			'priority' => 'low',
			'fields' => array(
				array(
					'name' => ,
					'id' => $this->meta_prefix.'attachment_modified',
					'type' => 'datetime',
				),
			)
		);*/

		return $meta_boxes;
	}

	function restrict_manage_posts()
	{
		global $post_type;

		if($post_type == 'attachment')
		{
			//$strFilterAttachmentCategory = get_or_set_table_filter(array('key' => 'strFilterAttachmentCategory', 'save' => true));
			$strFilterAttachmentCategory = check_var('strFilterAttachmentCategory');

			$arr_data = $this->get_categories_for_select(array('only_used' => true, 'add_choose_here' => true));

			if(count($arr_data) > 1)
			{
				echo show_select(array('data' => $arr_data, 'name' => 'strFilterAttachmentCategory', 'value' => $strFilterAttachmentCategory, 'class' => "filter_attachment_category"));
			}
		}
	}

	function pre_get_posts($wp_query)
	{
		global $post_type, $pagenow;

		if($pagenow == 'upload.php') // && $post_type == ''a
		{
			//$strFilterAttachmentCategory = get_or_set_table_filter(array('key' => 'strFilterAttachmentCategory'));
			$strFilterAttachmentCategory = check_var('strFilterAttachmentCategory');

			if($strFilterAttachmentCategory != '')
			{
				$wp_query = apply_filters('filter_on_category', $wp_query, $strFilterAttachmentCategory);
			}
		}
	}

	function column_header($cols)
	{
		global $post_type;

		switch($post_type)
		{
			case 'attachment':
				unset($cols['author']);
				unset($cols['parent']);
				unset($cols['comments']);

				if(IS_ADMINISTRATOR && get_option('setting_media_activate_categories') == 'yes')
				{
					unset($cols['categories']);

					$cols['media_categories'] = __("Categories", 'lang_media');
					$cols['media_roles'] = __("Roles", 'lang_media');
				}

				if(get_site_option('setting_media_activate_is_file_used') == 'yes')
				{
					$cols['used'] = __("Used", 'lang_media');
				}
			break;

			case $this->post_type_allowed:
				unset($cols['date']);

				$cols['action'] = __("Action", 'lang_media');
				$cols['role'] = __("Role", 'lang_media');
				$cols['types'] = __("Types", 'lang_media');
			break;
		}

		return $cols;
	}

	function get_used_amount($id)
	{
		$used_updated = get_post_meta($id, $this->meta_prefix.'used_updated', true);

		if($used_updated < date("Y-m-d H:i:s", strtotime("-1 week")))
		{
			$this->check_if_file_is_used($id);

			//$used_updated = get_post_meta($id, $this->meta_prefix.'used_updated', true);
		}

		return get_post_meta($id, $this->meta_prefix.'used_amount', true);
	}

	function column_cell($col, $id)
	{
		global $wpdb, $post;

		switch($post->post_type)
		{
			case 'attachment':
				switch($col)
				{
					case 'media_categories':
						$field_value = $this->get_media_categories($id);

						$i = 0;

						foreach($field_value as $category_id)
						{
							echo ($i > 0 ? ", " : "").get_cat_name($category_id);

							$i++;
						}
					break;

					case 'media_roles':
						$field_value = $this->get_media_roles($id);

						$arr_data = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

						$i = 0;

						foreach($arr_data as $key => $value)
						{
							if(in_array($key, $field_value))
							{
								echo ($i > 0 ? ", " : "").$value;

								$i++;
							}
						}
					break;

					case 'used':
						if($this->check_if_file_is_used_start < date("Y-m-d H:i:s", strtotime("-30 second")))
						{
							$used_amount = $this->get_used_amount($id);

							echo "<i class='fa ".($used_amount > 0 ? "fa-check green" : "fa-times red")." fa-lg' title='".sprintf(__("Used in %d places", 'lang_media'), $used_amount)."'></i>";
						}

						if(isset($used_amount) && $used_amount > 0)
						{
							$used_example = get_post_meta($id, $this->meta_prefix.'used_example', true);

							if($used_example != '')
							{
								echo "<div class='row-actions'>"
									."<a href='".$used_example."'>".__("View Example", 'lang_media')."</a>"
								."</div>";
							}

							$plugin_include_url = plugin_dir_url(__FILE__);
							$plugin_version = get_plugin_version(__FILE__);

							mf_enqueue_script('script_media_used', $plugin_include_url."script_used.js", $plugin_version);
						}
					break;
				}
			break;

			case $this->post_type_allowed:
				switch($col)
				{
					case 'action':
						$arr_actions = $this->get_media_actions();

						$post_meta = get_post_meta($id, $this->meta_prefix.$col, true);

						echo $arr_actions[$post_meta];
					break;

					case 'role':
						$arr_roles = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

						$arr_post_meta = get_post_meta($id, $this->meta_prefix.$col, false);

						$i = 0;

						foreach($arr_post_meta as $post_meta)
						{
							echo ($i > 0 ? ", " : "").$arr_roles[$post_meta];

							$i++;
						}
					break;

					case 'types':
						$arr_types = $this->get_media_types(array('type' => 'name'));

						$arr_post_meta = get_post_meta($id, $this->meta_prefix.$col, false);

						if(count($arr_post_meta) == 0)
						{
							$post_role = get_post_meta($id, $this->meta_prefix.'role', false);

							if(count($post_role) == 1 && in_array('administrator', $post_role))
							{
								echo __("All", 'lang_media');
							}

							else
							{
								echo __("None", 'lang_media')." (".__("Only administrators can have unfiltered uploads", 'lang_media').")";
							}
						}

						else
						{
							$i = 0;

							foreach($arr_post_meta as $post_meta)
							{
								if(isset($arr_types[$post_meta]))
								{
									echo ($i > 0 ? ", " : "").$arr_types[$post_meta];

									$i++;
								}

								else
								{
									do_log(sprintf("The mime type '%s' does not exist", $post_meta));
								}
							}
						}
					break;
				}
			break;
		}
	}

	function filter_last_updated_post_types($array, $type)
	{
		$array[] = $this->post_type_allowed;

		return $array;
	}

	function filter_on_category($query, $category = 0)
	{
		global $wpdb;

		//error_log("Filter - Before: ".str_replace(array("\n", "\r"), "", var_export($query, true)).", ".$category);

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			if($category > 0)
			{
				$intCategoryID = $category;
			}

			else
			{
				$intCategoryID = (isset($_REQUEST['query']['category']) ? check_var($_REQUEST['query']['category'], 'int', false) : 0);
			}

			if($intCategoryID > 0)
			{
				$arr_file_ids = array();

				$result = $wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $intCategoryID));

				if($wpdb->num_rows > 0)
				{
					foreach($result as $r)
					{
						$arr_file_ids[] = $r->fileID;
					}
				}

				else
				{
					$arr_file_ids = array(0 => 0);

					//error_log("No rows");
				}

				if($category > 0)
				{
					$query->query_vars['post__in'] = $arr_file_ids;

					//error_log("Cats: ".var_export($arr_file_ids, true));
				}

				else
				{
					$query['post__in'] = $arr_file_ids;

					//error_log("No cat");
				}

				update_user_meta(get_current_user_id(), 'meta_current_media_category', $intCategoryID);
			}

			//Is never executed since the default value has "all" as value
			/*else
			{
				delete_user_meta(get_current_user_id(), 'meta_current_media_category');
			}*/
		}

		//error_log("Filter - After: ".str_replace(array("\n", "\r"), "", var_export($query, true)).", ".$category);

		return $query;
	}

	function print_media_templates()
	{
		if(get_option('setting_media_activate_categories') == 'yes')
		{
?>
			<script type="text/html" id="tmpl-attachment">
				<div class="attachment-preview js--select-attachment type-{{ data.type }} subtype-{{ data.subtype }} {{ data.orientation }}">
					<div class="thumbnail">
						<# if ( data.uploading ) { #>
							<div class="media-progress-bar"><div style="width: {{ data.percent }}%"></div></div>
						<# } else if ( 'image' === data.type && data.sizes ) { #>
							<div class="centered">
								<img src="{{ data.size.url }}" draggable="false">
							</div>

							<# if(data.alt == '')
							{ #>
								<i class='fa fa-exclamation-triangle yellow fa-2x' title='<?php echo __("The file has got no alt text. Please add this to improve your SEO.", 'lang_media'); ?>'></i>
							<# }
						}

						else { #>
							<div class="centered">
								<# if ( data.image && data.image.src && data.image.src !== data.icon ) { #>
									<img src="{{ data.image.src }}" class="thumbnail" draggable="false">
								<# } else if ( data.sizes && data.sizes.medium ) { #>
									<img src="{{ data.sizes.medium.url }}" class="thumbnail" draggable="false">
								<# } else { #>
									<img src="{{ data.icon }}" class="icon" draggable="false">
								<# } #>
							</div>
							<div class="filename">
								<div>{{ data.filename }}</div>
							</div>
						<# } #>
					</div>
					<# if ( data.buttons.close ) { #>
						<button type="button" class="button-link attachment-close media-modal-icon"><span class="screen-reader-text"><?php echo __("Remove", 'lang_media'); ?></span></button>
					<# } #>
				</div>
				<# if ( data.buttons.check ) { #>
					<button type="button" class="check" tabindex="-1"><span class="media-modal-icon"></span><span class="screen-reader-text"><?php echo __("Deselect", 'lang_media'); ?></span></button>
				<# } #>
				<#
				var maybeReadOnly = data.can.save || data.allowLocalEdits ? '' : 'readonly';
				if ( data.describe ) {
					if ( 'image' === data.type ) { #>
						<input type="text" value="{{ data.caption }}" class="describe" data-setting="caption"
							placeholder="<?php echo __("Caption this image", 'lang_media')."&hellip;"; ?>" {{ maybeReadOnly }} />
					<# } else { #>
						<input type="text" value="{{ data.title }}" class="describe" data-setting="title"
							<# if ( 'video' === data.type ) { #>
								placeholder="<?php echo __("Describe this video", 'lang_media')."&hellip;"; ?>"
							<# } else if ( 'audio' === data.type ) { #>
								placeholder="<?php echo __("Describe this audio file", 'lang_media')."&hellip;"; ?>"
							<# } else { #>
								placeholder="<?php echo __("Describe this media file", 'lang_media')."&hellip;"; ?>"
							<# } #> {{ maybeReadOnly }} />
					<# }
				} #>
			</script>
<?php
		}
	}

	function ajax_attachments()
	{
		if(get_option('setting_media_activate_categories') == 'yes')
		{
			if(!current_user_can('upload_files'))
			{
				wp_send_json_error();
			}

			$taxonomies = get_object_taxonomies('attachment', 'names');

			$query = (isset($_REQUEST['query']) ? (array)$_REQUEST['query'] : array());

			$defaults = array('s', 'order', 'orderby', 'posts_per_page', 'paged', 'post_mime_type', 'post_parent', 'post__in', 'post__not_in');

			$query = array_intersect_key($query, array_flip(array_merge($defaults, $taxonomies)));
			$query['post_type'] = 'attachment';
			$query['post_status'] = 'inherit';

			if(current_user_can(get_post_type_object('attachment')->cap->read_private_posts))
			{
				$query['post_status'] .= ',private';
			}

			$query = apply_filters('filter_on_category', $query);
			$query = apply_filters('ajax_query_attachments_args', $query);
			$query = new WP_Query($query);

			$posts = array_map('wp_prepare_attachment_for_js', $query->posts);
			$posts = array_filter($posts);
			wp_send_json_success($posts);
		}
	}

	function attachment_fields_to_edit($form_fields, $post)
	{
		if(IS_ADMINISTRATOR && get_option('setting_media_activate_categories') == 'yes')
		{
			$html = "<ul class='term-list'>";

				$field_value = $this->get_media_categories($post->ID);

				$taxonomy = 'category';
				$arr_categories = $this->get_taxonomy(array('taxonomy' => $taxonomy));

				foreach($arr_categories as $r)
				{
					$key = $r->term_id;
					$value = $r->name;

					$html .= "<li>
						<label><input type='checkbox' value='".$key."' name='attachments[".$post->ID."][mf_mc_category][".$key."]'".checked(in_array($key, $field_value), true, false)."> ".$value."</label>";

						$arr_categories2 = $this->get_taxonomy(array('taxonomy' => $taxonomy, 'parent' => $key));

						if(count($arr_categories2) > 0)
						{
							$html .= "<ul class='children'>";

								foreach($arr_categories2 as $r)
								{
									$key2 = $r->term_id;
									$value2 = $r->name;

									$html .= "<li>
										<label><input type='checkbox' value='".$key2."' name='attachments[".$post->ID."][mf_mc_category][".$key2."]'".checked(in_array($key2, $field_value), true, false)."> ".$value2."</label>
									</li>";
								}

							$html .= "</ul>";
						}

					$html .= "</li>";
				}

			$html .= "</ul>";

			$form_fields['mf_mc_category'] = array(
				'label' => __("Categories", 'lang_media'),
				'input' => 'html',
				//'helps' => "",
				'html' => $html,
			);

			$html = "<ul class='term-list'>";

				$field_value = $this->get_media_roles($post->ID);

				$arr_data = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

				foreach($arr_data as $key => $value)
				{
					$html .= "<li>
						<label><input type='checkbox' value='".$key."' name='attachments[".$post->ID."][mf_mc_roles][".$key."]'".checked(in_array($key, $field_value), true, false)."> ".$value."</label>
					</li>";
				}

			$html .= "</ul>";

			$form_fields['mf_mc_roles'] = array(
				'label' => __("Roles", 'lang_media'),
				'input' => 'html',
				//'helps' => "",
				'html' => $html,
			);
		}

		return $form_fields;
	}

	function attachment_fields_to_save($post, $attachment)
	{
		global $wpdb;

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."media2category WHERE fileID = '%d'", $post['ID']));

			if(isset($attachment['mf_mc_category']))
			{
				$array = $attachment['mf_mc_category'];

				foreach($array as $key => $value)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."media2category SET fileID = '%d', categoryID = '%d'", $post['ID'], $value));
				}
			}

			$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."media2role WHERE fileID = '%d'", $post['ID']));

			if(isset($attachment['mf_mc_roles']))
			{
				$array = $attachment['mf_mc_roles'];

				foreach($array as $key => $value)
				{
					$wpdb->query($wpdb->prepare("INSERT INTO ".$wpdb->prefix."media2role SET fileID = '%d', roleKey = %s", $post['ID'], $value));
				}
			}
		}
	}

	function count_shortcode_button($count)
	{
		if($count == 0 && get_option('setting_media_activate_categories') == 'yes')
		{
			$this->get_categories();

			if(count($this->categories) > 0)
			{
				$count++;
			}
		}

		return $count;
	}

	function get_shortcode_output($out)
	{
		if(get_option('setting_media_activate_categories') == 'yes')
		{
			$arr_data = $this->get_categories_for_select(array('add_choose_here' => true));

			if(count($arr_data) > 1)
			{
				$out .= "<h3>".__("Choose a Category", 'lang_media')."</h3>"
				.show_select(array('data' => $arr_data, 'xtra' => "rel='mf_media_category'"));
			}
		}

		return $out;
	}

	function get_option_name_from_array($data)
	{
		foreach($data['array'] as $key => $value)
		{
			if(is_array($value))
			{
				$data_temp = $data;
				$data_temp['array'] = $value;

				$data['option_name'] = $this->get_option_name_from_array($data_temp);

				unset($data_temp);
			}

			else if($value != '' && strpos($value, $data['arr_used']['file_url']))
			{
				$data['option_name'] = $key;

				break;
			}
		}

		return $data['option_name'];
	}

	function filter_is_file_used($arr_used)
	{
		global $wpdb;

		// Content
		####################
		$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE (post_content LIKE %s OR post_content LIKE %s)", "%".$arr_used['file_url']."%", "%".$arr_used['file_thumb_url']."%"));
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			$arr_used['amount'] += $rows;

			foreach($result as $r)
			{
				if($arr_used['example'] != '')
				{
					break;
				}

				$arr_used['example'] = admin_url("post.php?action=edit&post=".$r->ID);
			}
		}
		####################

		// Options
		####################
		$result = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM ".$wpdb->options." WHERE (option_value = '%d' OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s)", $arr_used['id'], "%".$arr_used['file_url']."%", "%".$arr_used['file_thumb_url']."%", "%".str_replace("/sites/".$wpdb->blogid."/", "/", $arr_used['file_url'])."%", "%".str_replace("/sites/".$wpdb->blogid."/", "/", $arr_used['file_thumb_url'])."%"));
		$rows = $wpdb->num_rows;

		/*if($arr_used['id'] == )
		{
			do_log("Used in Options: ".$wpdb->last_query);
		}*/

		if($rows > 0)
		{
			$arr_used['amount'] += $rows;

			foreach($result as $r)
			{
				if($arr_used['example'] != '')
				{
					break;
				}

				if(substr($r->option_name, 0, 8) == "setting_")
				{
					$arr_used['example'] = admin_url("options-general.php?page=settings_mf_base#".$r->option_name);
				}

				else if(substr($r->option_name, 0, 11) == "theme_mods_")
				{
					$option_theme_mods = get_option($r->option_name);
					$option_name = $this->get_option_name_from_array(array('option_name' => $r->option_name, 'array' => $option_theme_mods, 'arr_used' => $arr_used));

					if(is_array($arr_used['file_url']))
					{
						do_log("URL is array: ".var_export($arr_used['file_url'], true));
					}

					$arr_used['example'] = admin_url("customize.php?autofocus[control]=".$option_name);
				}

				else if(substr($r->option_name, 0, 7) == "widget_")
				{
					$widget_option = get_option($r->option_name);
					$arr_sidebars = wp_get_sidebars_widgets();

					/*do_log("Widget: ".var_export($widget_option, true));
					do_log("Sidebars: ".var_export($arr_sidebars, true));*/

					$arr_used['example'] = admin_url("widgets.php#".$r->option_name);

					$widget_key = str_replace("widget_", "", $r->option_name);

					foreach($widget_option as $widget_key_temp => $arr_widget)
					{
						if(strpos(var_export($arr_widget, true), $arr_used['file_url']) || strpos(var_export($arr_widget, true), $arr_used['file_thumb_url']) || strpos(var_export($arr_widget, true), str_replace("/sites/".$wpdb->blogid."/", "/", $arr_used['file_url'])) || strpos(var_export($arr_widget, true), str_replace("/sites/".$wpdb->blogid."/", "/", $arr_used['file_thumb_url'])))
						{
							$widget_key .= "-".$widget_key_temp;

							$sidebar_key = "";

							foreach($arr_sidebars as $sidebar_key_temp => $arr_sidebar)
							{
								foreach($arr_sidebar as $widget_key_temp)
								{
									if($widget_key_temp == $widget_key)
									{
										$sidebar_key = $sidebar_key_temp;

										break;
									}
								}
							}

							$arr_used['example'] = admin_url("widgets.php#".$sidebar_key."&".$widget_key);
						}
					}
				}

				else if($r->option_value == $arr_used['id'])
				{
					$arr_used['example'] = "#option_name=".$r->option_name."&option_value=".$r->option_value;
				}

				else
				{
					$arr_used['example'] = "#option_name=".$r->option_name;
				}
			}
		}
		####################

		// Gallery
		####################
		$arr_widget_option = get_option('widget_media_gallery');

		if(is_array($arr_widget_option) && count($arr_widget_option) > 0)
		{
			$arr_sidebars = wp_get_sidebars_widgets();

			foreach($arr_widget_option as $widget_key_temp => $arr_widget)
			{
				if(is_array($arr_widget) && is_array($arr_widget['ids']) && in_array($arr_used['id'], $arr_widget['ids']))
				{
					$sidebar_key = "";
					$widget_key = "media_gallery-".$widget_key_temp;

					foreach($arr_sidebars as $sidebar_key_temp => $arr_sidebar)
					{
						foreach($arr_sidebar as $widget_key_temp)
						{
							if($widget_key_temp == $widget_key)
							{
								if($sidebar_key == '')
								{
									$sidebar_key = $sidebar_key_temp;
									$arr_used['example'] = admin_url("widgets.php#".$sidebar_key."&media"); //.$widget_key
								}

								$arr_used['amount']++;
							}
						}
					}
				}
			}
		}
		####################

		// Post meta
		####################
		$result = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM ".$wpdb->postmeta." WHERE post_id != '%d' AND (meta_value = '%d' OR meta_value LIKE %s OR meta_value LIKE %s)", $arr_used['id'], $arr_used['id'], "%".$arr_used['file_url']."%", "%".$arr_used['file_thumb_url']."%"));
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			$arr_used['amount'] += $rows;

			foreach($result as $r)
			{
				if($arr_used['example'] != '')
				{
					break;
				}

				$arr_used['example'] = admin_url("post.php?action=edit&post=".$r->post_id);
				$arr_used['example'] .= "#meta_key=".$r->meta_key;

				if($r->meta_value == $arr_used['id'])
				{
					$arr_used['example'] .= "&meta_value=".$r->meta_value;
				}
			}
		}

		//do_log("filter_is_file_used -> postmeta: ".$wpdb->last_query);
		####################

		// Custom header, background etc.
		####################
		$post = get_post($arr_used['id']);
		$arr_media_states = get_media_states($post);

		if(is_array($arr_media_states) && count($arr_media_states) > 0)
		{
			$arr_used['amount'] += count($arr_media_states);
			$arr_used['example'] = "#meta_state:".implode("|", $arr_media_states);
		}
		####################

		// Site meta
		####################
		if(isset($wpdb->sitemeta) && $wpdb->sitemeta != '')
		{
			$result = $wpdb->get_results($wpdb->prepare("SELECT meta_key FROM ".$wpdb->sitemeta." WHERE site_id = '%d' AND (meta_value LIKE %s OR meta_value LIKE %s)", $wpdb->blogid, "%".$arr_used['file_url']."%", "%".$arr_used['file_thumb_url']."%"));
			$rows = $wpdb->num_rows;

			if($rows > 0)
			{
				$arr_used['amount'] += $rows;

				foreach($result as $r)
				{
					if($arr_used['example'] != '')
					{
						break;
					}

					$arr_used['example'] = "#site:meta_key=".$r->meta_key;
				}
			}
		}
		####################

		// User meta
		####################
		$result = $wpdb->get_results($wpdb->prepare("SELECT user_id, meta_key FROM ".$wpdb->usermeta." WHERE (meta_value LIKE %s OR meta_value LIKE %s)", "%".$arr_used['file_url']."%", "%".$arr_used['file_thumb_url']."%"));
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			$arr_used['amount'] += $rows;

			foreach($result as $r)
			{
				if($arr_used['example'] != '')
				{
					break;
				}

				$arr_used['example'] = admin_url("user-edit.php?user_id=".$r->user_id."#user:meta_key=".$r->meta_key);
			}
		}
		####################

		// Categories and roles
		####################
		if(does_table_exist($wpdb->prefix."media2category"))
		{
			$result = $wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2category WHERE fileID = '%d'", $arr_used['id']));
			$rows = $wpdb->num_rows;

			if($rows > 0)
			{
				$arr_used['amount'] += $rows;
				$arr_used['example'] = admin_url("post.php?post=".$arr_used['id']."&action=edit");
			}
		}

		if(does_table_exist($wpdb->prefix."media2role"))
		{
			$result = $wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2role WHERE fileID = '%d'", $arr_used['id']));
			$rows = $wpdb->num_rows;

			if($rows > 0)
			{
				$arr_used['amount'] += $rows;
				$arr_used['example'] = admin_url("post.php?post=".$arr_used['id']."&action=edit");
			}
		}
		####################

		return $arr_used;
	}

	function init_base_admin($arr_views, $data = array())
	{
		if(!isset($data['init'])){	$data['init'] = false;}

		if($data['init'] == true)
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_style('style_media', $plugin_include_url."style.css", $plugin_version);
		}

		return $arr_views;
	}

	function shortcode_media_category($atts)
	{
		global $wpdb;

		extract(shortcode_atts(array(
			'id' => ''
		), $atts));

		$out = "";

		$current_user_role = get_current_user_role();

		$result = $wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $id));

		foreach($result as $r)
		{
			$file_id = $r->fileID;

			$display = true;

			$wpdb->get_var($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2role WHERE fileID = '%d' LIMIT 0, 1", $file_id));

			if($wpdb->num_rows > 0)
			{
				$wpdb->get_var($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2role WHERE fileID = '%d' AND roleKey = %s LIMIT 0, 1", $file_id, $current_user_role));

				if($wpdb->num_rows == 0)
				{
					$display = false;
				}
			}

			//do_log("File Media: ".$file_id.", ".get_post_title($file_id).", ".$display.", ".apply_filters('display_category_file', $display, $file_id));

			if($display == true && apply_filters('display_category_file', $display, $file_id) == true)
			{
				$file_url = wp_get_attachment_url($file_id);
				$file_thumb = wp_get_attachment_thumb_url($file_id);
				$file_name = get_post_title($file_id);

				if($file_name != '' && $file_url != '')
				{
					$out .= "<li>";

						if($file_thumb != '')
						{
							$out .= "<img src='".$file_thumb."'>";
						}

						else
						{
							$out .= get_file_icon(array('file' => $file_url, 'size' => "fa-3x"));
						}

						$out .= "<a href='".$file_url."'>".$file_name."</a>
					</li>";
				}
			}
		}

		$out = apply_filters('media_categories_displayed', $out, $id);

		if($out != '')
		{
			return "<ul class='media_categories'>"
				.$out
			."</ul>";
		}

		else
		{
			return "<p>".__("I could not find any information to show you", 'lang_media')."</p>";
		}
	}

	function get_media_actions()
	{
		return array(
			'allow' => __("Allow", 'lang_media'),
			'allow_only_these' => __("Allow only these", 'lang_media'),
			'disallow' => __("Disallow", 'lang_media'),
		);
	}

	function get_media_types($data)
	{
		global $obj_base;

		//https://codex.wordpress.org/Function_Reference/get_allowed_mime_types
		$font_name = __("Font", 'lang_media');
		$image_name = __("Image", 'lang_media');
		$audio_name = __("Audio", 'lang_media');
		$video_name = __("Video", 'lang_media');
		$compressed_name = __("Compressed", 'lang_media');
		$document_name = __("Document", 'lang_media');
		$presentation_name = __("Presentation", 'lang_media');
		$spreadsheet_name = __("Spreadsheet", 'lang_media');

		$arr_types_raw = array(
			'eot' =>				array($font_name." (Opentype)", "application/vnd.ms-fontobject"), //font/opentype
			'ttf' =>				array($font_name." (Truetype)", "application/x-font-ttf"), //font/truetype
			'woff' =>				array($font_name." (Woff)", "application/octet-stream"), //application/font-woff

			'exe' =>				array(__("Program", 'lang_media'), "application/x-msdownload"),
			'apk' =>				array("APK", "application/vnd.android.package-archive"),

			'ico' =>				array(__("Icon", 'lang_media'), "image/x-icon"),
			'svg' =>				array("SVG", "image/svg+xmln"),
			'jpg|jpeg|jpe' =>		array($image_name." (JPEG)", "image/jpeg"),
			'gif' =>				array($image_name." (GIF)", "image/gif"),
			'png' =>				array($image_name." (PNG)", "image/png"),
			'bmp' =>				array($image_name." (BMP)", "image/bmp"),
			'tiff|tif' =>			array($image_name." (TIFF)", "image/tiff"),

			'css' =>				array("CSS", "text/css"),
			'js' =>					array("Javascript", "application/javascript"),
			'htm|html' =>			array("HTML", "text/html"),

			'mp3|m4a|m4b' =>		array($audio_name." (MP3)", "audio/mpeg"),
			'wav' =>				array($audio_name." (Wav)", "audio/wav"),
			'ogg|oga' =>			array($audio_name." (OGG)", "audio/ogg"),
			//'mid|midi' =>			array($audio_name." (Midi)", "audio/midi"),
			'wma' =>				array($audio_name." (WMA)", "audio/x-ms-wma"),
			//'ra|ram' =>			array($audio_name." (Real)", "audio/x-realaudio"),

			//'asf|asx' =>			array($video_name." (ASF)", "video/x-ms-asf"),
			'wmv' =>				array($video_name." (WMV)", "video/x-ms-wmv"),
			//'wmx' =>				array($video_name." (WMX)", "video/x-ms-wmx"),
			//'wm' =>				array($video_name." (WM)", "video/x-ms-wm"),
			'avi' =>				array($video_name." (AVI)", "video/avi"),
			//'divx' =>				array($video_name." (DivX)", "video/divx"),
			'flv' =>				array($video_name." (FLV)", "video/x-flv"),
			'mov|qt' =>				array($video_name." (QT)", "video/quicktime"),
			'mpeg|mpg|mpe' =>		array($video_name." (MPEG)", "video/mpeg"),
			'mp4|m4v' =>			array($video_name." (MP4)", "video/mp4"),
			//'ogv' =>				array($video_name." (OGG)", "video/ogg"),
			//'webm' =>				array($video_name." (Webm)", "video/webm"),
			'mkv' =>				array($video_name." (MKV)", "video/x-matroska"),
			//'3gp|3gpp' =>			array($video_name." (3gp)", "video/3gpp"),
			//'3g2|3gp2' =>			array($video_name." (3g2)", "video/3gpp2"),

			'pdf' =>				array("PDF", "application/pdf"),
			'tar' =>				array($compressed_name." (Tar)", "application/x-tar"),
			'zip' =>				array($compressed_name." (Zip)", "application/zip"),
			'gz|gzip' =>			array($compressed_name." (Gzip)", "application/x-gzip"),
			'rar' =>				array($compressed_name." (Rar)", "application/rar"),
			'7z' =>					array($compressed_name." (7z)", "application/x-7z-compressed"),

			'psd' =>				array($image_name." (PSD)", "application/octet-stream"),
			'doc' =>				array($document_name." (Word)", "application/msword"),
			'docx' =>				array($document_name." (Word docx)", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"),
			'odt' =>				array($document_name." (Open)", "application/vnd.oasis.opendocument.text"),
			//'pot' =>				array($presentation_name." (PowerPoint - POT)", "application/vnd.ms-powerpoint"),
			//'pps' =>				array($presentation_name." (PowerPoint - PPS)", "application/vnd.ms-powerpoint"),
			'ppt' =>				array($presentation_name." (PowerPoint - PPT)", "application/vnd.ms-powerpoint"),
			'pptx' =>				array($presentation_name." (PowerPoint - PPTX)", "application/vnd.openxmlformats-officedocument.presentationml.presentation"),
			'odp' =>				array($presentation_name." (Open)", "application/vnd.oasis.opendocument.presentation"),
			//'xla' =>				array($spreadsheet_name." (Excel - XLA)", "application/vnd.ms-excel"),
			'xls' =>				array($spreadsheet_name." (Excel - XLS)", "application/vnd.ms-excel"),
			//'xls' =>				array($spreadsheet_name." (Excel - XLS)", array("application/excel", "application/vnd.ms-excel", "application/x-excel", "application/x-msexcel")),
			//'xlt' =>				array($spreadsheet_name." (Excel - XLT)", "application/vnd.ms-excel"),
			//'xlw' =>				array($spreadsheet_name." (Excel - XLW)", "application/vnd.ms-excel"),
			'xlsx' =>				array($spreadsheet_name." (Excel - XLSX)", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"),
			'ods' =>				array($spreadsheet_name." (Open)", "application/vnd.oasis.opendocument.spreadsheet"),

			//'txt|asc|c|cc|h|srt' => "Plain text", "text/plain
			//'csv' => "CSV", "text/csv
			//'tsv' => "", "text/tab-separated-values
			//'rtf' => "", "application/rtf
			//'ics' => "Calendar", "text/calendar
			//'rtx' => "", "text/richtext
			//'vtt' => "", "text/vtt

			//'dfxp' => "", "application/ttaf+xml
		);

		/*'wax' => 'audio/x-ms-wax', 'mka' => 'audio/x-matroska', 'class' => 'application/java', 'xcf' => 'application/octet-stream', 'wri' => 'application/vnd.ms-write', 'mdb' => 'application/vnd.ms-access', 'mpp' => 'application/vnd.ms-project', 'docm' => 'application/vnd.ms-word.document.macroEnabled.12', 'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'dotm' => 'application/vnd.ms-word.template.macroEnabled.12', 'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12', 'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12', 'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12', 'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12', 'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12', 'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template', 'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12', 'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12', 'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12', 'onetoc|onetoc2|onetmp|onepkg' => 'application/onenote', 'oxps' => 'application/oxps', 'xps' => 'application/vnd.ms-xpsdocument', 'odg' => 'application/vnd.oasis.opendocument.graphics', 'odc' => 'application/vnd.oasis.opendocument.chart', 'odb' => 'application/vnd.oasis.opendocument.database', 'odf' => 'application/vnd.oasis.opendocument.formula', 'wp|wpd' => 'application/wordperfect', 'key' => 'application/vnd.apple.keynote', 'numbers' => 'application/vnd.apple.numbers', 'pages' => 'application/vnd.apple.pages', */

		$arr_types = array();

		foreach($arr_types_raw as $key => $value)
		{
			$arr_types[$key] = $data['type'] == 'name' ? $value[0] : $value[1];
		}

		if(!isset($obj_base))
		{
			$obj_base = new mf_base();
		}

		return $obj_base->array_sort(array('array' => $arr_types, 'on' => 1, 'keep_index' => true));
	}

	function filter_categories()
	{
		foreach($this->categories as $cat_id => $cat_array)
		{
			if(count($cat_array['files']) > 0 || (!is_array($cat_array['files']) && $cat_array['files'] != '') || count($cat_array['sub']) > 0)
			{
				foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
				{
					if(count($cat_array2['files']) > 0 || (!is_array($cat_array2['files']) && $cat_array2['files'] != '')){}

					else
					{
						unset($this->categories[$cat_id]['sub'][$cat_id2]);
					}
				}
			}

			else
			{
				unset($this->categories[$cat_id]);
			}
		}
	}

	function get_categories_for_select($data = array())
	{
		global $wpdb;

		if(!isset($data['only_used'])){			$data['only_used'] = false;}
		if(!isset($data['add_choose_here'])){	$data['add_choose_here'] = true;}

		$arr_data = array();

		if($data['add_choose_here'] == true)
		{
			$arr_data[''] = "-- ".__("Choose Here", 'lang_media')." --";
		}

		$arr_categories = $this->get_taxonomy(array('taxonomy' => 'category'));

		foreach($arr_categories as $r)
		{
			$cat_id = $r->term_id;
			$cat_name = $r->name;

			if($data['only_used'] == true && does_table_exist($wpdb->prefix."media2category"))
			{
				$wpdb->get_results($wpdb->prepare("SELECT categoryID FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $cat_id));
				$rows = $wpdb->num_rows;

				if($rows > 0)
				{
					$arr_data[$cat_id] = $cat_name." (".$rows.")";
				}
			}

			else
			{
				$arr_data[$cat_id] = $cat_name;
			}

			//Children
			#######################
			$arr_categories_children = $this->get_taxonomy(array('taxonomy' => 'category', 'parent' => $cat_id));

			if(count($arr_categories_children) > 0)
			{
				$arr_data["opt_start_".$cat_id] = $cat_name;

				foreach($arr_categories_children as $r)
				{
					$cat_id = $r->term_id;
					$cat_name = $r->name;

					if($data['only_used'] == true && does_table_exist($wpdb->prefix."media2category"))
					{
						$wpdb->get_results($wpdb->prepare("SELECT categoryID FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $cat_id));
						$rows = $wpdb->num_rows;

						if($rows > 0)
						{
							$arr_data[$cat_id] = $cat_name." (".$rows.")";
						}
					}

					else
					{
						$arr_data[$cat_id] = $cat_name;
					}
				}

				$arr_data["opt_end_".$cat_id] = "";
			}
			#######################
		}

		return $arr_data;
	}

	function get_categories()
	{
		if(count($this->categories) == 0)
		{
			$role_id = get_current_user_role();

			$taxonomy = 'category';

			$arr_categories = $this->get_taxonomy(array('taxonomy' => $taxonomy));

			foreach($arr_categories as $r)
			{
				$cat_id = $r->term_id;
				$cat_name = $r->name;

				$cat_files = $this->get_files($cat_id, $role_id);

				$this->categories[$cat_id] = array('name' => $cat_name, 'files' => $cat_files, 'sub' => array());

				$arr_categories2 = $this->get_taxonomy(array('taxonomy' => $taxonomy, 'parent' => $cat_id));

				foreach($arr_categories2 as $r)
				{
					$cat_id2 = $r->term_id;
					$cat_name2 = $r->name;

					$cat_files2 = $this->get_files($cat_id2, $role_id);

					if(count($cat_files2) > 0)
					{
						$this->categories[$cat_id]['sub'][$cat_id2] = array('name' => $cat_name2, 'files' => $cat_files2);
					}
				}
			}

			$this->filter_categories();
		}
	}

	function get_files($cat_id, $role_id)
	{
		global $wpdb;

		$out = array();

		$result = $wpdb->get_results($wpdb->prepare("SELECT fileID, roleKey FROM ".$wpdb->prefix."media2category LEFT JOIN ".$wpdb->prefix."media2role USING (fileID) WHERE categoryID = '%d' GROUP BY fileID", $cat_id));

		foreach($result as $r)
		{
			$intFileID = $r->fileID;
			$strRoleKey = $r->roleKey;

			if($strRoleKey == $role_id){}
			else if($strRoleKey == ''){}

			else
			{
				$wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->prefix."media2role WHERE roleKey = %s LIMIT 0, 1", $role_id));

				if($wpdb->num_rows == 0)
				{
					continue;
				}
			}

			$out[] = $intFileID;
		}

		return $out;
	}

	function get_first_sub_category($cat_id)
	{
		global $wpdb;

		$child_id = $wpdb->get_var("SELECT cat_id FROM ".$wpdb->prefix."wpfb_cats WHERE cat_parent = '".$cat_id."' ORDER BY cat_order ASC, cat_name ASC LIMIT 0, 1");

		if($child_id > 0)
		{
			$cat_id = $child_id;
		}

		return $cat_id;
	}

	function get_category_link($cat_id, $cat_name)
	{
		return "<a href='#category_".$cat_id."' id='tab_category_".$cat_id."' class='nav-tab'>".$cat_name."</a>";
	}

	function get_file_container($cat_id, $cat_name, $cat_files)
	{
		$out = $out_temp = "";

		if(is_array($cat_files))
		{
			foreach($cat_files as $file_id)
			{
				list($file_name, $file_url) = get_attachment_data_by_id($file_id);

				if($file_name != '')
				{
					$out_temp .= "<tr>
						<td>".get_file_icon(array('file' => $file_url))."</td>
						<td><a href='".$file_url."'>".$file_name."</a></td>
					</tr>";
				}
			}
		}

		else
		{
			$out_temp .= $cat_files;
		}

		if($out_temp != '')
		{
			$out = $out_temp;
		}

		return $out;
	}

	function get_categories_options()
	{
		$out = "";

		if(count($this->categories) > 0)
		{
			foreach($this->categories as $cat_id => $cat_array)
			{
				$out .= ($out != '' ? ", " : "")."{'term_id': '".$cat_id."', 'term_name': '".$cat_array['name']."'}";

				foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
				{
					$out .= ($out != '' ? ", " : "")."{'term_id': '".$cat_id2."', 'term_name': '&mdash; ".$cat_array2['name']."'}";
				}
			}
		}

		return $out;
	}

	function display_categories_and_files()
	{
		$out = $out_categories = $out_files = "";

		if(count($this->categories) > 0)
		{
			$i = 0;

			foreach($this->categories as $cat_id => $cat_array)
			{
				$out_temp = $this->get_file_container($cat_id, $cat_array['name'], $cat_array['files']);

				if($out_temp != '')
				{
					if($i == 0)
					{
						$this->default_tab = "category_".$cat_id;
					}

					$out_categories .= $this->get_category_link($cat_id, $cat_array['name']);

					$out_files .= "<table id='category_".$cat_id."' class='nav-target mf_media_category widefat striped".($this->default_tab == "category_".$cat_id ? "" : " hide")."'>
						<tbody>"
							.$out_temp
						."</tbody>
					</table>";

					$i++;
				}

				foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
				{
					$out_temp = $this->get_file_container($cat_id2, $cat_array2['name'], $cat_array2['files']);

					if($out_temp != '')
					{
						$out_categories .= $this->get_category_link($cat_id2, "- ".$cat_array2['name']);

						$out_files .= "<table id='category_".$cat_id2."' class='nav-target mf_media_category widefat striped".($this->default_tab == "category_".$cat_id2 ? "" : " hide")."'>
							<tbody>"
								.$out_temp
							."</tbody>
						</table>";
					}

					/*else
					{
						$out .= "Sub-Nope: ".$cat_array2['name']."<br>";
					}*/

					$i++;
				}

				/*else
				{
					$out .= "Nope: ".$cat_array['name']."<br>";
				}*/
			}
		}

		//$out .= var_export($this->categories, true);

		if($out_categories != '' && $out_files != '')
		{
			$out .= "<h3 id='nav-tab-wrapper' class='nav-tab-wrapper'>".$out_categories."</h3>"
			.$out_files;
		}

		else
		{
			$out .= "<em>".__("There are no categories to show here", 'lang_media')."</em>";
		}

		return $out;
	}

	/*function show_categories()
	{
		$out = "";

		if(count($this->categories) > 0)
		{
			$out .= "<h3 id='nav-tab-wrapper' class='nav-tab-wrapper'>";

				$i = 0;

				foreach($this->categories as $cat_id => $cat_array)
				{
					if($i == 0)
					{
						$this->default_tab = "category_".$cat_id;
					}

					$out .= $this->get_category_link($cat_id, $cat_array['name']);

					foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
					{
						$out .= $this->get_category_link($cat_id2, "- ".$cat_array2['name']);
					}

					$i++;
				}

			$out .= "</h3>";
		}

		else
		{
			$out .= "<em>".__("There are no categories to show here", 'lang_media')."</em>";
		}

		return $out;
	}

	function show_files()
	{
		$out = "";

		if(count($this->categories) > 0)
		{
			foreach($this->categories as $cat_id => $cat_array)
			{
				$out_temp = $this->get_file_container($cat_id, $cat_array['name'], $cat_array['files']);

				if($out_temp != '')
				{
					$out .= "<table id='category_".$cat_id."' class='nav-target mf_media_category widefat striped".($this->default_tab == "category_".$cat_id ? "" : " hide")."'>
						<tbody>"
							.$out_temp
						."</tbody>
					</table>";
				}

				foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
				{
					$out_temp = $this->get_file_container($cat_id2, $cat_array2['name'], $cat_array2['files']);

					if($out_temp != '')
					{
						$out .= "<table id='category_".$cat_id2."' class='nav-target mf_media_category widefat striped".($this->default_tab == "category_".$cat_id2 ? "" : " hide")."'>
							<tbody>"
								.$out_temp
							."</tbody>
						</table>";
					}
				}
			}
		}

		return $out;
	}*/
}