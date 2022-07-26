<?php

class Itunes {
	private $endpoint;
	private $lang;

	public function __construct ($endpoint, $lang = 'se') {
		$this->endpoint = $endpoint;
		$this->lang = $lang;
	}

	public function fetch ($url, $data = []) {
		try {
			# Build endpoint
			$endpoint = $this->endpoint . $url;

			if (strpos($endpoint, '?') === false) {
				$endpoint .= '?';
			}
			else {
				$endpoint .= '&';
			}

			if ($data) {
				$endpoint .= build_query($data);
			}

			# Fetch all iTunes genres
			$response = wp_remote_get($endpoint, [
				'headers' => [
					'Accept' => 'application/json',
				]
			]);

			if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
				$response = json_decode($response['body']);

				if (json_last_error() === JSON_ERROR_NONE) {
					return $response;
				}
			}
		} catch (Exception $ex) {
			# Handle Exception.
			return false;
		}
	}

	# Import Podcast by genre
	public function importPodcasts ($genreId, $limit = 50) {
		$endpoint = $this->lang . '/rss/toppodcasts/genre=' . $genreId . '/limit=' . $limit . '/json';
		$res = $this->fetch($endpoint);

		$podcasts = count($res->feed->entry) ? $res->feed->entry : [];
		$ids = [];

		foreach ($podcasts as $podcast) {
			$ids[] = $this->importPodcast($podcast);
		}

		return $ids;
	}

	# Check for existing podcast
	public function getPodcastbyId ($id) {
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

	# Get Podcast feed by ID
	public function getPodcastFeed ($podcastId) {
		$url = 'https://itunes.apple.com/lookup?country=' . $this->lang . '&id=' . $podcastId;
		$response = wp_remote_get($url);

		if (!is_wp_error($response) and $response['response']['code'] === 200) {
			$body = json_decode($response['body'], true);

			if ($result = $body['results'][0]) {
				return $result['feedUrl'];
			}
		}
	}

	# Import podcast
	public function importPodcast ($podcast) {
		$existingPost = $this->getPodcastbyId($podcast->id->attributes->{'im:id'});

		# Create if not exist or update if changed
		if (true or !$existingPost or get_post_meta($existingPost->ID, 'release_date', true) != $podcast->{'im:releaseDate'}->label) {

			# Create new post
			$postArgs = [
				'ID' => $existingPost->ID ?? 0,
				'post_type' => 'podcast',
				'post_author' => 1, # Admin
				'post_date' => $podcast->{'im:releaseDate'}->label ?? null,
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

			# Get term ID
			$terms = get_terms([
				'taxonomy' => 'podcast_category',
				'hide_empty' => false,
				'meta_query' => [[
					'key' => 'genre_id',
					'value' => $podcast->category->attributes->{'im:id'},
					'compare' => 'LIKE'
				]]
			]);

			# Add term
			if (!empty($terms)) {
				$categories = [$terms[0]->term_id];

				# Check if parent
				if ($terms[0]->parent) {
					$categories[] = $terms[0]->parent;
				}

				wp_set_post_terms($postId, $categories, 'podcast_category', false);
			}

			# Add Feed URL if missing
			if (!get_post_meta($postId, 'episode_feed', true)) {
				$feed = $this->getPodcastFeed($podcast->id->attributes->{'im:id'});
				update_post_meta($postId, 'episode_feed', $feed);
			}
		}

		return $postId ?? false;
	}
}