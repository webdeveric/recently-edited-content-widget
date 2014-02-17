<?php
/*
Plugin Name: Recently Edited Content Widget
Plugin URI: http://phplug.in/
Plugin Group: Dashboard Widgets
Author: Eric King
Author URI: http://webdeveric.com/
Description: This plugin provides a dashboard widget that shows content you have modified recently.
Version: 0.2.11
*/

class RECW_Dashboard_Widget {

	const VERSION		= '0.2.11';

	const WIDGET_ID		= 'recently-edited-content';
	const WIDGET_TITLE	= 'Recent Content';
	const USER_META_KEY	= 'recw_options';

	private static $fields = array();

	private static $options = array();

	public static function load_options( $save_options = false ){
		// Load default values
		if( empty( self::$options ) ){
			foreach( self::$fields as $key => $setting ){
				self::$options[ $key ] = $setting['value'];
			}
		}
		$user_id = get_current_user_id();
		if( $user_id > 0 ){
			if( $save_options ){
				update_user_meta( $user_id, self::USER_META_KEY, self::$options );
			} else {
				$stored_options = get_user_meta( $user_id, self::USER_META_KEY, true );
				foreach( self::$options as $option_name => $value ){
					if( isset( $stored_options[ $option_name ] ) )
						self::$options[ $option_name ] = $stored_options[ $option_name ];
				}
			}
		}
		return self::$options;
	}


	public static function remove_options(){
		delete_metadata( 'user', 0, self::USER_META_KEY, '', true );
	}

	public static function excerpt_length( $length ){
		return isset( self::$options, self::$options['excerpt_length'] ) ? self::$options['excerpt_length'] : $length;
	}

	public static function excerpt_more( $more ){
		return self::$options['excerpt_length'] > 0 ? '&hellip;' : '';
	}

	public static function display(){

		self::load_options();

		global $post;

		$get_posts_args = array(
			'suppress_filters' => true,
			'post_type' => array_keys( self::$options['post_types'] ),
			'post_status' => array_keys( self::$options['post_status'] ),
			'posts_per_page' => self::$options['num_items'],
			'orderby' => 'modified',
			'order' => 'DESC',
			// 'perm' => 'edit_posts'
		);

		if( self::$options['current_user_only'] == true ){
			$get_posts_args['meta_key'] = '_edit_last';
			$get_posts_args['meta_value'] = get_current_user_id();
		}

		$recent_content = new WP_Query( $get_posts_args );

		if( isset( $recent_content ) && $recent_content->have_posts() ){
			$list = array();
			$even = false;

			add_filter( 'excerpt_length', array( __CLASS__, 'excerpt_length'), PHP_INT_MAX );
			add_filter( 'excerpt_more', array( __CLASS__, 'excerpt_more'), PHP_INT_MAX );

			while( $recent_content->have_posts() ):
				
				$recent_content->the_post();

				$permalink	= get_permalink();
				$post_title	= get_the_title();

				if( $user_can_edit = current_user_can( 'edit_post', $post->ID ) ){
					$post_title_link_title = sprintf( __( 'Edit &#8220;%s&#8221;' ), esc_attr( $post_title ) );
					$url = $post->post_status == 'trash' ? add_query_arg('post', get_the_ID(), 'edit.php?post_status=trash&post_type=post') : get_edit_post_link( get_the_ID() );
					$post_title = sprintf('<a href="%s" title="%s">%s</a>', $url, $post_title_link_title, esc_html( $post_title ) );
				} else {
					// $post_title = sprintf('<span class="post-title">%s</span>', esc_html( $post_title ) );
					$post_title = esc_html( $post_title );
				}

				$post_status = $post->post_status == 'publish' ? 'published' : $post->post_status;

				$publish_date = date_i18n('M jS, Y \a\t g:i A', strtotime( $post->post_modified ) );
				$publish_date_datetime = mysql2date('c', $post->post_modified );

				$excerpt = wpautop( get_the_excerpt() );
				$author_id = $post->post_author;

				if( $last_id = get_post_meta( get_the_ID(), '_edit_last', true ) ){
					$author_id = $last_id;
					unset( $last_id );
				}

				$author_name = get_userdata( $author_id )->display_name;
				$author = current_user_can('edit_users') ? sprintf('<a href="%1$s" title="Edit %2$s">%2$s</a>', get_edit_user_link( $author_id), $author_name ) : $author_name;
				$author = sprintf('<cite>%s</cite>', $author );

				unset( $author_id, $author_name );

				$even_odd = $even ? 'even' : 'odd';
				$even = !$even;

				$thumbnail_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ) );

