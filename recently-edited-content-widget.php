<?php
/*
Plugin Name: Recently Edited Content Widget
Plugin URI: http://phplug.in/
Plugin Group: Dashboard Widgets
Author: Eric King
Author URI: http://webdeveric.com/
Description: This plugin provides a dashboard widget that shows content you have modified recently.
Version: 0.3.2
*/

class RecentlyEditedContentWidget
{
    const VERSION       = '0.3.2';
    const WIDGET_ID     = 'recently-edited-content';
    const WIDGET_TITLE  = 'Recent Content';
    const USER_META_KEY = 'recw_options';

    private static $fields = array();

    private static $options = array();

    public static function init()
    {
        add_action('wp_dashboard_setup',        array(__CLASS__, 'dashboardSetup'));
        add_action('wp_ajax_recw_search',       array(__CLASS__, 'ajaxSearch'));
        add_action('wp_ajax_recw_autocomplete', array(__CLASS__, 'ajaxAutoComplete'));
        register_activation_hook(__FILE__,      array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__,    array(__CLASS__, 'deactivate'));
    }

    public static function blankExcerpt($more)
    {
        return '';
    }

    public static function addFilters()
    {
        add_filter('excerpt_more', array(__CLASS__, 'blankExcerpt'), PHP_INT_MAX, 1);
    }

    public static function removeFilters()
    {
        remove_filter('excerpt_more', array(__CLASS__, 'blankExcerpt'), PHP_INT_MAX, 1);
    }

    public static function loadOptions($save_options = false)
    {
        // Load default values
        if (empty(self::$options)) {
            foreach (self::$fields as $key => $setting) {
                self::$options[$key] = $setting['value'];
            }
        }

        $user_id = get_current_user_id();

        if ($user_id > 0) {
            if ($save_options) {
                update_user_meta($user_id, self::USER_META_KEY, self::$options);
            } else {
                $stored_options = get_user_meta($user_id, self::USER_META_KEY, true);
                foreach (self::$options as $option_name => $value) {
                    if (isset($stored_options[$option_name]))
                        self::$options[$option_name] = $stored_options[$option_name];
                }
            }
        }

        return self::$options;
    }

    public static function remove_options()
    {
        delete_metadata('user', 0, self::USER_META_KEY, '', true);
    }

    public static function excerpt_length($length)
    {
        return isset(self::$options, self::$options['excerpt_length']) ? self::$options['excerpt_length'] : $length;
    }

    public static function excerpt_more($more)
    {
        return self::$options['excerpt_length'] > 0 ? '&hellip;' : '';
    }

    protected static function getQuery($args = array())
    {
        $default_args = array(
            'suppress_filters' => true,
            'post_type'        => array_keys(array_filter(self::$options['post_types'])),
            'post_status'      => array_keys(array_filter(self::$options['post_status'])),
            'posts_per_page'   => self::$options['num_items'],
            'orderby'          => 'modified',
            'order'            => 'DESC',
        );

        $args = array_merge($default_args, $args);

        // Prior to WP 3.2, the post_status argument was assumed to be a string.
        if (version_compare(get_bloginfo('version'), '3.2', '<') && is_array($args['post_status'])) {
            $args['post_status'] = implode(',', $args['post_status']);
        }

        if (self::$options['current_user_only'] == true) {
            $args['meta_key'] = '_edit_last';
            $args['meta_value'] = get_current_user_id();
        }

        return new WP_Query($args);
    }

