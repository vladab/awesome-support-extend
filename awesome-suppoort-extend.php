<?php
/*
Plugin Name: Awesome Support Extend
Plugin URI: http://getawesomesupport.com v3W.1.2
Description: Extend Awesome Support plugin with pagination custom search and other goodies
Version: 1.0.0
Author: Vladica Bibeskovic
Author URI: http://github.com/vladab
*/
function wpdt_awesome_support_scripts()
{
    wp_enqueue_script( 'wp_typeahead_js', plugins_url( '/', __FILE__ ) . '/typea/js/typeahead.min.js', array( 'jquery' ), '', true );
    wp_enqueue_script( 'wp_hogan_js' , plugins_url( '/', __FILE__ ) . '/typea/js/hogan.min.js', array( 'wp_typeahead_js' ), '', true );

    wp_enqueue_script( 'typeahead_wp_plugin' , plugins_url( '/', __FILE__ ) . '/typea/js/wp-typeahead.js', array( 'wp_typeahead_js', 'wp_hogan_js' ), '', true );
    $wp_typeahead_vars = array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) );
    wp_localize_script( 'typeahead_wp_plugin', 'wp_typeahead', $wp_typeahead_vars );

    wp_enqueue_style( 'wp_typeahead_css', plugins_url( '/', __FILE__ ) . '/typea/css/typeahead.css' );
}

add_action('wp_enqueue_scripts', 'wpdt_awesome_support_scripts');
// List all tickets in my tickets list
add_action('wpas_before_tickets_list','wpdt_wpas_before_tickets_list',10,1);
function wpdt_wpas_before_tickets_list(){
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
        //'author'                 => $author,
        'post_type'              => 'ticket',
        'post_status'            => 'any',
        'order'                  => 'DESC',
        'orderby'                => 'date',
        'posts_per_page' 		 => 20,
        'paged' 				 => $paged,
        'no_found_rows'          => false,
        'cache_results'          => false,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,

    );
    if( isset( $_REQUEST['searchString']) && $_REQUEST['searchString'] != '' ) {
        $results = array();
        $args['post_title_like'] = $_REQUEST['searchString'];
        $args['order'] = 'ASC';
        $args['orderby'] = 'title';

        global $wpas_tickets;
        $wpas_tickets = new WP_Query( $args );
        if ( $wpas_tickets->have_posts() ) {
            while( $wpas_tickets->have_posts() ) {
                $wpas_tickets->the_post();
                $url = site_url() . "/ticket/" . get_the_title();
                $return_value = get_the_title();

                $results[] = array(
                    'value' => $return_value,
                    'slug' => get_the_permalink(),
                    'url' => $url,
                    'tokens' => explode( ' ', get_the_title() ),
                    'css_class' => 'no_class'
                );
            }
        } else {
            $results['value'] = 'No results found.';
        }
        wp_reset_postdata();
        echo json_encode( $results );
        exit();
    }
    global $wpas_tickets;
    $wpas_tickets = new WP_Query( $args );
}

add_filter( 'posts_where', 'title_like_posts_where', 10, 2 );
function title_like_posts_where( $where, &$wp_query ) {
    global $wpdb;
    if ( $post_title_like = $wp_query->get( 'post_title_like' ) ) {
        $searchString = esc_sql( $wpdb->esc_like( $post_title_like ) );
        if( is_numeric($searchString)) {
            $where .= ' AND ' . $wpdb->posts . '.ID LIKE "' . intval($searchString) . ';%"" ';
        } else {
            $where .= ' AND (' . $wpdb->posts . '.post_title LIKE \'' . $searchString . '%\'';
            $where .= ' OR ' . $wpdb->posts . '.post_content LIKE \'' . $searchString . '%\')';
        }
        $where .= ' AND ' . $wpdb->posts . '.post_type = "ticket"';
        $where .= ' AND ' . $wpdb->posts . '.post_type = "ticket"';
    }
    return $where;
}
// view all tickets
add_filter('wpas_can_view_ticket', '__return_true');

// Filtering by assignee
add_filter( 'parse_query', 'wpdt_filter_by_replies', 10, 1 );
function wpdt_filter_by_replies( $query ) {

    global $pagenow;

    /* Check if we are in the correct post type */
    if ( is_admin()
        && 'edit.php' == $pagenow
        && isset( $_GET['post_type'] )
        && 'ticket' == $_GET['post_type']
        && isset( $_GET['assignee'] )
        && !empty( $_GET['assignee'] )
        && $query->is_main_query() ) {

        //print_r( $query );
        $query->query_vars['meta_key']     = '_wpas_assignee';
        $query->query_vars['meta_value']   = sanitize_text_field( $_GET['assignee'] );
        $query->query_vars['meta_compare'] = '=';
    }

}
add_action( 'restrict_manage_posts', 'wpdt_assinge_filter',9, 0 );
function wpdt_assinge_filter() {

    global $typenow;

    if ( 'ticket' != $typenow ) {
        return;
    }
    $staff_atts = array(
        'cap'      => 'edit_ticket',
        'name'     => 'assignee',
        'id'       => 'wpas-assignee',
        'disabled' => ! current_user_can( 'assign_ticket' ) ? true : false,
        'select2'  => true
    );

    if ( isset( $post ) ) {
        $staff_atts['selected'] = get_post_meta( $post->ID, '_wpas_assignee', true );
    }
    if(isset( $_GET['assignee'] ) && !empty( $_GET['assignee'] ) ) {
        $staff_atts['selected'] = $_GET['assignee'];
    }

    echo wpas_users_dropdown( $staff_atts );

}
add_action('admin_head', 'my_custom_fonts');

function my_custom_fonts() {
    echo '<style>
    #posts-filter #s2id_autogen1 {
          width: 150px;
    }
  </style>';
}

// Front end filtering
add_action('wpas_before_tickets_list', 'wpdt_tickets_list_filter');



add_action( 'wp_ajax_wpdt_wpas_before_tickets_list', 'wpdt_wpas_before_tickets_list' );
function wpdt_tickets_list_filter() {
    $img_loading_gif = plugins_url( '/', __FILE__ ) . '/img/loading.gif';
    $url = site_url() . '/merchant/vouchers/?redirect_to=' . "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    ?>
    <div class="quick_reedem_dasboard">
        <div class="quick_redee_inner">
            <i class="fa fa-ticket"></i>
            <span class="quick_redde_title"><?php _e('Search for ticket'); ?></span>
            <div class="qick_redee_input">
                <label><?php _e('Ticket Name:'); ?>
                    <input type="text" name="search_voucher" id="quick_search_ticket_input"
                           placeholder="Start by typing the title of the ticket">
                    <input type="hidden" id="qr_voucher_url" value="<?php echo $url ?>" >
                </label>

            </div>
            <div class="qick_redee_table">
            </div>
        </div>
    </div>
    <?php
}