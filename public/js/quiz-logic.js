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
    const quizResults = $('#quiz-results');
    const prevBtn = $('#prev-btn');
    const nextBtn = $('#next-btn');
    const submitBtn = $('#submit-btn');

    let steps = [];
    let currentStepIndex = 0;
    const userAnswers = {};
    let answerMap = {};

    let categoryScores = {};
    let sortedCategoryIds = [];
    let currentResultIndex = 0;

    function buildAnswerMap() {
        assessmentQuizData.sections.forEach(section => {
            (section.questions || []).forEach(question => {
                (question.answers || []).forEach(answer => {
                    answerMap[answer.id] = answer;
                });
            });
        });
    }

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
                answersContainer.addClass(isMultipleChoice ? 'is-multiple-choice' : 'is-single-choice');

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

                    const answerData = userAnswers[question.id];
                    if (answerData) {
                        if (isMultipleChoice && Array.isArray(answerData)) {
                            if (answerData.some(a => a.answerId == answer.id)) {
                                input.prop('checked', true);
                                label.addClass('answer-selected');
                            }
                        } else if (!isMultipleChoice && typeof answerData === 'object') {
                            if (answerData.answerId == answer.id) {
                                input.prop('checked', true);
                                label.addClass('answer-selected');
                            }
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
        const isLastQuestionStep = !steps.slice(currentStepIndex + 1).some(step => step.type === 'question');
        
        nextBtn.toggle(!isLastQuestionStep && currentStepIndex < steps.length - 1);
        submitBtn.toggle(isLastQuestionStep && currentStepIndex > 0);

        const currentStep = steps[currentStepIndex];
        if (currentStep.type === 'question') {
            const question = currentStep.data;
            const isAnswerSelected = $(`input[name="question_${question.id}"]:checked`).length > 0;
            nextBtn.prop('disabled', !isAnswerSelected);
            submitBtn.prop('disabled', !isAnswerSelected);
        } else {
            nextBtn.prop('disabled', false);
        }
    }

    function saveCurrentAnswer() {
        const currentStep = steps[currentStepIndex];
        if (currentStep.type === 'question') {
            const question = currentStep.data;
            const inputName = `question_${question.id}`;
            const categoryId = question.category_id;

            const findAnswerData = (answerId) => answerMap[answerId] || null;

            if (question.question_type === 'multiple') {
                userAnswers[question.id] = $(`input[name="${inputName}"]:checked`).map(function() {
                    const answerId = $(this).val();
                    const answerData = findAnswerData(answerId);
                    return {
                        answerId: answerId,
                        points: answerData ? parseInt(answerData.points) : 0,
                        categoryId: categoryId
                    };
                }).get();
            } else {
                const selectedAnswerId = $(`input[name="${inputName}"]:checked`).val();
                if (selectedAnswerId) {
                    const answerData = findAnswerData(selectedAnswerId);
                    userAnswers[question.id] = {
                        answerId: selectedAnswerId,
                        points: answerData ? parseInt(answerData.points) : 0,
                        categoryId: categoryId
                    };
                } else {
                    delete userAnswers[question.id];
                }
            }
        }
    }

    function calculateScores() {
        Object.keys(assessmentQuizData.categories).forEach(catId => {
            categoryScores[catId] = 0;
        });

        for (const questionId in userAnswers) {
            const answerData = userAnswers[questionId];
            if (Array.isArray(answerData)) { // Multiple choice
                answerData.forEach(ans => {
                    if (ans.categoryId && categoryScores.hasOwnProperty(ans.categoryId)) {
                        categoryScores[ans.categoryId] += ans.points;
                    }
                });
            } else { // Single choice
                if (answerData.categoryId && categoryScores.hasOwnProperty(answerData.categoryId)) {
                    categoryScores[answerData.categoryId] += answerData.points;
                }
            }
        }
    }

    function renderResultPage(index) {
        const categoryId = sortedCategoryIds[index];
        const category = assessmentQuizData.categories[categoryId];
        const score = categoryScores[categoryId];

        let resultTier = 'high';
        if (score <= category.low_threshold) {
            resultTier = 'low';
        } else if (score <= category.medium_threshold) {
            resultTier = 'medium';
        }
        
        const resultHtml = `
            <div class="result-page" data-category-id="${categoryId}">
                <div class="result-header">
                    <h2>${category.name}</h2>
                    <p class="result-score">Your Score: ${score}</p>
                </div>
                <div class="result-content">
                    <div class="result-description">
                        <h3>Your Result (Tier: ${resultTier})</h3>
                        ${category.description}
                    </div>
                    <div class="result-focus-area">
                        <h3>${category.focus_area_title}</h3>
                        ${category.focus_area_description}
                    </div>
                    <div class="result-healing-plan">
                        <h3>Healing Plan</h3>
                        ${category.healing_plan_details}
                    </div>
                </div>
                <div class="result-navigation">
                    <button id="prev-result-btn" class="quiz-button">Previous Result</button>
                    <span class="result-nav-status">${index + 1} / ${sortedCategoryIds.length}</span>
                    <button id="next-result-btn" class="quiz-button">Next Result</button>
                </div>
            </div>
        `;

        quizResults.html(resultHtml);
        updateResultNavButtons();
    }

    function updateResultNavButtons() {
        $('#prev-result-btn').toggle(currentResultIndex > 0);
        $('#next-result-btn').toggle(currentResultIndex < sortedCategoryIds.length - 1);
    }

    function displayResults() {
        calculateScores();

        quizHeader.hide();
        quizSectionProgressBar.hide();
        quizQuestionProgressBar.hide();
        quizBody.hide();
        prevBtn.hide();
        nextBtn.hide();
        submitBtn.hide();

        quizResults.show();

        sortedCategoryIds = Object.keys(assessmentQuizData.categories).sort((a, b) => a - b);

        if (sortedCategoryIds.length > 0) {
            currentResultIndex = 0;
            renderResultPage(currentResultIndex);
        } else {
            quizResults.html('<p>There are no categorized results for this quiz.</p>');
        }
    }

    function initQuiz() {
        buildAnswerMap();
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
        
        const $input = $(this).find('input');

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

        if ($this.is(':radio')) {
            // For single-choice, remove selection from all labels in the group
            // and add it to the currently selected one.
            $answersContainer.find('label').removeClass('answer-selected');
            $this.closest('label').addClass('answer-selected');
        } else if ($this.is(':checkbox')) {
            // For multiple-choice, toggle the selection class.
            $this.closest('label').toggleClass('answer-selected', $this.is(':checked'));
        }

        saveCurrentAnswer();
        updateButtonVisibility();
    });

    nextBtn.on('click', function() {
        if (currentStepIndex < steps.length - 1) {
            currentStepIndex++;
            renderStep(currentStepIndex);
        }
    });

    prevBtn.on('click', function() {
        if (currentStepIndex > 0) {
            currentStepIndex--;
            renderStep(currentStepIndex);
        }
    });

    submitBtn.on('click', function() {
        $(this).prop('disabled', true).text('Calculating...');
        displayResults();

        $.ajax({
            url: assessmentQuizAjax.ajax_url,
            type: 'POST',
            data: {
                action: assessmentQuizAjax.save_action,
                nonce: assessmentQuizAjax.nonce,
                quiz_id: assessmentQuizData.id,
                answers: userAnswers
            },
            success: function(response) {
                if (response.success) {
                    console.log('Quiz submission saved successfully.', response.data);
                } else {
                    console.error('Failed to save quiz submission:', response.data ? response.data.message : 'Unknown error');
                }
            },
            error: function() {
                console.error('A server error occurred while saving quiz submission.');
            }
        });
    });

    quizResults.on('click', '#next-result-btn', function() {
        if (currentResultIndex < sortedCategoryIds.length - 1) {
            currentResultIndex++;
            renderResultPage(currentResultIndex);
        }
    });

    quizResults.on('click', '#prev-result-btn', function() {
        if (currentResultIndex > 0) {
            currentResultIndex--;
            renderResultPage(currentResultIndex);
        }
    });

    initQuiz();
});