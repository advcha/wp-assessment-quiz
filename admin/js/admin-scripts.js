(function ($) {
    'use strict';

    $(document).ready(function () {

        // In-memory data store for the quiz structure
        let quizData = {
            sections: []
        };

        // If editing a quiz, load the existing data from the PHP variable ---
        if (typeof existingQuizData !== 'undefined' && existingQuizData) {
            loadExistingData(existingQuizData);
        }

        // --- Editor Helper Functions ---

        function initializeEditor($textarea) {
            if (!$textarea.length || typeof wp === 'undefined' || typeof wp.editor === 'undefined') {
                return;
            }

            let editorId = $textarea.attr('id');
            if (!editorId) {
                editorId = 'editor-' + new Date().getTime() + '-' + Math.random().toString(36).substring(2);
                $textarea.attr('id', editorId);
            }

            // If editor already exists, do nothing.
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                return;
            }

            const settings = {
                tinymce: {
                    wpautop: true,
                    toolbar1: 'formatselect | bold italic strikethrough | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker | fullscreen | wp_adv',
                    toolbar2: 'styleselect | pastetext removeformat | charmap | outdent indent | undo redo | wp_help',
                },
                quicktags: true,
                mediaButtons: true
            };

            setTimeout(function () {
                wp.editor.initialize(editorId, settings);
            }, 50);
        }

        function removeEditor(editorId) {
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).remove();
            }
        }

        function getEditorContent(editorId) {
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                return tinymce.get(editorId).getContent();
            }
            return $('#' + editorId).val();
        }

        // Function to load existing quiz data into the `quizData` object ---
        function loadExistingData(data) {
            quizData.sections = data.sections.map(sectionData => {
                return {
                    id: sectionData.id, // Keep original DB ID
                    title: sectionData.title,
                    content_begin: sectionData.section_content_begin,
                    content_end: sectionData.section_content_end,
                    questions: sectionData.questions ? sectionData.questions.map(questionData => {
                        return {
                            id: questionData.id, // Keep original DB ID
                            text: questionData.question_text,
                            type: questionData.question_type,
                            answers: questionData.answers ? questionData.answers.map(answerData => {
                                return {
                                    id: answerData.id, // Keep original DB ID
                                    text: answerData.answer_text,
                                    points: answerData.points
                                };
                            }) : []
                        };
                    }) : []
                };
            });
            renderTable(); // Re-render the table with the loaded data
        }

        // --- Modal Control ---

        function openModal($modal) {
            $modal.show();
        }

        function closeModal($modal) {
            if ($modal.is('#question-modal')) {
                // Clean up answer editors before closing
                $('#answers-container .answer-text').each(function () {
                    removeEditor($(this).attr('id'));
                });
                $('#answers-container').empty();
                
                // Clear the main question editor content, but don't destroy it.
                if (typeof tinymce !== 'undefined' && tinymce.get('question-text')) {
                    tinymce.get('question-text').setContent('');
                }
            }

            if ($modal.is('#section-modal')) {
                // Instead of removing editors, just clear their content
                if (typeof tinymce !== 'undefined') {
                    if (tinymce.get('section-content-begin')) {
                        tinymce.get('section-content-begin').setContent('');
                    }
                    if (tinymce.get('section-content-end')) {
                        tinymce.get('section-content-end').setContent('');
                    }
                }
            }

            $modal.hide();
            $modal.find('form')[0].reset();
        }

        $('.aq-modal-close').on('click', function () {
            closeModal($(this).closest('.aq-modal'));
        });

        $(window).on('click', function (event) {
            if ($(event.target).hasClass('aq-modal')) {
                closeModal($(event.target));
            }
        });

        // --- Section Handling ---

        $('#add-section-btn').on('click', function () {
            $('#section-id').val('');
            $('#section-form')[0].reset();
            $('#section-content-begin').val('');
            $('#section-content-end').val('');

            initializeEditor($('#section-content-begin'));
            initializeEditor($('#section-content-end'));

            openModal($('#section-modal'));
        });

        $('#save-section-btn').on('click', function () {
            const sectionTitle = $('#section-title').val();
            if (!sectionTitle) {
                alert('Section title is required.');
                return;
            }

            const sectionId = $('#section-id').val();
            const contentBegin = getEditorContent('section-content-begin');
            const contentEnd = getEditorContent('section-content-end');

            if (sectionId) {
                // Editing existing section
                const section = quizData.sections.find(s => s.id == sectionId);
                section.title = sectionTitle;
                section.content_begin = contentBegin;
                section.content_end = contentEnd;
            } else {
                // Adding new section
                const newSection = {
                    // Use a prefix for temporary client-side IDs
                    id: 'new_' + new Date().getTime(),
                    title: sectionTitle,
                    content_begin: contentBegin,
                    content_end: contentEnd,
                    questions: []
                };
                quizData.sections.push(newSection);
            }

            renderTable();
            closeModal($('#section-modal'));
        });

        $('#quiz-structure-body').on('click', '.edit-section', function (e) {
            e.preventDefault();
            const sectionId = $(this).closest('tr').data('section-id');
            const section = quizData.sections.find(s => s.id == sectionId);

            $('#section-id').val(section.id);
            $('#section-title').val(section.title);

            // Set the value for the underlying textareas
            $('#section-content-begin').val(section.content_begin || '');
            $('#section-content-end').val(section.content_end || '');

            // Ensure editors are initialized (will only run on first open)
            initializeEditor($('#section-content-begin'));
            initializeEditor($('#section-content-end'));

            // Use a short timeout to ensure editors are ready before setting content
            setTimeout(function() {
                if (typeof tinymce !== 'undefined') {
                    const beginEditor = tinymce.get('section-content-begin');
                    if (beginEditor) {
                        beginEditor.setContent(section.content_begin || '');
                    }
                    const endEditor = tinymce.get('section-content-end');
                    if (endEditor) {
                        endEditor.setContent(section.content_end || '');
                    }
                }
            }, 100);

            openModal($('#section-modal'));
        });

        $('#quiz-structure-body').on('click', '.delete-section', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this section and all its questions and answers?')) {
                const sectionId = $(this).closest('tr').data('section-id');
                quizData.sections = quizData.sections.filter(s => s.id != sectionId);
                renderTable();
            }
        });

        // --- Question Handling ---

        $('#quiz-structure-body').on('click', '.add-question-btn', function (e) {
            e.preventDefault();
            const sectionId = $(this).closest('tr').data('section-id');
            $('#question-id').val('');
            $('#question-section-id').val(sectionId);
            openModal($('#question-modal'));
        });

        $('#save-question-btn').on('click', function () {
            const questionText = getEditorContent('question-text');
            if (!questionText) {
                alert('Question text is required.');
                return;
            }

            const questionId = $('#question-id').val();
            const sectionId = $('#question-section-id').val();
            const questionType = $('#question-type').val();

            let answers = [];
            $('#answers-container .answer-item').each(function () {
                const $item = $(this);
                const editorId = $item.find('.answer-text').attr('id');
                const answerText = getEditorContent(editorId);
                const answerPoints = $item.find('.answer-points').val();
                const answerId = $item.data('answer-id');
                answers.push({ id: answerId, text: answerText, points: answerPoints });
            });

            const section = quizData.sections.find(s => s.id == sectionId);

            if (questionId) {
                // Editing existing question
                const question = section.questions.find(q => q.id == questionId);
                question.text = questionText;
                question.type = questionType;
                question.answers = answers;
            } else {
                // Adding new question
                const newQuestion = {
                    id: 'new_' + new Date().getTime(),
                    text: questionText,
                    type: questionType,
                    answers: answers
                };
                section.questions.push(newQuestion);
            }

            renderTable();
            closeModal($('#question-modal'));
        });

        $('#quiz-structure-body').on('click', '.edit-question', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const questionId = $row.data('question-id');
            const sectionId = $row.data('section-id');

            const section = quizData.sections.find(s => s.id == sectionId);
            const question = section.questions.find(q => q.id == questionId);

            $('#question-id').val(question.id);
            $('#question-section-id').val(sectionId);
            
            if (tinymce.get('question-text')) {
                tinymce.get('question-text').setContent(question.text);
            } else {
                 $('#question-text').val(question.text);
            }

            $('#question-type').val(question.type);

            // Populate answers
            $('#answers-container').empty();
            question.answers.forEach(answer => {
                addAnswerField(answer);
            });

            openModal($('#question-modal'));
        });

        $('#quiz-structure-body').on('click', '.delete-question', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this question and all its answers?')) {
                const $row = $(this).closest('tr');
                const questionId = $row.data('question-id');
                const sectionId = $row.data('section-id');

                const section = quizData.sections.find(s => s.id == sectionId);
                section.questions = section.questions.filter(q => q.id != questionId);
                renderTable();
            }
        });


        // --- Answer Handling ---

        $('#add-answer-btn').on('click', function () {
            addAnswerField();
        });

        $('#answers-container').on('click', '.remove-answer-btn', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to remove this answer?')) {
                const $item = $(this).closest('.answer-item');
                const editorId = $item.find('.answer-text').attr('id');
                removeEditor(editorId);
                $item.remove();
            }
        });

        function addAnswerField(answer = {}) {
            const answerTemplate = $('#answer-template').html();
            const $newAnswer = $(answerTemplate);

            // Store the answer ID in a data attribute
            $newAnswer.data('answer-id', answer.id || 'new_' + new Date().getTime());

            if (answer.text) {
                $newAnswer.find('.answer-text').val(answer.text);
            }
            $newAnswer.find('.answer-points').val(answer.points || 0);

            $('#answers-container').append($newAnswer);
            initializeEditor($newAnswer.find('.answer-text'));
        }

        // --- Table Rendering ---

        function renderTable() {
            const $tbody = $('#quiz-structure-body');
            $tbody.empty();

            const sectionTemplate = $('#section-row-template').html();
            const questionTemplate = $('#question-row-template').html();

            quizData.sections.forEach(section => {
                let sectionHtml = sectionTemplate
                    .replace(/__SECTION_ID__/g, section.id)
                    .replace(/__SECTION_TITLE__/g, section.title);
                $tbody.append(sectionHtml);

                section.questions.forEach(question => {
                    // Sanitize and truncate for display
                    const questionTextPreview = $('<div>').html(question.text).text();
                    const truncatedText = questionTextPreview.length > 100 ? questionTextPreview.substring(0, 100) + '...' : questionTextPreview;

                    let questionHtml = questionTemplate
                        .replace(/__QUESTION_ID__/g, question.id)
                        .replace(/__SECTION_ID__/g, section.id)
                        .replace(/__QUESTION_TEXT__/g, truncatedText);

                    $tbody.append(questionHtml);
                });
            });
        }

        // --- Form Submission ---

        $('#add-quiz-form').on('submit', function (e) {
            // Clear previous hidden inputs
            $(this).find('.quiz-data-hidden').remove();

            // Create hidden inputs for sections, questions, and answers
            quizData.sections.forEach((section, sectionIndex) => {
                // Only add the ID input if it's an existing item (not a temporary 'new_' ID)
                if (section.id && !String(section.id).startsWith('new_')) {
                    $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][id]`, value: section.id, class: 'quiz-data-hidden' }).appendTo(this);
                }
                $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][title]`, value: section.title, class: 'quiz-data-hidden' }).appendTo(this);
                $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][content_begin]`, value: section.content_begin || '', class: 'quiz-data-hidden' }).appendTo(this);
                $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][content_end]`, value: section.content_end || '', class: 'quiz-data-hidden' }).appendTo(this);
                $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][order]`, value: sectionIndex, class: 'quiz-data-hidden' }).appendTo(this);

                section.questions.forEach((question, questionIndex) => {
                    if (question.id && !String(question.id).startsWith('new_')) {
                        $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][id]`, value: question.id, class: 'quiz-data-hidden' }).appendTo(this);
                    }
                    $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][text]`, value: question.text, class: 'quiz-data-hidden' }).appendTo(this);
                    $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][type]`, value: question.type, class: 'quiz-data-hidden' }).appendTo(this);
                    $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][order]`, value: questionIndex, class: 'quiz-data-hidden' }).appendTo(this);

                    question.answers.forEach((answer, answerIndex) => {
                        if (answer.id && !String(answer.id).startsWith('new_')) {
                            $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][answers][${answerIndex}][id]`, value: answer.id, class: 'quiz-data-hidden' }).appendTo(this);
                        }
                        $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][answers][${answerIndex}][text]`, value: answer.text, class: 'quiz-data-hidden' }).appendTo(this);
                        $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][answers][${answerIndex}][points]`, value: answer.points, class: 'quiz-data-hidden' }).appendTo(this);
                        $('<input>').attr({ type: 'hidden', name: `sections[${sectionIndex}][questions][${questionIndex}][answers][${answerIndex}][order]`, value: answerIndex, class: 'quiz-data-hidden' }).appendTo(this);
                    });
                });
            });
        });

        // --- Bulk Action Confirmation ---
        $('#doaction, #doaction2').on('click', function(e) {
            const $select = $(this).siblings('select[name="action"], select[name="action2"]');
            if ($select.val() === 'trash') {
                const numSelected = $('input[name="quiz_ids[]"]:checked').length;
                if (numSelected > 0) {
                    if (!confirm(`Are you sure you want to delete the ${numSelected} selected quiz(zes)? This will also delete all of their sections, questions, and answers.`)) {
                        e.preventDefault();
                    }
                }
            }
        });

    });

})(jQuery);