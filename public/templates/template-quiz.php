<?php
/**
 * Template Name: Quiz Template
 *
 * This is the template for displaying the assessment quiz.
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php
        // Start the loop.
        while ( have_posts() ) : the_post();

            // The content should contain the [assessment_quiz id="..."] shortcode
            the_content();

        // End the loop.
        endwhile;
        ?>
    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>