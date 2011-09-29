<?php

/*
Plugin Name: Babble – Proof of Concept
Plugin URI: https://github.com/simonwheatley/babble
Description: Translation proof of concept
Version: alpha 1
Author: Simon Wheatley
Author URI: http://simonwheatley.co.uk/wordpress/
*/
 
/*  Copyright 2011 Simon Wheatley

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once( 'api.php' );

// @FIXME: Move into class property when creating the actual plugin
define( 'SIL_LANG_REGEX', '|^[^/]+|i' );

// @FIXME: Proper method for assigning default language: https://github.com/simonwheatley/translations/issues/3
define( 'SIL_DEFAULT_LANG', 'en' );

/**
 * Hooks the WP init action early
 *
 * @return void
 * @access private
 **/
function sil_init_early() {
	global $sil_post_types;
	register_taxonomy( 'term_translation', 'term', array(
		'rewrite' => false,
		'public' => true,
		'show_ui' => true,
		'show_in_nav_menus' => false,
		'label' => __( 'Term Translation ID', 'sil' ),
	) );
	// Ensure we catch any existing language shadow post_types already registered
	if ( is_array( $sil_post_types ) )
		$post_types = array_merge( array( 'post', 'page' ), array_keys( $sil_post_types ) );
	else
		$post_types = array( 'post', 'page' );
	register_taxonomy( 'post_translation', $post_types, array(
		'rewrite' => false,
		'public' => true,
		'show_ui' => true,
		'show_in_nav_menus' => false,
		'label' => __( 'Post Translation ID', 'sil' ),
	) );
}
add_action( 'init', 'sil_init_early', 0 );

/**
 * Hooks the WP pre_update_option_rewrite_rules filter to add
 * the prefix to the rewrite rule regexes to deal with the
 * virtual language dir.
 * 
 * @param array $langs The language codes
 * @return array An array of language codes utilised for this site. 
 * @access private
 **/
function sil_rewrite_rules_filter( $rules ){
	// Add a prefix to the URL to pick up the virtual sub-dir specifying
	// the language. The redirect portion can and should remain perfectly
	// ignorant of it though, as we change it in parse_request.
    foreach( (array) $rules as $regex => $query )
		$new_rules[ '[a-zA-Z_]+/' . $regex ] = $query;
    return $new_rules;
}
add_filter( 'pre_update_option_rewrite_rules', 'sil_rewrite_rules_filter' );

/**
 * Hooks the WP locale filter to switch locales whenever we gosh darned want.
 *
 * @param string $locale The locale 
 * @return string The locale
 * @access private
 **/
function sil_locale( $locale ) {
	// @FIXME: Copying a huge hunk of code from WP->parse_request here, feels ugly.
	if ( ! is_admin() ) {
		// START: Huge hunk of WP->parse_request
		if ( isset($_SERVER['PATH_INFO']) )
			$pathinfo = $_SERVER['PATH_INFO'];
		else
			$pathinfo = '';
		$pathinfo_array = explode('?', $pathinfo);
		$pathinfo = str_replace("%", "%25", $pathinfo_array[0]);
		$req_uri = $_SERVER['REQUEST_URI'];
		$req_uri_array = explode('?', $req_uri);
		$req_uri = $req_uri_array[0];
		$self = $_SERVER['PHP_SELF'];
		$home_path = parse_url(home_url());
		if ( isset($home_path['path']) )
			$home_path = $home_path['path'];
		else
			$home_path = '';
		$home_path = trim($home_path, '/');

		// Trim path info from the end and the leading home path from the
		// front.  For path info requests, this leaves us with the requesting
		// filename, if any.  For 404 requests, this leaves us with the
		// requested permalink.
		$req_uri = str_replace($pathinfo, '', $req_uri);
		$req_uri = trim($req_uri, '/');
		$req_uri = preg_replace("|^$home_path|", '', $req_uri);
		$req_uri = trim($req_uri, '/');
		$pathinfo = trim($pathinfo, '/');
		$pathinfo = preg_replace("|^$home_path|", '', $pathinfo);
		$pathinfo = trim($pathinfo, '/');
		$self = trim($self, '/');
		$self = preg_replace("|^$home_path|", '', $self);
		$self = trim($self, '/');

		// The requested permalink is in $pathinfo for path info requests and
		//  $req_uri for other requests.
		if ( ! empty($pathinfo) && !preg_match('|^.*' . $wp_rewrite->index . '$|', $pathinfo) ) {
			$request = $pathinfo;
		} else {
			// If the request uri is the index, blank it out so that we don't try to match it against a rule.
			if ( $req_uri == $wp_rewrite->index )
				$req_uri = '';
			$request = $req_uri;
		}
		
		// END: Huge hunk of WP->parse_request

		// @FIXME: Should probably check the available languages here
		// @FIXME: Deal with converting /de/ to retrieve the de_DE.mo

		// error_log( "Locale (before): $locale for request ($request)" );
		// @FIXME: Should I be using $GLOBALS['request] here? Feels odd.
		if ( preg_match( SIL_LANG_REGEX, $request, $matches ) )
			$locale = $matches[ 0 ];
	} else { // Admin area
		if ( $lang = @ $_GET[ 'lang' ] ) {
			$current_user = wp_get_current_user();
			update_user_meta( $current_user->ID, 'bbl_admin_lang', $lang );
		} else {
			$lang = sil_get_current_lang_code();
		}
		$locale = $lang;
	}
	return $locale;
}
add_filter( 'locale', 'sil_locale' );

