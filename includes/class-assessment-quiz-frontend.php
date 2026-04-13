<?php
/**
 * The frontend-facing functionality of the plugin.
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

class Assessment_Quiz_Frontend {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets and scripts for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles_and_scripts() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../frontend/css/frontend-styles.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . '../frontend/js/frontend-scripts.js',
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
                'nonce'    => wp_create_nonce( 'assessment_quiz_nonce' )
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
        include_once plugin_dir_path( dirname( __FILE__ ) ) . 'frontend/templates/quiz-display.php';
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

        // 1. Get Quiz
        $quiz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $quizzes_table WHERE id = %d", $quiz_id ) );
        if ( ! $quiz ) {
            return null;
        }

        $quiz_data = (array) $quiz;
        $quiz_data['sections'] = array();

        // 2. Get Sections for the Quiz
        $sections = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $sections_table WHERE quiz_id = %d ORDER BY section_order ASC", $quiz_id ) );

        foreach ( $sections as $section ) {
            $section_data = (array) $section;
            $section_data['questions'] = array();

            // 3. Get Questions for each Section
            $questions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $questions_table WHERE section_id = %d ORDER BY question_order ASC", $section->id ) );

            foreach ( $questions as $question ) {
                $question_data = (array) $question;
                $question_data['answers'] = array();

                // 4. Get Answers for each Question
                $answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $answers_table WHERE question_id = %d ORDER BY answer_order ASC", $question->id ) );
                $question_data['answers'] = $answers;

                $section_data['questions'][] = $question_data;
            }
            $quiz_data['sections'][] = $section_data;
        }

        return $quiz_data;
    }
}