    public static function display()
    {

        self::loadOptions();

        global $post;

        $recent_content = self::getQuery();

        if (isset($recent_content) && $recent_content->have_posts()) {
            $list = array();
            $even = false;

            add_filter('excerpt_length', array(__CLASS__, 'excerpt_length'), PHP_INT_MAX);
            add_filter('excerpt_more',   array(__CLASS__, 'excerpt_more'),   PHP_INT_MAX);

            $dashicons_class = version_compare(get_bloginfo('version'), '3.8', '>=') ? 'has-dashicons' : 'no-dashicons';

            while ($recent_content->have_posts()):

                $recent_content->the_post();

                $permalink    = get_permalink();
                $post_title    = get_the_title();

                if ($user_can_edit = current_user_can('edit_post', $post->ID)) {
                    $post_title_link_title = sprintf(__('Edit &#8220;%s&#8221;'), esc_attr($post_title));
                    $url = $post->post_status == 'trash' ? add_query_arg('post', get_the_ID(), 'edit.php?post_status=trash&post_type=post') : get_edit_post_link(get_the_ID());
                    $post_title = sprintf('<a href="%s" title="%s">%s</a>', $url, $post_title_link_title, esc_html($post_title));
                } else {
                    // $post_title = sprintf('<span class="post-title">%s</span>', esc_html($post_title));
                    $post_title = esc_html($post_title);
                }

                $post_status = $post->post_status == 'publish' ? 'published' : $post->post_status;

                $publish_date = date_i18n('M jS, Y \a\t g:i A', strtotime($post->post_modified));
                $publish_date_datetime = mysql2date('c', $post->post_modified);

                $excerpt = wpautop(get_the_excerpt());
                $author_id = $post->post_author;

                if ($last_id = get_post_meta(get_the_ID(), '_edit_last', true)) {
                    $author_id = $last_id;
                    unset($last_id);
                }

                $author_name = get_userdata($author_id)->display_name;
                $author = current_user_can('edit_users') && function_exists(' get_edit_user_link') ? sprintf('<a href="%1$s" title="Edit %2$s">%2$s</a>', get_edit_user_link($author_id), $author_name) : $author_name;
                $author = sprintf('<cite>%s</cite>', $author);

                unset($author_id, $author_name);

                $even_odd = $even ? 'even' : 'odd';
                $even = !$even;

                $thumbnail_url = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID));

                if ($thumbnail_url !== false)
                    list($thumbnail_url) = $thumbnail_url;