/**
 * Hooks the WP sil_languages filter. Temporary for POC.
 *
 * @param array $langs The language codes
 * @return array An array of language codes utilised for this site. 
 * @access private
 **/
function sil_languages( $langs ) {
	$langs[] = 'de_DE';
	$langs[] = 'he_IL';
	return $langs;
}
add_filter( 'sil_languages', 'sil_languages' );

/**
 * Hooks the WP registered_post_type action. 
 * 
 * N.B. THIS HOOK IS NOT YET IMPLEMENTED
 *
 * @param string $post_type The post type which has just been registered. 
 * @param array $args The arguments with which the post type was registered
 * @return void
 * @access private
 **/
function sil_registered_post_type( $post_type, $args ) {
	// @FIXME: When we turn this into classes we can avoid a global $sil_syncing here
	global $sil_syncing, $sil_lang_map, $sil_post_types; 

	// Don't bother with non-public post_types for now
	// @FIXME: This may need to change for menus?
	if ( ! $args->public )
		return;
	
	if ( $sil_syncing )
		return;
	$sil_syncing = true;
	
	// @FIXME: Not sure this is the best way to specify languages
	$langs = apply_filters( 'sil_languages', array( 'en' ) );
	
	// This languages map will provide a mechanism to work out which 
	// post types associate with which
	if ( ! $sil_lang_map ) 
		$sil_lang_map = array();
	if ( ! $sil_post_types )
		$sil_post_types = array();
	
	// Lose the default language as the existing post_types are English
	// @FIXME: Need to specify the default language somewhere
	$default_lang = array_search( SIL_DEFAULT_LANG, $langs );
	unset( $langs[ $default_lang ] );
	
	// $args is an object at this point, but register_post_type needs an array
	$args = get_object_vars( $args );
	// @FIXME: Is it reckless to convert ALL object instances in $args to an array?
	foreach ( $args as $key => & $arg ) {
		if ( is_object( $arg ) )
			$arg = get_object_vars( $arg );
		// Don't set any args reserved for built-in post_types
		if ( '_' == substr( $key, 0, 1 ) )
			unset( $args[ $key ] );
	}
	
	$args[ 'supports' ] = array(
		'title',
		'editor',
		'comments',
		'revisions',
		'trackbacks',
		'author',
		'excerpt',
		'page-attributes',
		'thumbnail',
		'custom-fields'
	);
	
	$args[ 'show_ui' ] = false;

	foreach ( $langs as $lang ) {
		$new_args = $args;
		
		// @FIXME: We are in danger of a post_type name being longer than 20 chars
		// I would prefer to keep the post_type human readable, as human devs and sysadmins always 
		// end up needing to read this kind of thing.
		// Perhaps we need to create some kind of map like (post_type) + (lang) => (shadow translated post_type)
		$new_post_type = strtolower( $post_type . "_$lang" );
	
		// foreach ( $new_args[ 'labels' ] as & $label )
		// 	$label = "$label ($lang)";

		$result = register_post_type( $new_post_type, $new_args );
		if ( is_wp_error( $result ) ) {
			error_log( "Error creating shadow post_type for $new_post_type: " . print_r( $result, true ) );
		} else {
			$sil_post_types[ $new_post_type ] = $post_type;
			$sil_lang_map[ $new_post_type ] = $lang;
			// This will not work until init has run at the early priority used
			// to register the post_translation taxonomy. However we catch all the
			// post_types registered before the hook runs, so we don't miss any 
			// (take a look at where we register post_translation for more info).
			register_taxonomy_for_object_type( 'post_translation', $new_post_type );
		}
	}
	
	$sil_syncing = false;
}
add_action( 'registered_post_type', 'sil_registered_post_type', null, 2 );

