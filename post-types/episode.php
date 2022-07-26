<?php
namespace Sleek\PostTypes;

class Episode extends PostType {
	public function init () {
		# Add columns
		add_filter('manage_episode_posts_columns', function ($cols) {
			$newCols = $cols;
			$addCols = [
				'podcast' => __('Podcast', 'sleek')
			];

			# Insert new cols
			$newCols = array_slice($newCols, 0, 2, false) + $addCols + array_slice($newCols, 2, null, false);
			return $newCols;
		});

		# Insert their values
		add_action('manage_episode_posts_custom_column', function ($col, $postId) {
			if ($col === 'podcast') {
				$parent = wp_get_post_parent_id($postId);

				if ($parent) {
					$link = get_the_permalink($parent);
					$title = get_the_title($parent);

					echo '<a href="' . $link . '">' . $title . '</a>';
				}
			}
		}, 10, 2);

		add_filter('redirect_canonical', function ($redirect_url) {
			if (is_singular('podcast')) {
				return false;
			}

			return $redirect_url;
		});

		# Make them sortable
		add_action('manage_edit-episode_sortable_columns', function ($cols) {
			$cols['podcast'] = 'podcast';
			return $cols;
		});

		# Make WP understand how to sort them
		add_action('pre_get_posts', function ($query) {
			if (is_admin()) {
				if ($query->get('orderby') == 'podcast') {
					$query->set('orderby', 'parent');
				}
			}
		});

		# Add our Podcast parent meta box
		add_action('add_meta_boxes', function () {
			add_meta_box('episode_parent', 'Podcast', [$this,'episode_attributes_meta_box'], 'episode', 'side', 'high');
		});

		# Set permalink for podcasts
		add_filter('post_type_link', function ($permalink, $post, $leavename) {
			if (
				$post->post_type != 'episode' ||
				empty($permalink) ||
				in_array($post->post_status, ['draft', 'pending', 'auto-draft'])
			) {
			 	return $permalink;
			}

			$parent = $post->post_parent;
			$parent_post = get_post($parent);
			$permalink = str_replace('%podcast%', $parent_post->post_name, $permalink);

			return $permalink;
		}, 10, 3);

		# Add our own URL strucutre and rewrite rules
		add_action('init', function () {
			add_rewrite_tag('%episode%', '([^/]+)', 'episode=');
			add_permastruct('episode', '/podcast/%podcast%/%episode%', false);
			add_rewrite_rule('^podcast/([^/]+)/page/?([0-9]{1,})/?','index.php?podcast=$matches[1]&page=$matches[2]','top');
			add_rewrite_rule('^podcast/([^/]+)/([^/]+)/?','index.php?episode=$matches[2]','top');
		}, 99);
	}

	# Get the Podcast dropdown
	public function episode_attributes_meta_box ($post) {
		$pages = wp_dropdown_pages([
			'post_type' => 'podcast',
			'selected' => $post->post_parent ?? null,
			'name' => 'parent_id',
			'show_option_none' => __('(no parent)', 'sleek_admin'),
			'sort_column' => 'menu_order, post_title',
			'echo' => 0
		]);

		if (!empty($pages)) {
			echo $pages;
		}
	}

	# Get human readable episode duration
	public function getDuration ($postId) {
		$seconds = get_post_meta($postId, 'duration', true);

		if ($seconds) {
	        $hours = floor($seconds / 3600);
	        $seconds -= ($hours * 3600);

	        $minutes = floor($seconds / 60);
	        $seconds -= ($minutes * 60);

	        $values = array(
	            _x('h', 'duration', 'sleek') => $hours,
	            _x('min', 'duration', 'sleek') => $minutes
	        );

	        $parts = array();

	        foreach ($values as $text => $value) {
	            if ($value > 0) {
	                $parts[] = $value . ' ' . $text;
	            }
	        }

	        return implode(' ', $parts);
	    }

		return false;
	}

	public function fields () {
		return [];

	}

	public function config () {
		return [
			'supports' => [
				 'title', 'editor', 'custom-fields'
			],
			'show_in_menu' => 'edit.php?post_type=podcast'
		];
	}
}