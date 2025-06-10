<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FilterCacheService;

class RebuildFilterCacheCommand extends Command
{
    protected $signature = 'cache:rebuild-filters';
    protected $description = 'Rebuild Redis filter cache from database (without XML import)';

    protected FilterCacheService $filterCache;

    public function __construct(FilterCacheService $filterCache)
    {
        parent::__construct();
        $this->filterCache = $filterCache;
    }

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
