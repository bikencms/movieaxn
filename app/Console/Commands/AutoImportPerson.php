<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoImportPerson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'person:auto {argument*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatic import person when posting';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $argument = $this->argument('argument', '');            
        $movieId = $argument[0];
        $tmdbId = $argument[1];
        $postType = $argument[2];
        // Replace this with the actual command you want to run
        $command = '/home/vuejs/moviehqu.com/backend/import_person.sh ' . $movieId . ' ' . $tmdbId . ' ' . $postType . '';
 
        // Execute the Bash script
        $output = shell_exec($command);

        // Output the result
        $this->info($output);
    }
}
