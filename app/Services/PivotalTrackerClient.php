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
            $labelIds = array_column($story['labels'], 'name');
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

    /**
     * Checks if the set review type is set to pass
     *
     * @param array $reviews
     * @return bool
     */
    private function filterByReviews(array $reviews): bool
    {
        foreach ($reviews as $review) {
            if ($review['review_type_id'] == config('pivotal-tracker.review_type') && $review['status'] == 'pass') {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads and returns the message sent when setting newsletter review to pass
     *
     * @param int $storyId
     * @return string
     */
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
                $exploded = preg_split('/\*\*Newsletter\*\* review set to \*\*pass\*\*/', trim($comment['text']));

                if(isset($exploded[1])) {
                    return trim($exploded[1]);
                }
            }
        }

        return '';
    }
}
