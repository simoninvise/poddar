<?php

class ImportGenres {
	const BASE_URL = 'https://itunes.apple.com/WebObjects/MZStoreServices.woa/ws/genres?id=26&cc=se';
	const TAXONOMY = 'podcast_category';

	# Load iTunes genres into podcast categories
	public static function import () {
		try {
			# Fetch all iTunes genres
			$response = wp_remote_get(self::BASE_URL, [
				'headers' => [
					'Accept' => 'application/json',
				]
			]);

			if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
				$response = json_decode($response['body']);

				if (json_last_error() === JSON_ERROR_NONE) {
					$data = $response->{26}->subgenres;

					foreach ($data as $genre) {
						$term = wp_insert_term($genre->name, self::TAXONOMY);
						update_term_meta($term['term_id'], 'genre_id', $genre->id, true);

						# If sub genre
						if (isset($genre->subgenres)) {
							foreach ($genre->subgenres as $subgenre) {
								$sTerm = wp_insert_term($subgenre->name, self::TAXONOMY, ['parent' => $term['term_id']]);
								update_term_meta($sTerm['term_id'], 'genre_id', $subgenre->id, true);
							}
						}
					}
				}
			}
		} catch (Exception $ex) {
			# Handle Exception.
			die($ex);
		}
	}
}