/**
 * Hooks the WP registered_taxonomy action 
 *
 * @param string $taxonomy The name of the newly registered taxonomy 
 * @param array $args The args passed to register the taxonomy
 * @return void
 * @access private
 **/
function sil_registered_taxonomy( $taxonomy, $object_type, $args ) {
	// @FIXME: When we turn this into classes we can avoid a global $sil_syncing here
	global $sil_syncing;

	// Don't bother with non-public taxonomies for now
	// If we remove this, we need to avoid dealing with post_translation and term_translation
	if ( ! $args[ 'public' ] || 'post_translation' == $taxonomy || 'term_translation' == $taxonomy )
		return;
	
	if ( $sil_syncing )
		return;
	$sil_syncing = true;
	
	// @FIXME: Not sure this is the best way to specify languages
	$langs = apply_filters( 'sil_languages', array( 'en' ) );
	
	// Lose the default language as the existing taxonomies are English
	// @FIXME: Need to specify the default language somewhere
	$default_lang = array_search( SIL_DEFAULT_LANG, $langs );
	unset( $langs[ $default_lang ] );
	
	// @FIXME: Is it reckless to convert ALL object instances in $args to an array?
	foreach ( $args as $key => & $arg ) {
		if ( is_object( $arg ) )
			$arg = get_object_vars( $arg );
		// Don't set any args reserved for built-in post_types
		if ( '_' == substr( $key, 0, 1 ) )
			unset( $args[ $key ] );
	}

	$args[ 'rewrite' ] = false;
	unset( $args[ 'name' ] );
	unset( $args[ 'query_var' ] );

	foreach ( $langs as $lang ) {
		$new_args = $args;
		
		// @FIXME: Note currently we are in danger of a taxonomy name being longer than 32 chars
		// Perhaps we need to create some kind of map like (taxonomy) + (lang) => (shadow translated taxonomy)
		$new_taxonomy = $taxonomy . "_$lang";
	
		foreach ( $new_args[ 'labels' ] as & $label )
			$label = "$label ($lang)";
		
		// error_log( "Register $new_taxonomy for " . implode( ', ', $object_type ) . " with args: " . print_r( $new_args, true ) );
		register_taxonomy( $new_taxonomy, $object_type, $new_args );
	}
	
	$sil_syncing = false;
}
add_action( 'registered_taxonomy', 'sil_registered_taxonomy', null, 3 );

/**
 * Hooks the WP parse_request action 
 *
 * FIXME: Should I be extending and replacing the WP class?
 *
 * @param object $wp WP object, passed by reference (so no need to return)
 * @return void
 * @access private
 **/
