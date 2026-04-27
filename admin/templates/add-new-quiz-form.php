<?php
/**
 * Template for the "Add New Quiz" admin page with a table-based layout and modals.
 *
 * @package    Assessment_Quiz
 * @subpackage Assessment_Quiz/admin/templates
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div class="wrap assessment-quiz-admin-wrap">
    <h1><?php echo $quiz_id ? esc_html( 'Edit Quiz' ) : esc_html( 'Add New Assessment Quiz' ); ?></h1>

    <form id="add-quiz-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <!-- Security fields -->
        <input type="hidden" name="action" value="save_quiz_action">
        <?php wp_nonce_field( 'save_quiz_action', 'save_quiz_nonce' ); ?>
        <?php if ( $quiz_id ) : ?>
            <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
        <?php endif; ?>

        <!-- Main Quiz Details -->
        <div class="form-section">
            <h2>Quiz Details</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="quiz_title">Quiz Title</label></th>
                        <td><input name="quiz_title" type="text" id="quiz_title" class="regular-text" value="<?php echo isset( $existing_quiz_data['title'] ) ? esc_attr( $existing_quiz_data['title'] ) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="quiz_description">Description</label></th>
                        <td>
                            <?php
                            $content = isset( $existing_quiz_data['description'] ) ? $existing_quiz_data['description'] : '';
                            wp_editor( $content, 'quiz_description', [
                                'textarea_name' => 'quiz_description',
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
                </tbody>
            </table>
        </div>

        <!-- Sections and Questions Table -->
        <div class="form-section">
            <h2>Quiz Structure</h2>
            <table id="quiz-structure-table" class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-primary">Title</th>
                        <th scope="col" class="manage-column">Type</th>
                        <th scope="col" class="manage-column">Category</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody id="quiz-structure-body">
                    <!-- Rows will be added here by JavaScript -->
                </tbody>
            </table>
            <p>
                <button type="button" id="add-section-btn" class="button button-secondary">+ Add Section</button>
            </p>
        </div>

        <?php submit_button( 'Save Quiz' ); ?>
    </form>
</div>

<!-- ============================================================== -->
<!-- Modal Templates (hidden from view)                           -->
<!-- ============================================================== -->

<!-- Section Modal -->
<div id="section-modal" class="aq-modal" style="display:none;">
    <div class="aq-modal-content">
        <span class="aq-modal-close">&times;</span>
        <h2>Add/Edit Section</h2>
        <form id="section-form">
            <input type="hidden" id="section-id" value="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="section-title">Section Title</label></th>
                    <td><input type="text" id="section-title" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="section-content-begin">Content (Begin)</label></th>
                    <td><textarea id="section-content-begin" class="large-text" rows="5"></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="section-content-end">Content (End)</label></th>
                    <td><textarea id="section-content-end" class="large-text" rows="5"></textarea></td>
                </tr>
            </table>
            <p>
                <button type="button" id="save-section-btn" class="button button-primary">Save Section</button>
            </p>
        </form>
    </div>
</div>

<!-- Question Modal -->
<div id="question-modal" class="aq-modal" style="display:none;">
    <div class="aq-modal-content">
        <span class="aq-modal-close">&times;</span>
        <h2>Add/Edit Question</h2>
        <form id="question-form">
            <input type="hidden" id="question-id" value="">
            <input type="hidden" id="question-section-id" value="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="question-text">Question Text</label></th>
                    <td>
                        <?php
                        wp_editor( '', 'question-text', [
                            'textarea_name' => 'question_text',
                            'editor_class'  => 'wp-editor-area',
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
                    <th scope="row"><label for="question-type">Question Type</label></th>
                    <td>
                        <select id="question-type">
                            <option value="single">Single Choice (Radio)</option>
                            <option value="multiple">Multiple Choice (Checkbox)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="question-category">Category</label></th>
                    <td>
                        <select id="question-category">
                            <option value="">Select a category</option>
                            <?php if ( ! empty( $categories ) ) : ?>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h3>Answers</h3>
            <div id="answers-container">
                <!-- Answers will be added here -->
            </div>
            <button type="button" id="add-answer-btn" class="button button-secondary">+ Add Answer</button>

            <p>
                <button type="button" id="save-question-btn" class="button button-primary">Save Question</button>
            </p>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- JavaScript Templates for dynamic rows                          -->
<!-- ============================================================== -->

<script type="text/html" id="section-row-template">
    <tr class="section-row" data-section-id="__SECTION_ID__">
        <td class="column-primary has-row-actions">
            <strong>__SECTION_TITLE__</strong>
            <div class="row-actions">
                <span class="edit"><a href="#" class="edit-section">Edit</a> | </span>
                <span class="trash"><a href="#" class="delete-section">Delete</a></span>
            </div>
            <button type="button" class="toggle-row"></button>
        </td>
        <td>Section</td>
        <td></td>
        <td>
            <button type="button" class="button button-secondary add-question-btn">+ Add Question</button>
        </td>
    </tr>
</script>

<script type="text/html" id="question-row-template">
    <tr class="question-row" data-question-id="__QUESTION_ID__" data-section-id="__SECTION_ID__">
        <td class="column-primary has-row-actions">
            <div style="padding-left: 20px;">__QUESTION_TEXT__</div>
            <div style="padding-left: 20px;" class="row-actions">
                <span class="edit"><a href="#" class="edit-question">Edit</a> | </span>
                <span class="trash"><a href="#" class="delete-question">Delete</a></span>
            </div>
        </td>
        <td>Question</td>
        <td>__QUESTION_CATEGORY__</td>
        <td></td>
    </tr>
</script>

<script type="text/html" id="answer-template">
    <div class="answer-item">
        <hr>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Answer Text</label></th>
                <td><textarea class="large-text answer-text" rows="2"></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label>Points</label></th>
                <td><input type="number" class="small-text answer-points" value="0"></td>
            </tr>
        </table>
        <button type="button" class="button button-link-delete remove-answer-btn">Remove Answer</button>
    </div>
</script>

<script>
    // Pass existing quiz data to the JavaScript file for handling the dynamic parts
    var existingQuizData = <?php echo $existing_quiz_data ? json_encode( $existing_quiz_data ) : 'null'; ?>;
</script>