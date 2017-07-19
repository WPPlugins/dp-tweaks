<?php
/**
 * Plugin Name: DP Tweaks
 * Plugin URI: http://dedepress.com/plugins/dp-tweaks/
 * Description: Extends built in features of WordPress and combines several useful tweaks for admin, speed and security. 
 * Version: 1.0.12
 * Author: Cloud Stone
 * Author URI: http://dedepress.com/
 * License: GPL V2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Check whether class 'DP_Panel' is exists
if(!class_exists('DP_Panel')) {
	require_once('lib/core.php');
	require_once('lib/forms.php');
	require_once('lib/panel.php');
	require_once('lib/helpers.php');
	require_once('lib/debug.php');
}

/**
 * This class provides a way to have both Trash and Delete Permanently 
 * at the same time. If You want to disable trash completely, you can 
 * defining EMPTY_TRASH_DAYS to 0 in your wp-config.php file.
 *
 * @since 1.0
 */
class DP_Force_Delete {

	function DP_Force_Delete() {
		add_action('page_row_actions', array( &$this, 'post_row_actions'), 10,2);
		add_action('post_row_actions', array( &$this, 'post_row_actions'), 10,2);	
		add_action('comment_row_actions', array( &$this, 'comment_row_actions'), 10,2);	
		
		add_action( 'trashed_post', array( &$this, 'trashed_post') );
		add_action( 'trashed_comment', array( &$this, 'trashed_comment') );
		
		// TODO: Add Delete Permanently for bulk actions
		// add_filter('bulk_actions-edit-post', array( &$this, 'bulk_actions_edit_post') );
		// add_filter('bulk_actions-edit-page', array( &$this, 'bulk_actions_edit_posts') );
		// add_filter('bulk_actions-edit-comments', array( &$this, 'bulk_actions_edit_comments') );
	}
	
	function post_row_actions($actions, $post) {
		if (current_user_can('delete_post', $post->ID) && isset( $actions['trash'] ) && !isset( $actions['delete'] ) )
			$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently' ) ) . "' href='" . get_delete_post_link( $post->ID, '', true ) . "'>" . __( 'Delete Permanently' ) . "</a>";
				
		return $actions;
	}	

	function comment_row_actions($actions, $comment) {
		$post = get_post($comment->comment_post_ID);
		if ( current_user_can('edit_post', $post->ID) && isset( $actions['trash'] ) && !isset( $actions['delete'] ) ) {
			$del_nonce = esc_html( '_wpnonce='.wp_create_nonce( 'delete-comment_'.$comment->comment_ID ) );
			$delete_url = esc_url( 'comment.php?action=deletecomment&p='.$post->ID.'&c='.$comment->comment_ID.'&'.$del_nonce );
			$actions['delete'] = "<a href='$delete_url' class='delete:the-comment-list:comment-$comment->comment_ID::delete=1 delete vim-d vim-destructive'>" . __( 'Delete Permanently' ) . '</a>';
		}
		return $actions;
	}

	function bulk_actions_edit_post($actions) {
		if (isset( $actions['trash'] ) && !isset( $actions['delete'] ) )
			$actions['delete'] = __( 'Delete Permanently' );
		
		return $actions;
	}

	function bulk_actions_edit_comments($actions) {
		if (isset( $actions['trash'] ) && !isset( $actions['delete'] ) )
			$actions['delete'] = __( 'Delete Permanently' );
		
		return $actions;
	}	

	function trashed_post( $post_ID ) {
		global $action;
		if ( isset( $action ) && ( $action == 'delete' ) )
			wp_delete_post( $post_ID, true );
	}
	
	function trashed_comment( $comment_ID ) {
		global $action;
		if ( isset( $action ) ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				if ( ( $action == 'delete-comment' ) && isset( $_POST['delete'] ) && ($_POST['delete'] == 1 ) ) {
					if ( isset( $GLOBALS['comment'] ) && ( $GLOBALS['comment']->comment_ID == $comment_id ) ) {
						unset( $GLOBALS['comment'] );
					}
					wp_delete_comment( $comment_ID, true );
				}
			} else {
				if ( $action == 'deletecomment' ) {
					if ( isset( $GLOBALS['comment'] ) && ( $GLOBALS['comment']->comment_ID == $comment_id ) ) {
						unset( $GLOBALS['comment'] );
					}
					wp_delete_comment( $comment_ID, true );
				}
			}
		}
	}
}