				if( $thumbnail_url !== false )
					list( $thumbnail_url ) = $thumbnail_url;

				switch( true ){
					case $thumbnail_url !== false && $user_can_edit:
						$thumbnail = sprintf('<a href="%s" class="thumbnail" style="background-image: url(%s);"></a>', $url, $thumbnail_url );
					break;
					case $thumbnail_url !== false && ! $user_can_edit:
						$thumbnail = sprintf('<div class="thumbnail" style="background-image: url(%s);"></div>', $thumbnail_url );
					break;
					case $thumbnail_url === false && $user_can_edit:
						$thumbnail = sprintf('<a href="%s" class="thumbnail empty"></a>', $url );
					break;
					// case $thumbnail_url === false && ! $user_can_edit:
					default:
						$thumbnail = '<div class="thumbnail empty"></div>';
				}

				$actions = self::get_action_links();

$list[]=<<<ITEM

<div class="dashboard-recw-item {$post->post_status} {$even_odd} post-type-{$post->post_type}">
	{$thumbnail}
	<div class="dashboard-recw-item-wrap">

		<h4 class="post-title">
			{$post_title}
			<span class="post-type-meta"><span class="meta-sep"> - </span><span class="post-type">{$post->post_type}</span><span class="meta-sep"> - </span><span class="post-state">{$post_status}</span></span>
		</h4>

		<div class="post-meta">
			<span class="post-editor">Edited by {$author}</span> on <time class="publish-date" datetime="{$publish_date_datetime}">{$publish_date}</time>
		</div>

		{$excerpt}

		{$actions}

	</div>

</div>

ITEM;

			endwhile;

			wp_reset_query();

			remove_filter( 'excerpt_length', array( __CLASS__, 'excerpt_length'), PHP_INT_MAX );
			remove_filter( 'excerpt_more', array( __CLASS__, 'excerpt_more'), PHP_INT_MAX );	

