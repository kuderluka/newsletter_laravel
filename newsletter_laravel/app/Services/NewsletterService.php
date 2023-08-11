<?php
namespace App\Services;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Exception;

class NewsletterService {

    private Client $client;

    public function __construct()
    {
        //Ne dela ce imam /services/v5 tukaj namesto v requestu?
        $this->client = new Client(['base_uri' => 'https://www.pivotaltracker.com']);
    }

    public function exportStories()
    {
        try {
            $response = $this->client->request('GET', '/services/v5/projects/' . env('PIVOTAL_PROJECT_ID') . '/stories', [
                'headers' => [
                    'X-TrackerToken' => env('PIVOTAL_API_TOKEN'),
                    'with_state' => 'accepted'
                ]
            ]);

            $data = $this->handleReviews(json_decode($response->getBody(), true));
            return $data;

        } catch (RequestException $e) {
            throw new Exception("Error getting stories: " . $e->getMessage());
        }
    }

    public function handleReviews($data)
    {
        try {
            $output = [];
            foreach($data as $story) {
                $response = $this->client->request('GET', '/services/v5/projects/' . env('PIVOTAL_PROJECT_ID') . '/stories/' . $story['id'] . '/reviews', [
                    'headers' => [
                        'X-TrackerToken' => env('PIVOTAL_API_TOKEN'),
                    ]
                ]);

                $story['reviews'] = json_decode($response->getBody(), true);
                foreach ($story['reviews'] as $review) {
                    if ($review['review_type_id'] == env('PIVOTAL_REVIEW_TYPE') && $review['status'] == 'pass') {
                        $output[] = $story;
                        break;
                    }
                }
            }
            return $output;

        } catch (Exception $e) {
            throw new Exception('Error getting reviews');
        }
    }

    public function extractForCSV($stories)
    {
        foreach ($stories as $story) {
            $labelIds = array_column($story['labels'], 'id'); // Extract label IDs
            $labelsString = implode(', ', $labelIds);

            $output[] = [
                'story_id' => $story['id'],
                'story_title' => $story['name'],
                'labels' => $labelsString,
            ];
        }
        return $output;
    }
}