function sil_parse_request( $wp ) {
	// If this is the site root, redirect to default language homepage 
	if ( ! $wp->request ) {
		// error_log( "Removing home_url filter" );
		remove_filter( 'home_url', 'sil_home_url', null, 2 );
		wp_redirect( home_url( SIL_DEFAULT_LANG ) );
		// error_log( "Adding home_url filter" );
		add_filter( 'home_url', 'sil_home_url', null, 2 );
		exit;
	}
	
	// Check the language
	// @FIXME: Would explode be more efficient here?
	if ( ! is_admin() && preg_match( SIL_LANG_REGEX, $wp->request, $matches ) ) {
	 	// @FIXME: If we want to cater for non-pretty permalinks we could to handle a GET param or query var here
		$wp->query_vars[ 'lang' ] = $matches[ 0 ];
		// error_log( "Got lang from regex: (" . $matches[ 0 ] . ")" );
	} elseif ( $lang = @ $_GET[ 'lang' ] ) {
		// error_log( "Got GET lang: ($lang)" );
		$wp->query_vars[ 'lang' ] = $lang;
	} else {
		// error_log( "Default langL: (" . SIL_DEFAULT_LANG . ")" );
		$wp->query_vars[ 'lang' ] = SIL_DEFAULT_LANG;
	}
	error_log( "Request: $wp->request" );
	error_log( "Original Query: " . print_r( $wp->query_vars, true ) );

	// Sequester the original query, in case we need it to get the default content later
	$wp->query_vars[ 'sil_original_query' ] = $wp->query_vars;

	// Detect language specific homepages
	if ( $wp->request == $wp->query_vars[ 'lang' ] ) {
		unset( $wp->query_vars[ 'error' ] );
		// @FIXME: Cater for front pages which don't list the posts
		// Trigger the archive listing for the relevant shadow post type
		// for this language.
		if ( 'en' != $wp->query_vars[ 'lang' ] ) {
			$wp->query_vars[ 'post_type' ] = 'post_' . $wp->query_vars[ 'lang' ];
		}
		return;
	}

	// If we're asking for the default content, it's fine
	// error_log( "Original query: " . print_r( $wp->query_vars, true ) );
	if ( 'en' == $wp->query_vars[ 'lang' ] ) {
		// error_log( "Default content" );
		// error_log( "New Query 0: " . print_r( $wp->query_vars, true ) );
		return;
	}

	// Now swap the query vars so we get the content in the right language post_type
	
	// @FIXME: Do I need to change $wp->matched query? I think $wp->matched_rule is fine?
	// @FIXME: Danger of post type slugs clashing??
	if ( isset( $wp->query_vars[ 'pagename' ] ) && $wp->query_vars[ 'pagename' ] ) {
		// Substitute post_type for 
		$wp->query_vars[ 'name' ] = $wp->query_vars[ 'pagename' ];
		$wp->query_vars[ 'page_' . $wp->query_vars[ 'lang' ] ] = $wp->query_vars[ 'pagename' ];
		$wp->query_vars[ 'post_type' ] = 'page_' . $wp->query_vars[ 'lang' ];
		unset( $wp->query_vars[ 'page' ] );
		unset( $wp->query_vars[ 'pagename' ] );
	} elseif ( isset( $wp->query_vars[ 'year' ] ) ) { 
		// @FIXME: This is not a reliable way to detect queries for the 'post' post_type.
		$wp->query_vars[ 'post_type' ] = 'post_' . $wp->query_vars[ 'lang' ];
	} elseif ( isset( $wp->query_vars[ 'post_type' ] ) ) { 
		$wp->query_vars[ 'post_type' ] = $wp->query_vars[ 'post_type' ] . '_' . $wp->query_vars[ 'lang' ];
	}
	error_log( "New Query: " . print_r( $wp->query_vars, true ) );
}
add_action( 'parse_request', 'sil_parse_request' );

/**
 * Hooks the WP query_vars filter to add various of our geo
 * search specific query_vars.
 *
 * @param array $query_vars An array of the public query vars 
 * @return array An array of the public query vars
 * @access private
 **/
function sil_query_vars( $query_vars ) {
	// @FIXME: We only add the home_url filter at this point because having it earlier screws with the request method of the WP class.
	add_filter( 'home_url', 'sil_home_url', null, 2 );
	return array_merge( $query_vars, array( 'lang' ) );
}
add_filter( 'query_vars', 'sil_query_vars' );

/**
 * Hooks the WP the_posts filter. 
 * 
 * Check the post_title, post_excerpt, post_content and substitute from
 * the default language where appropriate.
 *
 * @param array $posts The posts retrieved by WP_Query, passed by reference 
 * @return array The posts
 * @access private
 **/
