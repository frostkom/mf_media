<?php

class mf_media
{
	function __construct()
	{
		$this->categories = array();
		$this->default_tab = 0;

		$this->meta_prefix = "mf_media_";
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

	function init()
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

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			$arr_settings['setting_show_admin_menu'] = __("Show admin menu with categories and files", 'lang_media');
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

	function admin_init()
	{
		global $pagenow;

		if(get_option('setting_media_activate_categories') == 'yes')
		{
			if($pagenow == 'upload.php' || $pagenow == 'admin.php' && substr(check_var('page'), 0, 9) == 'int_page_') //wp_script_is('media-editor') && 
			{
				$plugin_include_url = plugin_dir_url(__FILE__);
				$plugin_version = get_plugin_version(__FILE__);

				mf_enqueue_style('style_media', $plugin_include_url."style_wp.css", $plugin_version);

				/*$taxonomy = 'category';

				$this->get_categories();

				$attachment_terms = $this->get_categories_options();

				$current_media_category = get_user_meta(get_current_user_id(), 'meta_current_media_category', true);

				mf_enqueue_script('script_media', $plugin_include_url."script_wp.js", array(
					'taxonomy' => $taxonomy,
					'list_title' => "-- ".__("View all categories", 'lang_media')." --",
					'term_list' => "[".$attachment_terms."]",
					'terms_test' => get_terms($taxonomy, array('hide_empty' => false)),
					'current_media_category' => $current_media_category
				), $plugin_version);*/
			}
		}
	}

	function admin_menu()
	{
		$menu_root = 'mf_media/';
		$menu_start = $menu_root.'list/index.php';
		$menu_capability = 'read';

		if(get_option('setting_show_admin_menu') == 'yes')
		{
			$menu_title = __("Files", 'lang_media');

			add_menu_page($menu_title, $menu_title, $menu_capability, $menu_start, '', 'dashicons-admin-media', 11);
		}

		if(IS_ADMIN)
		{
			$menu_title = __("Allowed Types", 'lang_media');

			add_submenu_page("upload.php", $menu_title, $menu_title, $menu_capability, "edit.php?post_type=mf_media_allowed");
		}
	}

	function upload_mimes($existing_mimes = array())
	{
		global $wpdb;

		//$obj_media = new mf_media();
		$arr_types = $this->get_media_types(array('type' => 'mime'));

		$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_media_allowed' AND post_status = 'publish'");

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
			'post_types' => array('mf_media_allowed'),
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

		return $meta_boxes;
	}

	function column_header($cols)
	{
		if(IS_ADMIN && get_option('setting_media_activate_categories') == 'yes')
		{
			unset($cols['author']);
			unset($cols['date']);
			unset($cols['categories']);
			unset($cols['parent']);
			unset($cols['comments']);

			$cols['media_categories'] = __("Categories", 'lang_media');
			$cols['media_roles'] = __("Roles", 'lang_media');
		}

		return $cols;
	}

	function column_cell($col, $id)
	{
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
		}
	}

	function restrict_manage_posts()
	{
		global $post_type, $wpdb;

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
				/*$wp_query->query_vars['meta_query'] = array(
					array(
						'key' => $this->meta_prefix.'calendar',
						'value' => $strFilterAttachmentCategory,
						'compare' => '=',
					),
				);*/
			}
		}
	}

	function column_header_allowed($cols)
	{
		unset($cols['date']);

		$cols['action'] = __("Action", 'lang_media');
		$cols['role'] = __("Role", 'lang_media');
		$cols['types'] = __("Types", 'lang_media');

		return $cols;
	}

	function column_cell_allowed($col, $id)
	{
		switch($col)
		{
			case 'action':
				//$obj_media = new mf_media();

				$arr_actions = $this->get_media_actions();

				$post_meta = get_post_meta($id, $this->meta_prefix.$col, true);

				echo $arr_actions[$post_meta];
			break;

			case 'role':
				//$obj_media = new mf_media();

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
				//$obj_media = new mf_media();

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
							do_log(sprintf(__("The mime type '%s' does not exist", 'lang_media'), $post_meta));
						}
					}
				}
			break;
		}
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
				$intCategoryID = isset($_REQUEST['query']['category']) ? check_var($_REQUEST['query']['category'], 'int', false) : 0;
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
							<# } #>
<?php
							/*<# else if(data.size.url.match(/[aring|auml|ouml|Aring|Auml|Ouml]+/))
							{ #>
								<i class='fa fa-ban red fa-2x' title='<?php echo __("The file has got special characters in the filename. Please change this.", 'lang_media'); ?>'></i>
							<# } #>*/
