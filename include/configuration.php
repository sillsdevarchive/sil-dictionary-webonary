<?php
/**
 * Set up the SIL Dictionary in WordPress Dashboard Tools
 */
function add_admin_menu() {
	add_menu_page( "Webonary", "Webonary", true, "webonary", "webonary_conf_dashboard",  get_bloginfo('wpurl') . "/wp-content/plugins/sil-dictionary-webonary/images/webonary-icon.png", 76 );
}

function get_admin_sections() {
	//$q_config['admin_sections'] = array();
	//$admin_sections = &$q_config['admin_sections'];
	$admin_sections = array();

	$admin_sections['general'] = __('General', 'sil_dictionary');
	$admin_sections['import'] = __('Data (Import)', 'sil_dictionary');
	/*
	$admin_sections['browse'] = __('Browse Views/Alphabet', 'sil_dictionary');
	$admin_sections['fonts'] = __('Fonts', 'sil_dictionary');
	*/
	
	return $admin_sections;
}

function admin_section_start($nm) {
	echo '<div id="tab-'.$nm.'" class="hidden">'.PHP_EOL;
}

function admin_section_end($nm, $button_name=null, $button_class='button-primary') {
	if(!$button_name) $button_name = __('Save Changes', 'sil_dictionary');
	echo '<p class="submit"><input type="submit" name="submit"';
	if($button_class) echo ' class="'.$button_class.'"';
	echo ' value="'.$button_name.'" /></p>';
	echo '</div>'.PHP_EOL; //'<!-- id="tab-'.$nm.'" -->';
}

/**
 * Do what the user said to do.
 */
function save_configurations() {
	global $wpdb;

	if ( ! empty( $_POST['delete_data'])) {
		clean_out_dictionary_data();
	}
	if ( ! empty( $_POST['save_settings'])) {
		update_option("publicationStatus", $_POST['publicationStatus']);
		update_option("include_partial_words", $_POST['include_partial_words']);
		$displaySubentriesAsMainEntries = 'no';
		if(isset($_POST['DisplaySubentriesAsMainEntries']))
		{
			$displaySubentriesAsMainEntries = 1;
		}
		update_option("DisplaySubentriesAsMainEntries", $displaySubentriesAsMainEntries);
		update_option("languagecode", $_POST['languagecode']);
		update_option("vernacular_alphabet", $_POST['vernacular_alphabet']);
		 
		$IncludeCharactersWithDiacritics = 'no';
		if(isset($_POST['IncludeCharactersWithDiacritics']))
		{
			$IncludeCharactersWithDiacritics = 1;
		}
		update_option("IncludeCharactersWithDiacritics", $IncludeCharactersWithDiacritics);
		 
		update_option("reversalType", $_POST['reversalType']);
		update_option("reversal1_langcode", $_POST['reversal1_langcode']);
		update_option("reversal1_alphabet", $_POST['reversal1_alphabet']);
		update_option("reversal2_alphabet", $_POST['reversal2_alphabet']);
		update_option("reversal2_langcode", $_POST['reversal2_langcode']);

		if(trim(strlen($_POST['txtVernacularName'])) == 0)
		{
			echo "<br><span style=\"color:red\">Please fill out the textfields for the language names, as they will appear in a dropdown below the searcbhox.</span><br>";
		}

		$arrLanguages[0]['name'] = "txtVernacularName";
		$arrLanguages[0]['code'] = "languagecode";
		$arrLanguages[1]['name'] = "txtReversalName";
		$arrLanguages[1]['code'] = "reversal_langcode";
		$arrLanguages[2]['name'] = "txtReversal2Name";
		$arrLanguages[2]['code'] = "reversal2_langcode";

		foreach($arrLanguages as $language)
		{
			if(strlen(trim($_POST[$language['code']])) != 0)
			{
				$sql = "INSERT INTO  $wpdb->terms (name,slug) VALUES ('" . $_POST[$language['name']] . "','" . $_POST[$language['code']] . "')
		  		ON DUPLICATE KEY UPDATE name = '" . $_POST[$language['name']]  . "'";

				$wpdb->query( $sql );

				$lastid = $wpdb->insert_id;

				if($lastid != 0)
				{
					$sql = "INSERT INTO  $wpdb->term_taxonomy (term_id, taxonomy,description,count) VALUES (" . $lastid . ", 'sil_writing_systems', '" . $_POST[$language['name']] . "',999999)
			  		ON DUPLICATE KEY UPDATE description = '" . $_POST[$language['name']]  . "'";

					$wpdb->query( $sql );
				}
			}
		}

		echo "<br>" . _e('Settings saved');
	}
}

