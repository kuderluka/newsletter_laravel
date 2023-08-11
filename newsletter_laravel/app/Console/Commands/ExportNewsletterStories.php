<?php

namespace App\Console\Commands;

use App\Services\NewsletterService;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use League\Csv\Writer;

class ExportNewsletterStories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-newsletter-stories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all the stories with Newsletter review type';

    public function __construct(NewsletterService $newsletterService)
    {
        parent::__construct();
        $this->newsletterService = $newsletterService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $io = new SymfonyStyle($this->input, $this->output);

        try {
            $data = $this->newsletterService->exportStories();
            $csvData = $this->newsletterService->extractForCSV($data);

            $csvFilename = 'newsletter_stories';
            $csvPath = storage_path('app/' . $csvFilename);

            $csv = Writer::createFromPath($csvPath, 'w+');
            $csv->insertOne(['story_id', 'story_title', 'labels']);

            foreach ($csvData as $rowData) {
                $csv->insertOne($rowData);
            }

            $io->success('Data exported to CSV: ' . $csvPath);
        } catch (Exception $e) {
            $io->error($e->getMessage());
        }



    }
}