/**
 * For advanced WordPress Users/ developers, the objects IDs were quite
 * interesting for some plugins or template tags. This class does is to
 * show IDs in admin edit view.
 *
 * @since 1.0
 */
class DP_Show_IDs {

	function DP_Show_IDs() {
		global $wp_post_types, $wp_taxonomies;
		
		add_action( "admin_head", array(&$this, "styles") );
		
		add_filter('manage_posts_columns', array(&$this, 'id_column'));
		add_action('manage_posts_custom_column', array(&$this, 'echo_id'), 10, 2);
		
		add_filter('manage_pages_columns', array(&$this, 'id_column'));
		add_action('manage_pages_custom_column', array(&$this, 'echo_id'), 10, 2);

		add_filter('manage_media_columns', array(&$this, 'id_column'));
		add_action('manage_media_custom_column', array(&$this, 'echo_id'), 10, 2);

		add_filter('manage_link-manager_columns', array(&$this, 'id_column'));
		add_action('manage_link_custom_column', array(&$this, 'echo_id'), 10, 2);
		
		add_action('manage_edit-link-categories_columns', array(&$this, 'id_column'));
		add_filter('manage_link_categories_custom_column', array(&$this, 'return_id'), 10, 3);
		foreach ( get_taxonomies() as $taxonomy ) {
			add_action("manage_edit-${taxonomy}_columns", array(&$this, 'id_column'));
			add_filter("manage_${taxonomy}_custom_column", array(&$this, 'return_id'), 10, 3);
		}
		
		add_action('manage_users_columns', array(&$this, 'id_column'));
		add_filter('manage_users_custom_column', array(&$this, 'return_id'), 10, 3);
		
		add_action('manage_edit-comments_columns', array(&$this, 'id_column'));
		add_action('manage_comments_custom_column', array(&$this, 'echo_id'), 10, 2);
	}

	function id_column($cols) {
		return  array_merge ($cols, array ("dp_ids" => "ID"));
	}

	function return_id($value, $column_name, $id) {
		if($column_name === "dp_ids")
			$value = $id;
		return $value;
	}

	function echo_id($column_name, $id) {
		if($column_name === "dp_ids") 
			echo $id;
	}

	function styles() {
		$css = "<style type='text/css'>td.column-dp_ids, th.column-dp_ids { width: 45px; text-align:center; }</style> \n";
		echo $css;
	}
}

/**
 * Allows your site to use common javascript libraries from Google's AJAX Libraries CDN
 * rather than from Wordpress's own copies.
 *
 * This class by Jason Penney's "Use Google Libraries" plugin.
 *
 * @since 1.0
 * @author Jason Penney <http://jasonpenney.net/>
 * @link http://wordpress.org/extend/plugins/use-google-libraries/
 */
class DP_Google_CDN {

    private static $instance;

    public static function get_instance() {
      if (!isset(self::$instance)) {
        self::$instance =  new DP_Google_CDN();
      }
      return self::$instance;
    }

    protected $google_scripts;
    protected $noconflict_url;
    protected $noconflict_next;
    protected $is_ssl;
    protected static $script_before_init_notice =
      '<strong>DP Google CDN</strong>: Another plugin has registered or enqued a script before the "init" action.  Attempting to work around it.';

    /**
     * PHP 4 Compatible Constructor
     */
    function DP_Google_CDN(){
		$this->__construct();
	}
    
