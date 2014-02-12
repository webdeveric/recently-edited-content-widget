<?php
/*
Plugin Name: Recently Edited Content Widget
Plugin URI: http://phplug.in/
Author: Eric King
Author URI: http://webdeveric.com/
Description: This plugin provides a dashboard widget that shows content you have modified recently.
Version: 0.2.10
Plugin Group: Dashboard Widgets
*/

if( ! function_exists('ellipsis') ){
	function ellipsis( $str,$max_len,$ellipsis="&hellip;" ){
		$str = trim( $str );
		$str_len=strlen( $str );
		if( $str_len<=$max_len ){
			return $str;
		} else {
			$ellipsis_len=strlen( $ellipsis );
			if( $ellipsis == '&hellip;' || $ellipsis == '&#8230;' )
				$ellipsis_len = 2;
			return substr( $str, 0, $max_len-$ellipsis_len ).$ellipsis;
		}
	}
}

if( ! function_exists('ellipsis_words') ){
	/**
		This is similar to ellipsis() but it doesn't count string length. It uses word counts instead.
	*/
	function ellipsis_words( $str, $max_words, $ellipsis="&hellip;" ){
		$str = trim( $str );
		$words = preg_split('/\s+/', $str );
		if( count( $words ) <= $max_words )return $str;
		return implode(' ', array_slice( $words, 0 , $max_words ) ) . $ellipsis;
	}
}


class RECW_Dashboard_Widget {

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

			// var_dump( get_post_types('','objects') );
			/*
				@todo Look at each post type object for the capabilities it needs and check if the current user is capable.
			*/

			while( $recent_content->have_posts() ):
				
				$recent_content->the_post();

				$url = $post->post_status == 'trash' ? add_query_arg('post', get_the_ID(), 'edit.php?post_status=trash&post_type=post') : get_edit_post_link( get_the_ID() );
				$title = get_the_title();
				$excerpt = self::$options['excerpt_length'] > 0 ? get_the_excerpt() : '';
				
				if( $img_excerpt = ( $post->post_type == 'attachment' && $excerpt == '' ) ){
					$tn_url = wp_get_attachment_thumb_url( get_the_ID() );
					$excerpt = sprintf('<img src="%1$s" alt="%2$s" title="%2$s" />', $tn_url, $post->post_title );
				}

				$author_id = $post->post_author;

				if ( $last_id = get_post_meta( get_the_ID(), '_edit_last', true ) ){
					$author_id = $last_id;
					unset( $last_id );
				}

				$author_name = get_userdata( $author_id )->display_name;
				$author = current_user_can('edit_users') ? sprintf('<a href="%1$s" title="Edit %2$s">%2$s</a>', get_edit_user_link( $author_id), $author_name ) : $author_name;
				unset( $author_id, $author_name );

				$item = "<div class='header'>
					<a class='post-title' href='$url' title='" . sprintf( __( 'Edit &#8220;%s&#8221;' ), esc_attr( $title ) ) . "'>" . esc_html($title) . '</a> <span class="post-type">- ' . $post->post_type . '</span> <span class="post-state">- ' . $post->post_status . '</span>
					<div class="row-actions">
						<span class="edit"><a href="' . $url . '">Edit</a></span> | <span class="view"><a href="' . get_permalink() . '">View</a></span>
					</div>
				</div>
				<div class="post-meta">
					<span class="post-editor">Edited by ' . $author . '</span> on <time class="publish-date" datetime="' . mysql2date('c', $post->post_modified ) . '">' . date_i18n('l, F jS, Y \a\t g:i A', strtotime( $post->post_modified ) ) . '</time>
				</div>
				<div class="content">';

				if( $img_excerpt ){
					$item .= $excerpt;
				} elseif( isset( $excerpt ) && $excerpt != '' ){
					$item .= wpautop( ellipsis_words( strip_tags( $excerpt, '<p><em><strong><i><b>'), self::$options['excerpt_length'], '&hellip;' ) );
				}

				$item .= '</div>';


				$list[] = sprintf('<div class="dashboard-recw-item %4$s %1$s post-type-%3$s">%2$s</div>', $even ? 'even' : 'odd', $item, $post->post_type, $post->post_status );

				$even = !$even;

			endwhile;

			wp_reset_query();

	?>
		<ul id="recently-edited-content-list">
			<li><?php echo implode( '</li><li>', $list ); ?></li>
		</ul>
	<?php
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

	public static function config( $empty_str = '', $config = array() ){
		$form_id = self::WIDGET_ID . '-control';

		self::load_options();

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST[ $form_id ] ) ){

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
		wp_enqueue_style( 'recw', plugins_url( '/css/dist/recently-edited-content-widget.min.css', __FILE__ ) );
	}


	public static function activate(){
		self::load_options( true );
	}


	public static function deactivate(){
		self::remove_options();
	}

}
add_action('wp_dashboard_setup', array('RECW_Dashboard_Widget', 'init') );

register_activation_hook( __FILE__, array('RECW_Dashboard_Widget', 'activate' ) );

register_deactivation_hook( __FILE__, array('RECW_Dashboard_Widget', 'deactivate' ) );