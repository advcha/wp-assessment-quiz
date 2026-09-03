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
                                    'plugins'  => 'textcolor,lists,charmap,paste',
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
            <div class="search-box" style="margin-bottom: 10px; float: right;">
                <label class="screen-reader-text" for="quiz-search-input">Search:</label>
                <input type="search" id="quiz-search-input" placeholder="Search...">
            </div>
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

        <!-- Result Tier Colors -->
        <div class="form-section">
            <h2>Result Tier Colors</h2>
            <p class="description">Set a specific color for each result tier for this quiz. Tiers appear here when you add questions with associated categories.</p>
            <table class="form-table">
                <tbody id="result-tier-colors-container">
                    <?php if ( ! empty( $tiers ) ) : ?>
                        <?php foreach ( $tiers as $tier ) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="tier_color_<?php echo esc_attr( $tier->id ); ?>">
                                        <?php echo esc_html( $tier->tier_name ); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" id="tier_color_<?php echo esc_attr( $tier->id ); ?>" name="tier_colors[<?php echo esc_attr( $tier->id ); ?>]" value="<?php echo isset( $saved_colors[ $tier->id ] ) ? esc_attr( $saved_colors[ $tier->id ] ) : '#ffffff'; ?>" class="color-picker-field">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="2">
                                <p>No result tiers to display. Add questions with categories to see available tiers.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
                                'plugins'  => 'textcolor,lists,charmap,paste',
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
            <div style="padding-left: 20px;"><span class="drag-handle" style="cursor: move;">&#9776;</span> __QUESTION_TEXT__</div>
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

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Initialize color picker for any tiers loaded initially
        $('.color-picker-field').wpColorPicker({
            palettes: [
                '#28a745', // Green
                '#ffc107', // Yellow
                '#dc3545', // Red
                '#33a3dc', // Blue
                '#000000', // Black
                '#ffffff'  // White
            ]
        });

        // Store saved colors in a global JS variable to access them later
        window.savedTierColors = <?php echo json_encode( $saved_colors ); ?>;

        // Function to update result tiers via AJAX
        function updateResultTiers(data) {
            var currentColors = {};
            $('#result-tier-colors-container .wp-color-picker').each(function() {
                var tierId = $(this).attr('id').split('_').pop();
                var color = $(this).wpColorPicker('color');
                if (tierId && color) {
                    currentColors[tierId] = color;
                }
            });

            var categoryIds = [];
            // Ensure data is available
            if (data && data.sections) {
                data.sections.forEach(function(section) {
                    if (section.questions) {
                        section.questions.forEach(function(question) {
                            // Use parseInt to ensure we have a number
                            var catId = parseInt(question.category_id, 10);
                            if (catId > 0 && categoryIds.indexOf(catId) === -1) {
                                categoryIds.push(catId);
                            }
                        });
                    }
                });
            }

            var ajaxData = {
                action: 'get_tiers_for_categories',
                nonce: $('#save_quiz_nonce').val(), 
                category_ids: categoryIds
            };

            $.post(ajaxurl, ajaxData, function(response) {
                var container = $('#result-tier-colors-container');
                container.empty(); // Clear current content

                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(tier) {
                        var color = '#000000'; // Default color
                        if (currentColors[tier.id]) {
                            color = currentColors[tier.id];
                        } else if (window.savedTierColors && window.savedTierColors[tier.id]) {
                            color = window.savedTierColors[tier.id];
                        }
                        var rowHTML = `
                            <tr>
                                <th scope="row">
                                    <label for="tier_color_${tier.id}">${tier.tier_name}</label>
                                </th>
                                <td>
                                    <input type="text" id="tier_color_${tier.id}" name="tier_colors[${tier.id}]" value="${color}" class="color-picker-field">
                                </td>
                            </tr>`;
                        container.append(rowHTML);
                    });

                    // Re-initialize color pickers for the newly added fields
                    $('.color-picker-field').wpColorPicker({
                        palettes: [
                            '#28a745', // Green
                            '#ffc107', // Yellow
                            '#dc3545', // Red
                            '#33a3dc', // Blue
                            '#000000', // Black
                            '#ffffff'  // White
                        ]
                    });
                } else {
                    container.html('<tr><td colspan="2"><p>No result tiers to display. Add questions with categories to see available tiers.</p></td></tr>');
                }
            });
        }

        // Listen for the custom event to update tiers
        $(document).on('quizDataUpdated', function(event, updatedQuizData) {
            updateResultTiers(updatedQuizData);
        });

        // For existing quizzes, run the update on page load to show the correct tiers
        /*if (parseInt($('input[name="quiz_id"]').val(), 10) > 0) {
            updateResultTiers();
        }*/
       if (existingQuizData) {
            updateResultTiers(existingQuizData);
        }
    });
</script>