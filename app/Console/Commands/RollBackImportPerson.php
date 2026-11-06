<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use App\Models\PostMeta;

class RollBackImportPerson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'person:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback import person';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            DB::beginTransaction();
          
            $personList = json_decode(Storage::disk('local')->get('rollback_person.json'), true);
            foreach( $personList as $person) {
                $new = Post::find($person);
                if( $new != '' ) {
                    $new->delete();
                }
            }
            $personMetaList = json_decode(Storage::disk('local')->get('rollback_person_meta.json'), true);
            foreach( $personMetaList as $personMeta) {
                $newMeta = PostMeta::find($personMeta);
                if( $newMeta != '' ) {
                    $newMeta->delete();
                }
            }
            
            DB::commit();
           
            //send output to the console
            $this->info('Success!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error($e->getMessage());
        }
    }
}
