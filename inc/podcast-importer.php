<?php
class PodcastImporter {
	private static $log = [];

	# Log
	public static function log ($msg) {
		self::$log[] = $msg;
	}

	# Glog
	public static function getLog () {
		return self::$log;
	}

	# Handle import
	public static function import ($podcast) {
		# Check for existing post
		$existingPost = self::getPodcastById($podcast->id->attributes->{'im:id'});

		self::log('Importing ' . ($existingPost ? 'existing' : 'new') . ' podcast "' . $podcast->{'im:name'}->label . '" (' . $podcast->id->attributes->{'im:id'} . ')');

		# Create new post
		$postArgs = [
			'ID' => $existingPost->ID ?? 0,
			'post_type' => 'podcast',
			'post_author' => 1, # Admin
			//'post_date' => $podcast->{'im:releaseDate'}->label ?? null,
			'post_content' => $podcast->summary->label ?? '',
			'post_title' => $podcast->{'im:name'}->label ?? 'UNTITLED',
			'post_status' => 'publish',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'meta_input' => [
				'itunes_id' => $podcast->id->attributes->{'im:id'}
			]
		];

		# Insert the post
		$postId = wp_insert_post($postArgs);

		# Set ACF Fields
		update_post_meta($postId, 'release_date', $podcast->{'im:releaseDate'}->label);
		update_post_meta($postId, 'artist', $podcast->{'im:artist'}->label);
		update_post_meta($postId, 'has_new', 'yes');
		update_post_meta($postId, 'image', $podcast->{'im:image'}[0]->label);
		update_post_meta($postId, 'itunes_link', $podcast->link->attributes->href);

		# Insert cateogry
		if (isset($podcast->category->attributes->{'im:id'})) {
			self::log('Set podcast category...');

			# Get term ID
			$term = self::getGenrebyId($podcast->category->attributes->{'im:id'});

			# Add cateogry
			if ($term) {
				$categories = [$term->term_id];

				# Check if parent
				if ($term->parent) {
					$categories[] = $term->parent;
				}

				wp_set_post_terms($postId, $categories, 'podcast_category', false);
			}
		}

		# Add Feed URL if missing
		if (isset(Poddar::$api) and !get_post_meta($postId, 'episode_feed', true)) {
			$feed = Poddar::$api->getFeed($podcast->id->attributes->{'im:id'});
			update_post_meta($postId, 'episode_feed', $feed);
		}

		self::log('Podcast successfully imported');

		return $postId;
	}

	# Import Episode
	public static function importEpisode ($podcast, $item) {
		# Abort early if Episode exist
		if (in_array($item->get_id(), self::getEpisodes($podcast))) {
			return;
		}

		self::log('Importing new episode "' .$item->get_title() . '" (' . $item->get_id() . ')');

		$enclosure = $item->get_enclosure();
		$image = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ITUNES, 'image');

		# Create new post
		$postArgs = [
			'ID' => 0,
			'post_type' => 'episode',
			'post_author' => 1, # Admin
			'post_date' => $item->get_date('Y-m-d H:i:s') ?? null,
			'post_content' => $item->get_content() ?? '',
			'post_title' => $item->get_title() ?? 'UNTITLED',
			'post_status' => 'publish', # TODO: Add support for draft if some flag in deal is set
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_parent' => $podcast,
			'meta_input' => [
				'episode_id' => $item->get_id(), # Store the original ID
				'audio' => $enclosure->link, # And the audio
				'duration' => $enclosure->duration, # And the duration,
				'thumbnail' => $image[0]['attribs']['']['href'] ?? null
			]
		];

		# Insert the post
		$postId = wp_insert_post($postArgs);

		self::log('Episode successfully imported');

		return $postId;
	}

	# Import array of genres
	public static function importGenres ($genres) {
		self::log('Importing genres...');

		foreach ($genres as $genre) {
			$term = wp_insert_term($genre->name, 'podcast_category');
			update_term_meta($term->term_id, 'genre_id', $genre->id, true);

			# If sub genre
			if (isset($genre->subgenres)) {
				foreach ($genre->subgenres as $subgenre) {
					$sTerm = wp_insert_term($subgenre->name, 'podcast_category', ['parent' => $term['term_id']]);
					update_term_meta($sTerm->term_id, 'genre_id', $subgenre->id, true);
				}
			}
		}
	}

	# Import toplist order
	public static function importToplistOrder ($genre) {
		if (isset(Poddar::$api)) {
			$order = Poddar::$api->getToplistOrder($genre);

			if ($order) {
				# Get term ID
				$term = self::getGenrebyId($genre);

				if ($term) {
					update_term_meta($term->term_id, 'toplist_order', json_encode($order), true);
				}
			}
		}
	}

	# Get all Episodes for a Podcast
	public static function getEpisodes ($podcast) {
		$rows = get_posts([
			'post_type' => 'episode',
			'post_parent' => $podcast,
			'fields' => 'ids',
			'posts_per_page' => -1
		]);

		$episodes = [];

		foreach ($rows as $row) {
			$episodes[] = get_post_meta($row, 'episode_id', true);
		}

		return $episodes;
	}

	# Check for existing podcast
	public static function getPodcastbyId ($id) {
		$rows = get_posts([
			'post_type' => 'podcast',
			'post_status' => 'any',
			'meta_query' => [
				[
					'key' => 'itunes_id',
					'value' => $id
				]
			]
		]);

		if ($rows and count($rows)) {
			return $rows[0];
		}

		return false;
	}

	# Check for existing podcast
	public static function getGenrebyId ($id) {
		$rows = get_terms([
			'taxonomy' => 'podcast_category',
			'hide_empty' => false,
			'meta_query' => [[
				'key' => 'genre_id',
				'value' => $id,
				'compare' => 'LIKE'
			]]
		]);

		if ($rows and count($rows)) {
			return $rows[0];
		}

		return false;
	}
}