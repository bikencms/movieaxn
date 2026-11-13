<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\TvshowService;
use App\Services\HelperService;
use Illuminate\Support\Facades\Cache;
class TvshowController extends Controller
{
    private $imageUrlUpload;
    protected $tvshowService;
    protected $helperService;
    public function __construct(TvshowService $tvshowService, HelperService $helperService)
    {
        $this->imageUrlUpload = env('IMAGE_URL_UPLOAD', '/uploads');
        $this->tvshowService = $tvshowService;
        $this->helperService = $helperService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('limit', env('PAGE_LIMIT', 30));
        if( $perPage > env('PAGE_LIMIT', 30) ) {
            $perPage = env('PAGE_LIMIT', 30);
        }
        $orderBy = $request->get('orderBy', '');
        $title = $request->get('title', '');
        $type = $request->get('type', '');
        $genre = $request->get('genre', '');

        if( $page == 1 && $orderBy == 'date' && $genre == '' && $type == '' && Cache::has('tv_show_first') ) {
            $data = Cache::get('tv_show_first');
        } else if ( $page == 1 && $orderBy == 'date' && $genre == '' && Cache::has('tv_show_first_'.$type) ) {
            $data = Cache::get('tv_show_first_'.$type);
        } else {
            $select = "SELECT p.ID, p.post_title, p.original_title, p.post_content, p.post_date_gmt, p.post_date, p.post_modified FROM wp_posts p ";
            $where = " WHERE ((p.post_type = 'tv_show' AND (p.post_status = 'publish'))) ";

            if( $title != '' ) {
                $s_rp = str_replace(" ","", $title);
                // Chú ý thêm khoảng trắng trước chữ AND
                $whereTitle = " AND (p.post_title LIKE '%".$title."%' OR 
                REPLACE(p.post_title, ' ', '') LIKE '%".$s_rp."%' OR
                p.original_title LIKE '%".$title."%' OR
                REPLACE(p.original_title, ' ', '') LIKE '%".$s_rp."%'
                ) ";
                $where .= $whereTitle;
            }

            if( $type != '' ) {
                $categoryTvShowKorea = config('constants.categoryTvshowKoreas');
                if( in_array($type, $categoryTvShowKorea) ) {
                    $idType = "SELECT wr.object_id
                                FROM wp_terms t
                                LEFT JOIN wp_term_taxonomy wt ON t.term_id = wt.term_id
                                LEFT JOIN wp_term_relationships wr ON wr.term_taxonomy_id = wt.term_taxonomy_id
                                WHERE slug = '". $type ."'";
                    // Thêm khoảng trắng trước chữ AND
                    $whereType = " AND p.ID IN ( ". $idType ." ) ";
                } else {
                    $whereType = " " . $this->tvshowService->getWhereByType($type);
                }
                $where .= $whereType;
            }

            if( $genre != '' ) {
                $genreArray = explode(',', $genre);
                $escapedGenres = array_map(function($g) {
                    return "'" . addslashes(trim($g)) . "'";
                }, $genreArray);
                $genreString = join(",", $escapedGenres);

                $queryGenre = "SELECT tr.object_id FROM wp_terms t
                    LEFT JOIN wp_term_taxonomy tx ON tx.term_id = t.term_id
                    LEFT JOIN wp_term_relationships tr ON tr.term_taxonomy_id = tx.term_taxonomy_id
                    WHERE t.slug IN (". $genreString .") OR t.name IN (". $genreString .")";
                
                // Thêm khoảng trắng trước chữ AND
                $where .= " AND p.ID IN ( ". $queryGenre ." ) ";    
            }

            $selectRating = "";
            if( $orderBy == '' ) {
                $order = " ORDER BY p.post_date DESC ";
            } else if( $orderBy == 'titleAsc' ) {
                $order = " ORDER BY p.post_title ASC ";
            } else if( $orderBy == 'titleDesc' ) {
                $order = " ORDER BY p.post_title DESC ";
            } else if( $orderBy == 'date' ) {
                $order = " ORDER BY p.post_date DESC ";
            } else if( $orderBy == 'rating') {
                $selectRating = " LEFT JOIN wp_most_popular mp ON mp.post_id = p.ID ";
                $order = " ORDER BY mp.all_time_stats DESC ";
            } else if( $orderBy == 'menuOrder' ) {
                $order = " ORDER BY p.menu_order DESC ";
            } else {
                $order = " ORDER BY p.post_date DESC ";
            }

            // Gộp câu lệnh hoàn chỉnh
            $query = $select . $selectRating . $where . $order;
    
            $selectTotal = "SELECT COUNT(1) as total FROM wp_posts p ";
            $queryTotal = $selectTotal . $where;
    
            if( Cache::has('tv_show_query_total') && Cache::get('tv_show_query_total') === $queryTotal && Cache::has('tv_show_data_total')) {
                $total = Cache::get('tv_show_data_total');
            } else {
                $dataTotal = DB::select($queryTotal);
                $total = $dataTotal[0]->total;
                Cache::forever('tv_show_query_total', $queryTotal);
                Cache::forever('tv_show_data_total', $total);
            }
    
            //query limit tvshow
            $limit = " LIMIT " . ( ( $page - 1 ) * $perPage ) . ", $perPage ;";
            $query = $query . $limit;
            $data = $this->getData($query, $type, $request, $total, $perPage, $page);
            if( $page == 1 && $orderBy == 'date' && $genre == '' && $type == '' ) {
                Cache::forever('tv_show_first', $data);
            } else if ( $page == 1 && $orderBy == 'date' && $genre == '' && $type != '' ) {
                Cache::forever('tv_show_first_'.$type, $data);
            }
        }

