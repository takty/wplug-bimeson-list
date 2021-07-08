<?php
/**
 *
 * The Template for Literature Static Pages
 *
 * Template Name: Literatures
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


get_header();
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
<?php
while ( have_posts() ) : the_post();
	get_template_part( 'template-parts/content', 'page' );
	\wplug\bimeson_list\Bimeson::get_instance()->the_list_section();
endwhile;
?>
		</main>
	</div>
<?php
get_footer();