function sil_the_posts( $posts ) {
	if ( is_admin() )
		return $posts;
	$subs_index = array();
	foreach ( $posts as & $post )
		if ( ! sil_get_transid( $post ) && SIL_DEFAULT_LANG == sil_get_post_lang( $post ) )
			sil_set_transid( $post );
		if ( empty( $post->post_title ) || empty( $post->post_excerpt ) || empty( $post->post_content ) ) {
			if ( $default_post = sil_get_default_lang_post( $post->ID ) )
				$subs_index[ $post->ID ] = $default_post->ID;
		}
	if ( ! $subs_index )
		return;
	$subs_posts = get_posts( array( 'include' => array_values( $subs_index ), 'post_status' => 'publish' ) );
	// @FIXME: Check the above get_posts call results are cached somewhere… I think they are
	// @FIXME: Alternative approach: hook on save_post to save the current value to the translation, BUT content could get out of date – in post_content_filtered
	foreach ( $posts as & $post ) {
		// @FIXME: I'm assuming this get_post call is cached, which it seems to be
		$default_post = get_post( $subs_index[ $post->ID ] );
		if ( empty( $post->post_title ) )
			$post->post_title = 'Fallback: ' . $default_post->post_title;
		if ( empty( $post->post_excerpt ) )
			$post->post_excerpt = "Fallback excerpt\n\n" . $default_post->post_excerpt;
		if ( empty( $post->post_content ) )
			$post->post_content = "Fallback content\n\n" . $default_post->post_content;
	}
	return $posts;
}
add_action( 'the_posts', 'sil_the_posts' );

/**
 * Hooks the WP admin_bar_menu action 
 *
 * @param object $wp_admin_bar The WP Admin Bar, passed by reference
 * @return void
 * @access private
 **/
function sil_admin_bar_menu( $wp_admin_bar ) {
	global $wp, $sil_post_types, $sil_lang_map;
	$args = array(
		'id' => 'sil_languages',
		'title' => sil_get_current_lang_code(),
		'href' => '#',
		'parent' => false,
		'meta' => false
	);
	$wp_admin_bar->add_menu( $args );

	// @FIXME: Not sure this is the best way to specify languages
	$langs = apply_filters( 'sil_languages', array( 'en' ) );
	// Remove the current language
	foreach ( $langs as $i => & $lang )
		if ( $lang == sil_get_current_lang_code() )
			unset( $langs[ $i ] );
	
	// Create a handy flag for whether we're editing a post
	$editing_post = false;
	if ( is_admin() ) {
		$screen = get_current_screen();
		$editing_post = ( is_admin() && 'post' == $screen->base );
	}
	
	if ( is_singular() || is_single() || $editing_post ) {
		// error_log( "Get translations" );
		$translations = sil_get_post_translations( get_the_ID() );
	}

	foreach ( $langs as $i => & $lang ) {
		$title = sprintf( __( 'Switch to %s', 'sil' ), $lang );
		if ( is_admin() ) {
			if ( $editing_post ) {
				if ( isset( $translations[ $lang ]->ID ) ) { // Translation exists
					$href = add_query_arg( array( 'lang' => $lang, 'post' => $translations[ $lang ]->ID ) );
				} else { // Translation does not exist
					$default_post = $translations[ SIL_DEFAULT_LANG ];
					$href = sil_get_new_translation_url( $default_post, $lang );
					$title = sprintf( __( 'Create for %s', 'sil' ), $lang );
				}
			} else {
				$href = add_query_arg( array( 'lang' => $lang ) );
			}
		} else if ( is_singular() || is_single() ) {
			if ( isset( $translations[ $lang ]->ID ) ) { // Translation exists
				$href = get_permalink( $translations[ $lang ]->ID );
			} else { // Translation does not exist
				// Generate a URL to create the translation
				$default_post = $translations[ SIL_DEFAULT_LANG ];
				$href = sil_get_new_translation_url( $default_post, $lang );
				$title = sprintf( __( 'Create for %s', 'sil' ), $lang );
			}
			// error_log( "Lang ($lang) HREF ($href)" );
		} else if ( $wp->request == sil_get_current_lang_code() ) { // Language homepages
			// error_log( "Removing home_url filter" );
			remove_filter( 'home_url', 'sil_home_url', null, 2 );
			$href = home_url( $lang );
			// error_log( "Adding home_url filter" );
			add_filter( 'home_url', 'sil_home_url', null, 2 );
		}
		$args = array(
			'id' => "sil_languages_$lang",
			'href' => $href,
			'parent' => 'sil_languages',
			'meta' => false,
			'title' => $title,
		);
		$wp_admin_bar->add_menu( $args );
	}
}
add_action( 'admin_bar_menu', 'sil_admin_bar_menu', 100 );

/**
 * Hooks the WP home_url action 
 * 
 * Hackity hack: this function is attached with add_filter within
 * the query_vars filter.
 *
 * @param string $url The URL 
 * @param string $path The path 
 * @param string $orig_scheme The original scheme 
 * @param int $blog_id The ID of the blog 
 * @return string The URL
 * @access private
 **/