    /**
     * PHP 5 Constructor
     */		
    function __construct(){
      $this->google_scripts =   
        array(
              // any extra scripts listed here not provided by WordPress 
              // or another plugin will not be registered.  This liste
              // is just used to chancge where things load from.

              /* jQuery */
              'jquery' => array( 'jquery','jquery.min'),

              /* jQuery UI */
              'jquery-ui-core' => array('jqueryui','jquery-ui.min'),
              'jquery-ui-accordion' => array('',''),
              'jquery-ui-autocomplete' => array('',''), /* jQueri UI 1.8 */
              'jquery-ui-button' => array('',''), /* jQuery UI 1.8 */
              'jquery-ui-datepicker' => array('',''),
              'jquery-ui-dialog' => array('',''),
              'jquery-ui-draggable' => array('',''),
              'jquery-ui-droppable' => array('',''),
              'jquery-ui-menu' => array('',''),
              'jquery-ui-mouse' => array('',''),  /* jQuery UI 1.8 */
              'jquery-ui-position' => array('',''),  /* jQuery UI 1.8 */
              'jquery-ui-progressbar' => array('',''),
              'jquery-ui-resizable' => array('',''),
              'jquery-ui-selectable' => array('',''),
              'jquery-ui-slider' => array('',''),
              'jquery-ui-sortable' => array('',''),
              'jquery-ui-tabs' => array('',''),
              'jquery-ui-widget' => array('',''),  /* jQuery UI 1.8 */

              /* jQuery Effects */
              'jquery-effects-core' => array('',''),
              'jquery-effects-blind' => array('',''),
              'jquery-effects-bounce' => array('',''),
              'jquery-effects-clip' => array('',''),
              'jquery-effects-drop' => array('',''),
              'jquery-effects-explode' => array('',''),
              'jquery-effects-fade' => array('',''),  /* jQuery UI 1.8 */
              'jquery-effects-fold' => array('',''),
              'jquery-effects-highlight' => array('',''),
              'jquery-effects-pulsate' => array('',''),
              'jquery-effects-scale' => array('',''),
              'jquery-effects-shake' => array('',''),
              'jquery-effects-slide' => array('',''),
              'jquery-effects-transfer' => array('',''),

              /* prototype */
              'prototype' => array('prototype','prototype'),

              /* scriptaculous */
              'scriptaculous-root' => array('scriptaculous', 'scriptaculous'),
              'scriptaculous-builder' => array('',''),
              'scriptaculous-effects' => array('',''),
              'scriptaculous-dragdrop' => array('',''),
              'scriptaculous-controls' => array('',''),
              'scriptaculous-slider' => array('',''),
              'scriptaculous-sound' => array('',''),

              /* moo tools */
              'mootools' => array('mootools','mootools-yui-compressed'),

              /* Dojo */
              'dojo' => array('dojo','dojo.xd'),

              /* swfobject */
              'swfobject' => array('swfobject','swfobject'),

              /* YUI */
              'yui' => array('yui','build/yuiloader/yuiloader-min'),

              /* Ext Core */
              'ext-core' => array('ext-core','ext-core')

              );
      $this->noconflict_url = WP_PLUGIN_URL . '/use-google-libraries/js/jQnc.js';

      $this->noconflict_next = FALSE;
      // test for SSL
      // thanks to suggestions from Peter Wilson (http://peterwilson.cc/)
      // and Richard Hearne
      $is_ssl = false;
      if ((function_exists('getenv') AND 
           ((getenv('HTTPS') != '' AND getenv('HTTPS') != 'off')
            OR
            (getenv('SERVER_PORT') == '433')))
          OR
          (isset($_SERVER) AND
           ((isset($_SERVER['HTTPS']) AND $_SERVER['https'] !='' AND $_SERVER['HTTPS'] != 'off')
            OR
            (isset($_SERVER['SERVER_PORT']) AND $_SERVER['SERVER_PORT'] == '443')))) {
        $is_ssl = true;
      }
      $this->is_ssl = $is_ssl;
    }

