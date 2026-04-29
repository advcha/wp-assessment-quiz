<?php
/**
 * The frontend-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.4.0
 *
 * @package    Assessment_Quiz
 * @subpackage Assessment_Quiz/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Assessment_Quiz_Frontend {

    /**
     * The ID of this plugin.
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.4.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
        $this->register_shortcode();

        // Register the new, dedicated AJAX action for saving submissions
        add_action( 'wp_ajax_save_quiz_submission', array( $this, 'save_quiz_submission_callback' ) );
        add_action( 'wp_ajax_nopriv_save_quiz_submission', array( $this, 'save_quiz_submission_callback' ) );
    }

    /**
     * Register the stylesheets and scripts for the public-facing side of the site.
     *
     * @since    1.4.0
     */
    public function enqueue_styles_and_scripts() {
        // Only enqueue scripts and styles on pages using the quiz template or containing the shortcode.
        if ( ! is_page_template('public/templates/template-quiz.php') && ! has_shortcode( get_the_content(), 'assessment_quiz' ) ) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../public/css/quiz-styles.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../public/js/quiz-logic.js',
            array( 'jquery' ),
            $this->version,
            true // Load in footer
        );

        // Pass data to the script
        wp_localize_script(
            $this->plugin_name,
            'assessmentQuizAjax', // Object name in JavaScript
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'assessment_quiz_nonce' ),
                'save_action' => 'save_quiz_submission',
            )
        );
    }

    /**
     * Register the [assessment_quiz] shortcode.
     */
    public function register_shortcode() {
        add_shortcode( 'assessment_quiz', array( $this, 'display_quiz_shortcode' ) );
    }

    /**
     * The callback function for the [assessment_quiz] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string The quiz HTML.
     */
    public function display_quiz_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'assessment_quiz' );

        $quiz_id = intval( $atts['id'] );

        if ( ! $quiz_id ) {
            return '<p>Error: Quiz ID is missing or invalid.</p>';
        }

        $quiz_data = $this->get_quiz_data( $quiz_id );

        if ( ! $quiz_data ) {
            return '<p>Error: Quiz not found.</p>';
        }

        // Pass the quiz data to the frontend script
        wp_add_inline_script( $this->plugin_name, 'const assessmentQuizData = ' . json_encode( $quiz_data ) . ';', 'before' );

        ob_start();
        $template_path = plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/quiz-display.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<p>Error: Quiz display template not found.</p>';
        }
        return ob_get_clean();
    }

    /**
     * Fetches all data for a specific quiz from the database.
     *
     * @param int $quiz_id The ID of the quiz to fetch.
     * @return array|null The quiz data or null if not found.
     */
    private function get_quiz_data( $quiz_id ) {
        global $wpdb;

        // Table names
        $quizzes_table = $wpdb->prefix . 'assessment_quizzes';
        $sections_table = $wpdb->prefix . 'assessment_sections';
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';
        $categories_table = $wpdb->prefix . 'assessment_categories';

        // 1. Get Quiz
        $quiz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $quizzes_table WHERE id = %d", $quiz_id ) );
        if ( ! $quiz ) {
            return null;
        }

        $quiz_data = (array) $quiz;
        $quiz_data['title'] = stripslashes($quiz_data['title']);
        $quiz_data['description'] = wp_specialchars_decode(stripslashes($quiz_data['description']), ENT_QUOTES);
        $quiz_data['sections'] = array();
        $quiz_data['categories'] = array();

        $category_ids = array();

        // 2. Get Sections for the Quiz
        $sections = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $sections_table WHERE quiz_id = %d ORDER BY section_order ASC", $quiz_id ) );

        foreach ( $sections as $section ) {
            $section_data = (array) $section;
            $section_data['section_title'] = stripslashes($section_data['section_title']);
            $section_data['section_content_begin'] = wp_specialchars_decode(stripslashes($section_data['section_content_begin']), ENT_QUOTES);
            $section_data['section_content_end'] = wp_specialchars_decode(stripslashes($section_data['section_content_end']), ENT_QUOTES);
            $section_data['questions'] = array();

            // 3. Get Questions for each Section
            $questions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $questions_table WHERE section_id = %d ORDER BY question_order ASC", $section->id ) );

            foreach ( $questions as $question ) {
                $question_data = (array) $question;
                $question_data['question_text'] = wp_specialchars_decode(stripslashes($question_data['question_text']), ENT_QUOTES);
                $question_data['question_type'] = $question->question_type;

                if ( $question->category_id && ! in_array( $question->category_id, $category_ids ) ) {
                    $category_ids[] = $question->category_id;
                }
                
                // 4. Get Answers for each Question
                $answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $answers_table WHERE question_id = %d ORDER BY answer_order ASC", $question->id ) );
                $decoded_answers = [];
                foreach ($answers as $answer) {
                    $answer_data = (array) $answer;
                    $answer_data['answer_text'] = wp_specialchars_decode(stripslashes($answer_data['answer_text']), ENT_QUOTES);
                    $decoded_answers[] = $answer_data;
                }
                $question_data['answers'] = $decoded_answers;

                $section_data['questions'][] = $question_data;
            }
            $quiz_data['sections'][] = $section_data;
        }

        // 5. Get Categories for the Quiz
        if ( ! empty( $category_ids ) ) {
            $category_ids_placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
            $query = $wpdb->prepare( "SELECT * FROM $categories_table WHERE id IN ($category_ids_placeholders)", $category_ids );
            $categories = $wpdb->get_results( $query, OBJECT_K ); // Use OBJECT_K to key the array by category ID
            
            foreach ( $categories as $id => $category ) {
                $categories[$id]->name = stripslashes($category->name);
                $categories[$id]->description = wp_specialchars_decode(stripslashes($category->description), ENT_QUOTES);
                $categories[$id]->focus_area_title = stripslashes($category->focus_area_title);
                $categories[$id]->focus_area_description = wp_specialchars_decode(stripslashes($category->focus_area_description), ENT_QUOTES);
                $categories[$id]->healing_plan_details = wp_specialchars_decode(stripslashes($category->healing_plan_details), ENT_QUOTES);
            }
            $quiz_data['categories'] = $categories;
        }

        return $quiz_data;
    }

    /**
     * AJAX handler for saving a quiz submission.
     */
    public function save_quiz_submission_callback() {
        //check_ajax_referer( 'assessment_quiz_nonce', 'nonce' );
        // Nonce check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'assessment_quiz_nonce')) {
            wp_send_json_error(array('message' => 'Nonce verification failed.'), 403);
            return;
        }

        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        $answers = isset( $_POST['answers'] ) ? $_POST['answers'] : array();

        if ( ! $quiz_id || empty( $answers ) ) {
            wp_send_json_error( array( 'message' => 'Missing required data.' ) );
            return;
        }

        $submission_id = $this->save_submission_data( $quiz_id, $answers );

        if ( is_wp_error( $submission_id ) ) {
            wp_send_json_error( array( 'message' => $submission_id->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Submission saved successfully.', 'submission_id' => $submission_id ) );
    }

    /**
     * Saves the submission data to the database.
     *
     * @param int   $quiz_id The ID of the quiz.
     * @param array $answers The user's answers.
     * @return int|WP_Error The new submission ID or a WP_Error on failure.
     */
    private function save_submission_data( $quiz_id, $answers ) {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'assessment_submissions';

        $result = $wpdb->insert(
            $submissions_table,
            array(
                'quiz_id'         => $quiz_id,
                'user_id'         => get_current_user_id(),
                'submitted_at'   => current_time( 'mysql' ),
                'answers'         => wp_json_encode( $answers ),
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
            )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_insert_error', 'Could not save submission to the database.' );
        }

        return $wpdb->insert_id;
    }
}