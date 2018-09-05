<?php
/**
 * Search
 *
 * Search functions for SIL Dictionaries.
 *
 * PHP version 5.2
 *
 * LICENSE GPL v2
 *
 * @package WordPress
 * @since 3.1
 */

// This file was originally based upon the Search Custom Fields plugin and template
// (search-custom.php) by Kaf Oseo. http://guff.szub.net/search-custom-fields/.
// The code has since been mangled and evolved beyond recognition from that.

// don't load directly
if ( ! defined('ABSPATH') )
	die( '-1' );

function SearchFilter($query) {
	// If 's' request variable is set but empty
	if(isset($_GET['s']))
	{
		if (strlen(trim($_GET['s'])) == 0){
			$query->query_vars['s'] = NULL;
		}
	}

	return $query;
}
add_filter('pre_get_posts','SearchFilter');

//---------------------------------------------------------------------------//
function sil_dictionary_select_fields() {
	global $wp_query, $wpdb;
	$search_table_name = SEARCHTABLE;

	if( !is_page())
	{
		$upload_dir = wp_upload_dir();
		wp_register_style('configured_stylesheet', $upload_dir['baseurl'] . '/imported-with-xhtml.css?time=' . date("U"));
		$overrides_css = $upload_dir['baseurl'] . '/ProjectDictionaryOverrides.css';
		if(file_exists($overrides_css))
		{
			wp_register_style('overrides_stylesheet', $overrides_css . '?time=' . date("U"));
		}
		wp_enqueue_style( 'configured_stylesheet');

		if(file_exists($upload_dir['basedir'] . '/ProjectDictionaryOverrides.css'))
		{
			wp_register_style('overrides_stylesheet', $upload_dir['baseurl'] . '/ProjectDictionaryOverrides.css?time=' . date("U"));
			wp_enqueue_style( 'overrides_stylesheet');
		}

	}

	if(  !empty($wp_query->query_vars['s']) && isset($wp_query->query_vars['letter']))
	{
		return $wpdb->posts.".*, " . $search_table_name . ".search_strings";
	}
	else
	{
		return $wpdb->posts.".*";
	}
}
function sil_dictionary_select_distinct() {
	return "DISTINCTROW";
}

//---------------------------------------------------------------------------//