    static function configure_plugin() {
      add_action( 'wp_default_scripts', array( 'DP_Google_CDN', 'replace_default_scripts_action'), 1000);
      add_filter( 'script_loader_src',  array( "DP_Google_CDN", "remove_ver_query_filter" ), 1000);
      add_filter( 'init',array( "DP_Google_CDN", "setup_filter" ) );

      // There's a chance some plugin has called wp_enqueue_script outside 
      // of any hooks, which means that this plugin's 'wp_default_scripts' 
      // hook will never get a chance to fire.  This tries to work around 
      // that.
      global $wp_scripts;
      if ( is_a($wp_scripts, 'WP_Scripts') ) {
        if( WP_DEBUG !== false ) {
          error_log(self::$script_before_init_notice);
        }
        /*      
        if ( is_admin() ) {
          add_action('admin_notices', array("DP_Google_CDN", 'script_before_init_admin_notice'));
        }
        */
        $ugl =  self::get_instance();
        $ugl->replace_default_scripts( $wp_scripts );
      }
    }

    static function script_before_init_admin_notice() {
      echo '<div class="error fade"><p>' . self::$script_before_init_notice . '</p></div>';
    }

    static function setup_filter() {
      $ugl =  self::get_instance();
      $ugl->setup();
    }

    /**
     * Disables script concatination, which breaks when dependencies are not 
     * all loaded locally.
     */
    function setup() {
      global $concatenate_scripts;
      $concatenate_scripts = false; 
    }
	
    static function replace_default_scripts_action( &$scripts ) {
      $ugl = self::get_instance();
      $ugl->replace_default_scripts( $scripts );
    }

    /**
     * Replace as many of the wordpress default script registrations as possible
     * with ones from google 
     *
     * @param object $scripts WP_Scripts object.
     */
    function replace_default_scripts ( &$scripts ) { 
		$newscripts = array();
		foreach ( $this->google_scripts as $name => $values ) {
			if ($script = $scripts->query($name)) {
				$lib = $values[0];
				$js = $values[1];

				// default to requested ver
			$ver = $script->ver;

          // TODO: replace with more flexible option
          // quick and dirty work around for scriptaculous 1.8.0
          if ($name == 'scriptaculous-root' && $ver == '1.8.0') {
            $ver = '1.8';
          }

          if ($name == 'jquery-effects-core') {
            $script->deps[] = 'jquery-ui-core';
          }

          // if $lib is empty, then this script does not need to be 
          // exlicitly loaded when using googleapis.com, but we need to keep
          // it around for dependencies
	  if ($lib != '') {
	    // build new URL
	    $script->src = "http://ajax.googleapis.com/ajax/libs/$lib/$ver/$js.js";
            
            if ($this->is_ssl) {
              //use ssl
              $script->src = preg_replace('/^http:/', 'https:', $script->src);
            }
	  } else {
	    $script->src = "";
	  }
	  $newscripts[] = $script;
	}
      }

      foreach ($newscripts as $script) {
        $olddata = $this->WP_Dependency_get_data($scripts, $script->handle);
	$scripts->remove( $script->handle );
	// re-register with original ver
	$scripts->add($script->handle, $script->src, $script->deps, $script->ver);
        if ($olddata)
          foreach ($olddata as $data_name => $data) {
            $scripts->add_data($script->handle,$data_name,$data);
          }
      }

    }

	function WP_Dependency_get_data( $dep_obj, $handle, $data_name = false) {
      
      if ( !method_exists($dep_obj,'add_data') )
        return false;

      if ( !isset($dep_obj->registered[$handle]) )
        return false;

      if (!$data_name)
        return $dep_obj->registered[$handle]->extra;

      return $dep_obj->registered[$handle]->extra[$data_name];
    }
	
	/** 
     * Remove 'ver' from query string for scripts loaded from Google's
     * CDN
     *
     * @param string $src src attribute of script tag
     * @return string Updated src attribute
     */
    function remove_ver_query ($src) {
      if ($this->noconflict_next) {
        $this->noconflict_next = FALSE;
        echo "<script type='text/javascript'>try{jQuery.noConflict();}catch(e){};</script>\n";
      }
      if ( preg_match( '/ajax\.googleapis\.com\//', $src ) ) {
	$src = remove_query_arg('ver',$src);
        if (strpos($src,$this->google_scripts['jquery'][1] . ".js")) {
          $this->noconflict_next = TRUE;
        }
      } 
      return $src;
    }

