<?php

class ItunesAPI {
	private $endpoint;
	private $lang;
	private $log;

	public function __construct ($endpoint, $lang = 'en') {
		$this->endpoint = $endpoint;
		$this->lang = $lang;
		$this->log = [];
	}

	# Log
	public function log ($msg) {
		$this->log[] = $msg;
	}

	# Get log
	public function getLog () {
		return $this->log;
	}

	# Handles all requests
	public function fetch ($url, $data = null, $endpoint = null) {
		# Build endpoint
		$endpoint = ($endpoint ?? $this->endpoint) . $url;

		if (strpos($endpoint, '?') === false) {
			$endpoint .= '?';
		}
		else {
			$endpoint .= '&';
		}

		if ($data) {
			$endpoint .= build_query($data);
		}

		# Basic auth
		$response = wp_remote_get($endpoint, [
			'headers' => [
				'Accept' => 'application/json',
			]
		]);

		# Check for WP errors
		if (is_wp_error($response)) {
			$this->log('GET Request WP fail: ' . $endpoint);

			return false;
		}

		# Check for API errors
		if ($response['response']['code'] !== 200) {
			$this->log('GET Request 200 fail: ' . $endpoint);

			return false;
		}

		# Great success
		$this->log('GET Request success: ' . $endpoint);

		# Check if JSON
		$jsonBody = json_decode($response['body']);

		if (json_last_error() === JSON_ERROR_NONE) {
			return $jsonBody;
		}

		return false;
	}

	# Get Podcast by ID
	public function getPodcast ($podcastId) {
		$podcast = $this->fetch('lookup/', [
			'country' => $this->lang,
			'id' => $podcastId
		]);

		if ($podcast and isset($podcast->results[0]) and $podcast->results[0]) {
			return $podcast->results[0];
		}

		return false;
	}

	# Get Toplist by Genre
	public function getToplist ($genreId, $limit = 50) {
		$toplist = $this->fetch($this->lang . '/rss/toppodcasts/genre=' . $genreId . '/limit=' . $limit . '/json');

		if ($toplist and isset($toplist->feed->entry) and $toplist->feed->entry) {
			return $toplist->feed->entry;
		}

		return false;
	}

	public function fetchFeedLifetime () {
		return 300;
	}

	# Get all Genres
	public function getEpisodes ($feed) {
		add_filter('wp_feed_cache_transient_lifetime' , [$this, 'fetchFeedLifetime']);
		$feed = fetch_feed($feed);
		remove_filter('wp_feed_cache_transient_lifetime',  [$this, 'fetchFeedLifetime']);

		if (!is_wp_error($feed)) {
			$maxItems = $feed->get_item_quantity(0);
			return $feed->get_items(0, $maxItems);
		}

		return false;
	}

	# Get Toplist order by Genre
	public function getToplistOrder ($genreId, $limit = 50) {
		$toplist = self::getToplist($genreId, $limit);

		if ($toplist) {
			return array_map(function($podcast) {
				return intval($podcast->id->attributes->{'im:id'});
			}, $toplist);
		}

		return false;
	}

	# Get all Genres
	public function getGenres () {
		$genres = $this->fetch('/WebObjects/MZStoreServices.woa/ws/genres', [
			'id' => 26,
			'cc' => $this->lang
		]);

		if ($genres and isset($response->{26}->subgenres) and $response->{26}->subgenres) {
			return $response->{26}->subgenres;
		}

		return false;
	}

	# Get Podcast feed by ID
	public function getFeed ($podcastId) {
		$podcast = $this->getPodcast($podcastId);

		if ($podcast and isset($podcast->feedUrl) and $podcast->feedUrl) {
			return $podcast->feedUrl;
		}

		return false;
	}
}