<?php
namespace App\Services;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Exception;

class NewsletterService {

    public function exportStories() {

        //Ne dela ce imam /services/v5 tukaj namesto v requestu?
        $client = new Client([
            'base_uri' => 'https://www.pivotaltracker.com'
        ]);

        try {
            $response = $client->request('GET', '/services/v5/projects/' . env('PIVOTAL_PROJECT_ID') . '/stories', [
                'headers' => [
                    'X-TrackerToken' => env('PIVOTAL_API_TOKEN'),
                    'with_state' => 'accepted'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data;

        } catch (RequestException $e) {
            throw new Exception("Error getting stories: " . $e->getMessage());
        }
    }
}