    static function remove_ver_query_filter ($src) {
      $ugl =  self::get_instance();
      return $ugl->remove_ver_query($src);
    }
}

/**
 * All tweaks in one place. some of security tweaks from plugin 'Secure WordPress'.
 *
 * @since 1.0
 * @link http://wordpress.org/extend/plugins/secure-wordpress/
 */
class DP_Tweaks {
	function DP_Tweaks() {
		// Get user's settings
		$settings = get_option('dp_tweaks');
		if (empty($settings))
			return;
		
		// Tweaks for admin
		if (is_admin()) {
			if (empty($settings['flash_uploader']))
				add_filter('flash_uploader', create_function('$a', 'return false;'));
			
			if (empty($settings['post_revision']))
				remove_action('pre_post_update', 'wp_save_post_revision');
			
			if (empty($settings['autosave']))
				add_action('wp_print_scripts', create_function('$a', "wp_deregister_script('autosave');"));
			
			if (!empty($settings['force_delete']))
				$dp_force_delete =& new DP_Force_Delete();
			
			if (!empty($settings['show_ids']))
				$dp_show_ids = & new DP_Show_IDs();
			
			if (is_array($settings['google_cdn']) && in_array('admin', $settings['google_cdn']))
				DP_Google_CDN::configure_plugin();
				
			if( empty($settings['wp_version']) || !in_array('admin', $settings['wp_version']) )
				add_action( 'admin_init', array(&$this, 'remove_wp_version_on_admin'), 1 );
			
			if(empty($settings['update_msgs']) || !in_array('core', $settings['update_msgs']) )
				add_action( 'admin_init', array(&$this, 'remove_core_update'), 1 );

			if(empty($settings['update_msgs']) || !in_array('plugin', $settings['update_msgs']) )
				add_action( 'admin_init', array(&$this, 'remove_plugin_update'), 1 );

			if(empty($settings['update_msgs']) || !in_array('theme', $settings['update_msgs']) )
				add_action( 'admin_init', array(&$this, 'remove_theme_update'), 1 );
			
			if(!empty($settings['show_term_view_link'])) {
				foreach (get_taxonomies(array('show_ui' => true)) as $tax_name) { 
					add_filter($tax_name . '_row_actions', array(&$this, 'show_term_view_link'), 10, 2);
				}
			}
		}

		// Tweaks for front
		else {
			if(empty($settings['self_pings']))
				add_action('pre_ping', array(&$this, 'disable_self_pings'));
			
			if( is_array($settings['google_cdn']) && in_array('front', $settings['google_cdn']) )
				DP_Google_CDN::configure_plugin();
			
			// add_action('widgets_init', array(&$this, 'remove_recent_comments_style'));
			
			if( empty($settings['login_error']) ) {
				add_action( 'login_head', array(&$this, 'remove_login_error_div') );
				add_filter( 'login_errors', create_function( '$a', "return null;" ) );
			}
			
			if( !empty($settings['block_bad_url_request']) )
				add_action( 'init', array(&$this, 'block_bad_url_request') );
		}
		
		// Tweaks for admin and front
		if( empty($settings['wp_version']) || !in_array('front', (array)$settings['wp_version']) ) {
				add_filter('the_generator', create_function('$a', 'return null;'));
				$types = array('html', 'xhtml', 'atom', 'rss2', 'rdf', 'comment', 'export');
				foreach($types as $type)
					add_filter('get_the_generator_'.$type, create_function('$a', 'return null;'));
		}

		if( empty($settings['wp_version']) || !in_array('url', $settings['wp_version']) ) {
			add_filter( 'script_loader_src', array(&$this, 'remove_wp_version_on_url') );
			add_filter( 'style_loader_src', array(&$this, 'remove_wp_version_on_url') );
		}
	}
	
