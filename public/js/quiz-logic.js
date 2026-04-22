jQuery(document).ready(function($) {
    if (typeof assessmentQuizData === 'undefined') {
        console.error('Quiz data is not available.');
        $('#assessment-quiz-container').html('<p>Error: Quiz data could not be loaded.</p>');
        return;
    }

    // Constants for quiz elements
    const quizContainer = $('#assessment-quiz-container');
    const quizHeader = $('#quiz-header');
    const quizSectionProgressBar = $('#quiz-section-progress-bar');
    const quizQuestionProgressBar = $('#quiz-question-progress-bar');
    const quizBody = $('#quiz-body');
    const prevBtn = $('#prev-btn');
    const nextBtn = $('#next-btn');
    const submitBtn = $('#submit-btn');

    let steps = [];
    let currentStepIndex = 0;
    const userAnswers = {};

    // Build the sequence of steps for the quiz
    function buildSteps() {
        steps.push({ type: 'intro', sectionId: null });

        // First, calculate the total number of questions in the entire quiz
        let totalQuizQuestions = 0;
        assessmentQuizData.sections.forEach(section => {
            totalQuizQuestions += (section.questions || []).length;
        });

        let cumulativeQuestionIndex = 0;
        assessmentQuizData.sections.forEach(section => {
            const questions = section.questions || [];
            
            if (section.section_content_begin) {
                steps.push({
                    type: 'section_begin',
                    data: section,
                    sectionId: section.id,
                    progress: {
                        current: cumulativeQuestionIndex - 1,
                        total: totalQuizQuestions
                    }
                });
            }

            questions.forEach((question, index) => {
                steps.push({
                    type: 'question',
                    data: question,
                    sectionId: section.id,
                    progress: {
                        current: cumulativeQuestionIndex + index,
                        total: totalQuizQuestions
                    }
                });
            });
            
            cumulativeQuestionIndex += questions.length;

            if (section.section_content_end) {
                steps.push({
                    type: 'section_end',
                    data: section,
                    sectionId: section.id,
                    progress: {
                        current: cumulativeQuestionIndex - 1,
                        total: totalQuizQuestions
                    }
                });
            }
        });
    }

    // Renders the section titles (e.g., "Section 1 | Section 2")
    function renderSectionTitles() {
        quizSectionProgressBar.empty();
        const sectionList = $('<ul class="quiz-progress-list"></ul>');
        assessmentQuizData.sections.forEach((section, index) => {
            const sectionItem = $(`<li></li>`)
                .addClass('quiz-progress-item')
                .attr('data-section-id', section.id)
                .text(section.title);
            
            sectionList.append(sectionItem);

            if (index < assessmentQuizData.sections.length - 1) {
                sectionList.append($('<li class="quiz-progress-separator">|</li>'));
            }
        });
        quizSectionProgressBar.append(sectionList);
    }

    // Highlights the current section in the title list
    function updateSectionHighlight() {
        $('.quiz-progress-item').removeClass('active');
        const currentStep = steps[currentStepIndex];
        if (currentStep.sectionId) {
            $(`.quiz-progress-item[data-section-id="${currentStep.sectionId}"]`).addClass('active');
        }
    }

    // Renders the granular question-by-question progress bar
    function renderQuestionProgressBar(total, current) {
        quizQuestionProgressBar.empty();
        if (total > 0) {
            const container = $('<div class="progress-bar-container"></div>');
            for (let i = 0; i < total; i++) {
                const segment = $('<div class="progress-bar-segment"></div>');
                if (i <= current) {
                    segment.addClass('active');
                }
                container.append(segment);
            }
            quizQuestionProgressBar.append(container);
        }
    }

    // Main function to render a step
    function renderStep(index) {
        const step = steps[index];
        quizBody.empty();

        quizHeader.hide();
        quizSectionProgressBar.hide();
        quizQuestionProgressBar.hide();

        if (step.type === 'intro') {
            quizHeader.show();
            $('#quiz-title').html(assessmentQuizData.title);
            $('#quiz-description').html(assessmentQuizData.description);
        } else {
            quizSectionProgressBar.show();
            quizQuestionProgressBar.show();
            updateSectionHighlight();
            
            renderQuestionProgressBar(step.progress.total, step.progress.current);

            if (step.type === 'question') {
                const question = step.data;
                const questionContainer = $('<div class="question-container"></div>').attr('data-question-id', question.id);
                const questionText = $('<h4></h4>').html(question.question_text);
                const answersContainer = $('<div class="answers-container"></div>');

                const isMultipleChoice = question.question_type === 'multiple';
                if (isMultipleChoice) {
                    answersContainer.addClass('is-multiple-choice');
                } else {
                    answersContainer.addClass('is-single-choice');
                }

                // Check if any answer contains an image
                const hasImages = question.answers.some(answer => answer.answer_text.includes('<img'));
                if (hasImages) {
                    answersContainer.addClass('has-image-options');
                }

                question.answers.forEach(answer => {
                    const label = $('<label></label>');
                    const inputType = isMultipleChoice ? 'checkbox' : 'radio';
                    const inputName = `question_${question.id}`;
                    const input = $(`<input type="${inputType}" name="${inputName}">`).val(answer.id);

                    if (isMultipleChoice) {
                        if (userAnswers[question.id] && userAnswers[question.id].includes(answer.id)) {
                            input.prop('checked', true);
                            label.addClass('answer-selected');
                        }
                    } else {
                        if (userAnswers[question.id] && userAnswers[question.id] == answer.id) {
                            input.prop('checked', true);
                            //if (answersContainer.hasClass('has-image-options')) {
                            label.addClass('answer-selected');
                            //}
                        }
                    }
                    
                    label.append(input).append($('<span></span>').html(" " + answer.answer_text));
                    answersContainer.append(label);
                });

                questionContainer.append(questionText).append(answersContainer);
                quizBody.append(questionContainer);
            } else { // section_begin or section_end
                const contentKey = step.type === 'section_begin' ? 'section_content_begin' : 'section_content_end';
                quizBody.html(step.data[contentKey]);
            }
        }

        updateButtonVisibility();
    }

    function updateButtonVisibility() {
        prevBtn.toggle(currentStepIndex > 0);
        const isLastStep = currentStepIndex === steps.length - 1;
        nextBtn.toggle(!isLastStep);
        submitBtn.toggle(isLastStep);

        const currentStep = steps[currentStepIndex];
        if (currentStep.type === 'question') {
            const question = currentStep.data;
            const isAnswerSelected = $(`input[name="question_${question.id}"]:checked`).length > 0;
            nextBtn.prop('disabled', !isAnswerSelected);
            if (isLastStep) {
                submitBtn.prop('disabled', !isAnswerSelected);
            }
        } else {
            nextBtn.prop('disabled', false);
        }
    }

    function saveCurrentAnswer() {
        const currentStep = steps[currentStepIndex];
        if (currentStep.type === 'question') {
            const question = currentStep.data;
            const isMultipleChoice = question.question_type === 'multiple';
            const inputName = `question_${question.id}`;

            if (isMultipleChoice) {
                userAnswers[question.id] = $(`input[name="${inputName}"]:checked`).map(function() {
                    return $(this).val();
                }).get();
            } else {
                const selectedAnswer = $(`input[name="${inputName}"]:checked`).val();
                if (selectedAnswer) {
                    userAnswers[question.id] = selectedAnswer;
                }
            }
        }
    }

    function initQuiz() {
        buildSteps();
        renderSectionTitles();
        renderStep(currentStepIndex);
    }

    // Event Listeners
    quizBody.on('click', '.answers-container label', function(e) {
        // Don't interfere with clicks directly on inputs or links inside labels
        if ($(e.target).is('input, a, a *')) {
            return;
        }

        e.preventDefault(); // Prevent the browser's default label behavior
        
        const $label = $(this);
        const $input = $label.find('input');

        if ($input.is(':radio')) {
            // If it's already checked, do nothing.
            if ($input.prop('checked')) {
                return;
            }
            // Check the radio and trigger change to apply styles and update state
            $input.prop('checked', true).trigger('change');
        } else if ($input.is(':checkbox')) {
            // Toggle the checkbox and trigger change
            $input.prop('checked', !$input.prop('checked')).trigger('change');
        }
    });

    // Event Listeners
    quizBody.on('change', '.answers-container input', function() {
        const $this = $(this);
        const $answersContainer = $this.closest('.answers-container');

        //if ($answersContainer.hasClass('has-image-options')) {
        if ($this.is(':radio')) {
            // For single-choice, remove selection from all labels in the group
            // and add it to the currently selected one.
            $answersContainer.find('label').removeClass('answer-selected');
            $this.closest('label').addClass('answer-selected');
        } else if ($this.is(':checkbox')) {
            // For multiple-choice, toggle the selection class.
            $this.closest('label').toggleClass('answer-selected', $this.is(':checked'));
        }
        //}

        updateButtonVisibility();
    });

    nextBtn.on('click', function() {
        saveCurrentAnswer();
        if (currentStepIndex < steps.length - 1) {
            currentStepIndex++;
            renderStep(currentStepIndex);
        }
    });

    prevBtn.on('click', function() {
        saveCurrentAnswer();
        if (currentStepIndex > 0) {
            currentStepIndex--;
            renderStep(currentStepIndex);
        }
    });

    submitBtn.on('click', function() {
        saveCurrentAnswer();
        
        $(this).prop('disabled', true).text('Submitting...');

        $.ajax({
            url: assessmentQuizAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'submit_assessment_quiz',
                nonce: assessmentQuizAjax.nonce,
                quiz_id: assessmentQuizData.id,
                answers: userAnswers
            },
            success: function(response) {
                if (response.success) {
                    const resultHtml = `
                        <div class="quiz-result">
                            <h2></h2>
                            <p>Your score: ${response.data.score}</p>
                            <div></div>
                        </div>
                    `;
                    quizContainer.html(resultHtml);
                    quizContainer.find('h2').html(response.data.title);
                    quizContainer.find('.quiz-result div').html(response.data.report);
                } else {
                    alert('An error occurred: ' + (response.data ? response.data.message : 'Unknown error'));
                    submitBtn.prop('disabled', false).text('Submit');
                }
            },
            error: function() {
                alert('A server error occurred. Please try again.');
                submitBtn.prop('disabled', false).text('Submit');
            }
        });
    });

    initQuiz();
});