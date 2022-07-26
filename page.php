<?php get_header() ?>

<main>


	<?php
		//$itunes = new Itunes('https://itunes.apple.com/');
		//var_dump($itunes->importPodcasts(1324));

		//Poddar::handleImport(1324);

		var_dump(as_get_scheduled_actions());
	?>

	<?php get_template_part('modules/single-page') ?>

	<?php if (!post_password_required()) : ?>
		<?php comments_template('/modules/comments.php') ?>
		<?php comment_form() ?>
		<?php # Sleek\Modules\render_flexible('flexible_modules') ?>
	<?php endif ?>

</main>

<?php get_sidebar() ?>
<?php get_footer() ?>