        return response()->json($data, Response::HTTP_OK);
    }
    
    /**
     * Display the specified resource.
     *
     * @param  string  $title
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $titleTvshow = $request->get('slug', '');
        $newtitleTvshow = urlencode($titleTvshow);
        $select = "SELECT p.ID, p.post_title, p.original_title, p.post_content, p.post_date_gmt, p.post_date, p.post_modified FROM wp_posts p ";
        $where = " WHERE  ((p.post_type = 'tv_show' AND (p.post_status = 'publish'))) ";
        $whereTitle = " AND p.post_name='". $newtitleTvshow ."' LIMIT 1; ";

        $where = $where . $whereTitle;
        $movies = [];
        $tvShowSlug = '';

        $dataPost = DB::select($select . $where);
        $link = '';
        if (count($dataPost) == 0) {
            return response()->json($movies, Response::HTTP_NOT_FOUND);
        }
        $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                        left join wp_term_relationships t_r on t_r.object_id = p.ID
                        left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
                        left join wp_terms t on tx.term_id = t.term_id
                        where t.name != 'featured' AND t.name != '' AND p.ID = ". $dataPost[0]->ID .";";
        $dataTaxonomys = DB::select($queryTaxonomy);

        $genres = [];
        $slug = [];
        foreach( $dataTaxonomys as $dataTaxonomy ) {
            $genres[] = [
                'name' => $dataTaxonomy->name,
                'link' => $dataTaxonomy->slug,
                'slug' => $dataTaxonomy->slug
            ];
            $slug[] = "'" . $dataTaxonomy->name . "'";
        }

        $dataSeason = $dataPost[0];
    
        $queryEpisode = "SELECT meta_value FROM `wp_postmeta` WHERE meta_key = '_seasons' AND post_id =". $dataSeason->ID . " LIMIT 1;";
        $dataEpisode = DB::select($queryEpisode);
        $seasons = $this->tvshowService->getSeasons($dataEpisode);
        
        $querySrcMeta = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
                            LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $dataSeason->ID .";";
        $dataSrcMeta = DB::select($querySrcMeta);
        $src = $this->imageUrlUpload.$dataSrcMeta[0]->meta_value;
     
        $episodeData = DB::select($queryEpisode);
        $episodeData = $episodeData[0]->meta_value;
        $episodeData = @unserialize($episodeData);
        
        $lastSeason = end($episodeData);
        $episodeId = end($lastSeason['episodes']);

        //outlink only show in into
        $outlink = env('OUTLINK');
        $outlink = @file_get_contents($outlink);

        if( $outlink == NULL ) $outlink = env('DEFAULT_OUTLINK');
        $outlink =  $outlink . '?pid=' . $episodeId;

        //Get topweek
        $topWeeks = $this->tvshowService->getTopWeeks();

        //Get topmonth
        $topMonths = $this->tvshowService->getTopMonths();

        //get 8 movies related
        $slug = join(",", $slug);

        if ( $slug == '' ) {
            $dataRelateds = [];
        } else {
            $arrayTvShowError = config('constants.tv_show_error');
            $arrayTvShowError = join(",", $arrayTvShowError);
            $queryTaxonomyRelated = "SELECT DISTINCT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt, p.post_date FROM `wp_posts` p
            left join wp_term_relationships t_r on t_r.object_id = p.ID
            left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
            left join wp_terms t on tx.term_id = t.term_id
            where t.name != 'featured' AND t.name != '' AND ( t.name IN ( ".$slug." ) OR t.slug IN ( ".$slug." ) )AND p.ID NOT IN ( " . $arrayTvShowError . " ) LIMIT 10";
            $dataRelateds = $this->tvshowService->getItems($queryTaxonomyRelated);
        }

        $selectTitleEpisode = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt, p.post_date FROM wp_posts p ";
        $whereTitleEpisode = " WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) ";
        $whereTitleSub = " AND p.ID='". $episodeId ."' ";

        $queryTitle = $selectTitleEpisode . $whereTitleEpisode . $whereTitleSub;
        $dataEpisoTitle = DB::select($queryTitle);
        
        if( count($dataEpisoTitle) > 0 ) {
            $link = 'episode/' . $dataEpisoTitle[0]->post_title ."/";
            $tvShowSlug = $dataEpisoTitle[0]->post_name;
        }

        //get original title
        $originalTitle = '';
        $queryOriginalTitle = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_original_title' AND post_id =". $dataSeason->ID . " LIMIT 1;";
        $dataOriginalTitle = DB::select($queryOriginalTitle);
        if( count($dataOriginalTitle) > 0 ) {
            $originalTitle = $dataOriginalTitle[0]->meta_value;
        }

        $srcSet = $this->helperService->getAttachmentsByPostId($dataSeason->ID);
        $movies = [
            'id' => $dataSeason->ID,
            'title' => $dataSeason->post_title,
            'slug' => $tvShowSlug,
            'originalTitle' => $originalTitle,
            'description' => $dataSeason->post_content,
            'genres' => $genres,
            'src' => $src,
            'srcSet' => $srcSet,
            'link' => $link,
            'outlink' => $outlink,
            'postDateGmt' => $dataSeason->post_date_gmt,
            'postDate' => $dataSeason->post_date,
            'seasons' => $seasons,
            'topWeeks' => $topWeeks,
            'topMonths' => $topMonths,
            'relateds' => $dataRelateds
        ];

        return response()->json($movies, Response::HTTP_OK);
    }

    public function getData($query='', $type='', Request $request, $total=0, $perPage=0, $page=0) {
        $datas = DB::select($query);
        
        $movies = [];
        
        if( $type == 'ott-web' ) {
            $topWeeks = $this->tvshowService->getTopWeekOTT();
            $populars = $this->tvshowService->getTopWeekOTT();
        } else {
            if( $request->get('genre', '') != '' ) $type = $request->get('genre', '');
            $topWeeks = $this->tvshowService->getTopWeeks($type);
            $populars = $this->tvshowService->getPopulars($type);
        }
        
        $titleEpisode = '';
        $originalTitle = '';
        $link = '';
        $episodeNumber = '';
        $seasonNumber = '';
        $episodeId = '';
        $releaseDate = '';
        $src = '';
        $outlink = '';
        $chanel = '';
        $srcSet = [];
        foreach( $datas as $key => $data ) {
            $queryOriginalTitle = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_original_title' AND post_id =". $data->ID . " LIMIT 1;";
            $dataOriginalTitle = DB::select($queryOriginalTitle);
            if( count($dataOriginalTitle) > 0 ) {
                $originalTitle = $dataOriginalTitle[0]->meta_value;
            }
            
            $queryEpisode = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_seasons' AND post_id =". $data->ID . " LIMIT 1;";
            $dataEpisode = DB::select($queryEpisode);
            if( count($dataEpisode) > 0 ) {
                $episodeData = $dataEpisode[0]->meta_value;
                $episodeData = @unserialize($episodeData);
                
                $lastSeason = end($episodeData);
                $seasonNumber = $lastSeason['name'];     
                $episodeId = end($lastSeason['episodes']);
            }

            if( $episodeId != '' ) {
                $querryTitleEpisode = "SELECT p.post_title FROM wp_posts p WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) AND p.ID = " .  $episodeId . " ";
                $dataTitleEpisode = DB::select($querryTitleEpisode);
    
                if( count($dataTitleEpisode) > 0 ) {
                    $titleEpisode = $dataTitleEpisode[0]->post_title;
                }

                $queryMeta = "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ". $episodeId .";";

                $querySrcMeta = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
                                LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $data->ID .";";
                $dataSrcMeta = DB::select($querySrcMeta);
                
                $src = $this->imageUrlUpload.$dataSrcMeta[0]->meta_value;

                $dataMetas = DB::select($queryMeta);

                foreach($dataMetas as $dataMeta) {
                    if( $dataMeta->meta_key == '_episode_release_date' ) {
                        if (preg_match("/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/", $dataMeta->meta_value)) {
                            $newDataReleaseDate = explode('-', $dataMeta->meta_value);
                            $releaseDate = $newDataReleaseDate[0];
                        } else {
                            $releaseDate = $dataMeta->meta_value > 0 ? date('Y-m-d', $dataMeta->meta_value) : date('Y-m-d');
                        }
                    }

                    if( $dataMeta->meta_key == '_episode_number' ) {
                        $episodeNumber = $dataMeta->meta_value;
                    }
                }

                $constantChanelList = config('constants.chanelList');
                if( in_array($type, $constantChanelList) ) {
                    $queryChanel = "SELECT wt.description, wp.object_id FROM `wp_term_relationships` wp
                    LEFT JOIN wp_term_taxonomy wt ON wt.term_taxonomy_id = wp.term_taxonomy_id
                    RIGHT JOIN wp_terms t ON t.term_id = wt.term_id AND t.slug = '" . $type . "'
                    WHERE wt.taxonomy = 'category' AND wt.description != '' AND wp.object_id = ". $data->ID .";";
                } else {
                    $queryChanel = "SELECT wt.description, wp.object_id FROM `wp_term_relationships` wp
                    LEFT JOIN wp_term_taxonomy wt ON wt.term_taxonomy_id = wp.term_taxonomy_id
                    WHERE wt.taxonomy = 'category' AND wt.description != '' AND wp.object_id = ". $data->ID .";";
                }
                                
                $dataChanel = DB::select($queryChanel);

                if( count($dataChanel) > 0 ) {
                    $chanel = $dataChanel[0]->description;
                    $newChanel = explode('src="', $chanel);
                    $newChanel = explode('" alt', $newChanel[1]);
                    $newChanel = $newChanel[0];

                    $chanel = $this->getPathFromUploads($newChanel);
                } else {
                    $chanel = env('IMAGE_PLACEHOLDER');
                }

                $selectTitleEpisode = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt, p.post_date FROM wp_posts p ";
                $whereTitleEpisode = " WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) ";
                $whereTitleSub = " AND p.ID='". $episodeId ."' ";

                $queryTitle = $selectTitleEpisode . $whereTitleEpisode . $whereTitleSub;
                $dataEpisoTitle = DB::select($queryTitle);
                $episodeName = "";
                if( count($dataEpisoTitle) > 0 ) {
                    $link = 'episode/' . $dataEpisoTitle[0]->post_title;
                    $episodeName = $dataEpisoTitle[0]->post_name;
                }
            }

            $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                        left join wp_term_relationships t_r on t_r.object_id = p.ID
                        left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
                        left join wp_terms t on tx.term_id = t.term_id
                        where t.name != 'featured' AND t.name != '' AND p.ID = ". $data->ID .";";
            $dataTaxonomys = DB::select($queryTaxonomy);

            $genres = [];
            foreach( $dataTaxonomys as $dataTaxonomy ) {
                $genres[] = [
                    'name' => $dataTaxonomy->name,
                    'link' => $dataTaxonomy->slug,
                    'slug' => $dataTaxonomy->slug
                ];
            }
            if( $data->ID == 13686 ) {
                $srcSet = [];
            } else {
                $srcSet = $this->helperService->getAttachmentsByPostId($data->ID);
            }

            $movie = [
                'id' => $data->ID,
                'year' => $releaseDate,
                'genres' => $genres,
                'title' => $titleEpisode,
                'tvshowTitle' => $data->post_title,
                'originalTitle' => $originalTitle,
                'description' => $data->post_content,
                'src' => $src,
                'srcSet' => $srcSet,
                'link' => $link,
                'slug' => $episodeName,
                'outlink' => $outlink,
                'chanelImage' => $chanel,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
                'postDateGmt' => $data->post_date_gmt,
                'postDate' => $data->post_date
            ];
            $movies[$key] = $movie;
        }

        $data = [
            "total" => $total,
            "perPage" => $perPage,
            "currentPage" => $page,
            "data" => [
                'ottChanels' => config('constants.ottChanels'),
                'topWeeks' => $topWeeks,
                'populars' => $populars,
                'items' => $movies
            ]
        ];
        return $data;
    }

    public function getPathFromUploads($url) {
        $pos = strpos($url, '/uploads/');
        if ($pos !== false) {
            return substr($url, $pos);
        }
        return $url;
    }
}
