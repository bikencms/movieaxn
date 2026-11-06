<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\MovieService;
use App\Services\TvshowService;
use App\Services\SearchService;
use App\Services\HelperService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\Post;
use App\Models\PostMeta;
use Telegram\Bot\Laravel\Facades\Telegram;

class HomepageController extends Controller
{

    protected $movieService;
    protected $tvshowService;
    protected $searchService;
    protected $helperService;
    protected $imageUrlUpload;
    public function __construct(MovieService $movieService, TvshowService $tvshowService, SearchService $searchService, HelperService $helperService)
    {
        $this->movieService = $movieService;
        $this->tvshowService = $tvshowService;
        $this->searchService = $searchService;
        $this->helperService = $helperService;
        $this->imageUrlUpload = env('IMAGE_URL_UPLOAD', '/uploads');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if( Cache::has('home_page')) {
            $homepage = Cache::get('home_page');
            $sliderRandoms = $this->tvshowService->getTvShowRandom();
            $homepage['otts']['ottTitle'] = isset($sliderRandoms['title']) ? $sliderRandoms['title'] : "";
            $homepage['otts']['ottSliders'] = isset($sliderRandoms['items']) ? $sliderRandoms['items'] : [];
        } else {
            //Get header slider
            $sliderQuery = "SELECT meta_key, ID, post_title, post_name, post_type, post_date, meta_value, IF(pm.meta_value IS NOT NULL , CAST( pm.meta_value AS UNSIGNED ) , 0 ) as sort_order
            FROM wp_posts as p
            LEFT JOIN wp_postmeta as pm ON p.ID = pm.post_id and pm.meta_key= '_sort_order'
            WHERE ID IN ( SELECT object_id FROM `wp_term_relationships` WHERE term_taxonomy_id IN (17 , 43) ) 
                AND p.post_status = 'publish'
            ORDER BY sort_order ASC, post_date DESC;";

            $sliders = $this->helperService->getSliderItems($sliderQuery);
            
            //Get Chanel slider random between USA and Korea
            $sliderRandoms = $this->tvshowService->getTvShowRandom();

            //get 12 tv-show
            $queryTvshow = "SELECT DISTINCT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt, p.post_date FROM `wp_posts` p 
                                LEFT JOIN wp_term_relationships t_r ON t_r.object_id = p.ID 
                                LEFT JOIN wp_term_taxonomy tx ON t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
                                LEFT JOIN wp_terms t ON tx.term_id = t.term_id 
                                WHERE ((p.post_type = 'tv_show' AND (p.post_status = 'publish'))) ORDER BY p.post_date DESC LIMIT 12;";
            $dataTvshow = $this->tvshowService->getItems($queryTvshow);

            $categories = [
                'menu' => [
                    [
                        'title' => '전체',
                        'link' => 'tv-show'
                    ],
                    [
                        'title' => '드라마',
                        'link' => 'k-drama'
                    ],
                    [
                        'title' => '예능',
                        'link' => 'k-show'
                    ],
                    [
                        'title' => '시사/교양',
                        'link' => 'k-sisa'
                    ],
                    [
                        'title' => '미드',
                        'link' => 'u-drama'
                    ]
                ],
                'items' => [
                    'tv-show' => $dataTvshow,
                ]
            ];
            
            //Get 12 movies 
            $queryMovie = "SELECT p.ID as id, p.post_name as slug, p.post_title as title FROM wp_posts p WHERE p.post_type = 'movie' AND p.post_status = 'publish' ORDER BY p.post_date DESC LIMIT 12";
            $movies = DB::select($queryMovie);
            
            $queryTopWeek = "SELECT p.ID as id, p.post_name as slug, p.post_title as title FROM `wp_most_popular` mp
                LEFT JOIN wp_posts p ON p.ID = mp.post_id
                WHERE p.post_type = 'movie' AND p.post_title != '' AND mp.post_id != '' AND p.ID != ''
                ORDER BY mp.7_day_stats DESC
                LIMIT 5";
            $topWeeks = DB::select($queryTopWeek);

            //Get movies newest of Korea for slider in bottom
            $encodedSlug = urlencode('한국영화');
            $queryKoreaMovie = "SELECT p.ID as id, p.post_name as slug, p.post_title as title FROM `wp_posts` p
            LEFT JOIN wp_term_relationships t_r on t_r.object_id = p.ID
            LEFT JOIN wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'movie_genre'
            LEFT JOIN wp_terms t on tx.term_id = t.term_id AND t.slug = '" . $encodedSlug . "'
            WHERE t.name != 'featured' AND t.name != ''
            ORDER BY p.post_date DESC
            LIMIT 8;";

            $movieKoreas = DB::select($queryKoreaMovie);

            $movieIds = \array_map(fn($movie) => (int) $movie->id, $movies);
            $topWeekMovieIds = \array_map(fn($movie) => (int) $movie->id, $topWeeks);
            $movieKoreaIds = \array_map(fn($movie) => (int) $movie->id, $movieKoreas);
            $allMovieIds = \array_unique(\array_merge($movieIds, $topWeekMovieIds, $movieKoreaIds));
            $hasThumbnailMovieIds = \array_unique(\array_merge($movieIds, $movieKoreaIds));

            $moviesMetadata = $this->movieService->getMoviesMetadata($hasThumbnailMovieIds, [], ['thumbnailVerticalOnly']);
            $moviesMetadataTopWeek = $this->movieService->getMoviesMetadata($topWeekMovieIds, ['_movie_release_date']);
            $genres = $this->movieService->getMoviesGenres($allMovieIds);
            foreach ($movies as &$item) {
                $item = \get_object_vars($item);
                $item['genres'] = $genres[(int) $item['id']] ?? [];
                $item += $moviesMetadata[(int) $item['id']];
            }

            foreach ($movieKoreas as &$item) {
                $item = \get_object_vars($item);
                $item['genres'] = $genres[(int) $item['id']] ?? [];
                $item += $moviesMetadata[(int) $item['id']];
            }
            
            foreach ($topWeeks as &$item) {
                $item = \get_object_vars($item);
                $item['genres'] = $genres[(int) $item['id']] ?? [];

                $item += isset($moviesMetadataTopWeek[(int) $item['id']]) ? $moviesMetadataTopWeek[(int) $item['id']] : [];
            }

            $homepage = [
                'sliders' => $sliders,
                'otts' => [
                    'ottTitle' => isset($sliderRandoms['title']) ? $sliderRandoms['title'] : "",
                    'ottSliders' => isset($sliderRandoms['items']) ? $sliderRandoms['items'] : []
                ],
                'tvshows' => [
                    'title' => '최신등록 방송',
                    'categories' => $categories
                ],
                'movies' => [
                    'title' => '최신등록영화',
                    'items' => $movies
                ],
                'moviesCarousel' => $movieKoreas,
                'movieNewests' => [
                    'topWeeks' => $topWeeks,
                    'movieNewests' => \array_slice($movies, 0 , 8)
                ],
            ];
            Cache::forever('home_page', $homepage);
        }
        
        return response()->json($homepage, Response::HTTP_OK);
    }

