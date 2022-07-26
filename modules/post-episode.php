<?php $podcast = new Sleek\PostTypes\Podcast; ?>

<vds-media class="post--<?php echo get_post_type() ?>">
	<vds-media-sync single-playback>
		<?php $image = get_post_meta($post->ID, 'thumbnail', true) ?: $podcast->getImage($post->post_parent, 300, 75) ?>
		<?php if ($image) : ?>
			<figure class="img--shine">
				<a href="<?php the_permalink() ?>">
					<img src="<?php echo $image ?>" width="170" height="170" loading="lazy" alt="<?php the_title() ?>">
				</a>
			</figure>
		<?php endif ?>

		<header>
			<h3>
				<a href="<?php the_permalink() ?>">
					<?php the_title() ?>
				</a>
			</h3>

			<?php the_excerpt() ?>

			<time datetime="<?php echo get_the_time('Y-m-j') ?>">
				<?php echo get_the_time(get_option('date_format')) ?>
			</time>
		</header>


		<vds-audio>
			<audio src="<?php echo get_post_meta($post->ID, 'audio', true) ?>" preload="none"></audio>
		</vds-audio>

		<vds-play-button></vds-play-button>

		<vds-time-slider>
			<div class="slider-track"></div>
			<div class="slider-track fill"></div>
		</vds-time-slider>
	</vds-media-sync>
</vds-media>