<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Page;
use App\Models\Faq;

class RebuildSearchIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rebuild-search-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush and re-import all searchable models into the search index';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $models = [
            BlogPost::class,
            Product::class,
            Page::class,
            Faq::class,
        ];

        $this->info('Starting to rebuild search index for all models...');

        foreach ($models as $model) {
            $this->info("Flushing index for {$model}...");
            Artisan::call('scout:flush', ['model' => $model]);

            $this->info("Importing records for {$model}...");
            Artisan::call('scout:import', ['model' => $model]);
        }

        $this->info('Search index has been rebuilt successfully!');
        return 0;
    }
}