    public function search(Request $request) {
        $title = $request->get('title', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('limit', env('PAGE_LIMIT'));
        $orderBy = $request->get('orderBy', 'date');

        $titleNoWhitespace = \str_replace(' ', '', $title);
        $select = "select
            p.ID,
            p.post_name,
            p.post_title,
            p.post_type,
            p.original_title,
            group_concat(tx.term_taxonomy_id) as categories";
        $from = " from
            wp_posts p
        left join wp_term_relationships tr on p.ID = tr.object_id
        left join wp_term_taxonomy tx on tr.term_taxonomy_id = tx.term_taxonomy_id and tx.taxonomy = 'category'";
        
        $where = " WHERE p.post_type in ('tv_show', 'movie')
            and p.post_status = 'publish'
            and (replace(p.post_title, ' ', '') like '%" . $titleNoWhitespace . "%'
            or p.ID in (
                select
                    post_id
                from
                    wp_postmeta wp
                where
                    meta_key in ('_original_title', '_movie_original_title')
                        and replace(meta_value, ' ', '') like '%" . $titleNoWhitespace . "%'
            )) group by p.ID";

        if ($orderBy == 'titleAsc') {
            $order = " ORDER BY p.post_title ASC";
        } elseif ($orderBy == 'titleDesc') {
            $order = " ORDER BY p.post_title DESC";
        } else {
            $order = " ORDER BY p.post_date DESC";
        }

        //query all
        $query = $select . $from . $where . $order;

        $selectTotal = "SELECT p.ID" . $from;
        $queryTotal = $selectTotal . $where;
        $dataTotal = DB::select($queryTotal);
        $total = \count($dataTotal);

        //query limit
        $limit = " LIMIT " . ( ( $page - 1 ) * $perPage ) . ", $perPage ;";
        $query = $query . $limit;
        $items = $this->searchService->getItems($query);
        $topWeeks = $this->searchService->getTopWeeks();
        $data = [
            "total" => $total,
            "perPage" => $perPage,
            "data" => [
                'topWeeks' => $topWeeks,
                'items' => $items
            ]
        ];
        
        return response()->json($data, Response::HTTP_OK);
    }

    public function tvShowHomepage(Request $request) {
        $type = $request->get('type', '');
        if( Cache::has('tvshow_homepage_' . $type) ) {
            $tvShow = Cache::get('tvshow_homepage_' . $type);
        } else {
            $select = "SELECT p.ID, p.post_name, p.post_title, p.original_title, p.post_content, p.post_date_gmt, p.post_date, p.post_modified FROM wp_posts p ";
            $where = " WHERE  ((p.post_type = 'tv_show' AND (p.post_status = 'publish')))";
            $tvShow = [];
            if( $type != '' ) {
                $categoryTvShowKorea = config('constants.categoryTvshowKoreas');
                if( in_array($type, $categoryTvShowKorea) ) {
                    $idType = "SELECT wr.object_id
                                FROM wp_terms t
                                LEFT JOIN wp_term_taxonomy wt ON t.term_id = wt.term_id
                                LEFT JOIN wp_term_relationships wr ON wr.term_taxonomy_id = wt.term_taxonomy_id
                                WHERE slug = '". $type ."'";
                    $whereType = " AND p.ID IN ( ". $idType ." ) ";
                } else {
                    $whereType = $this->tvshowService->getWhereByType($type);
                }
                $where = $where . $whereType;
            }
            $queryTvshow = $select . $where . " ORDER BY p.post_date DESC LIMIT 12";
            $dataTvshow = $this->tvshowService->getItems($queryTvshow);
            $tvShow = $dataTvshow;
            Cache::forever('tvshow_homepage_' . $type, $tvShow);
        }
        
        return response()->json($tvShow, Response::HTTP_OK);
    }

    public function clearCache() {
        Artisan::call('cache:clear');
        $this->helperService->makeCacheFirst();
    }

    public function clearCacheByKey($key='') {
        Cache::forget($key);
        return "OK!";
    }

    public function putGmtTime() {
        $this->clearCache();
        Cache::forever('gmtTime', date('Y-m-d H:i:s'));
    }

    public function getMovieTMDBId(Request $request) {
        $limitFrom = $request->get('limit_from', 0);
        $limitTo = $request->get('limit_to', 30);
        $queryMovie = "SELECT p.ID, pm.meta_value as tmdb_id
        FROM wp_posts p
        LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_tmdb_id' AND pm.meta_value != ''
        WHERE ((p.post_type = 'movie' AND (p.post_status = 'publish'))) 
        ORDER BY p.post_date DESC 
        LIMIT " . $limitFrom . ", " . $limitTo . " ;";
        $dataMovie =  DB::select($queryMovie);
        Storage::disk('local')->put($limitFrom.'_'.$limitTo.'_tmdb.json', json_encode($dataMovie));
    }

    public function getMovieLimit() {
        for($i=0; $i <= 6987; $i=$i+100) {
            Http::get(route('movie.tmdb',  ['limit_from' => $i, 'limit_to' => 100]));
        }
        Http::get(route('movie.tmdb',  ['limit_from' => 6900, 'limit_to' => 100]));
    }

    public function getTvshowTMDBId(Request $request) {
        $limitFrom = $request->get('limit_from', 0);
        $limitTo = $request->get('limit_to', 30);
        $queryMovie = "SELECT p.ID, pm.meta_value as tmdb_id
        FROM wp_posts p
        LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_tmdb_id' AND pm.meta_value != ''
        WHERE ((p.post_type = 'tv_show' AND (p.post_status = 'publish'))) 
        ORDER BY p.post_date DESC 
        LIMIT " . $limitFrom . ", " . $limitTo . " ;";
        $dataMovie =  DB::select($queryMovie);
        Storage::disk('local')->put($limitFrom.'_'.$limitTo.'_tv_show_tmdb.json', json_encode($dataMovie));
    }

    public function getTvshowLimit() {
        for($i=0; $i <= 3057; $i=$i+100) {
            Http::get(route('tvshow.tmdb',  ['limit_from' => $i, 'limit_to' => 100]));
        }
        Http::get(route('tvshow.tmdb',  ['limit_from' => 3000, 'limit_to' => 100]));
    }

    public function insertPerson(Request $request) {
        $file = $request->get('file', '');
        $type = $request->get('type', 'movie');
        $personList = json_decode(Storage::disk('local')->get($file), true);
        $personListRollback = [];
        $personMetaListRollback = [];
        foreach( $personList as $person) {
            $person = json_decode($person, true);
            $person = $person[0];

            $movieId = $person['movie_id'];
            $tmdbId = $person['tmdb_id'];
            $guid = $person['link'];
            $image = $person['image'];
            $title = str_replace('"', '',$person['name']);
            $name = str_replace(' ', '-',(strtolower($person['name'])));
            $name = str_replace('"', '',$name);
            if (Post::where([ 'post_title' => $title, 'post_status' => 'publish'])->exists()) {
                $person = Post::select('ID')->where(['post_title'=> $title, 'post_status' => 'publish'])->first();
                $idNewPerson = $person->ID;

                $metaKey = '_movie_cast';
                if($type != 'movie') {
                    $metaKey = '_tv_show_cast';
                }
                $dataMovie =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $idNewPerson, 'meta_key' => $metaKey])->first();
                if($dataMovie != '' && $dataMovie->meta_value != '' ) {
                    $movies = @unserialize($dataMovie->meta_value);
                    //check exist and update movie of cast
                    if( !in_array($movieId, $movies) ) {
                        array_push($movies, $movieId);
                        $metaPost = PostMeta::find($dataMovie->meta_id);
                        $metaPost->meta_value = serialize($movies);
                        $metaPost->save();
                    }
                } else if( $dataMovie != '' && $dataMovie->meta_value == '' ) {
                    $movies = [];
                    array_push($movies, $movieId);
                    $metaPost = new PostMeta;
                    $metaPost->post_id = $idNewPerson;
                    $metaPost->meta_key = $metaKey;
                    $metaPost->meta_value = serialize($movies);
                    $metaPost->save();
                }
            } else {
                $newPerson = Post::create(
                    [
                        'post_title' => $title,
                        'post_name' => $name,
                        'post_content' => $title, 
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'comment_status' => 'closed',
                        'ping_status' => 'closed',
                        'guid' => $guid, 
                        'post_type' => 'person', 
                        'post_excerpt' => '', 
                        'to_ping' => '', 
                        'pinged' => '',
                        'post_content_filtered' => '',
                        'post_date' => now(),
                        'post_date_gmt' => now(),
                        'post_modified' => now(),
                        'post_modified_gmt' => now()
                    ]
                );

                $idNewPerson = $newPerson->ID;

                //insert tmdb id
                $idPostMeta_tmdb_id = PostMeta::insertGetId([
                    'post_id' => $idNewPerson, 
                    'meta_key' => '_tmdb_id',
                    'meta_value' => $tmdbId, 
                ]);
                array_push($personMetaListRollback, $idPostMeta_tmdb_id);

                //insert image custom
                $idPostMeta_person_image_custom = PostMeta::insertGetId([
                    'post_id' => $idNewPerson, 
                    'meta_key' => '_person_image_custom',
                    'meta_value' => $image,
                ]);
                array_push($personMetaListRollback, $idPostMeta_person_image_custom);

                //insert cast movie - tv_show
                $metaKey = '_movie_cast';
                if( $type != 'movie' ) {
                    $metaKey = '_tv_show_cast';
                }
                $idPostMeta_movie_cast = PostMeta::insertGetId([
                    'post_id' => $idNewPerson, 
                    'meta_key' => $metaKey,
                    'meta_value' =>  serialize(array($movieId)) 
                ]);
                array_push($personMetaListRollback, $idPostMeta_movie_cast);
                array_push($personListRollback, $idNewPerson);
            }
            //update movie cast
            $dataMovieCast =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $movieId, 'meta_key' => '_cast'])->first();
            $newCastMovie = [];
            if( $dataMovieCast == '') {
                $movieCasts = [];
                $newCastMovie = [
                    'id' => $idNewPerson,
                    'character' => '',
                    'position' => 0,
                ];
                array_push($movieCasts, $newCastMovie);
                $metaPostMovie = new PostMeta;
                $metaPostMovie->post_id = $movieId;
                $metaPostMovie->meta_key = '_cast';
                $metaPostMovie->meta_value = serialize($movieCasts);
                $metaPostMovie->save();
                array_push($personMetaListRollback, $metaPostMovie->meta_id);
            } else {
                if( $dataMovieCast->meta_value != '') {
                    $movieCasts = @unserialize($dataMovieCast->meta_value);
                    //check exist and update movie of cast
                    foreach($movieCasts as $movieCast ) {
                        if( isset($movieCast['id']) && $movieCast['id'] != $idNewPerson ) {
                            $newCastMovie = [
                                'id' => $idNewPerson,
                                'character' => '',
                                'position' => end($movieCasts)['position']++,
                            ];
                        }
                    }    
                    array_push($movieCasts, $newCastMovie);
                } else {
                    $movieCasts = [];
                    $newCastMovie = [
                        'id' => $idNewPerson,
                        'character' => '',
                        'position' => 0,
                    ];
                    array_push($movieCasts, $newCastMovie);
                }
                $metaPostMovie = PostMeta::find($dataMovieCast->meta_id);
                $metaPostMovie->post_id = $movieId;
                $metaPostMovie->meta_key = '_cast';
                $metaPostMovie->meta_value = serialize($movieCasts);
                $metaPostMovie->save();
                array_push($personMetaListRollback, $dataMovieCast->meta_id);
            }
        }
        Storage::disk('local')->put('rollback_person.json', json_encode($personListRollback));
        Storage::disk('local')->put('rollback_person_meta.json', json_encode($personMetaListRollback));   
        return "Ok!";
    }

    public function autoImportPerson(Request $request) {
        $movieId = $request->get('movieId', '0');
        $tmdbId = $request->get('tmdbId', '0');
        $postType = $request->get('postType', 'movie');
        if($tmdbId == 0) {
            return true;
        }
        Artisan::call('person:auto '. $movieId . ' ' . $tmdbId . ' ' . $postType);
        return "Ok!";
    }

    public function updatedActivity() {
        $activity = Telegram::getUpdates();
        $lastestActivity = end($activity);
        if( isset($lastestActivity) ) {
            $text = $lastestActivity->getMessage()->text;
            preg_match('/\/ping /', $text, $matches, PREG_OFFSET_CAPTURE);
            if( isset($matches[0][0]) && $matches[0][0] == '/ping ' ) {
                $domainRoot = explode(' ', $text);
                $domain = $domainRoot[1];
                $this->handlePing($domain);
            }
        }
        die("Ok!");
    }

    public function handleWebhook() {
        $token = env('TELEGRAM_BOT_TOKEN');
        $response = Telegram::setWebhook([
            'url' => "https://backend.moviehqu.com/api/$token/webhook",
            'has_custom_certificate' => false
        ]);
        Telegram::commandsHandler(true);
        die($response);
    }

    public function handlePing($domain='') {
        $wait = 10; // wait Timeout In Seconds
        $fp = @fsockopen($domain, 80, $errCode, $errStr, $wait);
        if (!$fp) {
            if ( $errCode == '10060' ) {
                $text = "Ping $domain ==> Timeout over 10s";
            } else {
                $text = "Ping $domain ==> ERROR: $errCode - $errStr";
            }
        } else {
            $text = "Ping $domain ==> Success";
        }
        Telegram::sendMessage([
            'chat_id' => env('TELEGRAM_CHANNEL_ID', '-4061154988'),
            'parse_mode' => 'HTML',
            'text' => $text
        ]);
    }

}