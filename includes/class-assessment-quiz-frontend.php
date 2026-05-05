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

        // Register AJAX action for sending result emails
        add_action( 'wp_ajax_send_quiz_result_email', array( $this, 'send_quiz_result_email_callback' ) );
        add_action( 'wp_ajax_nopriv_send_quiz_result_email', array( $this, 'send_quiz_result_email_callback' ) );
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
                'email_action' => 'send_quiz_result_email',
            )
        );
    }

    /**
     * Register the [assessment_quiz] shortcode.
     */
    public function register_shortcode() {
        add_shortcode( 'assessment_quiz', array( $this, 'display_quiz_shortcode' ) );
        add_shortcode( 'assessment_quiz_result', array( $this, 'display_result_shortcode' ) );
    }

    public function display_result_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'submission_id' => 0,
            'for_email'     => false,
        ), $atts, 'assessment_quiz_result' );

        $submission_id = intval( $atts['submission_id'] );
        $for_email     = filter_var( $atts['for_email'], FILTER_VALIDATE_BOOLEAN );

        if ( ! $submission_id ) {
            return '<p>Error: Submission ID is missing or invalid.</p>';
        }

        $result_data = $this->get_result_data( $submission_id );

        if ( ! $result_data || empty($result_data['categories']) ) {
            return '<p>Error: Could not retrieve result data. Please ensure you have configured categories, result tiers, and category results correctly.</p>';
        }

        ob_start();
        ?>
        <div class="assessment-result">
            <h2>Your Results</h2>

            <!-- Panel 1: Categories Summary -->
            <div class="result-panel" id="category-summary-panel">
                <h3>Categories Summary</h3>
                <ol class="category-summary-list">
                    <?php foreach ( $result_data['categories'] as $category_result ) : ?>
                        <li>
                            <div class="category-summary-item">
                                <h4><?php echo esc_html( $category_result['category_name'] ); ?></h4>
                                <?php if (!empty($category_result['category_description'])): ?>
                                    <p class="category-description"><?php echo wp_kses_post( $category_result['category_description'] ); ?></p>
                                <?php endif; ?>
                                <span class="category-score-tier">
                                    Result: <span class="tier-<?php echo sanitize_html_class( strtolower( $category_result['tier_name'] ) ); ?>"><?php echo esc_html( $category_result['tier_name'] ); ?></span> (Score: <?php echo esc_html( $category_result['score'] ); ?> of <?php echo esc_html( $category_result['total_possible_points'] ); ?>)
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <!-- Panel 2: Focus Areas -->
            <?php
            $focus_areas = array_filter($result_data['categories'], function($cat) {
                return !empty($cat['focus_area_title']);
            });
            if (!empty($focus_areas)):
            ?>
            <div class="result-panel" id="focus-areas-panel">
                <h3>Your Focus Areas</h3>
                <ol class="focus-areas-list">
                    <?php foreach ( $focus_areas as $category_result ) : ?>
                        <li>
                            <div class="focus-area-item">
                                <h4><?php echo esc_html( $category_result['focus_area_title'] ); ?></h4>
                                <?php if (!empty($category_result['focus_area_description'])): ?>
                                    <p><?php echo wp_kses_post( $category_result['focus_area_description'] ); ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <!-- Panel 3: Healing Plans -->
            <?php
            $healing_plans = array_filter($result_data['categories'], function($cat) {
                return !empty($cat['healing_plan_details']);
            });
            if ($for_email && !empty($healing_plans)):
            ?>
            <div class="result-panel" id="healing-plans-panel">
                <h3>Your Healing Plan</h3>
                <div class="healing-plans-container">
                    <?php foreach ( $healing_plans as $category_result ) : ?>
                        <div class="healing-plan-item">
                            <?php echo wp_kses_post( $category_result['healing_plan_details'] ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! $for_email ) : ?>
                <!-- Panel 4: Actions -->
                <div class="result-panel" id="actions-panel" data-submission-id="<?php echo esc_attr( $submission_id ); ?>">
                    <h3>Next Steps</h3>
                    <div class="actions-container">
                        <div class="action-item email-results">
                            <h4>Email Your Results</h4>
                            <p>Enter your email address to receive a copy of your results.</p>
                            <div class="email-form">
                                <input type="email" id="result-email-input" placeholder="your.email@example.com">
                                <button id="send-result-email-btn">Send Email</button>
                                <p class="email-status-message"></p>
                            </div>
                        </div>
                        <div class="action-item webinar-signup">
                            <h4>Join Our Webinar</h4>
                            <p>Learn more about how to apply these insights by joining our free webinar.</p>
                            <a href="https://example.com/webinar-registration" class="webinar-btn" target="_blank">Register Now</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_result_data( $submission_id ) {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'assessment_submissions';
        $submission_scores_table = $wpdb->prefix . 'assessment_submission_scores';
        $categories_table = $wpdb->prefix . 'assessment_categories';
        $result_tiers_table = $wpdb->prefix . 'assessment_result_tiers';
        $category_results_table = $wpdb->prefix . 'assessment_category_results';
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';
        $sections_table = $wpdb->prefix . 'assessment_sections';

        // 1. Get quiz_id from submission
        $quiz_id = $wpdb->get_var($wpdb->prepare("SELECT quiz_id FROM $submissions_table WHERE id = %d", $submission_id));
        if (!$quiz_id) {
            return null;
        }

        // 2. Get user's scores for the submission
        $scores = $wpdb->get_results( $wpdb->prepare(
            "SELECT ss.category_id, ss.score, c.name as category_name, c.description as category_description
            FROM $submission_scores_table ss
            JOIN $categories_table c ON ss.category_id = c.id
            WHERE ss.submission_id = %d",
            $submission_id
        ), OBJECT_K ); // Key by category_id

        if ( empty( $scores ) ) {
            return null;
        }

        $category_ids = array_keys($scores);
        $placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );

        // 3. Get max possible points for each relevant category in this quiz
        $sql = $wpdb->prepare(
            "SELECT
                q.category_id,
                SUM(
                    IF(
                        q.question_type = 'multiple',
                        (SELECT SUM(points) FROM {$answers_table} a WHERE a.question_id = q.id AND a.points > 0),
                        (SELECT MAX(points) FROM {$answers_table} a WHERE a.question_id = q.id)
                    )
                ) AS total_max_points
            FROM
                {$questions_table} q
            JOIN
                {$sections_table} s ON q.section_id = s.id
            WHERE
                s.quiz_id = %d AND q.category_id IN ($placeholders)
            GROUP BY
                q.category_id",
            array_merge([$quiz_id], $category_ids)
        );
        $total_possible_points_per_category = $wpdb->get_results($sql, OBJECT_K);

        $result_data = array(
            'submission_id' => $submission_id,
            'categories' => array(),
        );

        // 4. Process each category score
        foreach ( $scores as $category_id => $score_data ) {
            $total_possible_points = isset($total_possible_points_per_category[$category_id]) ? $total_possible_points_per_category[$category_id]->total_max_points : 0;

            $percentage = ($total_possible_points > 0) ? ( $score_data->score / $total_possible_points ) * 100 : 0;
            $rounded_percentage = round($percentage);

            // Look for a matching tier. Prioritize 'percentage' type, but fall back to 'value' type.
            // This makes the system more flexible, as you pointed out.
            $tier = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$result_tiers_table} WHERE threshold_type = 'percentage' AND threshold_value >= %d ORDER BY threshold_value ASC LIMIT 1",
                $rounded_percentage
            ) );

            if ( ! $tier ) {
                $tier = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$result_tiers_table} WHERE threshold_type = 'value' AND threshold_value >= %d ORDER BY threshold_value ASC LIMIT 1",
                    $score_data->score
                ) );
            }

            if ( $tier ) {
                $category_result = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM $category_results_table WHERE category_id = %d AND result_tier_id = %d",
                    $category_id,
                    $tier->id
                ) );

                $result_data['categories'][] = array(
                    'category_name' => $score_data->category_name,
                    'category_description' => !empty($score_data->category_description) ? wp_specialchars_decode(stripslashes($score_data->category_description), ENT_QUOTES) : '',
                    'score' => $score_data->score,
                    'total_possible_points' => $total_possible_points,
                    'tier_name' => $tier->tier_name,
                    'focus_area_title' => $category_result ? stripslashes($category_result->focus_area_title) : '',
                    'focus_area_description' => $category_result ? wp_specialchars_decode(stripslashes($category_result->focus_area_description), ENT_QUOTES) : 'Result details have not been configured for this tier.',
                    'healing_plan_details' => $category_result ? wp_specialchars_decode(stripslashes($category_result->healing_plan_details), ENT_QUOTES) : '',
                );
            } else {
                // Fallback if no tiers are configured at all for this quiz
                $result_data['categories'][] = array(
                    'category_name' => $score_data->category_name,
                    'category_description' => !empty($score_data->category_description) ? wp_specialchars_decode(stripslashes($score_data->category_description), ENT_QUOTES) : '',
                    'score' => $score_data->score,
                    'total_possible_points' => $total_possible_points,
                    'tier_name' => 'N/A',
                    'focus_area_title' => '',
                    'focus_area_description' => 'Result tiers have not been configured for this quiz.',
                    'healing_plan_details' => '',
                );
            }
        }

        return $result_data;
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
        } else {
            // Calculate and save scores
            $this->calculate_and_save_scores($submission_id, $quiz_id, $answers);

            // Get result HTML
            $result_html = $this->display_result_shortcode(array('submission_id' => $submission_id));

            wp_send_json_success( array( 
                'message' => 'Submission saved successfully.', 
                'submission_id' => $submission_id,
                'result_html' => $result_html
            ) );
        }
    }

    public function send_quiz_result_email_callback() {
        // Nonce check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'assessment_quiz_nonce')) {
            wp_send_json_error(array('message' => 'Nonce verification failed.'), 403);
            return;
        }
    
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
        if (!$submission_id || !is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid submission ID or email address.'));
            return;
        }

        // Subscribe to ConvertKit
        $this->subscribe_to_convertkit($email);
    
        // Generate the result HTML again
        $result_html = $this->display_result_shortcode(array(
            'submission_id' => $submission_id,
            'for_email'     => true,
        ));
    
        $subject = 'Your Quiz Results';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // To make it look better in email clients, wrap it in a basic HTML structure.
        $email_body = '<html><head><style>' . file_get_contents(plugin_dir_path( __FILE__ ) . '../public/css/quiz-styles.css') . '</style></head><body>';
        $email_body .= '<h1>Your Assessment Results</h1>';
        $email_body .= $result_html;
        $email_body .= '</body></html>';
    
        $sent = wp_mail($email, $subject, $email_body, $headers);
    
        if ($sent) {
            wp_send_json_success(array('message' => 'Your results have been sent to your email.'));
        } else {
            wp_send_json_error(array('message' => 'There was a problem sending your results. Please try again.'));
        }
    }

    private function subscribe_to_convertkit($email) {
        $api_key = get_option('assessment_quiz_convertkit_api_key');
        $form_id = get_option('assessment_quiz_convertkit_form_id');
        $tags_string = get_option('assessment_quiz_convertkit_tags');

        if (empty($api_key) || empty($form_id)) {
            error_log('ConvertKit Debug: API Key or Form ID is missing in settings.');
            return; // Don't do anything if settings are missing
        }

        $url = "https://api.convertkit.com/v3/forms/{$form_id}/subscribe";

        $body = array(
            'api_key' => $api_key,
            'email'   => $email,
        );

        if ( ! empty( $tags_string ) ) {
            $tag_ids = array_map( 'intval', array_map( 'trim', explode( ',', $tags_string ) ) );
            $tag_ids = array_filter( $tag_ids );

            if ( ! empty( $tag_ids ) ) {
                $body['tags'] = $tag_ids;
            }
        }
        
        $args = array(
            'body'    => json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'timeout' => 15, // 15 seconds
        );

        // Use wp_remote_post to send the request
        $response = wp_remote_post($url, $args);

        // Debugging: Log the response from ConvertKit
        if (is_wp_error($response)) {
            // Log WordPress-level errors (e.g., cURL errors, DNS issues)
            error_log('ConvertKit WP Error: ' . $response->get_error_message());
        } else {
            // Log the response from ConvertKit's server
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            // Log everything for debugging purposes
            error_log('ConvertKit API Response Code: ' . $response_code);
            error_log('ConvertKit API Response Body: ' . $response_body);
        }
    }

    private function calculate_and_save_scores($submission_id, $quiz_id, $answers) {
        global $wpdb;
        $questions_table = $wpdb->prefix . 'assessment_questions';
        $answers_table = $wpdb->prefix . 'assessment_answers';
        $submission_scores_table = $wpdb->prefix . 'assessment_submission_scores';
    
        $category_scores = array();
    
        foreach ($answers as $question_id => $submitted_answers) {
            // Get category_id for the question from the DB to be safe
            $category_id = $wpdb->get_var($wpdb->prepare("SELECT category_id FROM $questions_table WHERE id = %d", $question_id));
    
            if ($category_id) {
                if (!isset($category_scores[$category_id])) {
                    $category_scores[$category_id] = 0;
                }
    
                // Normalize submitted_answers to an array of answers
                // Check if the first key is numeric (like for multiple choice) or a string (like 'answerId' for single choice)
                $is_multiple_choice = isset($submitted_answers[0]);
    
                if ($is_multiple_choice) {
                    // It's an array of answers (multiple choice)
                    $answer_list = $submitted_answers;
                } else {
                    // It's a single answer object
                    $answer_list = array($submitted_answers);
                }
    
                foreach ($answer_list as $answer_data) {
                    if (isset($answer_data['answerId'])) {
                        $answer_id = $answer_data['answerId'];
                        // Get points from DB for security
                        $points = $wpdb->get_var($wpdb->prepare("SELECT points FROM $answers_table WHERE id = %d", $answer_id));
                        if (!is_null($points)) {
                            $category_scores[$category_id] += $points;
                        }
                    }
                }
            }
        }
    
        // Save the scores for each category
        foreach ($category_scores as $category_id => $score) {
            $wpdb->insert(
                $submission_scores_table,
                array(
                    'submission_id' => $submission_id,
                    'category_id' => $category_id,
                    'score' => $score,
                ),
                array('%d', '%d', '%d')
            );
        }
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