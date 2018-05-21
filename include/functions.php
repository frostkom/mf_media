<?php

function settings_media()
{
	$options_area = __FUNCTION__;

	add_settings_section($options_area, "", $options_area."_callback", BASE_OPTIONS_PAGE);

	$arr_settings = array();

	if(IS_SUPER_ADMIN)
	{
		$arr_settings['setting_media_sanitize_files'] = __("Sanitize Filenames", 'lang_media');
	}

	$arr_settings['setting_media_activate_categories'] = __("Activate Categories", 'lang_media');

	if(get_option('setting_media_activate_categories') == 'yes')
	{
		$arr_settings['setting_show_admin_menu'] = __("Show admin menu with categories and files", 'lang_media');
	}

	show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
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

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function setting_media_activate_categories_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'description' => __("This will add the possibility to connect categories and restrict roles to every file in the Media Library", 'lang_media')));
}

function setting_show_admin_menu_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
}

function menu_media()
{
	$menu_root = 'mf_media/';
	$menu_start = $menu_root.'list/index.php';
	$menu_capability = 'read';

	if(get_option('setting_show_admin_menu') == 'yes')
	{
		$menu_title = __("Files", 'lang_media');

		add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-media-default', 11);
	}

	if(IS_ADMIN)
	{
		$menu_title = __("Allowed Types", 'lang_media');

		add_submenu_page("upload.php", $menu_title, $menu_title, $menu_capability, "edit.php?post_type=mf_media_allowed");
	}
}

function upload_mimes_media($existing_mimes = array())
{
	global $wpdb;

	$obj_media = new mf_media();
	$arr_types = $obj_media->get_media_types(array('type' => 'mime'));

	$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_media_allowed' AND post_status = 'publish'");

	foreach($result as $r)
	{
		$post_id = $r->ID;

		$post_role = get_post_meta($post_id, $obj_media->meta_prefix.'role', false);
		$post_role_count = count($post_role);

		if($post_role_count == 0 || in_array(get_current_user_role(), $post_role))
		{
			$post_action = get_post_meta($post_id, $obj_media->meta_prefix.'action', true);
			$post_types = get_post_meta($post_id, $obj_media->meta_prefix.'types', false);

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

function update_count_callback_media_category_media() //$terms = array(), $taxonomy = 'category'
{
	global $wpdb;

	$taxonomy = 'category';

	// select id & count from taxonomy
	$query = "SELECT term_taxonomy_id, MAX(total) AS total FROM ((
		SELECT tt.term_taxonomy_id, COUNT(*) AS total FROM ".$wpdb->term_relationships." tr, ".$wpdb->term_taxonomy." tt WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s GROUP BY tt.term_taxonomy_id
	) UNION ALL (
		SELECT term_taxonomy_id, 0 AS total FROM ".$wpdb->term_taxonomy." WHERE taxonomy = %s
	)) AS unioncount GROUP BY term_taxonomy_id";

	$result = $wpdb->get_results($wpdb->prepare($query, $taxonomy, $taxonomy));

	// update all count values from taxonomy
	foreach($result as $r)
	{
		$intCategoryID = $r->term_taxonomy_id;

		$tax_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(categoryID) FROM ".$wpdb->prefix."media2category WHERE categoryID = '%d'", $intCategoryID));
		$tax_count += $r->total;

		$wpdb->update($wpdb->term_taxonomy, array('count' => $tax_count), array('term_taxonomy_id' => $intCategoryID));
	}
}

function init_media()
{
	$labels = array(
		'name' => _x(__("Types", 'lang_media'), 'post type general name'),
		'singular_name' => _x(__("Type", 'lang_media'), 'post type singular name'),
		'menu_name' => __("Allowed Types", 'lang_media')
	);

	$args = array(
		'labels' => $labels,
		'public' => true,
		'show_in_menu' => false,
		'show_in_nav_menus' => false,
		'exclude_from_search' => true,
		'supports' => array('title'),
		'hierarchical' => false,
		'has_archive' => false,
	);

	register_post_type('mf_media_allowed', $args);

	register_taxonomy_for_object_type('category', 'attachment');
}

function meta_boxes_media($meta_boxes)
{
	$obj_media = new mf_media();

	$arr_actions = $obj_media->get_media_actions();

	$arr_roles = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

	$arr_types = $obj_media->get_media_types(array('type' => 'name'));

	$meta_boxes[] = array(
		'id' => $obj_media->meta_prefix.'settings',
		'title' => __("Settings", 'lang_media'),
		'post_types' => array('mf_media_allowed'),
		//'context' => 'side',
		'priority' => 'low',
		'fields' => array(
			array(
				'name' => __("Action", 'lang_media'),
				'id' => $obj_media->meta_prefix.'action',
				'type' => 'select',
				'options' => $arr_actions,
			),
			array(
				'name' => __("Role", 'lang_media'),
				'id' => $obj_media->meta_prefix.'role',
				'type' => 'select',
				'options' => $arr_roles,
				'multiple' => true,
			),
			array(
				'name' => __("Type", 'lang_media'),
				'id' => $obj_media->meta_prefix.'types',
				'type' => 'select',
				'options' => $arr_types,
				'multiple' => true,
			),
		)
	);

	return $meta_boxes;
}

function column_header_media_allowed($cols)
{
	unset($cols['date']);

	$cols['action'] = __("Action", 'lang_media');
	$cols['role'] = __("Role", 'lang_media');
	$cols['types'] = __("Types", 'lang_media');

	return $cols;
}

function column_cell_media_allowed($col, $id)
{
	$obj_media = new mf_media();

	switch($col)
	{
		case 'action':
			$arr_actions = $obj_media->get_media_actions();

			$post_meta = get_post_meta($id, $obj_media->meta_prefix.$col, true);

			echo $arr_actions[$post_meta];
		break;

		case 'role':
			$arr_roles = get_roles_for_select(array('add_choose_here' => false, 'use_capability' => false));

			$arr_post_meta = get_post_meta($id, $obj_media->meta_prefix.$col, false);

			$i = 0;

			foreach($arr_post_meta as $post_meta)
			{
				echo ($i > 0 ? ", " : "").$arr_roles[$post_meta];

				$i++;
			}
		break;

		case 'types':
			$arr_types = $obj_media->get_media_types(array('type' => 'name'));

			$arr_post_meta = get_post_meta($id, $obj_media->meta_prefix.$col, false);

			if(count($arr_post_meta) == 0)
			{
				$post_role = get_post_meta($id, $obj_media->meta_prefix.'role', false);

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
						do_log(sprintf(__("The mime type '%s' does not exist", 'lang_media'), $post_meta));
					}
				}
			}
		break;
	}
}

