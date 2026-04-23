<?php
/**
 * Template Name: Quiz Template
 *
 * @package   Assessment_Quiz
 * @author    Satria Faestha
 * @license   GPL-2.0+
 * @link      https://www.example.com/
 */

get_header(); ?>

<?php 
    while (have_posts()): the_post();
?>

<?php 
    $snowlake_redux_demo = get_option('redux_demo');
    if(isset($snowlake_redux_demo['blog_image']['url']) && $snowlake_redux_demo['blog_image']['url'] != ''){?>
        <div class="breadcrumb-area breadcrumb-bg pt-260 pb-265" style="background-image:url(<?php echo esc_url($snowlake_redux_demo['blog_image']['url']);?>)">
    <?php }else{?>     
        <div class="breadcrumb-area breadcrumb-bg pt-260 pb-265" style="background-image:url(<?php echo (get_template_directory_uri().'/assets/img/bg/page-title-bg.jpg');?>)">   
    <?php } ?>        
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="breadcrumb-text text-center">
                            <h1><?php the_title();?></h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>
  <!-- Page Breadcrumbs End -->

  <!-- Main Body Content Start -->
  <section class="blog-area pt-120 pb-80">
        <div class="container">
            <div class="row" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <div class="col-lg-12">
                    <article class="postbox post format-image mb-40">
                        <div class="postbox__text bg-none">
                            <div class="post-text mb-20 page-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                        <?php 
                        // If comments are open or we have at least one comment, load up the comment template.
                        if ( comments_open() || get_comments_number() ) :
                            comments_template();
                        endif;
                        ?>
                    </article>
                </div>
            </div>
        </div>
    </section> 

<?php endwhile; // End of the loop. ?>

<?php
get_footer();
?>