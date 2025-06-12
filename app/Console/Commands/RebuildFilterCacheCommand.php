<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FilterCacheService;

class RebuildFilterCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:rebuild-filters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild Redis filter cache from database (without XML import)';


    protected FilterCacheService $filterCache;

    /**
     * Constructor
     */
    public function __construct(FilterCacheService $filterCache)
    {
        parent::__construct();
        $this->filterCache = $filterCache;
    }

    /**
     * Clears the existing Redis filter cache and rebuilds it from the database.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Clearing existing filter cache...');
        $this->filterCache->clearAll();

        $this->info('Rebuilding filter cache from database...');
        $this->filterCache->rebuildFromDatabase();

        $this->info('Filter cache has been rebuilt successfully.');

        return 0;
    }
}