function init_callback_media()
{
	global $wp_taxonomies;

	$taxonomy = 'category';

	if(!taxonomy_exists($taxonomy))
	{
		return false;
	}

	$new_arg = &$wp_taxonomies[$taxonomy]->update_count_callback;
	$new_arg = 'update_count_callback_media_category_media';
}

function filter_on_category_media($query)
{
	global $wpdb;

	if(get_option('setting_media_activate_categories') == 'yes')
	{
		$intCategoryID = isset($_REQUEST['query']['category']) ? check_var($_REQUEST['query']['category'], 'int', false) : 0;

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

				$query['post__in'] = $arr_file_ids;
			}

			update_user_meta(get_current_user_id(), 'meta_current_media_category', $intCategoryID);
		}

		//Is never executed since the default value has "all" as value
		/*else
		{
			delete_user_meta(get_current_user_id(), 'meta_current_media_category');
		}*/
	}
	
	return $query;
}

function ajax_attachments_media()
{
	if(get_option('setting_media_activate_categories') == 'yes')
	{
		if(!current_user_can('upload_files'))
		{
			wp_send_json_error();
		}

		$taxonomies = get_object_taxonomies('attachment', 'names');

		$query = isset($_REQUEST['query']) ? (array)$_REQUEST['query'] : array();

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

/*function enqueue_scripts_media()
{
	global $pagenow;

	if(get_option('setting_media_activate_categories') == 'yes')
	{
		$plugin_include_url = plugin_dir_url(__FILE__);
		$plugin_version = get_plugin_version(__FILE__);

		if(wp_script_is('media-editor') && 'upload.php' == $pagenow)
		{
			$taxonomy = 'category';

			$obj_media = new mf_media();
			$obj_media->get_categories();

			$attachment_terms = $obj_media->get_categories_options();

			$current_media_category = get_user_meta(get_current_user_id(), 'meta_current_media_category', true);

			mf_enqueue_script('script_media', $plugin_include_url."script.js", array('taxonomy' => $taxonomy, 'list_title' => "-- ".__("View all categories", 'lang_media')." --", 'term_list' => "[".$attachment_terms."]", 'current_media_category' => $current_media_category), $plugin_version);
		}

		mf_enqueue_style('style_media', $plugin_include_url."style_wp.css", $plugin_version);
	}
}*/

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

function attachment_edit_media($form_fields, $post)
{
	global $wpdb;

	if(IS_ADMIN && get_option('setting_media_activate_categories') == 'yes')
	{
		$html = "<ul class='term-list'>";

			$field_value = get_media_categories($post->ID);

			$taxonomy = 'category';
			$obj_media = new mf_media();
			$arr_categories = $obj_media->get_taxonomy(array('taxonomy' => $taxonomy));

			foreach($arr_categories as $r)
			{
				$key = $r->term_id;
				$value = $r->name;

				$html .= "<li>
					<label><input type='checkbox' value='".$key."' name='attachments[".$post->ID."][mf_mc_category][".$key."]'".checked(in_array($key, $field_value), true, false)."> ".$value."</label>";

					$arr_categories2 = $obj_media->get_taxonomy(array('taxonomy' => $taxonomy, 'parent' => $key));

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
			//'helps' => __("", 'lang_media'),
			'html' => $html,
		);

		$html = "<ul class='term-list'>";

			$field_value = get_media_roles($post->ID);

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
			//'helps' => __("", 'lang_media'),
			'html' => $html,
		);
	}

	return $form_fields;
}

function attachment_save_media($post, $attachment)
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

function column_header_media($cols)
{
	if(IS_ADMIN && get_option('setting_media_activate_categories') == 'yes')
	{
		unset($cols['categories']);
		unset($cols['parent']);
		unset($cols['comments']);

		$cols['media_categories'] = __("Categories", 'lang_media');
		$cols['media_roles'] = __("Roles", 'lang_media');
	}

	return $cols;
}

function column_cell_media($col, $id)
{
	if(IS_ADMIN && get_option('setting_media_activate_categories') == 'yes')
	{
		switch($col)
		{
			case 'media_categories':
				$field_value = get_media_categories($id);

				$taxonomy = 'category';
				$obj_media = new mf_media();
				$arr_categories = $obj_media->get_taxonomy(array('taxonomy' => $taxonomy));

				$i = 0;

				foreach($arr_categories as $r)
				{
					$key = $r->term_id;
					$value = $r->name;

					if(in_array($key, $field_value))
					{
						echo ($i > 0 ? ", " : "").$value;

						$i++;
					}
				}
			break;

			case 'media_roles':
				$field_value = get_media_roles($id);

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
		}
	}
}