<?php $podcast = new Sleek\PostTypes\Podcast; ?>
<article class="post--<?php echo get_post_type() ?>">

		<?php if ($image = $podcast->getImage($post->ID, 300, 75)) : ?>
			<figure class="img--shine">
				<a href="<?php the_permalink() ?>">
					<img src="<?php echo $image ?>" width="170" height="170" loading="lazy" alt="<?php the_title() ?>">
				</a>
			</figure>
		<?php endif ?>

		<h3>
			<a href="<?php the_permalink() ?>">
				<?php the_title() ?>
			</a>
		</h3>

		<p>
			<?php if (($terms = get_the_terms($post->ID, (get_post_type() === 'post' ? 'category' : get_post_type() . '_category'))) and !is_wp_error($terms)) : ?>
				<?php echo implode(', ', array_map(function ($term) {
					return '<a href="' . get_term_link($term) . '">' . $term->name . '</a>';
				}, $terms)) ?>
			<?php endif ?>
		</p>

</article>