	/**
	 * Show view link in term edit view.
	 *
	 * @since 1.1
	 */
	function show_term_view_link($actions, $tag) {
		$actions['view'] = '<a rel="permalink" href="' . get_term_link( $tag, $tag->taxonomy) . '">View</a>';
		return $actions;
	}

	/**
	 * Disable self pings
	 *
	 * @since 1.0
	 */
	function disable_self_pings($links) {
		$home = get_option('home');
		foreach ($links as $l => $link) {
			if (0 === strpos($link, $home))
				unset($links[$l]);
		}
	}
	
	/**
	 * Remove recent comments widget style
	 *
	 * @since 1.0
	 */
	function remove_recent_comments_style() {
		global $wp_widget_factory;
		remove_action('wp_head', array(
			$wp_widget_factory->widgets['WP_Widget_Recent_Comments'],
			'recent_comments_style'
		));
	}
	
	/**
	 * Hide login error div elements
	 *
	 * @since 1.0
	 */
	function remove_login_error_div() {
		echo '<style type="text/css">#login_error{display:none;}</style>';
	}
	
	/**
	 * Remove wordpress version in admin footer and dashboard right now
	 *
	 * @since 1.0
	 */
	function remove_wp_version_on_admin() {
		if ( !current_user_can('update_plugins') && is_admin() ) {
			remove_filter( 'update_footer', 'core_update_footer' );
			add_action('admin_head', array(&$this, 'remove_wp_verion_on_dashboard') );
		}
	}
	
	/**
	 * Remove wordpress verion on the url of scripts and styles.
	 *
	 * @since 1.0
	 */
	function remove_wp_verion_on_dashboard() {
		echo '<script type="text/javascript">jQuery(document).ready(function($) {$("#wp-version-message, #footer-upgrade").remove();});</script>';
	}
	
	/**
	 * Remove wordpress verion on the url of scripts and styles.
	 *
	 * @since 1.0
	 */
	function remove_wp_version_on_url($src) {
		global $wp_version;

		$src = explode('?ver=' . $wp_version, $src);
		return $src[0];
	}
	
	/**
	 * Remove core update information
	 *
	 * @since 1.0
	 */
	function remove_core_update() {
		if ( !current_user_can('update_plugins') ) {
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_notices', 'maintenance_nag' );" ) );
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_notices', 'update_nag', 3 );" ) );
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_init', '_maybe_update_core' );" ) );
			add_action( 'init', create_function( '$a', "remove_action( 'init', 'wp_version_check' );" ) );
			add_filter( 'pre_option_update_core', create_function( '$a', "return null;" ) );
			remove_action( 'wp_version_check', 'wp_version_check' );
			remove_action( 'admin_init', '_maybe_update_core' );
			add_filter( 'pre_transient_update_core', create_function( '$a', "return null;" ) );
			// 3.0
			add_filter( 'pre_site_transient_update_core', create_function( '$a', "return null;" ) );
			//wp_clear_scheduled_hook( 'wp_version_check' );
		}
	}

	/**
	 * Remove plugin update information
	 *
	 * @since 1.0
	 */
	function remove_plugin_update() {
		if ( !current_user_can('update_plugins') ) {
			add_action( 'wp_head', array(&$this, 'hide_plugin_update_div') );
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_init', 'wp_plugin_update_rows' );" ), 2 );
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_init', '_maybe_update_plugins' );" ), 2 );
			add_action( 'admin_menu', create_function( '$a', "remove_action( 'load-plugins.php', 'wp_update_plugins' );" ) );
			add_action( 'admin_init', create_function( '$a', "remove_action( 'admin_init', 'wp_update_plugins' );" ), 2 );
			add_action( 'init', create_function( '$a', "remove_action( 'init', 'wp_update_plugins' );" ), 2 );
			add_filter( 'pre_option_update_plugins', create_function( '$a', "return null;" ) );
			remove_action( 'load-plugins.php', 'wp_update_plugins' );
			remove_action( 'load-update.php', 'wp_update_plugins' );
			remove_action( 'admin_init', '_maybe_update_plugins' );
			remove_action( 'wp_update_plugins', 'wp_update_plugins' );
			// 3.0
			remove_action( 'load-update-core.php', 'wp_update_plugins' );
			add_filter( 'pre_transient_update_plugins', create_function( '$a', "return null;" ) );
			//wp_clear_scheduled_hook( 'wp_update_plugins' );
		}
	}
	
