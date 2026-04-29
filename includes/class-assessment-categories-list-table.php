<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Assessment_Categories_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'category',
            'plural'   => 'categories',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'name'        => 'Name',
            'description' => 'Description',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'name' => [ 'name', true ],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'trash' => 'Delete'
        ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'description':
                return esc_html( $item['description'] );
            default:
                return print_r( $item, true );
        }
    }

    public function column_name( $item ) {
        $edit_url = add_query_arg( [
            'page'        => 'assessment-quiz-settings',
            'tab'         => 'categories',
            'action'      => 'edit',
            'category_id' => $item['id']
        ], admin_url( 'admin.php' ) );

        $delete_nonce = wp_create_nonce( 'delete_category_' . $item['id'] );
        $delete_url = add_query_arg( [
            'action'      => 'delete_category',
            'category_id' => $item['id'],
            '_wpnonce'    => $delete_nonce
        ], admin_url( 'admin.php' ) );

        $actions = [
            'edit'  => sprintf( '<a href="%s">Edit</a>', $edit_url ),
            'trash' => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'Are you sure you want to delete this category?\');">Delete</a>', $delete_url )
        ];

        return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', $edit_url, esc_html($item['name']), $this->row_actions( $actions ) );
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="category_ids[]" value="%s" />', $item['id']
        );
    }

    public function process_bulk_action() {
        if ( 'trash' === $this->current_action() ) {
            if ( ! empty( $_POST['category_ids'] ) ) {
                check_admin_referer( 'bulk-' . $this->_args['plural'] );
                $category_ids = array_map( 'intval', $_POST['category_ids'] );
                $result = self::delete_categories( $category_ids );

                $redirect_url = 'admin.php?page=assessment-quiz-settings&tab=categories';
                if ( $result === -1 ) {
                    $redirect_url = add_query_arg( ['status' => 'error', 'msg' => 'in_use'], $redirect_url );
                } else {
                    $redirect_url = add_query_arg( 'status', 'deleted', $redirect_url );
                }
                wp_redirect( admin_url( $redirect_url ) );
                exit;
            }
        }
    }

    public static function delete_categories( $ids ) {
        global $wpdb;
        $ids = (array) $ids;
        $ids = array_map( 'absint', $ids );

        if ( empty( $ids ) ) {
            return 0;
        }

        $categories_table = $wpdb->prefix . 'assessment_categories';
        $questions_table = $wpdb->prefix . 'assessment_questions';

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        $category_names_query = $wpdb->prepare( "SELECT name FROM {$categories_table} WHERE id IN ($placeholders)", $ids );
        $category_names = $wpdb->get_col( $category_names_query );

        if ( empty( $category_names ) ) {
            return 0;
        }

        $name_placeholders = implode( ', ', array_fill( 0, count( $category_names ), '%s' ) );
        $usage_count_query = $wpdb->prepare( "SELECT COUNT(DISTINCT category) FROM {$questions_table} WHERE category IN ($name_placeholders)", $category_names );
        $usage_count = $wpdb->get_var( $usage_count_query );

        if ( $usage_count > 0 ) {
            return -1; // Indicate error: one or more categories are in use
        }

        $id_list = implode( ',', $ids );
        return $wpdb->query( "DELETE FROM {$categories_table} WHERE id IN ($id_list)" );
    }

    public function prepare_items() {
        global $wpdb;

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page( 'categories_per_page', 20 );
        $current_page = $this->get_pagenum();
        
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
        $where_clause = '';
        if ( ! empty( $search ) ) {
            $where_clause = $wpdb->prepare( " WHERE name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}assessment_categories{$where_clause}" );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );

        $orderby = ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'name';
        $order = ( ! empty( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), ['ASC', 'DESC'] ) ) ? esc_sql( $_REQUEST['order'] ) : 'ASC';

        $offset = ( $current_page - 1 ) * $per_page;
        
        $query = "SELECT id, name, description FROM {$wpdb->prefix}assessment_categories{$where_clause} ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
        
        $this->items = $wpdb->get_results( $query, ARRAY_A );
    }
}