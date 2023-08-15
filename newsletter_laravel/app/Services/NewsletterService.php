<?php
namespace App\Services;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class NewsletterService {

    private Client $client;

    private SymfonyStyle $io;

    public function __construct()
    {
        //Ne dela ce imam /services/v5 tukaj namesto v requestu?
        $this->client = new Client(['base_uri' => 'https://www.pivotaltracker.com']);
    }

    /**
     * Fetches all stories with the state set to accepted
     *
     * @param SymfonyStyle $io
     * @return array|void|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function exportStories(SymfonyStyle $io)
    {
        $this->io = $io;
        try {
            $response = $this->client->request('GET', '/services/v5/projects/' . env('PIVOTAL_PROJECT_ID') . '/stories', [
                'headers' => [
                    'X-TrackerToken' => env('PIVOTAL_API_TOKEN'),
                ],
                'query' => [
                    'with_state' => 'accepted',
                ]
            ]);

            $data = $this->filterByReviews(json_decode($response->getBody(), true));
            return $data;

        } catch (RequestException $e) {
            $this->io->error($e->getMessage());
        }
    }

    /**
     * Filters the given stories so that they only include those with a certain review type set to pass
     *
     * @param $data
     * @return array|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function filterByReviews($data)
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
            $this->io->error($e->getMessage());
        }
    }

    /**
     * Converts the filtered stories for saving via csv
     *
     * @param $stories
     * @return array
     */
    public function extractForCSV($stories)
    {
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
}