function sil_dictionary_custom_join($join) {
	global $wp_query, $wpdb;
	$search_table_name = SEARCHTABLE;

	/*
	 * The query I'm going for will hopefully end up looking something like this
	 * example:
	 * SELECT id, language_code, relevance, post_title
	 * FROM wp_posts p
	 * JOIN (
	 *	SELECT post_id, language_code, MAX(relevance) AS relevance, search_strings
	 *	FROM sil_multilingual_search
	 *	WHERE search_strings like '%sleeping%'
	 *	GROUP BY post_id, language_code
	 *	ORDER BY relevance DESC
	 *	) sil_multilingual_search ON sil_multilingual_search.post_id = p.id
	 * ORDER BY relevance DESC, post_title;
	 */
	mb_internal_encoding("UTF-8");
	if( !empty($wp_query->query_vars['s'])) {
		//search string gets trimmed and normalized to NFC
		if (class_exists("Normalizer", $autoload = false))
		{
			$normalization = Normalizer::FORM_C;
			if(get_option("normalization") == "FORM_D")
			{
				$normalization = Normalizer::FORM_D;
			}

			//$normalization = Normalizer::NFD;
			$search = normalizer_normalize(trim($wp_query->query_vars['s']), $normalization);
			//$search = normalizer_normalize(trim($wp_query->query_vars['s']), Normalizer::FORM_D);
		}
		else
		{
			$search = trim($wp_query->query_vars['s']);
		}
		$search = strtolower($search);

		$key = $_GET['key'];
		if(!isset($key))
		{
			$key = $wp_query->query_vars['langcode'];
		}
		$match_whole_words = 0;
		if(isset($_GET['match_whole_words']))
		{
			if($_GET['match_whole_words'] == 1)
			{
				$match_whole_words = 1;
			}
		}

		if(strlen($search) == 0 && $_GET['tax'] > 1)
		{
			$match_whole_words = 0;
		}

		$subquery_where = "";
		if( strlen( trim( $key ) ) > 0)
			$subquery_where .= " WHERE " . $search_table_name . ".language_code = '$key' ";
		$subquery_where .= empty( $subquery_where ) ? " WHERE " : " AND ";

		//by default d à, ä, etc. are handled as the same letters when searching
		$collateSearch = "";
		if(get_option('distinguish_diacritics') == 1)
		{
			$collateSearch = "COLLATE " . COLLATION . "_BIN"; //"COLLATE 'UTF8_BIN'";
		}

		if(isset($wp_query->query_vars['letter']))
		{
			$letter = addslashes(trim($wp_query->query_vars['letter']));
			$noletters = addslashes(trim($wp_query->query_vars['noletters']));

			//by default we use collate utf8_bin and à, ä, etc. are handled as different letters
			$collate = "COLLATE " . COLLATION . "_BIN"; //"COLLATE 'UTF8_BIN'";
			if(get_option('IncludeCharactersWithDiacritics') == 1)
			{
				$collate = "";
			}

			//$regex = "^(=|-|\\\*|~)?";
			//$subquery_where .= "(" . $search_table_name . ".search_strings REGEXP '" . $regex  . addslashes(strtolower($letter)) . "' " . $collate . " OR " . $search_table_name . ".search_strings REGEXP '" . $regex . addslashes(strtoupper($letter)) . "' " . $collate . ")" .
			if(get_has_browseletters() == 0)
			{
				$subquery_where .=  "(" . $search_table_name . ".search_strings LIKE '" . $letter . "%' " . $collate;
				$subquery_where .=  " OR " . $search_table_name . ".search_strings LIKE '-" . $letter . "%' " . $collate;
				$subquery_where .=  " OR " . $search_table_name . ".search_strings LIKE '*" . $letter . "%' " . $collate;
				$subquery_where .=  " OR " . $search_table_name . ".search_strings LIKE '=" . $letter . "%' " . $collate;
				$subquery_where .=  " OR " . $search_table_name . ".search_strings LIKE '" . $letter . "%'"  . $collate . ")";
				$subquery_where .= " AND ";
			}
			$subquery_where .= " relevance >= 95 AND language_code = '$key' ";

			$arrNoLetters = explode(",",  $noletters);
			foreach($arrNoLetters as $noLetter)
			{
				if(strlen($noLetter) > 0)
				{
					$subquery_where .= " AND " . $search_table_name . ".search_strings NOT LIKE '" . $noLetter ."%' " . $collate .
					" AND " . $search_table_name . ".search_strings NOT LIKE '" . strtoupper($noLetter) ."%' " . $collate;
				}
			}
		}

		//using search form
		if(!isset($wp_query->query_vars['letter']))
		{
			$match_accents = false;
			if(isset($_GET['match_accents']))
			{
				$match_accents = true;
			}

			$searchquery = $search;
			//this is for creating a regular expression that searches words with accents & composed characters by only using base characters
			if(preg_match('/([aeiou])/', $search) && $match_accents == false)
			{
				//first we add brackets around all letters that aren't a vowel, e.g. yag becomes (y)a(g)
				$searchquery = preg_replace('/(^[aeiou])/u', '($1)', $searchquery);
				//see https://en.wiktionary.org/wiki/Appendix:Variations_of_%22a%22
				//the mysql regular expression can't find words with  accented characters if we don't include them
				$searchquery = preg_replace('/([a])/u', '(à|ȁ|á|â|ấ|ầ|ẩ|ā|ä|ǟ|å|ǻ|ă|ặ|ȃ|ã|ą|ǎ|ȧ|ǡ|ḁ|ạ|ả|ẚ|a', $searchquery);
				$searchquery = preg_replace('/([e])/u', '(ē|é|ě|è|ȅ|ê|ę|ë|ė|ẹ|ẽ|ĕ|ȇ|ȩ|ḕ|ḗ|ḙ|ḛ|ḝ|ė|e', $searchquery);
				$searchquery = preg_replace('/([ε])/u', '(έ|ἐ|ἒ|ἑ|ἕ|ἓ|ὲ|ε', $searchquery);
				$searchquery = preg_replace('/([ɛ])/u', '(ɛ', $searchquery);
				$searchquery = preg_replace('/([ə])/u', '(ə', $searchquery);
				$searchquery = preg_replace('/([i])/u', '(ı|ī|í|ǐ|ĭ|ì|î|î|į|ï|ï|ɨ|i', $searchquery);
				$searchquery = preg_replace('/([o])/u', '(ō|ō̂|ṓ|ó|ǒ|ò|ô|ö|õ|ő|ṓ|ø|ǫ|ǫ́|ȱ|ṏ|ȯ|ꝍ|o', $searchquery);
				$searchquery = preg_replace('/([ɔ])/u', '(ɔ', $searchquery);
				$searchquery = preg_replace('/([u])/u', '(ū|ú|ǔ|ù|ŭ|û|ü|ů|ų|ũ|ű|ȕ|ṳ|ṵ|ṷ|ṹ|ṻ|ʉ|u', $searchquery);
				//for vowels we add [^a-z]* which will search for any character that comes after the normal character
				//one can't see it, but compoased characters actually consist of two characters, for instance the a in ya̧g
				$searchquery = preg_replace('/([aeiouɛεəɔ])/u', '$1)[^a-z^ ]*', $searchquery);
			}

			$searchquery = str_replace("'", "\'", $searchquery);

			if(mb_strlen($search) <= 3)
			{
				$match_whole_words = 1;
			}
			if(!isset($_GET['partialsearch']))
			{
				$partialsearch = get_option("include_partial_words");
				if($partialsearch == 1 && $_GET['match_whole_words'] == 0)
				{
					$match_whole_words = 0;
				}
			}
			else
			{
				if($_GET['partialsearch'] == 1)
				{
					$partialsearch = 1;
					$match_whole_words = 0;
				}
			}
			if(strlen($search) == 0 && $_GET['tax'] > 1)
			{
				$partialsearch = 1;
				$match_whole_words = 0;
			}

			if (is_CJK( $search ) || $match_whole_words == 0)
			{

				/* $subquery_where .= " LOWER(" . $search_table_name . ".search_strings) LIKE '%" .
					addslashes( $search ) . "%' " . $collateSearch; */
				$subquery_where .= " LOWER(" . $search_table_name . ".search_strings) REGEXP '" . $searchquery . "' " . $collateSearch;
			}
			else
			{
				if(mb_strlen($search) > 1)
				{
	            	$subquery_where .= $search_table_name . ".search_strings REGEXP '[[:<:]]" .
						$searchquery . "[[:digit:]]?[[:>:]]' " . $collateSearch;
				}
			}
			//echo $subquery_where . "<br>";
		}
		//if($_GET['tax'] < 1)
		//{
			$subquery =
				" (SELECT post_id, language_code, MAX(relevance) AS relevance, search_strings, sortorder " .
				"FROM " . $search_table_name .
				$subquery_where .
				" GROUP BY post_id, language_code, search_strings " .
				" ORDER BY relevance DESC) ";

			$join = " JOIN " . $subquery . $search_table_name . " ON $wpdb->posts.ID = " . $search_table_name . ".post_id ";
		//}
	}
	$tax = 0;
	if(isset($_GET['tax']))
	{
		$tax = $_GET['tax'];
	}
	if(isset($wp_query->query_vars['semdomain']))
	{
		if( $tax > 1 || strlen($wp_query->query_vars['semdomain']) > 0) {
			$join .= " LEFT JOIN $wpdb->term_relationships ON $wpdb->posts.ID = $wpdb->term_relationships.object_id ";
			$join .= " INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id ";
			if(get_option("useSemDomainNumbers") == 1) {
				$join .= " INNER JOIN $wpdb->terms ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id ";
			}
		}
	}
	if(isset($_GET['tax']))
	{
		if($_GET['tax'] > 1)
		{
			$join .= " INNER JOIN $wpdb->term_relationships ON $wpdb->posts.ID = $wpdb->term_relationships.object_id ";
			$join .= " INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id";
		}
	}

	return $join;
}

