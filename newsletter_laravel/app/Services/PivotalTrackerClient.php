<?php
namespace App\Services;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Exception;

/**
 * Class handles api requests to Pivotal Tracker
 */
class PivotalTrackerClient {

    protected $description = 'Handles requests to PivotalTrackers API';

    /**
     * GuzzleHttp client
     *
     * @var Client
     */
    private Client $client;

    /**
     * New GuzzleHttp client initialized
     */
    public function __construct()
    {
        //Ne dela ce imam /services/v5 tukaj namesto v requestu?
        $this->client = new Client(['base_uri' => 'https://www.pivotaltracker.com']);
    }

    /**
     * Fetches all stories with the state set to accepted
     *
     * @return array|void|null
     * @throws GuzzleException
     * @throws Exception
     */
    public function exportStories(): array
    {
        return $this->loadStoriesForProject(config('pivotal-tracker.project_id'));
    }

    /**
     * Filters the given stories so that they only include those with a certain review type set to pass
     *
     * @param array $data
     * @return array
     * @throws Exception|GuzzleException
     */
    public function filterByReviews(array $data): array
    {
        $output = [];
        foreach($data as $story) {
            $story['reviews'] = $this->loadReviewsForStory(config('pivotal-tracker.project_id'), $story['id']);

            foreach ($story['reviews'] as $review) {
                if ($review['review_type_id'] == env('PIVOTAL_REVIEW_TYPE') && $review['status'] == 'pass') {
                    $output[] = $story;
                    break;
                }
            }
        }

        return $output;
    }

    /**
     * Converts the filtered stories for saving via csv
     *
     * @param $stories
     * @return array
     */
    public function extractForCSV(array $stories): array
    {
        $output = [];

        foreach ($stories as $story) {
            $labelIds = array_column($story['labels'], 'id');
            $labelsString = implode(', ', $labelIds);

            $output[] = [
                'story_id' => $story['id'],
                'story_title' => $story['name'],
                'labels' => $labelsString,
            ];
        }
        return $output;
    }

    public function loadStoriesForProject(int $projectId): array
    {
         $response = $this->client->request('GET', '/services/v5/projects/' . config('pivotal-tracker.project_id') . '/stories', [
            'headers' => [
                'X-TrackerToken' => config('pivotal-tracker.api_token'),
            ],
            'query' => [
                'with_state' => 'accepted',
            ]
        ]);

        return $this->filterByReviews(json_decode($response->getBody(), true));
    }

    public function loadReviewsForStory(int $projectId, int $storyId): array
    {
        $response = $this->client->request('GET', '/services/v5/projects/' . $projectId . '/stories/' . $storyId . '/reviews', [
            'headers' => [
                'X-TrackerToken' => config('pivotal-tracker.api_token'),
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
}