	/**
	 * Hide plugin update msg <div> element
	 *
	 * @since 1.0
	 */
	function hide_plugin_update_div() {
		echo '<style type="text/css">.update-plugins {display: none !important;}</style>';
	}

	/**
	 * Remove theme update information
	 *
	 * @since 1.0
	 */
	function remove_theme_update() {
		if ( !current_user_can('edit_themes') ) {
			remove_action( 'load-themes.php', 'wp_update_themes' );
			remove_action( 'load-update.php', 'wp_update_themes' );
			remove_action( 'admin_init', '_maybe_update_themes' );
			remove_action( 'wp_update_themes', 'wp_update_themes' );
			// 3.0
			remove_action( 'load-update-core.php', 'wp_update_themes' );
			//wp_clear_scheduled_hook( 'wp_update_themes' );
			add_filter( 'pre_transient_update_themes', create_function( '$a', "return null;" ) );
		}
	}

	/**
	 * Block bad url request
	 *
	 * @since 1.0
	 * @see http://perishablepress.com/press/2009/12/22/protect-wordpress-against-malicious-url-requests/
	 * @author Jeff Starr
	 */
	function block_bad_url_request() {
		global $user_ID;
			if ($user_ID) {
			if ( !current_user_can('manage_options') ) {
				if (strlen($_SERVER['REQUEST_URI']) > 255 ||
					stripos($_SERVER['REQUEST_URI'], "eval(") ||
					stripos($_SERVER['REQUEST_URI'], "CONCAT") ||
					stripos($_SERVER['REQUEST_URI'], "UNION+SELECT") ||
					stripos($_SERVER['REQUEST_URI'], "base64")) {
					@header("HTTP/1.1 414 Request-URI Too Long");
					@header("Status: 414 Request-URI Too Long");
					@header("Connection: Close");
					@exit;
				}
			}
		}
	}

}

$dp_tweaks = new DP_Tweaks();

