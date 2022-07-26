<?php  ?>

<?php
	$parentId = get_queried_object()->parent !== 0 ? get_queried_object()->parent : get_queried_object()->term_id;

	if (($term = get_term($parentId)) and !is_wp_error($term)) {
		$parent = $term;
	}
?>

<section id="archive" class="container">

	<header>

		<?php if ($image = Sleek\ArchiveMeta\get_the_archive_image('large')) : ?>
			<figure>
				<?php echo $image ?>
			</figure>
		<?php endif ?>

		<?php if (isset($parent->name)) : ?>
			<p class="text--kicker"><?php echo $parent->name ?></p>
		<?php endif ?>

		<h1><?php the_archive_title() ?></h1>

		<?php the_archive_description() ?>

	</header>

	<?php
		$pt = Sleek\Utils\get_current_post_type();
		$tax = $pt === 'post' ? 'category' : $pt . '_category';
		$list = wp_list_categories(['show_option_all' => false, 'taxonomy' => $tax, 'title_li' => false, 'echo' => false, 'depth' => 1, 'parent' => $parent->term_id]);

		if ($list) {
			$all = '<li class="cat-item-all"><a href="' . ($parent ? get_term_link($parent) : get_post_type_archive_link($pt)) . '">' . __('All', 'sleek') . '</a></li>';
			$list = $all . $list;

			if (strpos($list, 'active') == false) {
				$list = str_replace('cat-item-all', 'active', $list);
			}
		}
	?>
	<?php if ($list) : ?>
		<nav>
			<ul><?php echo $list ?></ul>
	</nav>
	<?php endif ?>

	<?php if (have_posts()) : ?>
		<div class="grid--2 tablet:grid--3 laptop:grid--5">
			<?php while (have_posts()) : the_post() ?>
				<?php get_template_part('modules/post', get_post_type()) ?>
			<?php endwhile ?>
		</div>

		<footer>

			<?php the_posts_pagination() ?>

		</footer>
	<?php else : ?>
		<p><strong><?php _e('Sorry, nothing was found here.', 'sleek') ?></strong></p>
	<?php endif ?>

</section>