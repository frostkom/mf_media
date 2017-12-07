<?php

class mf_media
{
	function __construct()
	{
		$this->categories = array();
		$this->default_tab = 0;

		$this->meta_prefix = "mf_media_";
	}

	function print_media_templates()
	{
?>
		<script type="text/html" id="tmpl-attachment">
			<div class="attachment-preview js--select-attachment type-{{ data.type }} subtype-{{ data.subtype }} {{ data.orientation }}">
				<div class="thumbnail">
					<# if ( data.uploading ) { #>
						<div class="media-progress-bar"><div style="width: {{ data.percent }}%"></div></div>
					<# } else if ( 'image' === data.type && data.sizes ) { #>
						<div class="centered">
							<img src="{{ data.size.url }}" draggable="false" alt="" />
						</div>

						<# if(data.alt == '')
						{ #>
							<i class='fa fa-warning yellow fa-2x' title='<?php _e("The file has got no alt text. Please add this to improve your SEO.", 'lang_media'); ?>'></i>
						<# }

						else if(data.size.url.match(/[<?php echo __("aring", 'lang_media'). __("auml", 'lang_media').__("ouml", 'lang_media').__("Aring", 'lang_media').__("Auml", 'lang_media').__("Ouml", 'lang_media'); ?>]+/))
						{ #>
							<i class='fa fa-ban red fa-2x' title='<?php _e("The file has got special characters in the filename. Please change this.", 'lang_media'); ?>'></i>
						<# } #>
					<# }

					else { #>
						<div class="centered">
							<# if ( data.image && data.image.src && data.image.src !== data.icon ) { #>
								<img src="{{ data.image.src }}" class="thumbnail" draggable="false" alt="" />
							<# } else if ( data.sizes && data.sizes.medium ) { #>
								<img src="{{ data.sizes.medium.url }}" class="thumbnail" draggable="false" alt="" />
							<# } else { #>
								<img src="{{ data.icon }}" class="icon" draggable="false" alt="" />
							<# } #>
						</div>
						<div class="filename">
							<div>{{ data.filename }}</div>
						</div>
					<# } #>
				</div>
				<# if ( data.buttons.close ) { #>
					<button type="button" class="button-link attachment-close media-modal-icon"><span class="screen-reader-text"><?php _e( 'Remove' ); ?></span></button>
				<# } #>
			</div>
			<# if ( data.buttons.check ) { #>
				<button type="button" class="check" tabindex="-1"><span class="media-modal-icon"></span><span class="screen-reader-text"><?php _e( 'Deselect' ); ?></span></button>
			<# } #>
			<#
			var maybeReadOnly = data.can.save || data.allowLocalEdits ? '' : 'readonly';
			if ( data.describe ) {
				if ( 'image' === data.type ) { #>
					<input type="text" value="{{ data.caption }}" class="describe" data-setting="caption"
						placeholder="<?php esc_attr_e('Caption this image&hellip;'); ?>" {{ maybeReadOnly }} />
				<# } else { #>
					<input type="text" value="{{ data.title }}" class="describe" data-setting="title"
						<# if ( 'video' === data.type ) { #>
							placeholder="<?php esc_attr_e('Describe this video&hellip;'); ?>"
						<# } else if ( 'audio' === data.type ) { #>
							placeholder="<?php esc_attr_e('Describe this audio file&hellip;'); ?>"
						<# } else { #>
							placeholder="<?php esc_attr_e('Describe this media file&hellip;'); ?>"
						<# } #> {{ maybeReadOnly }} />
				<# }
			} #>
		</script>
<?php
	}

	//Clean filenames
	##########################
	function upload_filter($file)
	{
		if(get_option('setting_media_sanitize_files') == 'yes')
		{
			//$path = pathinfo($file['name']);
			//$file_suffix = $path['extension'];
			$file_suffix = get_file_suffix($file['name']);

			$file['name'] = sanitize_title(preg_replace("/.".$file_suffix."$/", '', $file['name'])).".".$file_suffix;
		}

	    return $file;
	}
	##########################

	function get_taxonomy($data)
	{
		global $wpdb;

		if(!isset($data['parent'])){	$data['parent'] = 0;}

		$result = $wpdb->get_results($wpdb->prepare("SELECT term_id, name FROM ".$wpdb->terms." INNER JOIN ".$wpdb->term_taxonomy." USING (term_id) WHERE taxonomy = %s AND parent = '%d' ORDER BY name ASC", $data['taxonomy'], $data['parent']));

		return $result;
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
			'pot|pps|ppt' =>		array($presentation_name." (PowerPoint)", "application/vnd.ms-powerpoint"),
			'pptx' =>				array($presentation_name." (PowerPoint pptx)", "application/vnd.openxmlformats-officedocument.presentationml.presentation"),
			'odp' =>				array($presentation_name." (Open)", "application/vnd.oasis.opendocument.presentation"),
			'xla|xls|xlt|xlw' =>	array($spreadsheet_name." (Excel)", "application/vnd.ms-excel"),
			'xlsx' =>				array($spreadsheet_name." (Excel xlsx)", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"),
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

	function get_categories()
	{
		global $wpdb;

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

	function get_files($cat_id, $role_id)
	{
		global $wpdb;

		$out = array();

		//$result = $wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->base_prefix."media2category INNER JOIN ".$wpdb->base_prefix."media2role USING (fileID) WHERE categoryID = '%d' AND roleKey = %s", $cat_id, $role_id));
		$result = $wpdb->get_results($wpdb->prepare("SELECT fileID, roleKey FROM ".$wpdb->base_prefix."media2category LEFT JOIN ".$wpdb->base_prefix."media2role USING (fileID) WHERE categoryID = '%d'", $cat_id));

		foreach($result as $r)
		{
			$intFileID = $r->fileID;
			$strRoleKey = $r->roleKey;

			if($strRoleKey == $role_id)
			{

			}

			else if($strRoleKey == '')
			{

			}

			else
			{
				$wpdb->get_results($wpdb->prepare("SELECT fileID FROM ".$wpdb->base_prefix."media2role WHERE roleKey = %s LIMIT 0, 1", $role_id));

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
							<td>".get_file_icon($file_url)."</td>
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
		mf_enqueue_script('script_base_settings', plugins_url()."/mf_base/include/script_settings.js", array('default_tab' => $this->default_tab, 'settings_page' => false), get_plugin_version(__FILE__));

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

/** Custom walker for wp_dropdown_categories, based on https://gist.github.com/stephenh1988/2902509 */
/*class walker_category_filter extends Walker_CategoryDropdown
{
	function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0)
	{
		$pad = str_repeat('&nbsp;', $depth * 3);
		$cat_name = apply_filters('list_cats', $category->name, $category);

		if(!isset($args['value']))
		{
			//$args['value'] = $category->taxonomy != 'category' ? 'slug' : 'id';
			$args['value'] = 'id';
		}

		$value = $args['value'] == 'slug' ? $category->slug : $category->term_id;

		if(0 == $args['selected'] && isset($_GET['category_media']) && '' != $_GET['category_media'])
		{
			$args['selected'] = $_GET['category_media'];
		}

		$output .= "<option class='level-".$depth."' value='".$value."'";

			if($value === (string) $args['selected'])
			{
				$output .= " selected";
			}

		$output .= ">"
			.$pad.$cat_name;

			if($args['show_count'])
			{
				$output .= "&nbsp;&nbsp;(".$category->count.")";
			}

		$output .= "</option>";
	}
}*/

/** Custom walker for wp_dropdown_categories for media grid view filter */
/*class walker_media_category extends Walker_CategoryDropdown
{
	function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0)
	{
		$pad = str_repeat('&nbsp;', $depth * 3);

		$cat_name = apply_filters('list_cats', $category->name, $category);

		$output .= ", {'term_id': '".$category->term_id."', 'term_name': '".$pad.esc_attr($cat_name);

		if($args['show_count'])
		{
			$output .= "&nbsp;&nbsp;(".$category->count.")";
		}

		$output .= "'}";
	}
}*/

/** Custom walker for wp_dropdown_categories for media grid view filter */
/*class walker_media_taxonomy extends Walker
{
	var $tree_type = 'category';

	var $db_fields = array(
		'parent' => 'parent',
		'id'     => 'term_id'
	);

	function start_lvl(&$output, $depth = 0, $args = array())
	{
		$output .= str_repeat("\t", $depth)."<ul class='children'>";
	}

	function end_lvl(&$output, $depth = 0, $args = array())
	{
		$output .= str_repeat("\t", $depth)."</ul>";
	}

	function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0)
	{
		extract($args);

		$taxonomy = 'category';
		//$taxonomy = apply_filters('mf_mc_taxonomy', $taxonomy);

		$name = 'tax_input['.$taxonomy.']';

		$class = in_array($category->term_id, $popular_cats) ? " class='popular-category'" : "";

		$output .= "<li id='".$taxonomy."-".$category->term_id."'".$class.">
			<label class='selectit'>
				<input type='checkbox' name='".$name."[".$category->slug."]' value='".$category->slug."' id='in-".$taxonomy."-".$category->term_id."'"
					.checked(in_array($category->term_id, $selected_cats), true, false)
					//.disabled(empty($args['disabled']), false, false)
				."> ".esc_html(apply_filters('the_category', $category->name))
			."</label>";
	}

	function end_el(&$output, $category, $depth = 0, $args = array())
	{
		$output .= "</li>";
	}
}*/