<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Assessment_Quiz_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'quiz',
            'plural'   => 'quizzes',
            'ajax'     => false
        ] );
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'title'     => 'Title',
            'shortcode' => 'Shortcode',
            'date'      => 'Date'
        ];
    }

    protected function get_sortable_columns() {
        return [
            'title' => [ 'title', true ],
            'date'  => [ 'created_at', false ]
        ];
    }

    protected function get_bulk_actions() {
        return [
            'trash' => 'Delete'
        ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'date':
                return mysql2date( 'Y/m/d g:i a', $item['created_at'] );
            default:
                return print_r( $item, true );
        }
    }

    public function column_title( $item ) {
        $edit_url = add_query_arg( [
            'page'    => 'add-new-quiz',
            'quiz_id' => $item['id']
        ], admin_url( 'admin.php' ) );

        $delete_nonce = wp_create_nonce( 'delete_quiz_' . $item['id'] );
        $delete_url = add_query_arg( [
            'action'   => 'delete_quiz',
            'quiz_id'  => $item['id'],
            '_wpnonce' => $delete_nonce
        ], admin_url( 'admin.php' ) );

        $actions = [
            'edit'  => sprintf( '<a href="%s">Edit</a>', $edit_url ),
            'trash' => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'Are you sure you want to delete this quiz? This will also delete all of its sections, questions, and answers.\');">Delete</a>', $delete_url )
        ];

        return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', $edit_url, $item['title'], $this->row_actions( $actions ) );
    }

    public function column_shortcode( $item ) {
        return '<code>[assessment_quiz id="' . $item['id'] . '"]</code>';
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="quiz_ids[]" value="%s" />', $item['id']
        );
    }

    public function process_bulk_action() {
        if ( 'trash' === $this->current_action() ) {
            if ( ! empty( $_POST['quiz_ids'] ) ) {
                check_admin_referer( 'bulk-' . $this->_args['plural'] );
                $quiz_ids = array_map( 'intval', $_POST['quiz_ids'] );
                self::delete_quizzes( $quiz_ids );

                // Redirect to avoid re-processing on refresh
                wp_redirect( admin_url( 'admin.php?page=assessment-quiz&status=deleted' ) );
                exit;
            }
        }
    }

    public static function delete_quizzes( $ids ) {
        global $wpdb;
        $ids = (array) $ids;
        $id_list = implode( ',', array_map( 'absint', $ids ) );

        if ( empty( $id_list ) ) {
            return;
        }

        $sections_table = $wpdb->prefix . 'assessment_sections';
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';
        $quizzes_table = $wpdb->prefix . 'assessment_quizzes';

        $question_ids = $wpdb->get_col( "SELECT q.id FROM {$questions_table} q JOIN {$sections_table} s ON q.section_id = s.id WHERE s.quiz_id IN ($id_list)" );
        if ( ! empty( $question_ids ) ) {
            $question_id_list = implode( ',', $question_ids );
            $wpdb->query( "DELETE FROM {$answers_table} WHERE question_id IN ($question_id_list)" );
        }

        $section_ids = $wpdb->get_col( "SELECT id FROM {$sections_table} WHERE quiz_id IN ($id_list)" );
        if ( ! empty( $section_ids ) ) {
            $section_id_list = implode( ',', $section_ids );
            $wpdb->query( "DELETE FROM {$questions_table} WHERE section_id IN ($section_id_list)" );
        }
        
        $wpdb->query( "DELETE FROM {$sections_table} WHERE quiz_id IN ($id_list)" );
        $wpdb->query( "DELETE FROM {$quizzes_table} WHERE id IN ($id_list)" );
    }

    public function prepare_items() {
        global $wpdb;

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page( 'quizzes_per_page', 20 );
        $current_page = $this->get_pagenum();
        
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
        $where_clause = '';
        if ( ! empty( $search ) ) {
            $where_clause = $wpdb->prepare( " WHERE title LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}assessment_quizzes{$where_clause}" );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );

        $orderby = ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'created_at';
        $order = ( ! empty( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), ['ASC', 'DESC'] ) ) ? esc_sql( $_REQUEST['order'] ) : 'DESC';

        $offset = ( $current_page - 1 ) * $per_page;
        
        $query = "SELECT id, title, created_at FROM {$wpdb->prefix}assessment_quizzes{$where_clause} ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
        
        $this->items = $wpdb->get_results( $query, ARRAY_A );
    }
}