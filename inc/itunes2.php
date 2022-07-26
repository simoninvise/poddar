<?php

require get_stylesheet_directory() . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
require __DIR__ . '/podcast-importer.php';
require __DIR__ . '/itunes-api.php';

############
# Main class
class Poddar {
	private static $log = [];
	public static $api;

	#####
	# Log
	public static function log ($msg) {
		if (is_array($msg)) {
			self::$log = array_merge(self::$log, $msg);
		}
		else {
			self::$log[] = $msg;
		}
	}

	############
	# Return log
	public static function getLog () {
		return self::$log;
	}

	#############
	# Init plugin
	public static function init () {
		# Schedule import
		add_action('init', ['Poddar', 'scheduleImport'], 99);

		# Update data
		add_action('import_podcasts', ['Poddar', 'handleImport']);
		add_action('import_episodes', ['Poddar', 'handleImportEpisodes']);
		add_action('update_podcast_order', ['Poddar', 'updateOrder']);

		# Custom filter
		add_filter('posts_orderby', ['Poddar', 'toplistOrder'], 10, 2);

		# API
		self::$api = new ItunesAPI('https://itunes.apple.com/', 'se');
	}

	######################
	# Update Podcast order
	public static function updateOrder () {
		$order = Poddar::$api->getToplistOrder(26, 200);

		if ($order) {
			update_option('podcast_order' , json_encode($order));
		}
	}

	#########################
	# Schedule Import handler
	public static function scheduleImport() {
		# Podcast Import
		$terms = get_terms([
			'taxonomy' => 'podcast_category',
			'hide_empty' => false,
			'parent' => 0
		]);

		foreach ($terms as $term) {
			$genreId = get_term_meta($term->term_id, 'genre_id', true);

			if (as_has_scheduled_action('import_podcasts', ['genre' => $genreId]) === false) {
				as_schedule_recurring_action(strtotime('tomorrow'), DAY_IN_SECONDS, 'import_podcasts', ['genre' => $genreId], 'Podcast (' . $term->name . ')');
			}
		}

		# Podcast Order
		if (as_has_scheduled_action('update_podcast_order') === false) {
			as_schedule_recurring_action(strtotime('tomorrow'), DAY_IN_SECONDS, 'update_podcast_order', [], 'Podcast order');
		}
	}

	########################
	# Handle import requests
	public static function handleImport ($genre) {
		# Make sure we have everything we need
		if ($genre and self::$api) {
			$genre = is_array($genre) ? $genre['genre'] : $genre;

			# Get Podcasts
			$podcasts = self::$api->getToplist($genre);

			self::log('Importing ' . count($podcasts) . ' podcasts from genre ' . $genre);

			# Import Podcasts
			foreach ($podcasts as $podcast) {
				$podcast = PodcastImporter::import($podcast);

				# Schedule import of Episodes
				if (as_has_scheduled_action('import_episodes', ['podcast_id' => $podcast]) === false) {
					as_schedule_recurring_action(strtotime('tomorrow'), DAY_IN_SECONDS, 'import_episodes', ['podcast_id' => $podcast], 'Episodes (' . get_the_title($podcast) . ')');
				}
			}

			# Add Toplist order
			PodcastImporter::importToplistOrder($genre);

			self::log(PodcastImporter::getLog());

			file_put_contents(get_stylesheet_directory() . '/log', "\n\n" . date('Y-m-d H:i:s') . "\n". implode("\n", self::getLog()), FILE_APPEND);
		}
		# Mandatory args not set
		else {
			self::log('Genre is not set - aborting');

			return __('Genre is not set - aborting', 'sleek');
		}
	}

	########################
	# Handle import requests
	public static function handleImportEpisodes ($podcastId) {
		# Make sure we have everything we need
		if ($podcastId and self::$api) {
			$podcastId = is_array($podcastId) ? $podcastId['podcast_id'] : $podcastId;
			$feed = get_post_meta($podcastId, 'episode_feed', true);

			# Check if Podcast exists
			if ($feed) {
				self::log('Importing episodes from podcast ' . get_the_title($podcastId));

				# Get Episodes
				$episodes = self::$api->getEpisodes($feed);

				# Import Episodes
				foreach ($episodes as $episode) {
					PodcastImporter::importEpisode($podcastId, $episode);
				}
			}
			else {
				self::log('Feed is missing for podcast ' . get_the_title($podcastId));
			}

			self::log(PodcastImporter::getLog());

			file_put_contents(get_stylesheet_directory() . '/log', "\n\n" . date('Y-m-d H:i:s') . "\n". implode("\n", self::getLog()), FILE_APPEND);
		}
		# Mandatory args not set
		else {
			self::log('Podcast ID is not set - aborting');

			return __('Podcast is not set - aborting', 'sleek');
		}
	}

	#########################
	# Schedule Import handler
	public static function toplistOrder ($orderby, $query) {
		$key = 'toplist_order';

		if ($key === $query->get('orderby') && ($list = $query->get($key))) {
			global $wpdb;

			if (!is_array($list)) {
				$list = preg_split('/[\s,]+/', $list);
			}

			$list = array_unique(array_map('sanitize_key', $list));

			$list = '"' . join('","', $list) . '"';

			$orderby = " FIELD( {$wpdb->postmeta}.meta_value, {$list} ) ";
		}

		return $orderby;
	}
}

# Init the plugin
Poddar::init();