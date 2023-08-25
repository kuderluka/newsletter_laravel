<?php
namespace App\Services;

use GuzzleHttp\Exception\GuzzleException;
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
        $this->client = new Client(['base_uri' => 'https://www.pivotaltracker.com']);
    }

    /**
     * Fetches all stories with the state set to accepted
     *
     * @return array
     * @throws GuzzleException
     */
    public function exportStories(): array
    {
        return $this->loadStoriesForProject();
    }

    /**
     * Filters the given stories so that they only include those with a certain review type set to pass
     *
     * @param array $data
     * @return array
     * @throws Exception|GuzzleException
     */
    public function filterStories(array $data): array
    {
        $output = [];
        foreach($data as $story) {
            $story['reviews'] = $this->loadReviewsForStory($story['id']);
            $story['newsletter_message'] = $this->loadNewsletterMessage($story['id']);

            if($this->filterByReviews($story['reviews'])) {
                $output[] = $story;
            }
        }

        return $output;
    }

    /**
     * Converts the filtered stories for saving via csv
     *
     * @param array $stories
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
                'newsletter_message' => $story['newsletter_message'],
                'labels' => $labelsString,
            ];
        }

        return $output;
    }

    /**
     * Loads all stories of a certain project
     *
     * @return array
     * @throws GuzzleException
     */
    public function loadStoriesForProject(): array
    {
         $response = $this->client->request('GET', '/services/v5/projects/' . config('pivotal-tracker.project_id') . '/stories', [
            'headers' => [
                'X-TrackerToken' => config('pivotal-tracker.api_token'),
            ],
            'query' => [
                'with_state' => 'accepted',
            ]
        ]);

        return $this->filterStories(json_decode($response->getBody(), true));
    }

    /**
     * Loads all reviews of a certain story
     *
     * @param int $storyId
     * @return array
     * @throws GuzzleException
     */
    public function loadReviewsForStory(int $storyId): array
    {
        $response = $this->client->request('GET', '/services/v5/projects/' . config('pivotal-tracker.project_id') . '/stories/' . $storyId . '/reviews', [
            'headers' => [
                'X-TrackerToken' => config('pivotal-tracker.api_token'),
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    private function filterByReviews(array $reviews): bool
    {
        foreach ($reviews as $review) {
            if ($review['review_type_id'] == config('pivotal-tracker.review_type') && $review['status'] == 'pass') {
                return true;
            }
        }

        return false;
    }

    private function loadNewsletterMessage(int $storyId): string
    {
        $response = $this->client->request('GET', '/services/v5/projects/' . config('pivotal-tracker.project_id')  . '/stories/' . $storyId . '/comments', [
            'headers' => [
                'X-TrackerToken' => config('pivotal-tracker.api_token'),
            ]
        ]);

        $comments = json_decode($response->getBody(), true);

        foreach($comments as $comment) {
            if(str_contains($comment['text'], '**Newsletter** review set to **pass**')) {
                $exploded = preg_split('/\r\n|\r|\n/', trim($comment['text']));

                if(isset($exploded[2])) {
                    return $exploded[2];
                }
                return 'There is no pass message';
            }
        }

        return '';
    }
}
