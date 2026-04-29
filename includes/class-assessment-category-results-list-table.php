<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Assessment_Category_Results_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'Category Result',
            'plural'   => 'Category Results',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'               => '<input type="checkbox" />',
            'category_name'    => 'Category',
            'tier_name'        => 'Result Tier',
            'focus_area_title' => 'Focus Area Title'
        ];
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] );
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="category_result_id[]" value="%s" />', $item['id'] );
    }

    public function column_focus_area_title( $item ) {
        $page = $_REQUEST['page'];
        $nonce = wp_create_nonce( 'delete_category_result_' . $item['id'] );
        $actions = [
            'edit'   => sprintf( '<a href="?page=%s&tab=category_results&action=edit&category_result_id=%s">Edit</a>', $page, $item['id'] ),
            'delete' => sprintf( '<a href="?page=%s&tab=category_results&action=delete_category_result&category_result_id=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure you want to delete this item?\');">Delete</a>', $page, $item['id'], $nonce ),
        ];
        return sprintf( '%1$s %2$s', esc_html($item['focus_area_title']), $this->row_actions( $actions ) );
    }

    public function get_bulk_actions() {
        return [
            'trash' => 'Delete'
        ];
    }

    public function process_bulk_action() {
        if ( 'trash' === $this->current_action() ) {
            if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
                wp_die( 'Security check failed' );
            }

            $ids = isset( $_REQUEST['category_result_id'] ) ? array_map( 'intval', $_REQUEST['category_result_id'] ) : [];

            if ( ! empty( $ids ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'assessment_category_results';
                $ids_format = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($ids_format)", $ids ) );
            }
            wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=category_results&status=deleted' ) );
            exit;
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_category_results';
        $categories_table = $wpdb->prefix . 'assessment_categories';
        $tiers_table = $wpdb->prefix . 'assessment_result_tiers';

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'category_name';
        $order = ( ! empty( $_GET['order'] ) && in_array( strtolower( $_GET['order'] ), [ 'asc', 'desc' ] ) ) ? $_GET['order'] : 'asc';

        $search_term = ( ! empty( $_GET['s'] ) ) ? '%' . $wpdb->esc_like( $_GET['s'] ) . '%' : '';

        $query = "SELECT cr.id, c.name as category_name, rt.tier_name, cr.focus_area_title
                  FROM {$table_name} cr
                  LEFT JOIN {$categories_table} c ON cr.category_id = c.id
                  LEFT JOIN {$tiers_table} rt ON cr.result_tier_id = rt.id";

        if ( $search_term ) {
            $query .= $wpdb->prepare( " WHERE c.name LIKE %s OR rt.tier_name LIKE %s OR cr.focus_area_title LIKE %s", $search_term, $search_term, $search_term );
        }

        $count_query = "SELECT COUNT(*) FROM ({$query}) as count_table";
        $total_items = $wpdb->get_var($count_query);

        $query .= " ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";

        $this->items = $wpdb->get_results( $query, ARRAY_A );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ] );
    }

    protected function get_sortable_columns() {
        return [
            'category_name'    => [ 'category_name', false ],
            'tier_name'        => [ 'tier_name', false ],
            'focus_area_title' => [ 'focus_area_title', false ],
        ];
    }
}