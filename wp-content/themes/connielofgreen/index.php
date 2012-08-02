<?php get_header(); ?>

		<div id="content-container">
			<section id="content" role="main">
				<div id="banner-container">
					<div id="banner">
							<div id="categories">
								<ul>
									<li><a href="#" class="banner"></a>
										<?php if ( is_active_sidebar( 'banner-widget-area' ) ) : ?>
										<ul>
											<?php dynamic_sidebar( 'banner-widget-area' ); ?>
										</ul>
									</li>
								</ul>
										<?php endif; ?>
									</li>
								</ul>
							</div><!-- #categories -->
	
							<div class="slides_container">

								<?php query_posts('post_type=banner_ads')?>
									<?php if (have_posts()) : ?>
										<?php while (have_posts()) : the_post(); ?>
											<?php if (get_field('banner_image_external_url')): ?>
												<a href="<?php the_field('banner_image_external_url'); ?>">
											<?php else: ?>
												<a href="<?php the_field('banner_ad_internal_url'); ?>">
											<?php endif; ?>
										<img src="<?php the_field('banner_image'); ?>" width="960" height="300" alt="<?php the_title(); ?>" /></a>
									<?php endwhile; endif; ?>
							</div><!-- .slides_container -->
					</div><!-- #banner -->
				</div><!-- #banner-container -->
				
				<div class="center-content">
		
				<div id="recent-articles">
					<h3>Recent Articles</h3>
				<?php if (have_posts()) : ?>
			<?php query_posts('posts_per_page=3'); ?>	
	<?php while (have_posts()) : the_post(); ?>
				
		<article>
			<div class="frontpage-article">
				
				<h2>
					<a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">	
						<?php get_short_title(); ?>		
					</a>
			</h2>
				<p><?php echo get_home_page_excerpt(); ?></p>
			</div>
		</article>
		
	<?php endwhile; ?>
		<h6><a href="<?php bloginfo('url'); ?>/blog">See all articles</a></h6>
	<?php else : ?>
				
		<article>
			<h2>No Posts Yet</h2>
			<p>check back soon...</p>
		</article>
				
<?php endif; ?>
				</div><!-- #recent-articles -->
				
				<div id="starpro-info">
					<h3>Treatment</h3>
						<div id="starpro-hzlogo">
							<img src="<?php bloginfo('template_directory'); ?>/images/logo2.png" width="117" height="149" alt="Logo2">
						</div>
					<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor.</p>
				</div>
		
			</div><!-- .center-content -->
			
			
			</section><!-- #content -->
		</div><!-- #content-container -->


<?php get_footer(); ?>