function webonary_conf_dashboard() {
	save_configurations();
	//wp_enqueue_script( 'admin-options', plugins_url( 'js/options.js', __FILE__ ), array());
	?>
	<script src="<?php echo get_bloginfo('wpurl'); ?>/wp-content/plugins/sil-dictionary-webonary/js/options.js" type="text/javascript"></script>
	<div class="wrap">
	<h2><?php _e( 'Webonary', 'webonary' ); ?></h2>
	<?php _e('Webonary provides the admininstration tools and framework for using WordPress for dictionaries.<br>See <a href="http://www.webonary.org/help" target="_blank">Webonary Support</a> for help.', 'sil_dictionary'); ?>
	
	<?php
	$admin_sections = get_admin_sections();
	echo '<h2 class="nav-tab-wrapper">'.PHP_EOL;
	foreach( $admin_sections as $slug => $name ){
		echo '<a class="nav-tab" href="#'.$slug.'" title="'.sprintf(__('Click to switch to %s', 'sil_dictionary'), $name).'">'.$name.'</a>'.PHP_EOL;
	}
	echo '</h2>'.PHP_EOL;
	
	$arrLanguageCodes = get_LanguageCodes();
	
	// enctype="multipart/form-data"
	?>
	<script>
	function getLanguageName(selectbox, langname)
	{
		var e = document.getElementById(selectbox);
		var langcode = e.options[e.selectedIndex].value;

		jQuery.ajax({
     		url: '<?php echo admin_url('admin-ajax.php'); ?>',
     		data : {action: "getAjaxlanguage", languagecode : langcode},
     		type:'POST',
     		dataType: 'html',
     		success: function(output_string){
        		jQuery('#' + langname).val(output_string);
     		}
	 })
	}
	</script>
		<div id="icon-tools" class="icon32"></div>
		<form id="configuration-form" method="post" action="">
			<?php
			/*
			 * Standard UI
			 */
			if ( empty( $_POST['delete_data'] ) ) {
				?>
				<div class="tabs-content"><?php //<!-- tabs-container --> ?>
				<?php
				//////////////////////////////////////////////////////////////////////////////
				admin_section_start('general');
				?>
				
				<h3><?php _e('Settings');?></h3>
				<p>
				<?php _e('Publication status:'); ?>
				<select name=publicationStatus>
					<option value=0><?php _e('no status set'); ?></option>
					<option value=1 <?php selected(get_option('publicationStatus'), 1); ?>><?php _e('Rough draft'); ?></option>
					<option value=2 <?php selected(get_option('publicationStatus'), 2); ?>><?php _e('Self-reviewed draft'); ?></option>
					<option value=3 <?php selected(get_option('publicationStatus'), 3); ?>><?php _e('Community-reviewed draft'); ?></option>
					<option value=4 <?php selected(get_option('publicationStatus'), 4); ?>><?php _e('Consultant approved'); ?></option>
					<option value=5 <?php selected(get_option('publicationStatus'), 5); ?>><?php _e('Finished (no formal publication)'); ?></option>
					<option value=6 <?php selected(get_option('publicationStatus'), 6); ?>><?php _e('Formally published'); ?></option>
				</select>
				<p>
				<b><?php _e('Search Options:'); ?></b>
				<p>
				<input name="include_partial_words" type="checkbox" value="1"
							<?php checked('1', get_option('include_partial_words')); ?> />
							<?php _e('Always include searching through partial words.'); ?>
				<p>
				<h3>Fonts</h3>
				<p>
				<?php
				$upload_dir = wp_upload_dir();
				
				$fontClass = new fontMonagment();
				$css_string = file_get_contents($upload_dir['baseurl'] . '/imported-with-xhtml.css');
				$arrUniqueCSSFonts = $fontClass->get_fonts_fromCssText($css_string);
				
				$fontFacesFile = file_get_contents($upload_dir['baseurl'] . '/custom.css');
				$arrFontFacesFile = $fontClass->get_fonts_fromCssText($fontFacesFile);
				
				$options = get_option('themezee_options');
				$arrFontFacesZeeOptions = $fontClass->get_fonts_fromCssText($options['themeZee_custom_css']);

				foreach($arrUniqueCSSFonts as $userFont)
				{
					$userFont = trim($userFont);
					
					if(!strstr($userFont, "default font"))
					{
						echo "<strong>" . $userFont . "</strong><br>";
						$fontLinked = false;
						if(count($arrFontFacesFile) > 0)
						{
							if(in_array($userFont, $arrFontFacesFile))
							{
								$fontLinked = true;
								echo "linked in <a href=\"" . $upload_dir['baseurl'] . "/custom.css\">custom.css</a><br>";
							}
						}
						if(count($arrFontFacesZeeOptions) > 0)
						{
							if(in_array($userFont, $arrFontFacesZeeOptions))
							{
								$fontLinked = true;
								echo "linked in <a href=\"wp-admin/themes.php?page=themezee\">zeeDisplay Options</a><br>";
							}
						}
						if(!$fontLinked)
						{
							$arrSystemFonts = $fontClass->get_system_fonts();
							if(in_array($userFont, $arrSystemFonts))
							{
								echo "This is a system font that most computers already have installed.";
							}
							else
							{
								echo "<strong style=\"color:red;\">Font not linked. Please contact the Webonary support team.</strong>";
							}
						}
						echo "<p></p>";
					}
				}
				?>
				<p>
				<h3>Browse Views</h3>
				<p>
				<?php
				$DisplaySubentriesAsMainEntries = get_option('DisplaySubentriesAsMainEntries');
				if($DisplaySubentriesAsMainEntries == 1)
				{
				?>
				<input name="DisplaySubentriesAsMainEntries" type="checkbox" value="1"
							<?php checked('1', $DisplaySubentriesAsMainEntries); ?> />
							<?php _e('Display subentries as main entries'); ?>
				<p>
				<?php
				}
				if(count($arrLanguageCodes) == 0)
				{
				?>
					<span style="color:red">You need to first import your xhtml file before you can select a language code.</span>
					<p>
				<?php
				}
				_e('Vernacular Language Code:'); ?>
				<select id=vernacularLanguagecode name="languagecode" onchange="getLanguageName('vernacularLanguagecode', 'vernacularName');">
					<option value=""></option>
					<?php
					$x = 0;
					foreach($arrLanguageCodes as $languagecode) {?>
						<option value="<?php echo $languagecode->language_code; ?>" <?php if(get_option('languagecode') == $languagecode->language_code) { $i = $x; ?>selected<?php }?>><?php echo $languagecode->language_code; ?></option>
					<?php
					$x++;
					} ?>
				</select>
				<?php _e('Language Name:'); ?> <input  id=vernacularName type="text" name="txtVernacularName" value="<?php if(count($arrLanguageCodes) > 0) { echo $arrLanguageCodes[$i]->name; } ?>">
				<p>
				<?php _e('Vernacular Alphabet:'); ?>
				<input name="vernacular_alphabet" type="text" size=50 value="<?php echo stripslashes(get_option('vernacular_alphabet')); ?>" />
				<?php _e('(Letters separated by comma)'); ?>
				<p>
				<?php
				$IncludeCharactersWithDiacritics = get_option('IncludeCharactersWithDiacritics');
				if($IncludeCharactersWithDiacritics != "no" && !isset($IncludeCharactersWithDiacritics))
				{
					$IncludeCharactersWithDiacritics = 1;
				}
				?>
				<input name="IncludeCharactersWithDiacritics" type="checkbox" value="1" <?php checked('1', $IncludeCharactersWithDiacritics); ?> />
				<?php _e('Include characters with diacritics (e.g. words starting with ä, à, etc. will all display under a)')?>
				<p>
				<b><?php _e('Reversal Indexes:'); ?></b>
				<p>
				<?php
				$displayXHTML = true;
				getReversalEntries("", 0, get_option('reversal1_langcode'), $displayXHTML);
				_e('Display:'); ?>
				<select name="reversalType">
					<option value="full">Full FLEx Reversal view</option>
					<option value="minimal" <?php if(!$displayXHTML || get_option("reversalType") == "minimal") { echo "selected"; }?>>Minimal Index view</option>
				</select>
				<p>
				<?php _e('Main reversal index code:'); ?>
				<select id=reversalLangcode name="reversal1_langcode" onchange="getLanguageName('reversalLangcode', 'reversalName');">
					<option value=""></option>
					<?php
						$x = 0;
						foreach($arrLanguageCodes as $languagecode) {?>
						<option value="<?php echo $languagecode->language_code; ?>" <?php if(get_option('reversal1_langcode') == $languagecode->language_code) { $k = $x; ?>selected<?php }?>><?php echo $languagecode->language_code; ?></option>
					<?php
						$x++;
						} ?>
				</select>
				<?php _e('Language Name:'); ?> <input id=reversalName type="text" name="txtReversalName" value="<?php if(count($arrLanguageCodes) > 0) { echo $arrLanguageCodes[$k]->name; } ?>">
				<p>
				<?php
				if(strlen(trim(stripslashes(get_option('reversal1_alphabet')))) == 0)
				{
					$reversal1alphabet = "";
					$alphas = range('a', 'z');
					$i = 1;
					foreach($alphas as $letter)
					{
						$reversal1alphabet .= $letter;
						if($i != count($alphas))
						{
							$reversal1alphabet .= ",";
						}
						$i++;
					}
				}
				else
				{
					$reversal1alphabet = stripslashes(get_option('reversal1_alphabet'));
				}
				?>
				<?php _e('Main Reversal Index Alphabet:'); ?>
				<input name="reversal1_alphabet" type="text" size=50 value="<?php echo $reversal1alphabet; ?>" />
				<?php _e('(Letters separated by comma)'); ?>
				<hr>
				 <i><?php _e('If you have a second reversal index, enter the information here:'); ?></i>
				 <p>
				<?php _e('Secondary reversal index code:'); ?>
				<select id=reversal2Langcode name="reversal2_langcode" onchange="getLanguageName('reversal2Langcode', 'reversal2Name');">
					<option value=""></option>
					<?php
					$x = 0;
					foreach($arrLanguageCodes as $languagecode) {?>
						<option value="<?php echo $languagecode->language_code; ?>" <?php if(get_option('reversal2_langcode') == $languagecode->language_code) { $n = $x; ?>selected<?php }?>><?php echo $languagecode->language_code; ?></option>
					<?php
					$x++;
					} ?>
				</select>
				<?php _e('Language Name:'); ?> <input id=reversal2Name type="text" name="txtReversal2Name" value="<?php if(count($arrLanguageCodes) > 0) { echo $arrLanguageCodes[$n]->name; } ?>">
				<p>
				<?php _e('Secondary Reversal Index Alphabet:'); ?>
				<input name="reversal2_alphabet" type="text" size=50 value="<?php echo stripslashes(get_option('reversal2_alphabet')); ?>" />
				<?php _e('(Letters separated by comma)'); ?>
				<p>
				<input type="submit" name="save_settings" value="<?php _e('Save', 'sil_dictionary'); ?>">
				</p>
				<?php
				/*
				?>
				<h3><?php _e('Comments');?></h3>
				If you have the comments turned on, you need to re-sync your comments after re-importing of your posts.
				<p>
				<a href="admin.php?import=comments-resync">Re-sync comments</a>
				<?php
				*/
				}

			/*
			 * Delete finished
			 */
			else {
				?>
				<p>
					<?php _e('Finished!', 'sil_dictionary'); ?>
					<input type="submit" name="finished_deleting" value="<?php _e('OK', 'sil_dictionary'); ?>">
				</p>
				<?php
			}

		?>
		<?php admin_section_end('general'); ?>

		<?php
		//////////////////////////////////////////////////////////////////////////////
		admin_section_start('import');
		?>
		
		<h3><?php _e( 'Import Data', 'sil_dictionary' ); ?></h3>
		<p><?php _e('You can find the <a href="admin.php?import=pathway-xhtml">SIL FLEX XHTML importer</a> by clicking on Import under the Tools menu.', 'sil_dictionary'); ?></p>
		<p><?php _e('Each dictionary entry is stored in a "post." You will find the entries in the Posts menu.', 'sil_dictionary'); ?></p>

		<h3><?php _e( 'Delete Data', 'sil_dictionary' ); ?></h3>
		<?php if(strpos($_SERVER['HTTP_HOST'], 'localhost') === false && is_super_admin()) { ?>
			<strong style=color:red;>You are not in your testing environment!</strong>
			<br>
		<?php } ?>
		<p><?php _e('Lists are kept unless you check the following:'); ?><br>
			<label for="delete_taxonomies">
				<input name="delete_taxonomies" type="checkbox" id="delete_taxonomies" value="1"
					<?php checked('1', get_option('delete_taxonomies')); ?> />
				<?php _e('Delete lists such as Part of Speech?') ?>
				<?php /*
				<input name="delete_allposts" type="checkbox" id="delete_allposts" value="1"
					<?php checked('1', get_option('delete_allposts')); ?> />
				<?php _e('Delete all posts, including the ones not in category "webonary" (legacy function)') */ ?>
			</label>
			<label for="delete_pages">
				<!--<input name="delete_pages" type="checkbox" id="delete_pages" value="1"
					<?php checked('1', get_option('delete_pages')); ?> />-->
			</label><br />
			<?php _e('Are you sure you want to delete the dictionary data?', 'sil_dictionary'); ?>
			<input type="submit" name="delete_data" value="<?php _e('Delete', 'sil_dictionary'); ?>">
			<br>
			<?php _e('(deletes all posts in the category "webonary")', 'sil_dictionary'); ?>
		</p>

		<?php admin_section_end('import'); ?>
		</div><?php //<!-- /tabs-container --> ?>
		</form>
	</div>
	<?php
}

