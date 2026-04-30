<?php
/**
 * The template for displaying the quiz.
 *
 * This template can be overridden by copying it to yourtheme/assessment-quiz/quiz-display.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div id="assessment-quiz-container" class="assessment-quiz-container">
    <!-- Header: Displays Quiz Title and Description -->
    <div id="quiz-header">
        <h1 id="quiz-title"></h1>
        <div id="quiz-description"></div>
    </div>

    <!-- Progress Bar: Will be populated by JavaScript -->
    <div id="quiz-section-progress-bar"></div>
    <div id="quiz-question-progress-bar"></div>

    <!-- Body: Displays Steps (Intro, Sections, Questions) -->
    <div id="quiz-body">
        <!-- Quiz steps will be rendered here -->
    </div>

    <!-- Navigation: Previous, Next, and Submit Buttons -->
    <div id="quiz-navigation">
        <!--button id="prev-btn" style="display: none;">Previous</button-->
        <button id="next-btn" style="display: none;">Next</button>
        <button id="submit-btn" style="display: none;">Submit</button>
    </div>
    <!-- Spinner -->
    <div id="quiz-spinner" style="display: none;">
        <div class="spinner-container">
            <div class="fancy-spinner">
                <div class="bounce1"></div>
                <div class="bounce2"></div>
                <div class="bounce3"></div>
            </div>
            <p class="spinner-text">Processing your quiz...</p>
        </div>
    </div>
</div>