function sil_home_url( $url, $path ) {
	$orig_url = $url;
	// @FIXME: The way I'm working out the home_url, by replacing the path with an empty string; it feels hacky… is it?
	// @FIXME: Do I need to use something multibyte string safe, rather than str_replace?
	if ( '/' != $path && ':' != $path )
		$base_url = str_replace( $path, '', $url );
	$path = ltrim( $path, '/' );
	$url = trailingslashit( $base_url ) . sil_get_current_lang_code() . '/' . $path;
	// error_log( "Home URL: $url" );
	return $url;
}

/**
 * Hooks the WP admin_init action 
 *
 * @return void
 * @access private
 **/
function sil_admin_init(  ) {
	add_filter( 'home_url', 'sil_home_url', null, 2 );
}
add_action( 'admin_init', 'sil_admin_init' );

/**
 * Hooks the WP admin_url filter to ensure we keep a consistent domain as we click around.
 *
 * @param string $admin_url The admin URL 
 * @return string The URL with the appropriate language (lang) GET parameter
 * @access private
 **/
function sil_admin_url( $url ) {
	return add_query_arg( array( 'lang' => sil_get_current_lang_code() ), $url );
}
add_filter( 'admin_url', 'sil_admin_url' );

/**
 * Hooks the WP post_type_link filter 
 *
 * @param string $post_link The permalink 
 * @param object $post The WP Post object being linked to
 * @return string The permalink
 * @access private
 **/
function sil_post_type_link( $post_link, $post, $leavename ) {
	global $sil_post_types, $sil_lang_map, $wp_rewrite;

	if ( ! isset( $sil_post_types[ $post->post_type ] ) )
		return $post_link;

	error_log( "Post link for type: ($post->post_type)" );
	if ( 'post' == $post->post_type || 'page' == $post->post_type ) { // Deal with regular ol' posts & pages
		$base_post_type = $post->post_type;
	} else if ( ! $base_post_type = $sil_post_types[ $post->post_type ] ) { // Deal with shadow post types
		return $post_link;
	}

	// Deal with post_types shadowing the post post_type
	if ( 'post' == $base_post_type ) {
		// @FIXME: Is there any way I can provide an appropriate permastruct so I can avoid having to copy all this code, with the associated maintenance headaches?
		// START copying from get_permalink function
		// N.B. The $permalink var is replaced with $post_link
		$rewritecode = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			'%postname%',
			'%post_id%',
			'%category%',
			'%author%',
			'%pagename%',
		);

		$post_link = get_option('permalink_structure');

		// @FIXME: Should I somehow fake this, so plugin authors who hook it still get some consequence?
		// $post_link = apply_filters('pre_post_link', $post_link, $post, $leavename);

		if ( '' != $post_link && ! in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			$unixtime = strtotime($post->post_date);

			$category = '';
			if ( strpos($post_link, '%category%') !== false ) {
				$cats = get_the_category($post->ID);
				if ( $cats ) {
					usort($cats, '_usort_terms_by_ID'); // order by ID
					$category = $cats[0]->slug;
					if ( $parent = $cats[0]->parent )
						$category = get_category_parents($parent, false, '/', true) . $category;
				}
				// show default category in permalinks, without
				// having to assign it explicitly
				if ( empty($category) ) {
					$default_category = get_category( get_option( 'default_category' ) );
					$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
				}
			}

			$author = '';
			if ( strpos($post_link, '%author%') !== false ) {
				$authordata = get_userdata($post->post_author);
				$author = $authordata->user_nicename;
			}

			$date = explode(" ",date('Y m d H i s', $unixtime));
			$rewritereplace =
			array(
				$date[0],
				$date[1],
				$date[2],
				$date[3],
				$date[4],
				$date[5],
				$post->post_name,
				$post->ID,
				$category,
				$author,
				$post->post_name,
			);
			$sequestered_lang = get_query_var( 'lang' );
			$lang = sil_get_post_lang( $post );
			if ( is_admin() ) {
				set_query_var( 'lang', false );
				$post_link = home_url( "/$lang" . str_replace( $rewritecode, $rewritereplace, $post_link ) );
			} else {
				set_query_var( 'lang', $lang );
				$post_link = home_url( str_replace( $rewritecode, $rewritereplace, $post_link ) );
			}
			set_query_var( 'lang', $sequestered_lang );
			$post_link = user_trailingslashit($post_link, 'single');
			// END copying from get_permalink function
		} else { // if they're not using the fancy permalink option
			return $post_link;
		}
		
	} else if ( 'page' == $base_post_type ) {
		// error_log( "Get page link for $post_link" );
		return get_page_link( $post->ID, $leavename );
	}

	return $post_link;
}
add_filter( 'post_link', 'sil_post_type_link', null, 3 );
add_filter( 'post_type_link', 'sil_post_type_link', null, 3 );

