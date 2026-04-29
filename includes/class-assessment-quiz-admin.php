<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.1.0
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
        add_action( 'admin_post_save_category_action', array( $this, 'save_category_data' ) );
        add_action( 'admin_action_delete_category', array( $this, 'delete_category_action' ) );
        add_action( 'admin_post_save_result_tier_action', array( $this, 'save_result_tier_data' ) );
        add_action( 'admin_action_delete_result_tier', array( $this, 'delete_result_tier_action' ) );
        add_action( 'admin_post_save_category_result_action', array( $this, 'save_category_result_data' ) );
        add_action( 'admin_action_delete_category_result', array( $this, 'delete_category_result_action' ) );
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

        // Enqueue SortableJS from a CDN
        wp_enqueue_script(
            'sortable-js',
            'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js',
            array(),
            '1.15.0', // You can specify a version
            true
        );

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            plugin_dir_url( __FILE__ ) . '../admin/js/admin-scripts.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        // If on the 'add-new-quiz' page, pass categories data to the script
        if ( strpos( $hook, 'add-new-quiz' ) !== false ) {
            global $wpdb;
            $categories_table = $wpdb->prefix . 'assessment_categories';
            $categories = $wpdb->get_results( "SELECT id, name FROM {$categories_table} ORDER BY name ASC", ARRAY_A );
            wp_localize_script( $this->plugin_name . '-admin', 'aq_categories', $categories );
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Assessment Quiz',
            'Assessment Quiz',
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

        // Add a "Settings" submenu
        add_submenu_page(
            'assessment-quiz',
            'Settings',
            'Settings',
            'manage_options',
            'assessment-quiz-settings',
            array( $this, 'display_settings_page' )
        );

        add_submenu_page(
            'assessment-quiz',
            'Submissions',
            'Submissions',
            'manage_options',
            'assessment-quiz-submissions',
            array( $this, 'display_submissions_page' )
        );
    }

    public function display_quizzes_page() {
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-quiz-list-table.php';

        $list_table = new Assessment_Quiz_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">All Quizzes</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=add-new-quiz' ) ); ?>" class="page-title-action">Add New Quiz</a>

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

        global $wpdb;
        $categories_table = $wpdb->prefix . 'assessment_categories';
        $categories = $wpdb->get_results( "SELECT * FROM {$categories_table} ORDER BY name ASC", ARRAY_A );

        // Correctly include the form template from the 'admin/templates' directory
        require_once plugin_dir_path( __FILE__ ) . '../admin/templates/add-new-quiz-form.php';
    }

    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Assessment Quiz Settings</h1>
            <?php
            // Determine the active tab, defaulting to 'categories'
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'categories';
            ?>
            <!-- Tab navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=assessment-quiz-settings&tab=categories" class="nav-tab <?php echo $active_tab == 'categories' ? 'nav-tab-active' : ''; ?>">Categories</a>
                <a href="?page=assessment-quiz-settings&tab=result_tiers" class="nav-tab <?php echo $active_tab == 'result_tiers' ? 'nav-tab-active' : ''; ?>">Result Tiers</a>
                <a href="?page=assessment-quiz-settings&tab=category_results" class="nav-tab <?php echo $active_tab == 'category_results' ? 'nav-tab-active' : ''; ?>">Category Results</a>
            </h2>

            <?php
            // Display content based on the active tab
            if ( $active_tab == 'categories' ) {
                $this->display_categories_tab_content();
            } elseif ( $active_tab == 'result_tiers' ) {
                $this->display_result_tiers_tab_content();
            } elseif ( $active_tab == 'category_results' ) {
                $this->display_category_results_tab_content();
            }
            ?>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#doaction, #doaction2').on('click', function(e) {
                    var action = $(this).prev('select').val();
                    if (action === 'trash') {
                        <?php
                        $confirm_message = '';
                        if ( $active_tab === 'categories' ) {
                            $confirm_message = 'Are you sure you want to delete the selected categories?';
                        } elseif ( $active_tab === 'result_tiers' ) {
                            $confirm_message = 'Are you sure you want to delete the selected Result Tier?';
                        } elseif ( $active_tab === 'category_results' ) {
                            $confirm_message = 'Are you sure you want to delete the selected Category Results?';
                        }
                        ?>
                        var message = '<?php echo esc_js( $confirm_message ); ?>';
                        if (message && !confirm(message)) {
                            e.preventDefault();
                        }
                    }
                });
            });
        </script>
        <?php
    }

    public function display_result_tiers_tab_content() {
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-result-tiers-list-table.php';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $result_tier_id = isset( $_GET['result_tier_id'] ) ? intval( $_GET['result_tier_id'] ) : 0;
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_result_tiers';
        $result_tier = null;
        if ( $result_tier_id ) {
            $result_tier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $result_tier_id ), ARRAY_A );
        }
        if ( 'edit' === $action && $result_tier ) {
            $this->display_result_tier_form( 'edit', $result_tier );
        } elseif ( 'add' === $action ) {
            $this->display_result_tier_form( 'add' );
        } else {
            $list_table = new Assessment_Result_Tiers_List_Table();
            $list_table->prepare_items();
            ?>
            <h2>Result Tiers
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=result_tiers&action=add' ) ); ?>" class="page-title-action">Add New</a>
            </h2>

            <?php if ( ! empty( $_GET['status'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <?php if ( $_GET['status'] === 'saved' ) : ?>
                        <p><?php esc_html_e( 'Result tier saved successfully.', 'assessment-quiz' ); ?></p>
                    <?php elseif ( $_GET['status'] === 'deleted' ) : ?>
                        <p><?php esc_html_e( 'Result tier(s) deleted successfully.', 'assessment-quiz' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="page" value="assessment-quiz-settings">
                <input type="hidden" name="tab" value="result_tiers">
                <?php
                $list_table->search_box( 'Search Tiers', 'tier' );
                $list_table->display();
                ?>
            </form>
            <?php
        }
    }

    private function display_result_tier_form( $action = 'add', $tier = null ) {
        $page_title = ( 'add' === $action ) ? 'Add New Result Tier' : 'Edit Result Tier';
        $submit_button_text = ( 'add' === $action ) ? 'Add Result Tier' : 'Update Result Tier';
        
        $tier_name = $tier ? $tier['tier_name'] : '';
        $tier_label = $tier ? $tier['tier_label'] : '';
        $threshold_type = $tier ? $tier['threshold_type'] : 'value';
        $threshold_value = $tier ? $tier['threshold_value'] : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="save_result_tier_action">
                <?php if ( 'edit' === $action && $tier ) : ?>
                    <input type="hidden" name="result_tier_id" value="<?php echo esc_attr( $tier['id'] ); ?>">
                <?php endif; ?>
                <?php wp_nonce_field( 'save_result_tier_action', 'save_result_tier_nonce' ); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="tier_name">Tier Name</label></th>
                            <td><input name="tier_name" type="text" id="tier_name" value="<?php echo esc_attr( $tier_name ); ?>" class="regular-text" required>
                            <p class="description">A short name for the tier (e.g., High, Medium, Low).</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tier_label">Tier Label</label></th>
                            <td><input name="tier_label" type="text" id="tier_label" value="<?php echo esc_attr( $tier_label ); ?>" class="regular-text" required>
                            <p class="description">A descriptive label shown to the user.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="threshold_type">Threshold Type</label></th>
                            <td>
                                <select name="threshold_type" id="threshold_type">
                                    <option value="percentage" <?php selected( $threshold_type, 'percentage' ); ?>>Percentage</option>
                                    <option value="value" <?php selected( $threshold_type, 'value' ); ?>>Value</option>
                                </select>
                                <p class="description">The type of threshold calculation.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="threshold_value">Threshold Value</label></th>
                            <td><input name="threshold_value" type="number" id="threshold_value" value="<?php echo esc_attr( $threshold_value ); ?>" class="small-text" required>
                            <p class="description">The maximum score (or percentage) required to achieve this tier.</p></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( $submit_button_text ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=result_tiers' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    public function display_categories_tab_content() {
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-categories-list-table.php';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $category_id = isset( $_GET['category_id'] ) ? intval( $_GET['category_id'] ) : 0;
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_categories';
        $category = null;
        if ( $category_id ) {
            $category = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $category_id ), ARRAY_A );
        }
        if ( 'edit' === $action && $category ) {
            // Display the edit form
            $this->display_category_form( 'edit', $category );
        } elseif ( 'add' === $action ) {
            // Display the add new form
            $this->display_category_form( 'add' );
        } else {
            // Display the list table
            $list_table = new Assessment_Categories_List_Table();
            $list_table->prepare_items();
            ?>
            
            <h2>Categories
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=categories&action=add' ) ); ?>" class="page-title-action">Add New</a>
            </h2>

            <?php if ( ! empty( $_GET['status'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <?php if ( $_GET['status'] === 'saved' ) : ?>
                        <p><?php esc_html_e( 'Category saved successfully.', 'assessment-quiz' ); ?></p>
                    <?php elseif ( $_GET['status'] === 'deleted' ) : ?>
                        <p><?php esc_html_e( 'Category(ies) deleted successfully.', 'assessment-quiz' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="page" value="assessment-quiz-settings">
                <input type="hidden" name="tab" value="categories">
                <?php
                $list_table->search_box( 'Search Categories', 'category' );
                $list_table->display();
                ?>
            </form>
            
            <?php
        }
    }

    public function display_category_results_tab_content() {
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-category-results-list-table.php';
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        $category_result_id = isset( $_GET['category_result_id'] ) ? intval( $_GET['category_result_id'] ) : 0;
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_category_results';
        $category_result = null;
        if ( $category_result_id ) {
            $category_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $category_result_id ), ARRAY_A );
        }
        if ( ( 'edit' === $action && $category_result ) || 'add' === $action ) {
            $this->display_category_result_form( $action, $category_result );
        } else {
            $list_table = new Assessment_Category_Results_List_Table();
            $list_table->prepare_items();
            ?>
            <h2>Category Results
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=category_results&action=add' ) ); ?>" class="page-title-action">Add New</a>
            </h2>

            <?php if ( ! empty( $_GET['status'] ) ) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <?php if ( $_GET['status'] === 'saved' ) : ?>
                        <p><?php esc_html_e( 'Category result saved successfully.', 'assessment-quiz' ); ?></p>
                    <?php elseif ( $_GET['status'] === 'deleted' ) : ?>
                        <p><?php esc_html_e( 'Category result(s) deleted successfully.', 'assessment-quiz' ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="page" value="assessment-quiz-settings">
                <input type="hidden" name="tab" value="category_results">
                <?php
                $list_table->search_box( 'Search Results', 'result' );
                $list_table->display();
                ?>
            </form>
            <?php
        }
    }

    private function display_category_result_form( $action = 'add', $result = null ) {
        global $wpdb;
        $page_title = ( 'add' === $action ) ? 'Add New Category Result' : 'Edit Category Result';
        $submit_button_text = ( 'add' === $action ) ? 'Add Category Result' : 'Update Category Result';

        $category_id = $result ? $result['category_id'] : '';
        $result_tier_id = $result ? $result['result_tier_id'] : '';
        $focus_area_title = $result ? $result['focus_area_title'] : '';
        $focus_area_description = $result ? $result['focus_area_description'] : '';
        $healing_plan_details = $result ? $result['healing_plan_details'] : '';

        $categories_table = $wpdb->prefix . 'assessment_categories';
        $categories = $wpdb->get_results( "SELECT id, name FROM {$categories_table} ORDER BY name ASC" );

        $tiers_table = $wpdb->prefix . 'assessment_result_tiers';
        $tiers = $wpdb->get_results( "SELECT id, tier_name FROM {$tiers_table} ORDER BY tier_name ASC" );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="save_category_result_action">
                <?php if ( 'edit' === $action && $result ) : ?>
                    <input type="hidden" name="category_result_id" value="<?php echo esc_attr( $result['id'] ); ?>">
                <?php endif; ?>
                <?php wp_nonce_field( 'save_category_result_action', 'save_category_result_nonce' ); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="category_id">Category</label></th>
                            <td>
                                <select name="category_id" id="category_id" required>
                                    <option value="">Select a Category</option>
                                    <?php foreach ( $categories as $category ) : ?>
                                        <option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $category_id, $category->id ); ?>>
                                            <?php echo esc_html( $category->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="result_tier_id">Result Tier</label></th>
                            <td>
                                <select name="result_tier_id" id="result_tier_id" required>
                                    <option value="">Select a Result Tier</option>
                                    <?php foreach ( $tiers as $tier ) : ?>
                                        <option value="<?php echo esc_attr( $tier->id ); ?>" <?php selected( $result_tier_id, $tier->id ); ?>>
                                            <?php echo esc_html( $tier->tier_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="focus_area_title">Focus Area Title</label></th>
                            <td><input name="focus_area_title" type="text" id="focus_area_title" value="<?php echo esc_attr( $focus_area_title ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="focus_area_description">Focus Area Description</label></th>
                            <td>
                                <?php 
                                wp_editor( $focus_area_description, 'focus_area_description', [ 
                                    'textarea_name' => 'focus_area_description',
                                    'media_buttons' => true,
                                    'textarea_rows' => 5,
                                    'tinymce'       => [
                                        'toolbar1' => 'formatselect | bold italic strikethrough | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker | fullscreen | wp_adv',
                                        'toolbar2' => 'styleselect | pastetext removeformat | charmap | outdent indent | undo redo | wp_help | forecolor backcolor | fontsizeselect',
                                        'plugins'  => 'textcolor',
                                    ], 
                                ] ); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="healing_plan_details">Healing Plan Details</label></th>
                            <td>
                                <?php 
                                wp_editor( $healing_plan_details, 'healing_plan_details', [ 
                                    'textarea_name' => 'healing_plan_details',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'tinymce'       => [
                                        'toolbar1' => 'formatselect | bold italic strikethrough | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker | fullscreen | wp_adv',
                                        'toolbar2' => 'styleselect | pastetext removeformat | charmap | outdent indent | undo redo | wp_help | forecolor backcolor | fontsizeselect',
                                        'plugins'  => 'textcolor',
                                    ], 
                                ] ); 
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( $submit_button_text ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=category_results' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    private function display_category_form( $action = 'add', $category = null ) {
        $page_title = ( 'add' === $action ) ? 'Add New Category' : 'Edit Category';
        $submit_button_text = ( 'add' === $action ) ? 'Add Category' : 'Update Category';
        $category_name = $category ? $category['name'] : '';
        $category_description = $category ? $category['description'] : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="save_category_action">
                <?php if ( 'edit' === $action && $category ) : ?>
                    <input type="hidden" name="category_id" value="<?php echo esc_attr( $category['id'] ); ?>">
                <?php endif; ?>
                <?php wp_nonce_field( 'save_category_action', 'save_category_nonce' ); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="category_name">Name</label></th>
                            <td><input name="category_name" type="text" id="category_name" value="<?php echo esc_attr( $category_name ); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="category_description">Description</label></th>
                            <td><textarea name="category_description" id="category_description" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $category_description ); ?></textarea></td>
                        </tr>
                    </tbody>
                </table>
                <?php //submit_button( $submit_button_text ); ?>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( $submit_button_text ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=assessment-quiz-settings&tab=categories' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    public function save_category_data() {
        if ( ! isset( $_POST['save_category_nonce'] ) || ! wp_verify_nonce( $_POST['save_category_nonce'], 'save_category_action' ) ) {
            wp_die( 'Security check failed' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_categories';
        $category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        $name = sanitize_text_field( $_POST['category_name'] );
        $description = sanitize_textarea_field( $_POST['category_description'] );
        $data = [
            'name'        => $name,
            'description' => $description,
        ];
        if ( $category_id ) {
            $wpdb->update( $table_name, $data, [ 'id' => $category_id ] );
        } else {
            $wpdb->insert( $table_name, $data );
        }
        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=categories&status=saved' ) );
        exit;
    }

    public function delete_category_action() {
        if ( empty( $_GET['category_id'] ) || empty( $_GET['_wpnonce'] ) ) {
            wp_die( 'Invalid request.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to delete this item.' );
        }
        $category_id = absint( $_GET['category_id'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] );
        if ( ! wp_verify_nonce( $nonce, 'delete_category_' . $category_id ) ) {
            wp_die( 'Security check failed.' );
        }
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-categories-list-table.php';
        Assessment_Categories_List_Table::delete_categories( $category_id );
        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=categories&status=deleted' ) );
        exit;
    }

    public function save_result_tier_data() {
        if ( ! isset( $_POST['save_result_tier_nonce'] ) || ! wp_verify_nonce( $_POST['save_result_tier_nonce'], 'save_result_tier_action' ) ) {
            wp_die( 'Security check failed' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_result_tiers';
        $result_tier_id = isset( $_POST['result_tier_id'] ) ? intval( $_POST['result_tier_id'] ) : 0;
        
        $threshold_type = sanitize_key( $_POST['threshold_type'] );
        if ( ! in_array( $threshold_type, ['percentage', 'value'] ) ) {
            $threshold_type = 'value';
        }

        $data = [
            'tier_name'       => sanitize_text_field( $_POST['tier_name'] ),
            'tier_label'      => sanitize_text_field( $_POST['tier_label'] ),
            'threshold_type'  => $threshold_type,
            'threshold_value' => intval( $_POST['threshold_value'] ),
        ];

        if ( $result_tier_id ) {
            $wpdb->update( $table_name, $data, [ 'id' => $result_tier_id ] );
        } else {
            $wpdb->insert( $table_name, $data );
        }
        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=result_tiers&status=saved' ) );
        exit;
    }

    public function delete_result_tier_action() {
        if ( empty( $_GET['result_tier_id'] ) || empty( $_GET['_wpnonce'] ) ) {
            wp_die( 'Invalid request.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to delete this item.' );
        }
        $result_tier_id = absint( $_GET['result_tier_id'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] );
        if ( ! wp_verify_nonce( $nonce, 'delete_result_tier_' . $result_tier_id ) ) {
            wp_die( 'Security check failed.' );
        }
        require_once plugin_dir_path( __FILE__ ) . 'class-assessment-result-tiers-list-table.php';
        Assessment_Result_Tiers_List_Table::delete_result_tiers( $result_tier_id );
        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=result_tiers&status=deleted' ) );
        exit;
    }

    public function save_category_result_data() {
        if ( ! isset( $_POST['save_category_result_nonce'] ) || ! wp_verify_nonce( $_POST['save_category_result_nonce'], 'save_category_result_action' ) ) {
            wp_die( 'Security check failed' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_category_results';
        $id = isset( $_POST['category_result_id'] ) ? intval( $_POST['category_result_id'] ) : 0;
        
        $data = [
            'category_id'            => intval( $_POST['category_id'] ),
            'result_tier_id'         => intval( $_POST['result_tier_id'] ),
            'focus_area_title'       => sanitize_text_field( $_POST['focus_area_title'] ),
            'focus_area_description' => sanitize_textarea_field( $_POST['focus_area_description'] ),
            'healing_plan_details'   => wp_kses_post( $_POST['healing_plan_details'] ),
        ];

        if ( $id ) {
            $wpdb->update( $table_name, $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( $table_name, $data );
        }

        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=category_results&status=saved' ) );
        exit;
    }

    public function delete_category_result_action() {
        if ( empty( $_GET['category_result_id'] ) || empty( $_GET['_wpnonce'] ) ) {
            wp_die( 'Invalid request.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to delete this item.' );
        }
        $id = absint( $_GET['category_result_id'] );
        $nonce = sanitize_text_field( $_GET['_wpnonce'] );
        if ( ! wp_verify_nonce( $nonce, 'delete_category_result_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'assessment_category_results';
        $wpdb->delete( $table_name, [ 'id' => $id ] );
        wp_redirect( admin_url( 'admin.php?page=assessment-quiz-settings&tab=category_results&status=deleted' ) );
        exit;
    }

    public function display_submissions_page() {
        echo '<h1>All Submissions</h1>';
        echo '<p>A list of all user submissions will appear here.</p>';
    }

    public function save_quiz_data() {
        if ( ! isset( $_POST['save_quiz_nonce'] ) || ! wp_verify_nonce( $_POST['save_quiz_nonce'], 'save_quiz_action' ) ) {
            wp_die( 'Security check failed' );
        }

        // Remove auto-added slashes from the POST data to prevent duplication.
        $_POST = stripslashes_deep( $_POST );

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
                        $question_category_id = isset( $question_data['category_id'] ) ? intval( $question_data['category_id'] ) : 0;
                        $question_order = isset( $question_data['order'] ) ? intval( $question_data['order'] ) : $question_index + 1;

                        $question_db_data = [
                            'section_id'        => $section_id,
                            'question_text'     => $question_text,
                            'question_type'     => $question_type,
                            'category_id'       => $question_category_id,
                            'question_order'    => $question_order,
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
            $questions = $wpdb->get_results( $wpdb->prepare( "SELECT q.*, c.name as category_name FROM {$wpdb->prefix}assessment_questions q LEFT JOIN {$wpdb->prefix}assessment_categories c ON q.category_id = c.id WHERE q.section_id = %d ORDER BY `question_order` ASC", $section['id'] ), ARRAY_A );
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