<?php
/**
 * Handles AJAX requests for the Assessment Quiz plugin.
 *
 * @package    Assessment_Quiz
 * @subpackage Assessment_Quiz/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Assessment_Quiz_Ajax {

    /**
     * The ID of this plugin.
     * @var      string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     * @var      string
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'wp_ajax_submit_assessment_quiz', array( $this, 'submit_quiz' ) );
        add_action( 'wp_ajax_nopriv_submit_assessment_quiz', array( $this, 'submit_quiz' ) );
    }

    /**
     * Handles the quiz submission.
     */
    public function submit_quiz() {
        // 1. Security Check
        check_ajax_referer( 'assessment_quiz_nonce', 'nonce' );

        // 2. Sanitize and retrieve POST data
        $quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
        $answers = isset( $_POST['answers'] ) ? (array) $_POST['answers'] : array();
        // A simple way to sanitize the array of answers.
        $answers = array_map( 'intval', $answers );

        if ( ! $quiz_id || empty( $answers ) ) {
            wp_send_json_error( array( 'message' => 'Invalid data provided.' ) );
        }

        // 3. Calculate Score
        $score = $this->calculate_score( $answers );

        // 4. Save Submission
        $submission_id = $this->save_submission( $quiz_id, $score, $answers );

        if ( ! $submission_id ) {
            wp_send_json_error( array( 'message' => 'Could not save submission.' ) );
        }

        // 5. Get Result
        $result = $this->get_result_by_score( $quiz_id, $score );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Could not find a result for your score.' ) );
        }

        // 6. Send Response
        wp_send_json_success( array(
            'title' => $result->title,
            'report' => $result->report_text,
            'score' => $score
        ) );
    }

    /**
     * Calculates the total score from the user's answers.
     *
     * @param array $answer_ids An array of selected answer IDs.
     * @return int The total score.
     */
    private function calculate_score( $answer_ids ) {
        global $wpdb;
        $answers_table = $wpdb->prefix . 'assessment_answers';
        
        $total_score = 0;
        if ( empty( $answer_ids ) ) {
            return $total_score;
        }

        // Create a string of placeholders for the IN clause
        $placeholders = implode( ', ', array_fill( 0, count( $answer_ids ), '%d' ) );
        
        // Prepare the query
        $query = $wpdb->prepare( "SELECT SUM(points) FROM $answers_table WHERE id IN ( $placeholders )", $answer_ids );
        
        $total_score = (int) $wpdb->get_var( $query );

        return $total_score;
    }

    /**
     * Saves the quiz submission to the database.
     *
     * @param int $quiz_id The ID of the quiz.
     * @param int $score The final score.
     * @param array $answers The user's answers (question_id => answer_id).
     * @return int|false The new submission ID or false on failure.
     */
    private function save_submission( $quiz_id, $score, $answers ) {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'assessment_submissions';
        $user_answers_table = $wpdb->prefix . 'assessment_user_answers';

        // Save to submissions table
        $wpdb->insert(
            $submissions_table,
            array(
                'quiz_id'     => $quiz_id,
                'user_id'     => get_current_user_id(), // 0 if not logged in
                'final_score' => $score,
            ),
            array( '%d', '%d', '%d' )
        );

        $submission_id = $wpdb->insert_id;

        if ( ! $submission_id ) {
            return false;
        }

        // Save each answer to user_answers table
        foreach ( $answers as $question_id => $answer_id ) {
            $wpdb->insert(
                $user_answers_table,
                array(
                    'submission_id' => $submission_id,
                    'question_id'   => $question_id,
                    'answer_id'     => $answer_id,
                ),
                array( '%d', '%d', '%d' )
            );
        }

        return $submission_id;
    }

    /**
     * Retrieves the result/report based on the score.
     *
     * @param int $quiz_id The ID of the quiz.
     * @param int $score The user's score.
     * @return object|null The result object or null if not found.
     */
    private function get_result_by_score( $quiz_id, $score ) {
        global $wpdb;
        $results_table = $wpdb->prefix . 'assessment_results';

        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT title, report_text FROM $results_table WHERE quiz_id = %d AND %d BETWEEN min_score AND max_score",
            $quiz_id,
            $score
        ) );

        return $result;
    }
}