?>
						<# }

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

	function attachment_fields_to_edit($form_fields, $post)
	{
		global $wpdb;

		if(IS_ADMIN && get_option('setting_media_activate_categories') == 'yes')
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
				//'helps' => __("", 'lang_media'),
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
				//'helps' => __("", 'lang_media'),
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
			'apk' =>				array(__("APK", 'lang_media'), "application/vnd.android.package-archive"),

			'ico' =>				array(__("Icon", 'lang_media'), "image/x-icon"),
			'svg' =>				array(__("SVG", 'lang_media'), "image/svg+xmln"),
			'jpg|jpeg|jpe' =>		array($image_name." (JPEG)", "image/jpeg"),
			'gif' =>				array($image_name." (GIF)", "image/gif"),
			'png' =>				array($image_name." (PNG)", "image/png"),
			'bmp' =>				array($image_name." (BMP)", "image/bmp"),
			'tiff|tif' =>			array($image_name." (TIFF)", "image/tiff"),

			'css' =>				array(__("Stylesheet", 'lang_media'), "text/css"),
			'js' =>					array(__("Javascript", 'lang_media'), "application/javascript"),
			'htm|html' =>			array(__("HTML", 'lang_media'), "text/html"),

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

			'pdf' =>				array(__("PDF", 'lang_media'), "application/pdf"),
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

			//'txt|asc|c|cc|h|srt' => __("Plain text", 'lang_media'), "text/plain
			//'csv' => __("CSV", 'lang_media'), "text/csv
			//'tsv' => __("", 'lang_media'), "text/tab-separated-values
			//'rtf' => __("", 'lang_media'), "application/rtf
			//'ics' => __("Calendar", 'lang_media'), "text/calendar
			//'rtx' => __("", 'lang_media'), "text/richtext
			//'vtt' => __("", 'lang_media'), "text/vtt

			//'dfxp' => __("", 'lang_media'), "application/ttaf+xml
		);

		/*'wax' => 'audio/x-ms-wax', 'mka' => 'audio/x-matroska', 'class' => 'application/java', 'xcf' => 'application/octet-stream', 'wri' => 'application/vnd.ms-write', 'mdb' => 'application/vnd.ms-access', 'mpp' => 'application/vnd.ms-project', 'docm' => 'application/vnd.ms-word.document.macroEnabled.12', 'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'dotm' => 'application/vnd.ms-word.template.macroEnabled.12', 'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12', 'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12', 'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template', 'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12', 'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12', 'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12', 'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template', 'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12', 'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12', 'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide', 'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12', 'onetoc|onetoc2|onetmp|onepkg' => 'application/onenote', 'oxps' => 'application/oxps', 'xps' => 'application/vnd.ms-xpsdocument', 'odg' => 'application/vnd.oasis.opendocument.graphics', 'odc' => 'application/vnd.oasis.opendocument.chart', 'odb' => 'application/vnd.oasis.opendocument.database', 'odf' => 'application/vnd.oasis.opendocument.formula', 'wp|wpd' => 'application/wordperfect', 'key' => 'application/vnd.apple.keynote', 'numbers' => 'application/vnd.apple.numbers', 'pages' => 'application/vnd.apple.pages', */

		$arr_types = array();

		foreach($arr_types_raw as $key => $value)
		{
			$arr_types[$key] = $data['type'] == 'name' ? $value[0] : $value[1];
		}

		return array_sort(array('array' => $arr_types, 'on' => 1, 'keep_index' => true));
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

			if($data['only_used'] == true)
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

					if($data['only_used'] == true)
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
		global $wpdb;

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

		$result = $wpdb->get_results($wpdb->prepare("SELECT fileID, roleKey FROM ".$wpdb->prefix."media2category LEFT JOIN ".$wpdb->prefix."media2role USING (fileID) WHERE categoryID = '%d'", $cat_id));

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
		$out = "";

		$out .= "<table id='category_".$cat_id."' class='nav-target mf_media_category widefat striped'>
			<tbody>";

				if(is_array($cat_files))
				{
					foreach($cat_files as $file_id)
					{
						list($file_name, $file_url) = get_attachment_data_by_id($file_id);

						$out .= "<tr>
							<td>".get_file_icon(array('file' => $file_url))."</td>
							<td><a href='".$file_url."'>".$file_name."</a></td>
						</tr>";
					}
				}

				else
				{
					$out .= $cat_files;
				}

			$out .= "</tbody>
		</table>";

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

	function show_categories()
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
		mf_enqueue_script('script_base_settings', plugins_url()."/mf_base/include/script_settings.js", array('default_tab' => $this->default_tab, 'settings_page' => false), get_plugin_version(__FILE__)); //Should be placed in admin_init

		$out = "";

		if(count($this->categories) > 0)
		{
			foreach($this->categories as $cat_id => $cat_array)
			{
				//$cat_id = $this->get_first_sub_category($cat_id);

				$out .= $this->get_file_container($cat_id, $cat_array['name'], $cat_array['files']);

				foreach($cat_array['sub'] as $cat_id2 => $cat_array2)
				{
					$out .= $this->get_file_container($cat_id2, $cat_array2['name'], $cat_array2['files']);
				}
			}
		}

		return $out;
	}
}