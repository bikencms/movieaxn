<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HelperService;
use App\Services\TvshowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EpisodeController extends Controller
{
    protected $imageUrlUpload;
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
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $episodeTitle
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $watch = $request->get('watch', '');
        $countViewId = $request->get('countViewId', '');
        if ($countViewId) {
            Http::get('https://moviehqu.com/rest-api/popular/episode/' . $countViewId . '/');
            return response()->json(['status' => 'OK'], Response::HTTP_OK);
        }
        if( $watch != '' ) {
            $outLink = $this->helperService->getKokoatvLink($watch);
            if( isset($outLink->link) && $outLink->link != '' ) {
                $item['watchLinks'] = $outLink->link;
            } else {
                $item['watchLinks'] = [];
            }
            return response()->json($item, Response::HTTP_OK);
        } else {
            $title = $request->get('title', '');
            $newTitle = urlencode($title);
    
            $imageUrlUpload = env('IMAGE_URL_UPLOAD', '/uploads');
    
            $select = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt, p.post_date FROM wp_posts p ";
            $where = " WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) ";
            $whereTitle = " AND (p.post_title='". $title ."' OR p.post_name='". $newTitle ."')";
    
            $where = $where . $whereTitle;
            $movies = [];
            $query = $select . $where;
            $dataPost = DB::select($query);
            
            $tvshowTitle = '';
            $tvshowSlug = '';
            if (count($dataPost) == 0) {
                return response()->json($movies, Response::HTTP_NOT_FOUND);
            }
            //get all seasons and episode
            $src = '';
            $casts = [];
            $queryGetIdTvShow = "SELECT post_id, meta_value, meta_key FROM wp_postmeta WHERE post_id=" . $dataPost[0]->ID .  " AND meta_key='_tv_show_id'";
            $dataGetIdTvShow = DB::select($queryGetIdTvShow);
            if( count($dataGetIdTvShow) > 0 ) {
                $tvShowId = $dataGetIdTvShow[0]->meta_value;

                //get casts of tv-show
                $queryCast = "SELECT meta_value FROM wp_postmeta WHERE post_id=" . $tvShowId . " AND meta_key='_cast' LIMIT 1;";
                $dataCast = DB::select($queryCast);
                if( count($dataCast) > 0 && $dataCast[0]->meta_value != '' ) {
                    $unserializeCasts = @unserialize($dataCast[0]->meta_value);
                    if($unserializeCasts != '' && count($unserializeCasts) > 0) {
                        $castDatas = $unserializeCasts;
                        $castDatas = array_values(array_unique($castDatas, SORT_REGULAR));
                        //get data of person
                        $castDatas = array_slice($castDatas, 0, 5, true);
                        foreach ( $castDatas as $castData ) {
                            $queryCasts = "SELECT DISTINCT p.ID as id, p.post_name as slug, p.post_title as name, wp.meta_value as src FROM wp_posts p
                            LEFT JOIN wp_postmeta wp ON wp.post_id = p.ID AND wp.meta_key = '_person_image_custom'
                            WHERE p.ID=".$castData['id']." and p.post_status = 'publish' ORDER BY p.post_date LIMIT 5;";
                            $cast = DB::select($queryCasts);
                            if( count($cast) > 0 ) {
                                array_push($casts, $cast[0]);
                            }
                        }
                    }
                }
                $querySeasonEpisode = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, pm.meta_value, pm.meta_key, pm.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID WHERE p.ID=" . $tvShowId . " AND pm.meta_key='_seasons' ORDER BY p.ID ASC LIMIT 1;";
                $tvshowTitleData = DB::select($querySeasonEpisode);
                $tvshowTitle = $tvshowTitleData[0]->post_title;
                $tvshowSlug = $tvshowTitleData[0]->post_name;
                $seasons = $this->tvshowService->getSeasons($tvshowTitleData);

                $dataSeason = $dataPost[0];
                $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                                left join wp_term_relationships t_r on t_r.object_id = p.ID
                                left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'tv_show_genre'
                                left join wp_terms t on tx.term_id = t.term_id
                                where t.name != 'featured' AND t.name != '' AND p.ID = ". $tvshowTitleData[0]->ID .";";
                $dataTaxonomys = DB::select($queryTaxonomy);
                $genres = [];
                foreach( $dataTaxonomys as $dataTaxonomy ) {
                    $genres[] = [
                        'name' => $dataTaxonomy->name,
                        'link' => $dataTaxonomy->slug,
                        'slug' => $dataTaxonomy->slug
                    ];
                }
                //outlink only show in into
                $outLink = $this->helperService->getOutLink();
                if ( $outLink != '' ) {
                    $outlink =  $outLink . '?pid=' . $dataSeason->ID;
                } else {
                    $outlink = '';
                }
                $querySrcMeta = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
                                    LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $tvshowTitleData[0]->ID .";";
                $dataSrcMeta = DB::select($querySrcMeta);
                
                if( count($dataSrcMeta) > 0 ) {
                    $src = $imageUrlUpload.$dataSrcMeta[0]->meta_value;
                }
                
                $seasonName = '';
                foreach ( $seasons as $season ) {
                    foreach ( $season['episodes'] as $episode ) {
                        if ( $dataSeason->post_title == $episode['title'] ) {
                            $seasonName = $season['name'];
                            break;
                        }
                    }
                }
                $srcSet = $this->helperService->getAttachmentsByPostId($tvshowTitleData[0]->ID);

                //get original title
                $queryOriginalTitle = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_original_title' AND post_id =". $tvshowTitleData[0]->ID . " LIMIT 1;";
                $dataOriginalTitle = DB::select($queryOriginalTitle);
                if( count($dataOriginalTitle) > 0 ) {
                    $originalTitle = $dataOriginalTitle[0]->meta_value;
                }

                $movies = [
                    'id' => $dataSeason->ID,
                    'title' => $dataSeason->post_title,
                    'originalTitle' => $originalTitle,
                    'description' => $tvshowTitleData[0]->post_content,
                    'genres' => $genres,
                    'src' => $src,
                    'srcSet' => $srcSet,
                    'outlink' => $outlink,
                    'postDateGmt' => $dataSeason->post_date_gmt,
                    'postDate' => $dataSeason->post_date,
                    'seasonName' => $seasonName,
                    'tvshowTitle' => $tvshowTitle,
                    'tvshowSlug' => $tvshowSlug,
                    'seasons' => $seasons,
                    'casts' => $casts
                ];
            }
            return response()->json($movies, Response::HTTP_OK);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