			echo implode( '', $list );

		} else {

			$message = '<p>There isn&#8217;t any recently edited content in the system.</p>';

			if( self::$options['current_user_only'] == true ){
				global $wpdb;
				$num_posts = $wpdb->get_var('select count(*) from ' . $wpdb->posts );
				$num_edits = $wpdb->get_var('select count(*) from ' . $wpdb->postmeta . ' where meta_key = "_edit_last"' );
				$message = '<p>You don&#8217;t have any recently edited content in the system.</p>';
				if( $num_posts > 0 && $num_edits == 0 )
					$message .= '<p>It looks like you have a new site or have just imported your data. Started editing your content to have it show up here.</p>';
			}

			printf('<div class="dashboard-recw-notice">%s</div>', __( $message ) );

		}

	}

	public static function get_admin_color( $index = 0, $default_color = '#333' ){
		static $colors = null;
		if( ! isset( $colors ) ){
			global $wp_styles, $_wp_admin_css_colors;
			$color_scheme = get_user_option( 'admin_color' );
			if( isset( $color_scheme ) && $color_scheme !== false && $color_scheme != '' )
				$colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
		}
		return isset( $colors[ $index ] ) ? $colors[ $index ] : $default_color;
	}

	public static function get_action_links(){

		global $post;
		$edit_link = get_edit_post_link( $post->ID );
		$title = _draft_or_post_title();
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		$actions = array();

		if( $can_edit_post && 'trash' != $post->post_status ){
			$actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '" title="' . esc_attr( __( 'Edit this item' ) ) . '">' . __( 'Edit' ) . '</a>';
		}

		if( current_user_can( 'delete_post', $post->ID ) ){
			if( 'trash' == $post->post_status )
				$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash' ) ) . "' href='" . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ) . "'>" . __( 'Restore' ) . "</a>";
			elseif( EMPTY_TRASH_DAYS )
				$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash' ) ) . "' href='" . get_delete_post_link( $post->ID ) . "'>" . __( 'Trash' ) . "</a>";
			if( 'trash' == $post->post_status || !EMPTY_TRASH_DAYS )
				$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently' ) ) . "' href='" . get_delete_post_link( $post->ID, '', true ) . "'>" . __( 'Delete' ) . "</a>";
		}

		if( $post_type_object->public ){
			if( in_array( $post->post_status, array( 'pending', 'draft', 'future' ) ) ){
				if( $can_edit_post )
					$actions['view'] = '<a href="' . esc_url( apply_filters( 'preview_post_link', set_url_scheme( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
			} elseif( 'trash' != $post->post_status ){
				$actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
			}
		}

		$actions = apply_filters( is_post_type_hierarchical( $post->post_type ) ? 'page_row_actions' : 'post_row_actions', $actions, $post );

		if( empty( $actions ) )
			return '';

		$action_links = array();
		foreach ( $actions as $action => $link ){
			$action_links[] = sprintf('<span class="%s">%s</span>', $action, $link );
		}

		return '<p class="row-actions">' . implode('|', $action_links ) . '</p>';
	}

	public static function config( $empty_str = '', $config = array() ){
		$form_id = self::WIDGET_ID . '-control';

		self::load_options();

		if( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST[ $form_id ] ) ){

			foreach( self::$fields as $option_name => $opt ){

				switch( $opt['type'] ){
					case 'int':

						if( isset( $_POST[ $form_id ][ $option_name ] ) && is_numeric( $_POST[ $form_id ][ $option_name ] ) ){
							self::$options[ $option_name ] = (int)$_POST[ $form_id ][ $option_name ];
							if( isset( self::$fields[ $option_name ]['minvalue'] ) && self::$options[ $option_name ] < self::$fields[ $option_name ]['minvalue'] )
								self::$options[ $option_name ] = self::$fields[ $option_name ]['minvalue'];
							if( isset( self::$fields[ $option_name ]['maxvalue'] ) && self::$options[ $option_name ] > self::$fields[ $option_name ]['maxvalue'] )
								self::$options[ $option_name ] = self::$fields[ $option_name ]['maxvalue'];
						} else {
							self::$options[ $option_name ] = self::$fields[ $option_name ]['value'];
						}

					break;
					case 'bool':
						if( isset( $opt['values'] ) ){
							self::$options[ $option_name ] = array();
							foreach( $opt['values'] as $name => $post_type ){
								if( isset( $_POST[ $form_id ][ $option_name ][ $name ] ) && ( $_POST[ $form_id ][ $option_name ][ $name ] == true || $_POST[ $form_id ][ $option_name ][ $name ] == 'true' ) )
									self::$options[ $option_name ][ $name ] = true;
								//printf('<pre>self::$options[ %s ][ %s ] = %d</pre>', $option_name , $name, self::$options[ $option_name ][ $name ] );
							}
						} else {
							self::$options[ $option_name ] = ( isset( $_POST[ $form_id ][ $option_name ] ) && ( $_POST[ $form_id ][ $option_name ] == true || $_POST[ $form_id ][ $option_name ] == 'true' ) );
						}
					break;
					default:
						self::$options[ $option_name ] = isset( $_POST[ $form_id ][ $option_name ] ) ? esc_html( $_POST[ $form_id ][ $option_name ] ) : self::$fields[ $option_name ]['value'];
				}

			}
			self::load_options( true );
		}

		foreach( self::$fields as $option_name => $opt ){
			echo '<p>';
			echo '<label for="' . self::WIDGET_ID . '-' . $option_name . '">' . __( $opt['label'] ) . '</label>';

			$input = '<input id="' . self::WIDGET_ID . '-' . $option_name . '" name="'.$form_id.'[' . $option_name . ']" type="' . $opt['input'] . '" value="%s" %s />';
			switch( $opt['input'] ){
				case 'checkbox':
					if( isset( $opt['values'] ) ){
						$checkboxes = array();
						foreach( $opt['values'] as $name => $label ){
							//printf('<pre>%s</pre>', print_r( $post_type, true ) );
							$checkboxes[] = sprintf(
								'<label><input id="' . self::WIDGET_ID . '-' . $option_name . '" name="'.$form_id.'[' . $option_name . '][' . $name . ']" type="' . $opt['input'] . '" value="%s" %s /> %s</label>',
								true,
								checked( self::$options[ $option_name ][ $name ], true, false ),
								$label
							);
						}
						echo '<ul><li>' . implode('</li><li>', $checkboxes ) . '</li></ul>';
					} else {
						printf( $input, true, checked( self::$options[ $option_name ], true, false ) );
					}
				break;
				case 'number':

					$min	= isset( $opt['minvalue'] ) ? $opt['minvalue'] : 0;
					$max	= isset( $opt['maxvalue'] ) ? $opt['maxvalue'] : 999;
					$size	= strlen( $max );

					printf(
						$input,
						self::$options[ $option_name ],
						self::html_attr( compact( 'size', 'min', 'max' ) )
					);

				break;
				default:
					printf( $input, self::$options[ $option_name ], '' );
			}
		    echo '</p>';
		}
	}

	public static function html_attr( array $attributes = array() ){
		$attr = array();
		foreach( $attributes as $name => $value ){
			$attr[] = $name . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $attr );
	}

	public static function get_post_types(){
		static $post_types;
		if( ! isset( $post_types ) ){
			$post_types = get_post_types('','objects');
			foreach( $post_types as $name => $type ){
				$post_types[ $name ] = $type->label;
			}
		}
		return $post_types;
	}

	public static function init(){
		if( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_others_posts' ) ) )
			return;

		add_action( 'admin_head-index.php', array( __CLASS__, 'admin_head' ) );

		self::$fields = array(
			'num_items' => array(
				'type'	=> 'int',
				'input'	=> 'number',
				'label'	=> 'Number of items to show:',
				'value'	=> 5,
				'minvalue' => 1,
				'maxvalue' => 100
			),
			'excerpt_length' => array(
				'type'	=> 'int',
				'input'	=> 'number',
				'label'	=> 'Excerpt length (# of words):',
				'value'	=> 30,
				'minvalue' => 0
			),
			'current_user_only' => array(
				'type'	=> 'bool',
				'input'	=> 'checkbox',
				'label'	=> 'Only show my edits:',
				'value'	=> false
			),
			'post_types' => array(
				'type'	=> 'bool',
				'input'	=> 'checkbox',
				'label'	=> 'Post types:',
				'values'=> self::get_post_types(),
				'value'	=> array_fill_keys(
					array_keys(
						array_diff_key(
							self::get_post_types(),
							array(
								'nav_menu_item'	=> false,
								'revision'		=> false
							)
						)
					),
					true
				)
			),
			'post_status' => array(
				'type'	=> 'bool',
				'input'	=> 'checkbox',
				'label'	=> 'Post status:',
				'values'=> array(
					'publish'		=> 'Published',
					'pending'		=> 'Pending Review',
					'draft'			=> 'Draft',
					//'auto-draft'	=> 'Auto Draft',
					'future'		=> 'Future',
					'private'		=> 'Private',
					'inherit'		=> 'Inherit (Revision)',
					'trash'			=> 'Trash'
				),
				'value'	=> array(
					'publish'	=> true,
					'pending'	=> true,
					'draft'		=> true,
					'future'	=> true,
					'private'	=> true,
					'trash'		=> true
				)
			)
		);

		wp_add_dashboard_widget( self::WIDGET_ID, self::WIDGET_TITLE, array( __CLASS__, 'display' ), array( __CLASS__, 'config' ) );
		wp_enqueue_style( 'recw', plugins_url( '/css/dist/recently-edited-content-widget.min.css', __FILE__ ), array(), self::VERSION );
	}

	public static function admin_head(){
		printf('<style>#recently-edited-content .inside .dashboard-recw-item .thumbnail.empty{background-color:%s;}</style>', self::get_admin_color( 3 ) );
	}

	public static function activate(){
		self::load_options( true );
	}

	public static function deactivate(){
		self::remove_options();
	}

}

add_action( 'wp_dashboard_setup',		array( 'RECW_Dashboard_Widget', 'init' ) );
register_activation_hook( __FILE__,		array( 'RECW_Dashboard_Widget', 'activate' ) );
register_deactivation_hook( __FILE__,	array( 'RECW_Dashboard_Widget', 'deactivate' ) );