                switch (true) {
                    case $thumbnail_url !== false && $user_can_edit:
                        $thumbnail = sprintf('<a href="%1$s" class="thumbnail %3$s" style="background-image: url(%2$s);"></a>', $url, $thumbnail_url, $dashicons_class);
                        break;
                    case $thumbnail_url !== false && ! $user_can_edit:
                        $thumbnail = sprintf('<div class="thumbnail %2$s" style="background-image: url(%1$s);"></div>', $thumbnail_url, $dashicons_class);
                        break;
                    case $thumbnail_url === false && $user_can_edit:
                        $thumbnail = sprintf('<a href="%1$s" class="thumbnail %2$s empty"></a>', $url, $dashicons_class);
                        break;
                    default:
                        $thumbnail = sprintf('<div class="thumbnail %1$s empty"></div>', $dashicons_class);
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

            remove_filter('excerpt_length', array(__CLASS__, 'excerpt_length'), PHP_INT_MAX);
            remove_filter('excerpt_more',   array(__CLASS__, 'excerpt_more'),   PHP_INT_MAX);

            self::searchForm();

            echo implode('', $list);

        } else {

            $message = '<p>There isn&#8217;t any recently edited content in the system.</p>';

            if (self::$options['current_user_only'] == true) {

                global $wpdb;
                $num_posts = $wpdb->get_var('select count(*) from ' . $wpdb->posts);
                $num_edits = $wpdb->get_var('select count(*) from ' . $wpdb->postmeta . ' where meta_key = "_edit_last"');
                $message = '<p>You don&#8217;t have any recently edited content in the system.</p>';
                if ($num_posts > 0 && $num_edits == 0)
                    $message .= '<p>It looks like you have a new site or have just imported your data. Started editing your content to have it show up here.</p>';

            }

            printf('<div class="dashboard-recw-notice">%s</div>', __($message));

        }

    }

    protected static function searchForm($return_value = false){

           $nonce = wp_nonce_field('search','recw_action', true, false );

        $form = sprintf(
            '<form id="recw-search" role="search" method="post" action="" accept-charset="UTF-8">
                %1$s
                <input id="recw-search-input" type="text" required value="" name="search-term" placeholder="Search for recent content" class="widefat" /><button type="submit" id="recw-search-button" class="button button-secondary">Search</button>
                <div id="recw-search-output"></div><noscript><p>This form requires JavaScript to be enabled.</p></noscript>
            </form>',
            $nonce
        );

        if ($return_value)
            return $form;

        echo $form;


    }

    public static function getAdminColor($index = 0, $default_color = '#333')
    {
        static $colors = null;
        if (! isset($colors)) {
            global $wp_styles, $_wp_admin_css_colors;
            $color_scheme = get_user_option('admin_color');
            if (isset($color_scheme) && $color_scheme !== false && $color_scheme != '')
                $colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
        }
        return isset($colors[ $index ]) ? $colors[ $index ] : $default_color;
    }

    public static function get_action_links() {

        global $post;
        $edit_link = get_edit_post_link($post->ID);
        $title = _draft_or_post_title();
        $post_type_object = get_post_type_object($post->post_type);
        $can_edit_post = current_user_can('edit_post', $post->ID);

        $actions = array();

        if ($can_edit_post && 'trash' != $post->post_status) {
            $actions['edit'] = '<a href="' . get_edit_post_link($post->ID, true) . '" title="' . esc_attr(__('Edit this item')) . '">' . __('Edit') . '</a>';
        }

        if (current_user_can('delete_post', $post->ID)) {
            if ('trash' == $post->post_status)
                $actions['untrash'] = "<a title='" . esc_attr(__('Restore this item from the Trash')) . "' href='" . wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $post->ID)), 'untrash-post_' . $post->ID) . "'>" . __('Restore') . "</a>";
            elseif (EMPTY_TRASH_DAYS)
                $actions['trash'] = "<a class='submitdelete' title='" . esc_attr(__('Move this item to the Trash')) . "' href='" . get_delete_post_link($post->ID) . "'>" . __('Trash') . "</a>";
            if ('trash' == $post->post_status || !EMPTY_TRASH_DAYS)
                $actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Delete this item permanently')) . "' href='" . get_delete_post_link($post->ID, '', true) . "'>" . __('Delete') . "</a>";
        }

        if ($post_type_object->public) {
            if (in_array($post->post_status, array('pending', 'auto-draft', 'draft', 'future'))) {
                if ($can_edit_post) {
                    $preview_url = add_query_arg('preview', 'true', get_permalink($post->ID));
                    if (function_exists('set_url_scheme'))
                        $preview_url = set_url_scheme($preview_url);
                    $actions['view'] = '<a href="' . esc_url(apply_filters('preview_post_link', $preview_url)) . '" title="' . esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $title)) . '" rel="permalink">' . __('Preview') . '</a>';
                }
            } elseif ('trash' != $post->post_status) {
                $actions['view'] = '<a href="' . get_permalink($post->ID) . '" title="' . esc_attr(sprintf(__('View &#8220;%s&#8221;'), $title)) . '" rel="permalink">' . __('View') . '</a>';
            }
        }

        $actions = apply_filters(is_post_type_hierarchical($post->post_type) ? 'page_row_actions' : 'post_row_actions', $actions, $post);

        if (empty($actions))
            return '';

        $action_links = array();
        foreach ($actions as $action => $link) {
            $action_links[] = sprintf('<span class="%s">%s</span>', $action, $link);
        }

        return '<p class="row-actions">' . implode('|', $action_links) . '</p>';
    }

    public static function number_in_range($num, $min, $max)
    {
        return min(max($min, $num), $max);
    }

    public static function config($empty_str = '', $config = array())
    {
        $form_id = self::WIDGET_ID . '-control';

        self::loadOptions();

        if (filter_has_var(INPUT_POST, $form_id) && isset($_POST[ $form_id ])) {

            $data = $_POST[ $form_id ];

            foreach(self::$fields as $option_name => $opt) {

                switch($opt['type']) {
                    case 'int':

                        if (isset($data[ $option_name ]) && is_numeric($data[ $option_name ])) {

                            self::$options[ $option_name ] = (int)$data[ $option_name ];

                            if (isset($opt['minvalue']) && self::$options[ $option_name ] < $opt['minvalue'])
                                self::$options[ $option_name ] = $opt['minvalue'];

                            if (isset($opt['maxvalue']) && self::$options[ $option_name ] > $opt['maxvalue'])
                                self::$options[ $option_name ] = $opt['maxvalue'];

                        } else {

                            self::$options[ $option_name ] = $opt['value'];

                        }

                    break;
                    case 'bool':
                        if (isset($opt['values'])) {
                            self::$options[ $option_name ] = array();
                            foreach($opt['values'] as $name => $post_type) {
                                if (isset($data[ $option_name ][ $name ]) && ($data[ $option_name ][ $name ] == true || $data[ $option_name ][ $name ] == 'true'))
                                    self::$options[ $option_name ][ $name ] = true;
                                //printf('<pre>self::$options[ %s ][ %s ] = %d</pre>', $option_name , $name, self::$options[ $option_name ][ $name ]);
                            }
                        } else {
                            self::$options[ $option_name ] = (isset($data[ $option_name ]) && ($data[ $option_name ] == true || $data[ $option_name ] == 'true'));
                        }
                    break;
                    default:
                        self::$options[ $option_name ] = isset($data[ $option_name ]) ? esc_html($data[ $option_name ]) : $opt['value'];
                }

            }
            self::loadOptions(true);
        }

        foreach (self::$fields as $option_name => $opt) {
            echo '<p>';
            echo '<label for="' . self::WIDGET_ID . '-' . $option_name . '">' . __($opt['label']) . '</label>';

            $input = '<input id="' . self::WIDGET_ID . '-' . $option_name . '" name="'.$form_id.'[' . $option_name . ']" type="' . $opt['input'] . '" value="%s" %s />';
            switch($opt['input']) {
                case 'checkbox':
                    if (isset($opt['values'])) {
                        $checkboxes = array();
                        foreach($opt['values'] as $name => $label) {

                            $opt_checked = isset(self::$options[ $option_name ][ $name ]) ? self::$options[ $option_name ][ $name ] : 0;

                            $checkboxes[] = sprintf(
                                '<label title="%3$s"><input id="' . self::WIDGET_ID . '-' . $option_name . '" name="'.$form_id.'[' . $option_name . '][' . $name . ']" type="' . $opt['input'] . '" value="%1$s" %2$s /><span>%3$s</span></label>',
                                true,
                                checked($opt_checked, true, false),
                                $label
                            );

                        }
                        echo '<ul><li>' . implode('</li><li>', $checkboxes) . '</li></ul>';
                    } else {
                        printf($input, true, checked(self::$options[ $option_name ], true, false));
                    }
                    break;
                case 'number':

                    $min    = isset($opt['minvalue']) ? $opt['minvalue'] : 0;
                    $max    = isset($opt['maxvalue']) ? $opt['maxvalue'] : 999;
                    $size    = strlen($max);

                    printf(
                        $input,
                        self::$options[ $option_name ],
                        self::htmlAttr(compact('size', 'min', 'max'))
                    );

                    break;
                default:
                    printf($input, self::$options[ $option_name ], '');
            }
            echo '</p>';
        }
    }

    public static function htmlAttr(array $attributes = array())
    {
        $attr = array();
        foreach($attributes as $name => $value) {
            $attr[] = $name . '="' . esc_attr($value) . '"';
        }
        return implode(' ', $attr);
    }

    public static function getPostTypeLables($do_not_include = array())
    {
        return self::getObjectLabels(
            get_post_types(array(), 'objects'),
            $do_not_include
        );
    }

    public static function getPostStatiLables($do_not_include = array())
    {
        return self::getObjectLabels(
            get_post_stati(array(), 'objects'),
            $do_not_include
        );
    }

    protected static function getObjectLabels($objects, $do_not_include = array())
    {
        $object_labels = array();
        foreach ($objects as $name => $type)
            $object_labels[ $name ] = ucwords($type->label);

        if ( ! is_array($do_not_include)) {
            if (is_string($do_not_include))
                $do_not_include = array_map('trim', explode(',', $do_not_include));
            else
                $do_not_include = (array) $do_not_include;
        }

        foreach ($do_not_include as $key)
            if (array_key_exists($key, $object_labels))
                unset($object_labels[ $key ]);

        asort($object_labels);

        return $object_labels;
    }

    protected static function initFields()
    {
        $default_post_types = array_merge(
            array_fill_keys(get_post_types(array('public' => true), 'names'), true),
            array_fill_keys(get_post_types(array('public' => false), 'names'), false)
        );
        $default_post_types['attachment'] = false;

        $default_post_stati = array_fill_keys(get_post_stati(), true);
        $default_post_stati['auto-draft'] = false;

        self::$fields = array(
            'num_items' => array(
                'type'     => 'int',
                'input'    => 'number',
                'label'    => 'Number of items to show:',
                'value'    => 5,
                'minvalue' => 1,
                'maxvalue' => 100
            ),
            'excerpt_length' => array(
                'type'     => 'int',
                'input'    => 'number',
                'label'    => 'Excerpt length <span class="nowrap">(# of words)</span>:',
                'value'    => 30,
                'minvalue' => 0
            ),
            'current_user_only' => array(
                'type'  => 'bool',
                'input' => 'checkbox',
                'label' => 'Only show my edits:',
                'value' => false
            ),
            'post_types' => array(
                'type'   => 'bool',
                'input'  => 'checkbox',
                'label'  => 'Post types:',
                'values' => self::getPostTypeLables(),
                'value'  => $default_post_types
            ),
            'post_status' => array(
                'type'    => 'bool',
                'input'   => 'checkbox',
                'label'   => 'Post statuses:',
                'values'  => self::getPostStatiLables(array('auto-draft')),
                'value'   => $default_post_stati
            )
        );
    }

    public static function dashboardSetup()
    {

        if ( ! (current_user_can('edit_posts') || current_user_can('edit_others_posts')))
            return;

        add_action('admin_head-index.php', array(__CLASS__, 'adminHead'));

        self::initFields();

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            apply_filters('recw-dashboard-widget-title', self::WIDGET_TITLE),
            array(__CLASS__, 'display'),
            array(__CLASS__, 'config')
        );

        wp_enqueue_style('recw', plugins_url('/css/main.css', __FILE__ ), array(), self::VERSION );
        wp_enqueue_script('recw', plugins_url( '/js/dist/main.js', __FILE__ ), array('jquery', 'jquery-ui-autocomplete'), self::VERSION );
    }

    public static function adminHead()
    {
        // printf('<style>#recently-edited-content .inside .dashboard-recw-item .thumbnail.empty{background-color:%1$s;}#recw-search{border-left:4px solid %2$s;}</style>', self::getAdminColor(3), self::getAdminColor(2));
        printf('<style>#recently-edited-content .inside .dashboard-recw-item .thumbnail.empty{background-color:%1$s;}</style>', self::getAdminColor(3));
    }

    public static function activate()
    {
        self::loadOptions(true);
    }

    public static function deactivate()
    {
        self::remove_options();
    }

    public static function ajaxSearch()
    {
        self::initFields();
        self::loadOptions();
        self::addFilters();

        $data = array(
            'items'  => array(),
            'errors' => array(),
        );

        if ( ! (filter_has_var(INPUT_POST, 'recw_action') && wp_verify_nonce( $_POST['recw_action'], 'search'))) {
            $data['errors'][] = 'Empty or invalid nonce value.';
        } elseif ( filter_has_var(INPUT_POST, 'term') && $_POST['term'] == '') {
            $data['errors'][] = 'Please specify a search term.';
        } else {

            $term = filter_has_var(INPUT_POST, 'term') ? $_POST['term'] : '';

            $get_posts_args = array(
                'posts_per_page' => 5,
                's'              => $term
            );

            $search = self::getQuery($get_posts_args);

            while ($search->have_posts()) {
                $search->the_post();
                $item            = new stdClass;
                $item->title     = get_the_title();
                $item->excerpt   = wpautop(get_the_excerpt());
                $item->edit_link = get_edit_post_link(get_the_ID());
                $data['items'][] = $item;
            }

            wp_reset_query();

        }

        self::removeFilters();

        if ( ! empty($errors)) {
            $data['errors'] = $errors;
            wp_send_json_error($data);
            exit;
        }

        wp_send_json_success( $data );
        exit;
    }


    public static function ajaxAutoComplete()
    {
        self::initFields();
        self::loadOptions();

        $data = array(
            'terms'  => array(),
            'errors' => array(),
        );

        if ( ! (filter_has_var(INPUT_POST, 'recw_action') && wp_verify_nonce( $_POST['recw_action'], 'search'))) {
            $data['errors'][] = 'Empty or invalid nonce value.';
        } elseif ( filter_has_var(INPUT_POST, 'term') && $_POST['term'] == '') {
            $data['errors'][] = 'Please specify a search term.';
        } else {

            $term = filter_has_var(INPUT_POST, 'term') ? $_POST['term'] : '';

            $get_posts_args = array(
                'posts_per_page' => 5,
                // 'orderby'     => 'none',
                's'              => $term,
            );

            $search = self::getQuery($get_posts_args);

            while ($search->have_posts()) {
                $search->the_post();
                $data['terms'][] = get_the_title();
            }

            wp_reset_query();

        }

        if ( ! empty($errors)) {
            $data['errors'] = $errors;
            wp_send_json_error($data);
            exit;
        }

        wp_send_json_success( $data );
        exit;
    }

}

RecentlyEditedContentWidget::init();