function sil_dictionary_custom_message()
{
	$match_whole_words = 0;
	if(isset($_GET['match_whole_words']))
	{
		if($_GET['match_whole_words'] == 1)
		{
			$match_whole_words = 1;
		}
	}

	$partialsearch = $_GET['partialsearch'];
	if(!isset($_GET['partialsearch']))
	{
		$partialsearch = get_option("include_partial_words");
	}

	mb_internal_encoding("UTF-8");
	if($partialsearch != 1)
	{
		if(!is_CJK($_GET['s']) && mb_strlen($_GET['s']) > 0 && (mb_strlen($_GET['s']) <= 3 || $match_whole_words == 1))
		{
			//echo getstring("partial-search-omitted");
			_e('Because of the brevity of your search term, partial search was omitted.', 'sil_dictionary');
			echo "<br>";
			$replacedQueryString = str_replace("match_whole_words=1", "match_whole_words=0", $_SERVER["QUERY_STRING"]);
			echo '<a href="?partialsearch=1&' . $replacedQueryString . '" style="text-decoration: underline;">'; _e('Click here to include searching through partial words.', 'sil_dictionary'); echo '</a>';
		}
	}
}

//---------------------------------------------------------------------------//

function sil_dictionary_custom_where($where) {
	global $wp_query, $wp_version, $wpdb;
	$search_table_name = SEARCHTABLE;
	if(isset($wp_query->query_vars['s']))
	{
		if( strlen(trim($wp_query->query_vars['s'])) > 0) {
			$search = $wp_query->query_vars['s'];
			$key = $_GET['key'];
			if(!isset($key))
			{
				$key = $wp_query->query_vars['langcode'];
			}
			$where = ($wp_version >= 2.1) ? ' AND post_type = \'post\' AND post_status = \'publish\'' : ' AND post_status = \'publish\'';

			$letter = addslashes(trim($wp_query->query_vars['letter']));
			if(strlen(trim($letter)) > 0)
			{
				$collate = "COLLATE " . COLLATION . "_BIN"; //"COLLATE 'UTF8_BIN'";
				if(get_option('IncludeCharactersWithDiacritics') == 1)
				{
					$collate = "";
				}

				if(get_has_browseletters() > 0)
				{
					$where .= " AND $wpdb->posts.post_content_filtered = '" . $letter ."' " . $collate . " AND $wpdb->posts.post_content_filtered != '' ";
				}
			}
		}
	}

	if(isset($wp_query->query_vars['letter']))
	{
		if($wp_query->query_vars['DisplaySubentriesAsMainEntries'] == false)
		{
			$where .= " AND " . $search_table_name. ".search_strings = " . $wpdb->posts . ".post_title ";
		}
	}

	if(isset($_GET['tax']))
	{
		if($_GET['tax'] > 1)
		{
			$wp_query->is_search = true;
			$where .= " AND $wpdb->term_taxonomy.term_id = " . $_GET['tax'];
		}
	}

	if(isset($wp_query->query_vars['semdomain']))
	{
		if(strlen($wp_query->query_vars['semdomain']) > 0)
		{
		$wp_query->is_search = true;
		$where .= " AND $wpdb->term_taxonomy.taxonomy = 'sil_semantic_domains'";
			if(get_option("useSemDomainNumbers") == 1) {
				$where .= " AND $wpdb->terms.slug  REGEXP '^" . $wp_query->query_vars['semnumber'] ."([-]|$)'";
			}
			else
			{
				$where .= " AND $wpdb->term_taxonomy.description = '" . $wp_query->query_vars['semdomain'] ."'";
			}
		}
	}

	return $where;
}

