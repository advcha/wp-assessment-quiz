<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Assessment_Result_Tiers_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'result_tier',
            'plural'   => 'result_tiers',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" />',
            'tier_name'       => 'Tier Name',
            'tier_label'      => 'Tier Label',
            'threshold_type'  => 'Threshold Type',
            'threshold_value' => 'Threshold Value',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'tier_name'       => [ 'tier_name', false ],
            'threshold_value' => [ 'threshold_value', true ],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'trash' => 'Delete'
        ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'tier_label':
            case 'threshold_type':
            case 'threshold_value':
                return esc_html( $item[ $column_name ] );
            default:
                return print_r( $item, true );
        }
    }

    public function column_tier_name( $item ) {
        $edit_url = add_query_arg( [
            'page'           => 'assessment-quiz-settings',
            'tab'            => 'result_tiers',
            'action'         => 'edit',
            'result_tier_id' => $item['id']
        ], admin_url( 'admin.php' ) );

        $delete_nonce = wp_create_nonce( 'delete_result_tier_' . $item['id'] );
        $delete_url = add_query_arg( [
            'action'         => 'delete_result_tier',
            'result_tier_id' => $item['id'],
            '_wpnonce'       => $delete_nonce
        ], admin_url( 'admin.php' ) );

        $actions = [
            'edit'  => sprintf( '<a href="%s">Edit</a>', $edit_url ),
            'trash' => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'Are you sure you want to delete this result tier?\');">Delete</a>', $delete_url )
        ];

        return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', $edit_url, esc_html($item['tier_name']), $this->row_actions( $actions ) );
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="result_tier_ids[]" value="%s" />', $item['id']
        );
    }

    public function process_bulk_action() {
        if ( 'trash' === $this->current_action() ) {
            if ( ! empty( $_POST['result_tier_ids'] ) ) {
                check_admin_referer( 'bulk-' . $this->_args['plural'] );
                $tier_ids = array_map( 'intval', $_POST['result_tier_ids'] );
                self::delete_result_tiers( $tier_ids );

                $redirect_url = add_query_arg( [
                    'page'   => 'assessment-quiz-settings',
                    'tab'    => 'result_tiers',
                    'status' => 'deleted'
                ], admin_url( 'admin.php' ) );
                
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }

    public static function delete_result_tiers( $ids ) {
        global $wpdb;
        $ids = (array) $ids;
        $ids = array_map( 'absint', $ids );

        if ( empty( $ids ) ) {
            return 0;
        }

        $table_name = $wpdb->prefix . 'assessment_result_tiers';
        $id_list = implode( ',', $ids );
        return $wpdb->query( "DELETE FROM {$table_name} WHERE id IN ($id_list)" );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_result_tiers';

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page( 'result_tiers_per_page', 20 );
        $current_page = $this->get_pagenum();
        
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
        $where_clause = '';
        if ( ! empty( $search ) ) {
            $where_clause = $wpdb->prepare( " WHERE tier_name LIKE %s OR tier_label LIKE %s", '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}{$where_clause}" );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );

        $orderby = ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'threshold_value';
        $order = ( ! empty( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), ['ASC', 'DESC'] ) ) ? esc_sql( $_REQUEST['order'] ) : 'ASC';

        $offset = ( $current_page - 1 ) * $per_page;
        
        $query = "SELECT * FROM {$table_name}{$where_clause} ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
        
        $this->items = $wpdb->get_results( $query, ARRAY_A );
    }
}