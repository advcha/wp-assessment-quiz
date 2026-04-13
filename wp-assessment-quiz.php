<?php
/**
 * Plugin Name:       Assessment Quiz
 * Description:       A plugin for creating anxiety and depression assessment quizzes.
 * Version:           1.0.0
 * Author:            Satria Faestha
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       assessment-quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'ASSESSMENT_QUIZ_VERSION', '1.0.0' );
define( 'ASSESSMENT_QUIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSESSMENT_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The main plugin class.
 * This class loads all the dependencies and sets up the hooks.
 */
final class Assessment_Quiz {

    /**
     * The single instance of the class.
     * @var Assessment_Quiz
     */
    private static $_instance = null;

    /**
     * The plugin name.
     * @var string
     */
    protected $plugin_name;

    /**
     * The plugin version.
     * @var string
     */
    protected $version;

    /**
     * Main Assessment_Quiz Instance.
     * Ensures only one instance of the plugin is loaded.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->version = ASSESSMENT_QUIZ_VERSION;
        $this->plugin_name = 'assessment-quiz';

        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once ASSESSMENT_QUIZ_PLUGIN_DIR . 'includes/class-assessment-quiz-admin.php';
        require_once ASSESSMENT_QUIZ_PLUGIN_DIR . 'includes/class-assessment-quiz-frontend.php';
        require_once ASSESSMENT_QUIZ_PLUGIN_DIR . 'includes/class-assessment-quiz-ajax.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Activation hook for database setup
        register_activation_hook( __FILE__, array( $this, 'activate' ) );

        // Initialize classes
        add_action( 'plugins_loaded', array( $this, 'init_classes' ) );
    }

    /**
     * Instantiate classes.
     */
    public function init_classes() {
        new Assessment_Quiz_Admin( $this->get_plugin_name(), $this->get_version() );
        //new Assessment_Quiz_Frontend();
        //new Assessment_Quiz_Ajax();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * The code that runs during plugin activation.
     * This function creates the necessary database tables.
     */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_prefix = $wpdb->prefix;

        // Table for Quizzes
        $table_name = $table_prefix . 'assessment_quizzes';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Sections
        $table_name = $table_prefix . 'assessment_sections';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            section_content_begin TEXT,
            section_content_end TEXT,
            section_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Questions
        $table_name = $table_prefix . 'assessment_questions';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            section_id BIGINT(20) NOT NULL,
            question_text TEXT NOT NULL,
            question_type VARCHAR(50) NOT NULL DEFAULT 'single',
            question_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY section_id (section_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Answers
        $table_name = $table_prefix . 'assessment_answers';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) NOT NULL,
            answer_text VARCHAR(255),
            points INT NOT NULL DEFAULT 0,
            answer_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Results/Reports
        $table_name = $table_prefix . 'assessment_results';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) NOT NULL,
            min_score INT NOT NULL,
            max_score INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            report_text TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Submissions
        $table_name = $table_prefix . 'assessment_submissions';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20) NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            email VARCHAR(255),
            final_score INT,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for User's selected answers
        $table_name = $table_prefix . 'assessment_user_answers';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            submission_id BIGINT(20) NOT NULL,
            question_id BIGINT(20) NOT NULL,
            answer_id BIGINT(20) NOT NULL,
            chosen_answers TEXT,
            PRIMARY KEY (id),
            KEY submission_id (submission_id)
        ) $charset_collate;";
        dbDelta( $sql );
    }
}

/**
 * Begins execution of the plugin.
 */
function assessment_quiz_run() {
    return Assessment_Quiz::instance();
}
assessment_quiz_run();