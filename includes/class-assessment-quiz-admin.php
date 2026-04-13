<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Assessment_Quiz
 * @subpackage Assessment_Quiz/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Assessment_Quiz_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
        add_action( 'admin_post_save_quiz_action', array( $this, 'save_quiz_data' ) );
        add_action( 'admin_action_delete_quiz', array( $this, 'delete_quiz_action' ) );
    }

    public function enqueue_styles_and_scripts( $hook ) {
        // Only load on our plugin's pages
        if ( strpos( $hook, 'assessment-quiz' ) === false && strpos( $hook, 'add-new-quiz' ) === false ) {
            return;
        }

        // Enqueue the rich text editor scripts
        wp_enqueue_editor();

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            plugin_dir_url( __FILE__ ) . '../admin/css/admin-styles.css',
            array(),
            $this->version,
            'all'
        );

        // This is required for the media uploader
        wp_enqueue_media();

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            plugin_dir_url( __FILE__ ) . '../admin/js/admin-scripts.js',
            array( 'jquery' ),
            $this->version,
            true
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Quiz Assessments',
            'Quiz Assessments',
            'manage_options',
            'assessment-quiz',
            array( $this, 'display_quizzes_page' ),
            'dashicons-forms',
            25
        );

        add_submenu_page(
            'assessment-quiz',
            'All Quizzes',
            'All Quizzes',
            'manage_options',
            'assessment-quiz',
            array( $this, 'display_quizzes_page' )
        );

        add_submenu_page(
            'assessment-quiz',
            'Add New Quiz',
            'Add New Quiz',
            'manage_options',
            'add-new-quiz',
            array( $this, 'display_add_new_quiz_page' )
        );

        /*add_submenu_page(
            'assessment-quiz',
            'Submissions',
            'Submissions',
            'manage_options',
            'assessment-quiz-submissions',
            array( $this, 'display_submissions_page' )
        );*/
    }

    public function display_quizzes_page() {
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-quiz-list-table.php';

        $list_table = new Assessment_Quiz_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">All Quizzes</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=add-new-quiz' ) ); ?>" class="page-title-action">Add New</a>

            <?php if ( ! empty( $_GET['status'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <?php if ( $_GET['status'] === 'saved' ) : ?>
                        <p><?php esc_html_e( 'Quiz saved successfully.', 'assessment-quiz' ); ?></p>
                    <?php elseif ( $_GET['status'] === 'deleted' ) : ?>
                        <p><?php esc_html_e( 'Quiz(zes) deleted successfully.', 'assessment-quiz' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="page" value="assessment-quiz">
                <?php
                $list_table->search_box( 'Search Quizzes', 'quiz' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function display_add_new_quiz_page() {
        $quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
        $existing_quiz_data = null;
        if ( $quiz_id ) {
            $existing_quiz_data = $this->get_quiz_data_for_editing( $quiz_id );
        }
        // Correctly include the form template from the 'admin/templates' directory
        require_once plugin_dir_path( __FILE__ ) . '../admin/templates/add-new-quiz-form.php';
    }

    public function display_submissions_page() {
        echo '<h1>All Submissions</h1>';
        echo '<p>A list of all user submissions will appear here.</p>';
    }

    public function save_quiz_data() {
        if ( ! isset( $_POST['save_quiz_nonce'] ) || ! wp_verify_nonce( $_POST['save_quiz_nonce'], 'save_quiz_action' ) ) {
            wp_die( 'Security check failed' );
        }

        global $wpdb;
        $quizzes_table = $wpdb->prefix . 'assessment_quizzes';
        $sections_table = $wpdb->prefix . 'assessment_sections';
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;

        $quiz_title = sanitize_text_field( $_POST['quiz_title'] );
        $quiz_description = wp_kses_post( $_POST['quiz_description'] );

        $quiz_data = [
            'title'       => $quiz_title,
            'description' => $quiz_description,
        ];

        if ( $quiz_id ) {
            $wpdb->update( $quizzes_table, $quiz_data, [ 'id' => $quiz_id ] );
        } else {
            $quiz_data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $quizzes_table, $quiz_data );
            $quiz_id = $wpdb->insert_id;
        }

        $submitted_section_ids = [];
        $submitted_question_ids = [];
        $submitted_answer_ids = [];

        if ( isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ) {
            foreach ( $_POST['sections'] as $section_index => $section_data ) {
                $section_id = isset( $section_data['id'] ) ? intval( $section_data['id'] ) : 0;
                $section_title = sanitize_text_field( $section_data['title'] );
                $section_content_begin = wp_kses_post( $section_data['content_begin'] );
                $section_content_end = wp_kses_post( $section_data['content_end'] );

                $section_db_data = [
                    'quiz_id' => $quiz_id,
                    'title'   => $section_title,
                    'section_content_begin' => $section_content_begin,
                    'section_content_end'   => $section_content_end,
                    'section_order' => $section_index + 1,
                ];

                if ( $section_id ) {
                    $wpdb->update( $sections_table, $section_db_data, [ 'id' => $section_id ] );
                } else {
                    $wpdb->insert( $sections_table, $section_db_data );
                    $section_id = $wpdb->insert_id;
                }
                $submitted_section_ids[] = $section_id;

                if ( isset( $section_data['questions'] ) && is_array( $section_data['questions'] ) ) {
                    foreach ( $section_data['questions'] as $question_index => $question_data ) {
                        $question_id = isset( $question_data['id'] ) ? intval( $question_data['id'] ) : 0;
                        $question_text = wp_kses_post( $question_data['text'] );
                        $question_type = sanitize_text_field( $question_data['type'] );

                        $question_db_data = [
                            'section_id'        => $section_id,
                            'question_text'     => $question_text,
                            'question_type'     => $question_type,
                            'question_order'    => $question_index + 1,
                        ];

                        if ( $question_id ) {
                            $wpdb->update( $questions_table, $question_db_data, [ 'id' => $question_id ] );
                        } else {
                            $wpdb->insert( $questions_table, $question_db_data );
                            $question_id = $wpdb->insert_id;
                        }
                        $submitted_question_ids[] = $question_id;

                        if ( isset( $question_data['answers'] ) && is_array( $question_data['answers'] ) ) {
                            foreach ( $question_data['answers'] as $answer_index => $answer_data ) {
                                $answer_id = isset( $answer_data['id'] ) ? intval( $answer_data['id'] ) : 0;
                                $answer_text = wp_kses_post( $answer_data['text'] );
                                $answer_points = intval( $answer_data['points'] );

                                $answer_db_data = [
                                    'question_id'   => $question_id,
                                    'answer_text'   => $answer_text,
                                    'points'        => $answer_points,
                                    'answer_order'  => $answer_index + 1,
                                ];

                                if ( $answer_id ) {
                                    $wpdb->update( $answers_table, $answer_db_data, [ 'id' => $answer_id ] );
                                } else {
                                    $wpdb->insert( $answers_table, $answer_db_data );
                                    $answer_id = $wpdb->insert_id;
                                }
                                $submitted_answer_ids[] = $answer_id;
                            }
                        }
                    }
                }
            }
        }

        if ( $quiz_id ) {
            $this->delete_removed_items( $quiz_id, $submitted_section_ids, $submitted_question_ids, $submitted_answer_ids );
        }

        wp_redirect( admin_url( 'admin.php?page=assessment-quiz&status=saved' ) );
        exit;
    }

    private function delete_removed_items( $quiz_id, $submitted_section_ids, $submitted_question_ids, $submitted_answer_ids ) {
        global $wpdb;
        $sections_table = $wpdb->prefix . 'assessment_sections';
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';

        $existing_answer_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT a.id FROM $answers_table a
             JOIN $questions_table q ON a.question_id = q.id
             JOIN $sections_table s ON q.section_id = s.id
             WHERE s.quiz_id = %d",
            $quiz_id
        ) );
        $answers_to_delete = array_diff( $existing_answer_ids, $submitted_answer_ids );
        if ( ! empty( $answers_to_delete ) ) {
            $wpdb->query( "DELETE FROM $answers_table WHERE id IN (" . implode( ',', array_map( 'intval', $answers_to_delete ) ) . ")" );
        }

        $existing_question_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT q.id FROM $questions_table q
             JOIN $sections_table s ON q.section_id = s.id
             WHERE s.quiz_id = %d",
            $quiz_id
        ) );
        $questions_to_delete = array_diff( $existing_question_ids, $submitted_question_ids );
        if ( ! empty( $questions_to_delete ) ) {
            $wpdb->query( "DELETE FROM $questions_table WHERE id IN (" . implode( ',', array_map( 'intval', $questions_to_delete ) ) . ")" );
        }

        $existing_section_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $sections_table WHERE quiz_id = %d", $quiz_id ) );
        $sections_to_delete = array_diff( $existing_section_ids, $submitted_section_ids );
        if ( ! empty( $sections_to_delete ) ) {
            $wpdb->query( "DELETE FROM $sections_table WHERE id IN (" . implode( ',', array_map( 'intval', $sections_to_delete ) ) . ")" );
        }
    }

    private function get_quiz_data_for_editing( $quiz_id ) {
        global $wpdb;
        $quiz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}assessment_quizzes WHERE id = %d", $quiz_id ), ARRAY_A );
        if ( ! $quiz ) {
            return null;
        }

        $sections = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}assessment_sections WHERE quiz_id = %d ORDER BY `section_order` ASC", $quiz_id ), ARRAY_A );
        foreach ( $sections as $s_key => &$section ) {
            $questions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}assessment_questions WHERE section_id = %d ORDER BY `question_order` ASC", $section['id'] ), ARRAY_A );
            foreach ( $questions as $q_key => &$question ) {
                $question['answers'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}assessment_answers WHERE question_id = %d ORDER BY `answer_order` ASC", $question['id'] ), ARRAY_A );
            }
            $section['questions'] = $questions;
        }
        $quiz['sections'] = $sections;

        return $quiz;
    }

    public function delete_quiz_action() {
        if ( empty( $_GET['quiz_id'] ) || empty( $_GET['_wpnonce'] ) ) {
            wp_die( 'Invalid request.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to delete this item.' );
        }

        $quiz_id = absint( $_GET['quiz_id'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] );

        if ( ! wp_verify_nonce( $nonce, 'delete_quiz_' . $quiz_id ) ) {
            wp_die( 'Security check failed.' );
        }

        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-quiz-list-table.php';
        Assessment_Quiz_List_Table::delete_quizzes( $quiz_id );

        wp_redirect( admin_url( 'admin.php?page=assessment-quiz&status=deleted' ) );
        exit;
    }
}