//---------------------------------------------------------------------------//

function sil_dictionary_custom_order_by($orderby) {
	global $wp_query, $wp_version, $wpdb;
	$search_table_name = SEARCHTABLE;

	$orderby = "";
	if(  !empty($wp_query->query_vars['s']) && !isset($wp_query->query_vars['letter'])) {
		$orderby = $search_table_name . ".relevance DESC, CHAR_LENGTH(" . $search_table_name . ".search_strings) ASC, ";
	}

	if( !empty($wp_query->query_vars['s']) && $_GET['tax'] < 1)
	{
		if(isset($wp_query->query_vars['letter']))
		{
			$orderby .= $search_table_name . ".sortorder ASC, " . $search_table_name . ".search_strings ASC";
		}
		else
		{
			$orderby .= "menu_order ASC, " . $search_table_name . ".search_strings ASC";
		}
		//$orderby .= " $wpdb->posts.post_title ASC";
	}

	if(isset($wp_query->query_vars['semdomain']) || isset($_GET['tax']))
	{
		if(strlen($wp_query->query_vars['semdomain']) > 0 || $_GET['tax'] > 1)
		{
			$orderby .= "menu_order ASC, post_title ASC";
		}
	}

	return $orderby;
}

//---------------------------------------------------------------------------//

/**
 * Does the string have Chinese, Japanese, or Korean characters?
 * @param <string> $string = string to check
 * @return <boolean> = whether the string has Chinese/Japanese/Korean characters.
 */
function is_CJK( $string ) {
    $regex = '/' . implode( '|', get_CJK_unicode_ranges() ) . '/u';
    return preg_match( $regex, $string );
}

//---------------------------------------------------------------------------//

/**
 * A function that returns Chinese/Japanese/Korean (CJK) Unicode code points
 * Slightly adapted from an answer by "simon" found at:
 * @link http://stackoverflow.com/questions/5074161/what-is-the-most-efficient-way-to-whitelist-utf-8-characters-in-php
 * @return array
 */