/**
 * Hooks the WP page_link filter to ensure correct virtual language directory prefix, etc.
 *
 * @param string $link The permalink for the page
 * @param int $id The ID for the post represented by this permalink 
 * @return string
 * @access private
 **/
function sil_page_link( $link, $id ) {
	global $sil_syncing;
	if ( $sil_syncing )
		return $link;
	$sil_syncing = true;
	$sequestered_lang = sil_get_current_lang_code();
	$lang = sil_get_post_lang( $id );
	set_query_var( 'lang', $lang );
	$link = get_page_link( $id, $leavename );
	set_query_var( 'lang', $sequestered_lang );
	$sil_syncing = false;
	return $link;
}
add_filter( 'page_link', 'sil_page_link', null, 2 );

/**
 * Get the transID for this post, this is an identifier linking all the translations 
 * for a single piece of content together.
 *
 * Marked private as we may change how translations are linked. Please use API, or 
 * raise an issue.
 *
 * @param int|object $post The WP Post object, or the ID of a post
 * @return string The transid
 * @access private
 **/
function sil_get_transid( $post ) {
	$post = get_post( $post );
	$transids = (array) wp_get_object_terms( $post->ID, 'post_translation', array( 'fields' => 'ids' ) );
	// "There can be only one" (so we'll just drop the others)
	if ( isset( $transids[ 0 ] ) )
		return $transids[ 0 ];
	if ( SIL_DEFAULT_LANG == sil_get_post_lang( $post ) )
		return false;
	
	return new WP_Error( 'no_transid', __( "No TransID available for post ID ($post->ID)", 'bbl' ) );
}

/**
 * Hooks the WP wp_insert_post action to set a transid on 
 *
 * @param int $post_id The ID of the post which has just been inserted
 * @param object $post The WP Post object which has just been inserted 
 * @return void
 * @access private
 **/
function sil_wp_insert_post( $post_id, $post ) {
	global $sil_syncing;
	if ( $sil_syncing )
		return;

	if ( 'auto-draft' != $post->post_status )
		return;

	$sil_syncing = true;

	// Get any approved term ID for the transid for any new translation
	$transid = (int) @ $_GET[ 'sil_transid' ];
	sil_set_transid( $post, $transid );

	// Ensure the post is in the correct shadow post_type
	if ( SIL_DEFAULT_LANG != sil_get_current_lang_code() ) {
		$new_post_type = strtolower( $post->post_type . '_' . sil_get_current_lang_code() );
		wp_update_post( array( 'ID' => $post_id, 'post_type' => $new_post_type ) );
	}
	$sil_syncing = false;
	// Now we have to do a redirect, to ensure the WP Nonce gets generated correctly
	wp_redirect( admin_url( "/post.php?post=$post_id&action=edit" ) );
}
add_action( 'wp_insert_post', 'sil_wp_insert_post', null, 2 );

/**
 * Create and assign a new TransID to a post.
 *
 * @param int|object $post Either a Post ID or a WP Post object 
 * @param string $transid (optional) A transid to associate with the post
 * @return void
 * @author Simon Wheatley
 **/
function sil_set_transid( $post, $transid = false ) {
	$post = get_post( $post );
	// @FIXME: Abstract the code for generating and associating a new TransID
	if ( ! $transid ) {
		$transid_name = 'post_transid_' . uniqid();
		$result = wp_insert_term( $transid_name, 'post_translation', array() );
		if ( is_wp_error( $result ) )
			error_log( "Problem creating a new TransID: " . print_r( $result, true ) );
		else
			$transid = $result[ 'term_id' ];
	}
	$result = wp_set_object_terms( $post->ID, $transid, 'post_translation' );
	if ( is_wp_error( $result ) )
		error_log( "Problem associating TransID with new posts: " . print_r( $result, true ) );
}

?>