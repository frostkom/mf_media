<?php

$obj_media = new mf_media();
$obj_media->get_categories();

echo "<div class='wrap'>
	<h2>".__("Files", 'lang_media')."</h2>"
	.$obj_media->show_categories()
	.$obj_media->show_files()
."</div>";