function get_CJK_unicode_ranges() {
    return array(
		"[\x{2E80}-\x{2EFF}]",      # CJK Radicals Supplement
		"[\x{2F00}-\x{2FDF}]",      # Kangxi Radicals
		"[\x{2FF0}-\x{2FFF}]",      # Ideographic Description Characters
		"[\x{3000}-\x{303F}]",      # CJK Symbols and Punctuation
		"[\x{3040}-\x{309F}]",      # Hiragana
		"[\x{30A0}-\x{30FF}]",      # Katakana
		"[\x{3100}-\x{312F}]",      # Bopomofo
		"[\x{3130}-\x{318F}]",      # Hangul Compatibility Jamo
		"[\x{3190}-\x{319F}]",      # Kanbun
		"[\x{31A0}-\x{31BF}]",      # Bopomofo Extended
		"[\x{31F0}-\x{31FF}]",      # Katakana Phonetic Extensions
		"[\x{3200}-\x{32FF}]",      # Enclosed CJK Letters and Months
		"[\x{3300}-\x{33FF}]",      # CJK Compatibility
		"[\x{3400}-\x{4DBF}]",      # CJK Unified Ideographs Extension A
		"[\x{4DC0}-\x{4DFF}]",      # Yijing Hexagram Symbols
		"[\x{4E00}-\x{9FFF}]",      # CJK Unified Ideographs
		"[\x{A000}-\x{A48F}]",      # Yi Syllables
		"[\x{A490}-\x{A4CF}]",      # Yi Radicals
		"[\x{AC00}-\x{D7AF}]",      # Hangul Syllables
		"[\x{F900}-\x{FAFF}]",      # CJK Compatibility Ideographs
		"[\x{FE30}-\x{FE4F}]",      # CJK Compatibility Forms
		"[\x{1D300}-\x{1D35F}]",    # Tai Xuan Jing Symbols
		"[\x{20000}-\x{2A6DF}]",    # CJK Unified Ideographs Extension B
		"[\x{2F800}-\x{2FA1F}]"     # CJK Compatibility Ideographs Supplement
    );
}

//---------------------------------------------------------------------------//

// I'm not sure this is being used.

function no_standard_sort($k) {
	global $wp_query;
	if(!empty($wp_query->query_vars['s'])) {
		$k->query_vars['orderby'] = 'none';
		$k->query_vars['order'] = 'none';
	}
}

function get_has_browseletters()
{
	global $wpdb;

	$sql = "SELECT COUNT(post_content_filtered) AS numberOfLetters " .
			" FROM " . $wpdb->posts .
			" WHERE pinged = 'linksconverted' AND post_content_filtered <> ''";

	return $wpdb->get_var($sql);
}

function get_has_reversalbrowseletters()
{
	global $wpdb;

	$sql = "SELECT COUNT(browseletter) AS numberOfLetters " .
			" FROM " . REVERSALTABLE .
			" WHERE browseletter <> ''";

	return $wpdb->get_var($sql);
}


function get_post_id_bycontent($query)
{
	global $wpdb;

	$sql = "SELECT ID " .
			" FROM " . $wpdb->posts .
			" WHERE post_content LIKE '%" . $query . "%'";

	return $wpdb->get_var($sql);
}
function my_404_override() {
	global $wp_query;

	if(is_404())
	{
		$postname = get_query_var('name');

		$postid = get_post_id_bycontent($postname);

		if(isset($postid))
		{
			status_header( 200 );
			$wp_query->is_404=false;

			query_posts('p=' . $postid);
		}
	}
}
add_filter('template_redirect', 'my_404_override' );

function filter_the_content_in_the_main_loop( $content ) {

	$content = normalizer_normalize($content, Normalizer::NFC );
	return $content;
}
add_filter( 'the_content', 'filter_the_content_in_the_main_loop' );

function webonary_css()
{
	?>
<style>
	a:hover {text-decoration:none;}
	a:hover span {text-decoration:none}

	.entry{
		clear:none;
		white-space:unset;
	}
	.reversalindexentry {
		clear:none !important;
		white-space:unset !important;
	}
	.minorentrycomplex{
		clear:none;
		white-space:unset;
	}

.minorentryvariant{
		clear:none;
		white-space:unset;
	}
.mainentrysubentries .mainentrysubentry{
		clear:none;
		white-space:unset;
	}
span.comment {
	background: none;
	padding: 0px;
}
<?php
if(get_option('vernacularRightToLeft') == 1 && isset($_GET['s']))
{
?>
	#searchresults {
	text-align: right;
	}
	.postentry {
	width: 60%;
	}
	.entry {
		white-space: unset !important;
	}
<?php
}
?>
}
</style>
<?php
}
add_action('wp_head', 'webonary_css');
?>