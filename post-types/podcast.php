<?php
namespace Sleek\PostTypes;

class Podcast extends PostType {
	public function init () {
		add_action('pre_get_posts', function ($query) {
			if (!is_admin() && $query->is_main_query()) {
				if (is_tax() or is_post_type_archive('podcast')) {
					global $post;

					if (is_post_type_archive()) {
						$order = array_slice(json_decode(get_option('podcast_order')), 0, 50);
						$query->set('posts_per_page', 50);

					}
					else {
						$order = json_decode(get_term_meta(get_queried_object()->term_id, 'toplist_order', true));
						$query->set('posts_per_page', 50);
					}

					$query->set('meta_key', 'itunes_id');
					$query->set('orderby', 'toplist_order');
					$query->set('toplist_order', $order);

					$query->set('meta_query', [[
						'key'   => 'itunes_id',
						'value' => $order
					]]);
				}
			}
		});
	}

	public function episodeCountFilter ($where) {
		global $wpdb;
		return str_replace("WHERE", "WHERE " . $wpdb->posts . ".post_parent = " . $this->countPodcast . " AND", $where);
		return $where;
	}

	public function getEpisodeCount($podcast) {
		$this->countPodcast = $podcast;

		add_filter('query', [$this, 'episodeCountFilter']);
		$count = wp_count_posts('episode');
		remove_filter('query', [$this, 'episodeCountFilter']);

		unset($this->countPodcast);

		return $count->publish ?? 0;
	}

	public function config () {
		return [
			'menu_icon' => 'dashicons-businesswoman',
			'hide_from_search' => true,
			'hierarchical' => true,
			'taxonomies' => ['podcast_category'],
		];
	}

	public function fields () {
		return [];
	}

	public function getImage ($postId, int $size = 0, $quality = 75) {
		$image = get_post_meta($postId, 'image', true);

		if ($size) {
			$image = str_replace('55x55', $size . 'x' . $size, $image);
		}

		if ($quality) {
			$image = str_replace('bb.png', 'bb-' . $quality . '.png', $image);
		}

		return $image;
	}
}