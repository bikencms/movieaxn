<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\HelperService;
use App\Services\MovieService;
use App\Services\TvshowService;
use App\Services\SearchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Models\PostMeta;
use App\Models\Post;
class CastController extends Controller
{
    protected $imageUrlUpload;
    protected $tvshowService;
    protected $helperService;
    protected $movieService;
    protected $searchService;
    public function __construct(HelperService $helperService, MovieService $movieService, TvshowService $tvshowService, SearchService $searchService)
    {
        $this->imageUrlUpload = env('IMAGE_URL_UPLOAD', '/uploads');
        $this->helperService = $helperService;
        $this->movieService = $movieService;
        $this->tvshowService = $tvshowService;
        $this->searchService = $searchService;
    }

    public function index(Request $request) {
        $page = $request->get('page', 1);
        $perPage = $request->get('limit', env('PAGE_LIMIT'));
        $orderBy = $request->get('orderBy', '');
        $search = $request->get('search', '');

        $select = "SELECT p.ID as id, p.post_name as slug, p.post_title as name, wp.meta_value as src, wp.meta_id FROM wp_posts p 
        LEFT JOIN wp_postmeta wp ON wp.post_id = p.ID AND wp.meta_key = '_person_image_custom' ";
        $where = " WHERE p.post_status = 'publish' AND p.post_type='person' ";

        if( $orderBy == '' ) {
            $order = "ORDER BY wp.meta_value DESC ";
        } else if( $orderBy == 'nameAsc' ) {
            $order = "ORDER BY p.post_title ASC ";
        } else if( $orderBy == 'nameDesc' ) {
            $order = "ORDER BY p.post_title DESC ";
        } else {
            $order = "ORDER BY p.post_title DESC ";
        }
        
        $whereSearch = '';
        if( $search != '' ) {
            $whereSearch = " AND p.post_title LIKE '%". $search ."%' ";
            $where = $where . $whereSearch;
        }

        //query all
        $query = $select . $where . $order;
        $queryTotal = "SELECT count(p.ID) as total, p.post_title
        FROM wp_posts p 
        LEFT JOIN wp_postmeta wp ON wp.post_id = p.ID AND wp.meta_key = '_person_image_custom' 
        WHERE p.post_status = 'publish' AND p.post_type='person' AND wp.meta_value != '' " . $whereSearch;
        if( Cache::has('person_query_total') && Cache::get('person_query_total') === $queryTotal && Cache::has('person_data_total')) {
            $total = Cache::get('person_data_total');
        } else {
            $dataTotal = DB::select($queryTotal);
            $total = $dataTotal[0]->total;
            Cache::forever('person_query_total', $queryTotal);
            Cache::forever('person_data_total', $total);
        }

        //query limit
        $limit = "LIMIT " . ( ( $page - 1 ) * $perPage ) . ", $perPage ;";
        $query = $query . $limit;
        $casts = [];

        $items = DB::select($query);
        //clear cast dupplicate
        $this->helperService->clearCastDupplicate($items);
        $items = DB::select($query);
        foreach ($items as $key => $item) {
            $newSlug = (preg_match("@^[a-zA-Z0-9%+-_]*$@", $item->slug)) ? urldecode($item->slug) : $item->slug;
            //check no image
            $newSrc = '';
            if( empty($item->src) ) {
                $urlTmdb = "https://www.themoviedb.org/search/person?query=" . $item->name;
                $contentTmdb = @file_get_contents($urlTmdb);
                preg_match("/<img loading=\"lazy\" class=\"profile\" src=\"(.*)\" srcset=\"(.*)\" alt=\"$item->name\">/", $contentTmdb, $result);
                if( isset($result[1]) ) {
                    $imageSrc = $result[1];
                    if( !empty($imageSrc) ) {
                        $newImageSrc = str_replace('w90_and_h90_face', 'w300_and_h450_bestv2', $imageSrc);
                        $newImageSrc = "https://www.themoviedb.org" . $newImageSrc;
                        $newSrc = $newImageSrc;
                        $newImagePerson = new PostMeta();
                        $newImagePerson->post_id = $item->id;
                        $newImagePerson->meta_key = '_person_image_custom';
                        $newImagePerson->meta_value = $newSrc;
                        $newImagePerson->save();
                    }
                }
            } else {
                $newSrc = str_replace('w66_and_h66_face', 'w300_and_h450_bestv2', $item->src);
                $newSrc = str_replace('w300_and_h450_bestv2e', '/w300_and_h450_bestv2', $newSrc);
            }
            
            //check person name incorrect
            $newName = $item->name;  
            preg_match("/—/", $item->name, $match);
            if(isset($match[0])) {
                $newName = str_replace('—', '', $item->name);
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();

            }
            preg_match("/\[(.*)\]/", $item->name, $match2);
            if(isset($match2[0])) {
                $newName = explode('[', $item->name);
                $newName = $newName[0];
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName[0];
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            preg_match("/Self/", $item->name, $match3);
            if(isset($match3[0])) {
                $newName = explode('Self', $item->name);
                $newName = $newName[0];
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            
            preg_match("/(.*) (.*)-(.*) (.*)-(.*)/", $item->name, $match4);
            if(isset($match4[0])) {
                $checker = $match4[3];
                $count = strlen($checker);
                $newName = $checker[0];
                for( $i = 1; $i < $count; $i++ ) {
                    if( ctype_upper($checker[$i]) ) {
                        break;
                    } else {
                        $newName .= $checker[$i];
                    }
                }
                $newName = $match4[1] . ' ' . $match4[2] . '-' . $newName;
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            preg_match("/(.*) (.*)-(.*) (.*) (.*)/", $item->name, $match5);
            if(isset($match5[0])) {
                $checker = $match5[3];
                $count = strlen($checker);
                $newName = $checker[0];
                for( $i = 1; $i < $count; $i++ ) {
                    if( ctype_upper($checker[$i]) ) {
                        break;
                    } else {
                        $newName .= $checker[$i];
                    }
                }
                $newName = $match5[1] . ' ' . $match5[2] . '-' . $newName;
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            preg_match("/(.*) (.*) (.*) (.*)/", $item->name, $match6);
            if(isset($match6[0])) {
                $checker = $match6[2];
                $count = strlen($checker);
                $newName = $checker[0];
                for( $i = 1; $i < $count; $i++ ) {
                    if( ctype_upper($checker[$i]) ) {
                        break;
                    } else {
                        $newName .= $checker[$i];
                    }
                }
                $newName = $match6[1] . '-' . $newName;
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            preg_match("/(.*) (.*)-(.*)-(.*)/", $item->name, $match7);
            if(isset($match7[0])) {
                $checker = $match7[2];
                $count = strlen($checker);
                $newName = $checker[0];
                for( $i = 1; $i < $count; $i++ ) {
                    if( ctype_upper($checker[$i]) ) {
                        break;
                    } else {
                        $newName .= $checker[$i];
                    }
                }
                $newName = $match7[1] . '-' . $newName;
                $changeName = Post::find($item->id);
                $changeName->post_title = $newName;
                $changeName->post_name = str_replace(' ', '-', strtolower($newName));
                $changeName->save();
            }
            $casts[$key] = [
                'id' => $item->id,
                'slug' => urlencode($newSlug),
                'name' => $newName,
                'src' =>  $newSrc
            ];
        }
        $topWeeks = $this->topWeek();
        $data = [
            "total" => $total,
            "perPage" => $perPage,
            "data" => [
                'topWeeks' => $topWeeks,
                'items' => $casts
            ]
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    public function show(Request $request) 
    {
        $slug = $request->get('slug', '');
        $newSlug = addslashes($slug);
        $queryCast = "SELECT p.ID as id, p.post_name as slug, p.post_title as name, wp.meta_value as src, wp_tv_show.meta_value as tv_show, wp_movie.meta_value as movie
        FROM wp_posts p 
        LEFT JOIN wp_postmeta wp ON wp.post_id = p.ID AND wp.meta_key = '_person_image_custom' 
        LEFT JOIN wp_postmeta wp_tv_show ON wp_tv_show.post_id = p.ID AND wp_tv_show.meta_key = '_tv_show_cast'
        LEFT JOIN wp_postmeta wp_movie ON wp_movie.post_id = p.ID AND wp_movie.meta_key = '_movie_cast'
        WHERE ( p.post_name= '". $newSlug ."' )";
        
        $dataCast = DB::select($queryCast);
        $cast = [];
        $data = [];
        $newSrc = '';
        if( count($dataCast) > 0 ) {
            $data = $dataCast[0];
            $newSlug = (preg_match("@^[a-zA-Z0-9%+-_]*$@", $data->slug)) ? urldecode($data->slug) : $data->slug;

            //check no image
            if( empty($data->src) ) {
                $urlTmdb = "https://www.themoviedb.org/search/person?query=" . $data->slug;
                $contentTmdb = @file_get_contents($urlTmdb); 
                preg_match("/<img loading=\"lazy\" class=\"profile\" src=\"(.*)\" srcset=\"(.*)\" alt=\"$data->name\">/", $contentTmdb, $result);
                if( isset($result[1]) ) {
                    $imageSrc = $result[1];
                    if( !empty($imageSrc) ) {
                        $newImageSrc = str_replace('w90_and_h90_face', 'w300_and_h450_bestv2', $imageSrc);
                        $newImageSrc = "https://www.themoviedb.org" . $newImageSrc;
                        $newSrc = $newImageSrc;
                        $newImagePerson = new PostMeta();
                        $newImagePerson->post_id = $data->id;
                        $newImagePerson->meta_key = '_person_image_custom';
                        $newImagePerson->meta_value = $newSrc;
                        $newImagePerson->save();
                    }
                }
            } else {
                $newSrc = str_replace('w66_and_h66_face', 'w300_and_h450_bestv2', $data->src);
                $newSrc = str_replace('w300_and_h450_bestv2e', '/w300_and_h450_bestv2', $newSrc);
            }
            $cast = [
                'id' => $data->id,
                'slug' => $newSlug,
                'name' => $data->name,
                'src' => $newSrc
            ];
            //get tv-show
            $tvShow = @unserialize($data->tv_show);
            $ids = [];
            $items = [];
            if( $tvShow != '' && count($tvShow) > 0 ) {
                foreach( $tvShow as  $tvShowId) {
                    array_push($ids, $tvShowId);
                }
            }
            
            //get movie
            $movies = @unserialize($data->movie);
            if( $movies != '' && count($movies) > 0 ) {
                foreach( $movies as  $movieId) {
                    array_push($ids, $movieId);
                }
            }

            //sorting items
            $page = $request->get('page', '1');
            $orderBy = $request->get('orderBy', '');
            $perPage = $request->get('limit', env('PAGE_LIMIT'));

            $ids = join(",", $ids);
            
            $select = "SELECT p.ID, p.post_name, p.post_title, p.post_type, p.original_title, group_concat(tx.term_taxonomy_id) as categories FROM wp_posts p left join wp_term_relationships tr on p.ID = tr.object_id left join wp_term_taxonomy tx on tr.term_taxonomy_id = tx.term_taxonomy_id and tx.taxonomy = 'category'";
            $where = " WHERE p.post_status = 'publish' AND p.post_type IN ('tv_show', 'movie') AND p.ID IN ( " . $ids . " )";

            if( $orderBy == '' ) {
                $order = "ORDER BY p.post_date DESC ";
            } else if( $orderBy == 'titleAsc' ) {
                $order = "ORDER BY p.post_title ASC ";
            } else if( $orderBy == 'titleDesc' ) {
                $order = "ORDER BY p.post_title DESC ";
            } else if($orderBy == 'date' ) {
                $selectYear = "SELECT IF(pm.meta_value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$', DATE_FORMAT(pm.meta_value, '%Y'), DATE_FORMAT(FROM_UNIXTIME(pm.meta_value), '%Y')) as year , 
                p.ID, p.post_name, p.post_title, p.post_type, p.original_title, group_concat(tx.term_taxonomy_id) as categories FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key='_movie_release_date' left join wp_term_relationships tr on p.ID = tr.object_id left join wp_term_taxonomy tx on tr.term_taxonomy_id = tx.term_taxonomy_id and tx.taxonomy = 'category' ";
                $select = $selectYear;
                $order = "ORDER BY year DESC ";
            } else if($orderBy == 'rating') {
                $selectRating = "LEFT JOIN wp_most_popular mp ON mp.post_id = p.ID";
                $select = $select . $selectRating;
                $order = "ORDER BY mp.all_time_stats DESC ";
            } else {
                $order = "ORDER BY p.post_date DESC ";
            }

            $query = $select . $where . 'GROUP BY p.ID ' . $order;
            $selectTotal = "SELECT COUNT(p.ID) as total FROM wp_posts p ";
            $queryTotal = $selectTotal . $where;
            $dataTotal = DB::select($queryTotal);
            $total = $dataTotal[0]->total;

            //query limit
            $limit = "LIMIT " . ( ( $page - 1 ) * $perPage ) . ", $perPage ;";
            $query = $query . $limit;
            
            $items = $this->searchService->getItems($query);
            $cast['items'] = $items;
            $data = [
                "total" => $total,
                "perPage" => $perPage
            ];
            $data = array_merge($data, $cast);
        }
        return response()->json($data, Response::HTTP_OK);
    }

    public function topWeek() {
        $queryTopWeek = "SELECT p.ID, p.post_name, p.post_title, p.original_title, p.post_content, p.post_type, p.post_date_gmt, p.post_date FROM `wp_most_popular` mp
                            LEFT JOIN wp_posts p ON p.ID = mp.post_id
                            WHERE p.post_title != '' AND mp.post_id != '' AND p.ID != '' AND p.post_status = 'publish'
                            ORDER BY mp.7_day_stats DESC
                            LIMIT 5";
        return $this->getItems($queryTopWeek);
    }

    public function getItems($query) {
        $sliders = [];
        $slug = '';
        $linkSlider = '';
        $sliderDatas = DB::select($query);
        $year = '';
        foreach ( $sliderDatas as $sliderData ) {
            $titleSlider = $sliderData->post_title;
            if ($sliderData->post_type == 'movie') {
                $linkSlider = 'movie/' . $sliderData->post_title;
                $year = '';
                $queryMeta = "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ". $sliderData->ID .";";
                $dataMetas = DB::select($queryMeta);
                if( count($dataMetas) > 0 ) {
                    foreach ( $dataMetas as $dataMeta ) {
                        if( $dataMeta->meta_key == '_movie_release_date' || $dataMeta->meta_key == '_episode_release_date' ) {
                            if (preg_match("/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/", $dataMeta->meta_value)) {
                                $newDataReleaseDate = explode('-', $dataMeta->meta_value);
                                $year = $newDataReleaseDate[0];
                            } else {
                                $year = $dataMeta->meta_value > 0 ? date('Y', $dataMeta->meta_value) : date('Y');
                            }
                        }
                    }
                }
                $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                                        left join wp_term_relationships t_r on t_r.object_id = p.ID
                                        left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'movie_genre'
                                        left join wp_terms t on tx.term_id = t.term_id
                    where t.name != 'featured' AND t.name != '' AND p.ID = ". $sliderData->ID .";";
                $slug = $sliderData->post_name;
            } else if( $sliderData->post_type == 'tv_show' ) {
                $queryEpisode = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_seasons' AND post_id =". $sliderData->ID . " LIMIT 1;";
                $dataEpisode = DB::select($queryEpisode);
                $episodeData = $dataEpisode[0]->meta_value;
                $episodeData = @unserialize($episodeData);
                $lastSeason = end($episodeData);
                $episodeId = end($lastSeason['episodes']);
                
                $select = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt FROM wp_posts p ";
                $where = " WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) ";
                $whereTitle = " AND p.ID='". $episodeId ."' ";
    
                $where = $where . $whereTitle;
                $query = $select . $where;
                $dataEpisoSlider = DB::select($query);
                
                if( count($dataEpisoSlider) > 0 ) {
                    $linkSlider = 'episode/' . $dataEpisoSlider[0]->post_title;
                    $slug = $dataEpisoSlider[0]->post_name;
                    $titleSlider = $dataEpisoSlider[0]->post_title;
                }
                
                $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                left join wp_term_relationships t_r on t_r.object_id = p.ID
                left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
                left join wp_terms t on tx.term_id = t.term_id
                where t.name != 'featured' AND t.name != '' AND p.ID = ". $sliderData->ID .";";
            }
            $dataTaxonomys = DB::select($queryTaxonomy);
            $genres = [];
            if ( count($dataTaxonomys) > 0 ) {
                foreach( $dataTaxonomys as $dataTaxonomy ) {
                    $genres[] = [
                        'name' => $dataTaxonomy->name,
                        'link' =>  $dataTaxonomy->slug,
                        'slug' =>  $dataTaxonomy->slug
                    ];
                }
            }
            $sliders[] = [
                'id' => $sliderData->ID,
                'year' => $year,
                'genres' => $genres,
                'title' => $titleSlider,
                'link' => $linkSlider,
                'slug' => $slug,
                'postType' => $sliderData->post_type,
                'postDate' => $sliderData->post_date
            ];
        }
        return $sliders;
    }

    public function makeCacheCast(Request $request) {
        $from = $request->get('from', 0);
        $to = $request->get('to', 0);
        for($from; $from < $to; $from++) {
            Http::get(route('casts',  ['page' => $from]));
        }
    }

    public function checkCastOfMovie(Request $request) {
        $limitFrom = $request->get('limit_from', 0);
        $limitTo = $request->get('limit_to', 30);
        $queryMovie = "SELECT p.ID, p.post_title, pm.meta_value as tmdb_id, pm2.meta_value as casts
        FROM wp_posts p
        LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_tmdb_id' AND pm.meta_value != ''
        LEFT JOIN wp_postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_cast' AND pm2.meta_value != ''
        WHERE ((p.post_type = 'movie' AND (p.post_status = 'publish'))) 
        ORDER BY p.post_date DESC 
        LIMIT " . $limitFrom . ", " . $limitTo . " ;";
        $dataMovies =  DB::select($queryMovie);
        //check cast right or wrong
        $dataWrong = [];
        foreach ( $dataMovies as $dataMovie ) {
            //get first cast 
            if( $dataMovie->casts != '' || $dataMovie->tmdb_id != '' ) {
                try {
                    $castsOfMovie = @unserialize($dataMovie->casts);
                    $firstCast = $castsOfMovie[0]['id'];
                    //get title of first cast
                    $queryCast = "SELECT p.post_title FROM wp_posts p
                    WHERE ((p.post_type = 'person' AND (p.post_status = 'publish'))) AND p.ID=".$firstCast;
                    $dataCast =  DB::select($queryCast);
                    if( count($dataCast) > 0 ) {
                        //check tmdb movie
                        $urlTmdb = "https://www.themoviedb.org/movie/" . $dataMovie->tmdb_id . "/cast";
                        $contentTmdb = @file_get_contents($urlTmdb);
                        preg_match_all("/\">(.*)<\/a><p>/", $contentTmdb, $result);
                        $name = str_replace("<p>", "", $result[0]);
                        $name = str_replace("\">", "", $name);
                        
                        if ( $dataCast[0]->post_title != strip_tags(html_entity_decode($name[0])) ) {
                            $wrong = [
                                'movie_id' => $dataMovie->ID,
                                'tmdb_id' => $dataMovie->tmdb_id,
                            ];
                            array_push($dataWrong, $wrong);
                        }
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }
        Storage::disk('local')->put($limitFrom.'_'.$limitTo.'movie_wrong_person.json', json_encode($dataWrong));  
        return ("Ok!");
    }
    public function checkCastOfTvShow(Request $request) {
        $limitFrom = $request->get('limit_from', 0);
        $limitTo = $request->get('limit_to', 30);
        $queryMovie = "SELECT p.ID, p.post_title, pm.meta_value as tmdb_id, pm2.meta_value as casts
        FROM wp_posts p
        LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_tmdb_id' AND pm.meta_value != ''
        LEFT JOIN wp_postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_cast' AND pm2.meta_value != ''
        WHERE ((p.post_type = 'tv_show' AND (p.post_status = 'publish'))) 
        ORDER BY p.post_date DESC 
        LIMIT " . $limitFrom . ", " . $limitTo . " ;";
        $dataMovies =  DB::select($queryMovie);
        //check cast right or wrong
        $dataWrong = [];
        foreach ( $dataMovies as $dataMovie ) {
            //get first cast 
            if( $dataMovie->casts != '' || $dataMovie->tmdb_id != '' ) {
                try {
                    $castsOfMovie = @unserialize($dataMovie->casts);
                    $firstCast = $castsOfMovie[0]['id'];
                    //get title of first cast
                    $queryCast = "SELECT p.post_title FROM wp_posts p
                    WHERE ((p.post_type = 'person' AND (p.post_status = 'publish'))) AND p.ID=".$firstCast;
                    $dataCast =  DB::select($queryCast);
                    if( count($dataCast) > 0 ) {
                        //check tmdb movie
                        $urlTmdb = "https://www.themoviedb.org/tv/" . $dataMovie->tmdb_id . "/cast";
                        $contentTmdb = @file_get_contents($urlTmdb);
                        
                        preg_match_all("/\">(.*)<\/a>/", $contentTmdb, $result);
                        $name = str_replace("\">", "", $result[0][41]);
                        if ( $dataCast[0]->post_title != strip_tags(html_entity_decode($name)) ) {
                            $wrong = [
                                'movie_id' => $dataMovie->ID,
                                'tmdb_id' => $dataMovie->tmdb_id
                            ];
                            array_push($dataWrong, $wrong);
                        }
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }
        Storage::disk('local')->put($limitFrom.'_'.$limitTo.'tv_show_wrong_person.json', json_encode($dataWrong));  
        return ("Ok!");
    }

    public function updateCastOfMovie(Request $request) {
        $file = $request->get('file', '');
        $movieList = json_decode(Storage::disk('local')->get($file), true);
        foreach( $movieList as $movie) {
            Http::get(route('cast.import',  ['movieId' => $movie['movie_id'], 'tmdbId' => $movie['tmdb_id'], 'postType' => 'movie']));
        }
        return "Ok!";
    }

    public function updateCastOfTvShow(Request $request) {
        $file = $request->get('file', '');
        $movieList = json_decode(Storage::disk('local')->get($file), true);
        //print_r(count($movieList)); die;        
        foreach( $movieList as $movie) {
            Http::get(route('cast.import',  ['movieId' => $movie['movie_id'], 'tmdbId' => $movie['tmdb_id'], 'postType' => 'tv_show']));
        }
        return "Ok!";
    }
}
