<?php

namespace App\Console\Commands;

use App\Services\PivotalTrackerClient;
use Exception;
use Illuminate\Console\Command;
use League\Csv\Writer;

class ExportNewsletterStoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-newsletter-stories-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all the stories with Newsletter review type';

    /**
     * Executes the console command. Writes the needed data to a csv file
     */
    public function handle(PivotalTrackerClient $pivotalTrackerClient)
    {
        try {
            $csvData = $pivotalTrackerClient->extractForCSV($pivotalTrackerClient->exportStories());
            $this->writeToCSV($csvData);

            $this->line('Data exported to CSV');
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Writes given data to a file
     *
     * @param array $csvData
     * @return void
     * @throws \League\Csv\Exception
     */
    public function writeToCSV(array $csvData): void
    {
        $csvFilename = 'newsletter_stories';
        $csvPath = storage_path('app/' . $csvFilename);

        $csv = Writer::createFromPath($csvPath, 'w+');
        $csv->insertOne(['story_id', 'story_title', 'labels']);

        foreach ($csvData as $rowData) {
            $csv->insertOne($rowData);
        }
    }
}