load_plugin_textdomain( 'dp-tweaks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * DP Tweaks Panel
 * 
 * @since 1.0 
 */
class DP_Tweaks_Panel extends DP_Panel {

	function DP_Tweaks_Panel() {
		$this->menu_slug = 'dp-tweaks';
		$this->plugin_file = 'dp-tweaks/dp-tweaks.php';
		$this->textdomain = 'dp-tweaks';
		$this->wp_plugin_url = 'http://wordpress.org/extend/plugins/dp-tweaks/';
		$this->support_url = 'http://dedepress.com/support/forum/dp-tweaks';
		$this->settings_url = admin_url( 'options-general.php?page=dp-tweaks' );
		
		$this->DP_Panel();
	}
	
	function add_menu_pages() {
		$this->page_hook = add_options_page('DP Tweaks', 'DP Tweaks', 'edit_plugins', 'dp-tweaks', array(&$this, 'menu_page'));
	}
	
	function add_meta_boxes() {
		add_meta_box('dp-admin-tweaks-meta-box', __('Admin Tweaks', $this->textdomain), array(&$this, 'meta_box'), $this->page_hook, 'normal');
		add_meta_box('dp-speed-tweaks-meta-box', __('Speed Tweaks', $this->textdomain), array(&$this, 'meta_box'), $this->page_hook, 'normal');
		add_meta_box('dp-security-tweaks-meta-box', __('Security Tweaks', $this->textdomain), array(&$this, 'meta_box'), $this->page_hook, 'normal');
		
		$this->add_default_meta_boxes(array('plugin-info', 'like-this', 'need-support'));
	}
	
	function defaults() {
	
		/* General Tweaks */
		$defaults['dp-admin-tweaks-meta-box'] = array(
			array(
				'name' => 'dp_tweaks[show_ids]',
				'title' => __('Show IDs', $this->textdomain),
				'label' => __('Show IDs in admin edit view?', $this->textdomain),
				'value' => true,
				'type' => 'checkbox'
			),
			array(
				'name' => 'dp_tweaks[show_term_view_link]',
				'title' => __('Term View Link', $this->textdomain),
				'label' => __('Show view link in term edit view?', $this->textdomain),
				'value' => true,
				'type' => 'checkbox'
			),
			 array(
				'name' => 'dp_tweaks[autosave]',
				'title' => __('Autosave', $this->textdomain),
				'label' => __('Enable Autosave?', $this->textdomain),
				'type' => 'checkbox'
			),
			 array(
				'name' => 'dp_tweaks[post_revision]',
				'title' => __('Post Revision', $this->textdomain),
				'label' => __('Enable Post Revision?',  $this->textdomain),
				'type' => 'checkbox'
			),
			 array(
				'name' => 'dp_tweaks[self_pings]',
				'title' => __('Self Pings', $this->textdomain),
				'label' => __('Allow WordPress from sending pings to your own site?', $this->textdomain),
				'type' => 'checkbox'
			),
			 array(
				'name' => 'dp_tweaks[flash_uploader]',
				'title' => __('Flash Uploader', $this->textdomain),
				'label' => __('Enable the Flash-based media uploader?', 'remix'),
				'value' => true,
				'type' => 'checkbox'
			),
			array(
				'name' => 'dp_tweaks[force_delete]',
				'title' => __('Force delete', $this->textdomain),
				'label' => __('Enable both Trash and Delete Permanently at the same time. You can also disable trash completely by defining <code>EMPTY_TRASH_DAYS</code> to 0 in your wp-config.php file.', $this->textdomain),
				'value' => false,
				'type' => 'checkbox'
			)
		);
		
		/* Speed Tweaks */
		$defaults['dp-speed-tweaks-meta-box'] = array(
			array(
				'name' => 'dp_tweaks[google_cdn]',
				'title' => __('Google CDN', $this->textdomain),
				'desc' => '<br />'. __('Allows your site to use common javascript libraries from Google\'s AJAX Libraries CDN.', $this->textdomain),
				'value' => array('front'),
				'type' => 'checkboxes',
				'options' => array('front'=> __('Used in front-end?', $this->textdomain), 'admin' => __('Used in admin?', $this->textdomain))
			)
		);
		
		/* Security Tweaks */
		$defaults['dp-security-tweaks-meta-box'] = array(
			array(
				'name' => 'dp_tweaks[wp_version]',
				'title' => __('WordPress Version', $this->textdomain),
				'type' => 'checkboxes',
				'options' => array(
					'front' => __('Show WordPress version in your head file and RSS feeds?', $this->textdomain),
					'admin' => __('Show WordPress version in admin footer and dashboard for non-admins? ', $this->textdomain),
					'url' => __('Show WordPress version on the url of scripts and styles?', $this->textdomain)
				)
			),
			array(
				'name' => 'dp_tweaks[update_msgs]',
				'title' => __('Update Infomation', $this->textdomain),
				'type' => 'checkboxes',
				'options' => array(
					'core' => __('Show core update infomation for non-admin?', $this->textdomain),
					'plugin' => __('Show plugin update infomation for non-admin? ', $this->textdomain),
					'theme' => __('Show theme update infomation for non-admin?', $this->textdomain)
				)
			),
			array(
				'name' => 'dp_tweaks[login_error]',
				'title' => __('Login Error', $this->textdomain),
				'label' => __('Show Login Error Messages?', $this->textdomain),
				'value' => true,
				'type' => 'checkbox'
			),
			array(
				'name' => 'dp_tweaks[bloack_bad_url_request]',
				'title' => __('Block Bad Request', $this->textdomain),
				'label' => __('Protect WordPress against malicious URL requests?', $this->textdomain),
				'value' => true,
				'type' => 'checkbox'
			)
		);
		
		return $defaults;
	}
	
}
dp_register_panel('DP_Tweaks_Panel');