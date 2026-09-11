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
    //const prevBtn = $('#prev-btn');
    const nextBtn = $('#next-btn');
    const submitBtn = $('#submit-btn');
    const quizSpinner = $('#quiz-spinner');

    //let steps = [];
    //let currentStepIndex = 0;

    // Add these new state variables:
    let currentView = 'intro'; // Can be: 'intro', 'section_begin', 'question', 'section_end'
    let currentSectionIndex = 0;
    let currentQuestionIndex = 0;
    const userAnswers = {};
    let answerMap = {};
    const jumpHistory = []; // Stack to track jumps

    function findLastMainSectionIndex() {
        for (let i = assessmentQuizData.sections.length - 1; i >= 0; i--) {
            if (assessmentQuizData.sections[i].section_type === 'main') {
                return i;
            }
        }
        return -1; // No more main sections
    }
    const lastMainSectionIndex = findLastMainSectionIndex();

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

    // Renders the section titles (e.g., "Section 1 | Section 2")
    function renderSectionTitles() {
        quizSectionProgressBar.empty();
        const sectionList = $('<ul class="quiz-progress-list"></ul>');
        
        // Filter sections to only include those with type 'main'
        const mainSections = assessmentQuizData.sections.filter(section => section.section_type === 'main');

        mainSections.forEach((section, index) => {
            const sectionItem = $(`<li></li>`)
                .addClass('quiz-progress-item')
                .attr('data-section-id', section.id)
                .text(section.title);
            
            sectionList.append(sectionItem);

            if (index < mainSections.length - 1) {
                sectionList.append($('<li class="quiz-progress-separator">|</li>'));
            }
        });
        quizSectionProgressBar.append(sectionList);
    }

    // Highlights the current section in the title list
    function updateSectionHighlight() {
        $('.quiz-progress-item').removeClass('active');
        const currentSection = assessmentQuizData.sections[currentSectionIndex];
        
        if (currentSection) {
            let sectionItem = $(`.quiz-progress-item[data-section-id="${currentSection.id}"]`);
    
            // If the section is not in the progress bar, add it in the correct order.
            if (sectionItem.length === 0) {
                const sectionList = $('.quiz-progress-list');
                if (sectionList.length > 0) {
                    const newSectionItem = $(`<li></li>`)
                        .addClass('quiz-progress-item')
                        .attr('data-section-id', currentSection.id)
                        .text(currentSection.title);
                    
                    const currentSectionOrderIndex = assessmentQuizData.sections.findIndex(s => s.id == currentSection.id);
                    let inserted = false;
    
                    // Find the first section in the progress bar that should come *after* the new section.
                    for (let i = currentSectionOrderIndex + 1; i < assessmentQuizData.sections.length; i++) {
                        const nextSectionInOrder = assessmentQuizData.sections[i];
                        const nextSectionElement = sectionList.find(`.quiz-progress-item[data-section-id="${nextSectionInOrder.id}"]`);
    
                        if (nextSectionElement.length > 0) {
                            // We found the element to insert before.
                            const separator = $('<li class="quiz-progress-separator">|</li>');
                            nextSectionElement.before(newSectionItem);
                            newSectionItem.after(separator);
                            inserted = true;
                            break;
                        }
                    }
    
                    if (!inserted) {
                        // If we didn't find a section to insert before, it means this new section
                        // belongs at the end of the current list.
                        const separator = $('<li class="quiz-progress-separator">|</li>');
                        if (sectionList.children().length > 0) {
                            sectionList.append(separator);
                        }
                        sectionList.append(newSectionItem);
                    }
                    sectionItem = newSectionItem; // Update reference to the newly added item
                }
            }
            
            // Add active class
            if (sectionItem.length > 0) {
                sectionItem.addClass('active');
            }
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

    // Main function to render the current view
    function renderCurrentView() {
        quizBody.empty();

        // Hide all components by default
        quizHeader.hide();
        quizSectionProgressBar.hide();
        quizQuestionProgressBar.hide();
        nextBtn.hide();
        submitBtn.hide();

        updateSectionHighlight();

        switch (currentView) {
            case 'intro':
                quizHeader.show();
                $('#quiz-title').html(assessmentQuizData.title);
                $('#quiz-description').html(assessmentQuizData.description);
                nextBtn.show().prop('disabled', false).text('Start Quiz');
                break;

            case 'section_begin':
                quizSectionProgressBar.show();
                const sectionBegin = assessmentQuizData.sections[currentSectionIndex];
                if (sectionBegin && sectionBegin.section_content_begin) {
                    quizBody.html(sectionBegin.section_content_begin);
                }
                nextBtn.show().prop('disabled', false).text('Continue');
                break;

            case 'question':
                quizSectionProgressBar.show();
                quizQuestionProgressBar.show();
                
                const section = assessmentQuizData.sections[currentSectionIndex];
                const question = section.questions[currentQuestionIndex];
                
                renderQuestionProgressBar(section.questions.length, currentQuestionIndex);

                const questionContainer = $('<div class="question-container"></div>').attr('data-question-id', question.id);
                const questionText = $('<h4></h4>').html(question.question_text);
                const answersContainer = $('<div class="answers-container"></div>');

                const isMultipleChoice = question.question_type === 'multiple';
                answersContainer.addClass(isMultipleChoice ? 'is-multiple-choice' : 'is-single-choice');

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

                // --- Button Visibility ---
                const isLastMainSection = currentSectionIndex === lastMainSectionIndex;
                const isLastQuestionOfSection = currentQuestionIndex === section.questions.length - 1;
                const doesSectionEndQuiz = section.on_end === 'end_quiz';

                const isLastQuestionInQuiz = (isLastMainSection && isLastQuestionOfSection) || (doesSectionEndQuiz && isLastQuestionOfSection);

                if (question.question_type === 'multiple') {
                    const isAnswerSelected = $(`input[name="question_${question.id}"]:checked`).length > 0;
                    if (isLastQuestionInQuiz) {
                        submitBtn.show().prop('disabled', !isAnswerSelected);
                    } else {
                        nextBtn.show().prop('disabled', !isAnswerSelected).text('Next');
                    }
                } else { // For radio, button is hidden, auto-advance will handle it
                    nextBtn.hide();
                    submitBtn.hide();
                }
                break;

            case 'section_end':
                quizSectionProgressBar.show();
                const sectionEnd = assessmentQuizData.sections[currentSectionIndex];
                if (sectionEnd && sectionEnd.section_content_end) {
                    quizBody.html(sectionEnd.section_content_end);
                }
                
                const isLastMain = currentSectionIndex === lastMainSectionIndex;
                // This is the key change: check if the current section is set to end the quiz.
                const shouldEndQuiz = sectionEnd.on_end === 'end_quiz';

                if (isLastMain || shouldEndQuiz) {
                    submitBtn.show().prop('disabled', false);
                } else {
                    nextBtn.show().prop('disabled', false).text('Next Section');
                }
                break;
        }
    }

    function saveCurrentAnswer() {
        if (currentView !== 'question') return;

        const section = assessmentQuizData.sections[currentSectionIndex];
        if (!section || !section.questions) return;

        const question = section.questions[currentQuestionIndex];
        if (!question) return;

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

    function initializeResultActions() {
        const $actionsPanel = $('#actions-panel');
        if (!$actionsPanel.length) {
            return; // Exit if the actions panel isn't on the page
        }
    
        const submissionId = $actionsPanel.data('submission-id');
        const $emailBtn = $('#send-result-email-btn');
        const $emailInput = $('#result-email-input');
        const $statusMsg = $('.email-status-message');
    
        // Use .off().on() to prevent binding multiple click handlers
        $emailBtn.off('click').on('click', function() {
            const email = $emailInput.val();
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                $statusMsg.text('Please enter a valid email address.').css('color', 'red');
                return;
            }
    
            const $this = $(this);
            $this.prop('disabled', true).text('Sending...');
            $statusMsg.text('').css('color', '');
    
            $.ajax({
                url: assessmentQuizAjax.ajax_url,
                type: 'POST',
                data: {
                    action: assessmentQuizAjax.email_action,
                    nonce: assessmentQuizAjax.nonce,
                    submission_id: submissionId,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        $statusMsg.text(response.data.message).css('color', 'green');
                        $emailInput.val(''); // Clear input on success
                    } else {
                        $statusMsg.text(response.data.message).css('color', 'red');
                    }
                },
                error: function() {
                    $statusMsg.text('An unexpected error occurred. Please try again.').css('color', 'red');
                },
                complete: function() {
                    $this.prop('disabled', false).text('Send Email');
                }
            });
        });
    }

    function displayResults() {
        calculateScores();

        quizHeader.hide();
        quizSectionProgressBar.hide();
        quizQuestionProgressBar.hide();
        quizBody.hide();
        //prevBtn.hide();
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
        renderSectionTitles();
        currentView = 'intro';
        renderCurrentView();
    }

    function findNextMainSectionIndex(startIndex) {
        for (let i = startIndex + 1; i < assessmentQuizData.sections.length; i++) {
            if (assessmentQuizData.sections[i].section_type === 'main') {
                return i;
            }
        }
        return -1; // No more main sections
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
        
        const question = assessmentQuizData.sections[currentSectionIndex].questions[currentQuestionIndex];
        if (question.question_type === 'multiple') {
            // Re-render to update button state
            renderCurrentView();
        }

        if ($this.is(':radio')) {
            setTimeout(function() {
                const section = assessmentQuizData.sections[currentSectionIndex];
                const isLastQuestionInQuiz = (currentSectionIndex === lastMainSectionIndex) && 
                                             (currentQuestionIndex === section.questions.length - 1);
                
                const selectedAnswerId = $this.val();
                const answer = answerMap[selectedAnswerId];
                const hasJump = answer && answer.jump_to_section_id;

                if (isLastQuestionInQuiz && !hasJump) {
                    submitBtn.trigger('click');
                } else {
                    nextBtn.trigger('click');
                }
            }, 300);
        }
    });

    // This function determines the next view based on current state and branching rules.
    function findNextStep() {
        // Default: linear progression
        let nextView = null;
        let nextSectionIndex = currentSectionIndex;
        let nextQuestionIndex = currentQuestionIndex;

        // Check for branching first (from a question view)
        if (currentView === 'question') {
            const question = assessmentQuizData.sections[currentSectionIndex].questions[currentQuestionIndex];
            const selectedAnswerId = $(`input[name="question_${question.id}"]:checked`).val();
            
            if (selectedAnswerId) {
                const answer = answerMap[selectedAnswerId];
                //console.log("Debugging jump functionality. Selected answer:", answer);
                // Note: The backend needs to add 'jump_to_section_id' to the answer data for this to work.
                if (answer && answer.jump_to_section_id) {
                    const jumpToSectionId = answer.jump_to_section_id; // Use string for comparison
                    const targetSectionIndex = assessmentQuizData.sections.findIndex(s => s.id == jumpToSectionId);
                    const currentSection = assessmentQuizData.sections[currentSectionIndex];

                    if (targetSectionIndex !== -1 && currentSection.section_type === 'main') {
                        // Store where we are jumping from
                        jumpHistory.push({
                            sectionIndex: currentSectionIndex,
                            questionIndex: currentQuestionIndex
                        });

                        // Found a jump target!
                        nextSectionIndex = targetSectionIndex;
                        nextQuestionIndex = 0; // Start at the first question of the target section
                        
                        if (assessmentQuizData.sections[nextSectionIndex].section_content_begin) {
                            nextView = 'section_begin';
                        } else {
                            nextView = 'question';
                        }
                        
                        currentView = nextView;
                        currentSectionIndex = nextSectionIndex;
                        currentQuestionIndex = nextQuestionIndex;
                        return; // Branching logic complete
                    }
                }
            }
        }

        // If no branching, proceed linearly
        switch (currentView) {
            case 'intro':
                nextSectionIndex = 0;
                if (assessmentQuizData.sections[0] && assessmentQuizData.sections[0].section_content_begin) {
                    nextView = 'section_begin';
                } else {
                    nextView = 'question';
                    nextQuestionIndex = 0;
                }
                break;

            case 'section_begin':
                nextView = 'question';
                nextQuestionIndex = 0;
                break;

            case 'question':
                const section = assessmentQuizData.sections[currentSectionIndex];
                if (currentQuestionIndex < section.questions.length - 1) {
                    nextView = 'question';
                    nextQuestionIndex++;
                } else {
                    // End of a section
                    if (section.section_type === 'jump') {
                        // Handle the end of a jump section based on its 'on_end' property
                        switch (section.on_end) {
                            case 'jump_to_section':
                                const targetSectionId = section.on_end_jump_to;
                                const targetSectionIndex = assessmentQuizData.sections.findIndex(s => s.id == targetSectionId);
                                if (targetSectionIndex !== -1) {
                                    nextSectionIndex = targetSectionIndex;
                                    if (assessmentQuizData.sections[nextSectionIndex].section_content_begin) {
                                        nextView = 'section_begin';
                                    } else {
                                        nextView = 'question';
                                        nextQuestionIndex = 0;
                                    }
                                } else {
                                    // Fallback if jump target is invalid, just end the quiz
                                    submitBtn.trigger('click');
                                    return;
                                }
                                break;
                            case 'end_quiz':
                                submitBtn.trigger('click');
                                return; // Stop further processing
                            case 'return':
                            default: // Default to return
                                if (jumpHistory.length > 0) {
                                    const returnLocation = jumpHistory.pop();
                                    nextSectionIndex = returnLocation.sectionIndex;
                                    nextQuestionIndex = returnLocation.questionIndex + 1;
                                    const returnSection = assessmentQuizData.sections[nextSectionIndex];

                                    if (nextQuestionIndex >= returnSection.questions.length) {
                                        // We returned to the end of a main section
                                        if (returnSection.section_content_end) {
                                            nextView = 'section_end';
                                        } else {
                                            // Move to the next main section
                                            const nextMainSectionIndex = findNextMainSectionIndex(nextSectionIndex);
                                            if (nextMainSectionIndex !== -1) {
                                                nextSectionIndex = nextMainSectionIndex;
                                                if (assessmentQuizData.sections[nextSectionIndex].section_content_begin) {
                                                    nextView = 'section_begin';
                                                } else {
                                                    nextView = 'question';
                                                    nextQuestionIndex = 0;
                                                }
                                            } else {
                                                // No more main sections, end the quiz
                                                submitBtn.trigger('click');
                                                return;
                                            }
                                        }
                                    } else {
                                        nextView = 'question';
                                    }
                                } else {
                                    // If there's no history, something is wrong. End the quiz as a fallback.
                                    submitBtn.trigger('click');
                                    return;
                                }
                                break;
                        }
                    } else {
                        // Normal progression at the end of a 'main' section
                        if (section.section_content_end) {
                            nextView = 'section_end';
                        } else {
                            const nextMainSectionIndex = findNextMainSectionIndex(currentSectionIndex);
                            if (nextMainSectionIndex !== -1) {
                                nextSectionIndex = nextMainSectionIndex;
                                if (assessmentQuizData.sections[nextSectionIndex].section_content_begin) {
                                    nextView = 'section_begin';
                                } else {
                                    nextView = 'question';
                                    nextQuestionIndex = 0;
                                }
                            } else {
                                submitBtn.trigger('click');
                                return;
                            }
                        }
                    }
                }
                break;

            case 'section_end':
                const nextMainSectionIndex = findNextMainSectionIndex(currentSectionIndex);
                if (nextMainSectionIndex !== -1) {
                    nextSectionIndex = nextMainSectionIndex;
                    if (assessmentQuizData.sections[nextSectionIndex].section_content_begin) {
                        nextView = 'section_begin';
                    } else {
                        nextView = 'question';
                        nextQuestionIndex = 0;
                    }
                } else {
                    submitBtn.trigger('click');
                    return;
                }
                break;
        }

        // Update state
        currentView = nextView;
        currentSectionIndex = nextSectionIndex;
        currentQuestionIndex = nextQuestionIndex;
    }

    nextBtn.on('click', function() {
        saveCurrentAnswer();
        findNextStep();
        renderCurrentView();
        quizContainer[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    submitBtn.on('click', function() {
        saveCurrentAnswer(); // Ensure the last answer is saved

        $(this).prop('disabled', true).text('Calculating...');
        $('#quiz-navigation').hide();
        quizSpinner.css('display', 'flex');
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
                quizSpinner.hide();
                if (response.success) {
                    // On success, replace the quiz form with the result HTML
                    var quizContainer = $('#assessment-quiz-container');
                    if (quizContainer.length) {
                        quizContainer.html(response.data.result_html);
                        initializeResultActions(); // Initialize actions for the new result content
                        
                        // Optional: Scroll to the top of the results
                        $('html, body').animate({
                            scrollTop: quizContainer.offset().top
                        }, 500);
                    } else {
                         // Fallback if the container is not found
                        alert('Quiz submitted successfully! Check your results.');
                    }
                } else {
                    // Display error message
                    $('#quiz-submission-feedback').html('<p>Error: ' + response.data.message + '</p>').show();
                    submitBtn.prop('disabled', false);
                    $('#quiz-navigation').show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                quizSpinner.hide();
                //console.error('A server error occurred while saving quiz submission.');
                // Display error message
                //$('#quiz-submission-feedback').html('<p>Error: ' + response.data.message + '</p>').show();
                $('#quiz-submission-feedback').html('<p>An unexpected error occurred. Please try again.</p>').show();
                console.error("AJAX Error:", textStatus, errorThrown);
                submitBtn.prop('disabled', false);
                $('#quiz-navigation').show();
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

    // Initial check in case the page loads directly with results (e.g., a shared link)
    initializeResultActions();
});