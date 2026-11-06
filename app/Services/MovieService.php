<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\HelperService;
use Illuminate\Support\Facades\Cache;
class MovieService {
    protected $helperService;
    protected $imageUrlUpload;
    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;        
        $this->imageUrlUpload = env('IMAGE_URL_UPLOAD');
    }

    public function getTopWeeks()
    {
        $queryTopWeek = "SELECT p.ID, p.post_name, p.post_title, p.original_title, p.post_content, p.post_date_gmt FROM `wp_most_popular` mp
                            LEFT JOIN wp_posts p ON p.ID = mp.post_id
                            WHERE p.post_type = 'movie' AND p.post_title != '' AND mp.post_id != '' AND p.ID != ''
                            ORDER BY mp.7_day_stats DESC
                            LIMIT 5";
        return $this->getItems($queryTopWeek);
    }

    public function getPopulars() {
        $queryPopular = "SELECT p.ID, p.post_name, p.post_title, p.original_title, p.post_content, p.post_date_gmt FROM `wp_most_popular` wp
                            LEFT JOIN wp_posts p ON p.ID = wp.post_id 
                            WHERE p.post_type = 'movie' AND wp.post_id != '' AND p.ID != ''

                            ORDER BY wp.`1_day_stats` DESC
                            LIMIT 6";
        return $this->getItems($queryPopular);
    }

    public function getItems($query) {
        $items = [];
        $dataItems = DB::select($query);
        $releaseDate = '';
        $imageUrlUpload = env('IMAGE_URL_UPLOAD');
        $movieRunTime = '';
        $outlink = '';
        $srcSet = [];
        $originalTitle = '';
        $link = '';
        if( count($dataItems) > 0 ) {
            foreach ( $dataItems as $dataItem ) {
                $queryMeta = "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ". $dataItem->ID .";";
                $dataMetas = DB::select($queryMeta);
                if( count($dataMetas) > 0 ) {
                    foreach ( $dataMetas as $dataMeta ) {
                        if( $dataMeta->meta_key == '_movie_release_date' ) {
                            if (preg_match("/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/", $dataMeta->meta_value)) {
                                $newDataReleaseDate = explode('-', $dataMeta->meta_value);
                                $releaseDate = $newDataReleaseDate[0];
                            } else {
                                $releaseDate = $dataMeta->meta_value > 0 ? date('Y', $dataMeta->meta_value) : '';
                            }
                        }
                    
                        if( $dataMeta->meta_key == '_movie_run_time' ) {
                            $movieRunTime = $dataMeta->meta_value;
                        }

                        if( $dataMeta->meta_key == '_movie_original_title' ) {
                            $originalTitle = $dataMeta->meta_value;
                        }
                    }
                }
                $querySrcMeta = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
                            LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $dataItem->ID .";";
                $dataSrcMeta = DB::select($querySrcMeta);

                $src = $imageUrlUpload.$dataSrcMeta[0]->meta_value;

                $queryTaxonomy = "SELECT t.name, t.slug FROM `wp_posts` p
                                    left join wp_term_relationships t_r on t_r.object_id = p.ID
                                    left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'movie_genre'
                                    left join wp_terms t on tx.term_id = t.term_id
                where t.name != 'featured' AND t.name != '' AND p.ID = ". $dataItem->ID .";";
                $dataTaxonomys = DB::select($queryTaxonomy);

                $genres = [];
                if ( count($dataTaxonomys) > 0 ) {
                    foreach( $dataTaxonomys as $dataTaxonomy ) {
                        $genres[] = [
                            'name' => $dataTaxonomy->name,
                            'link' =>  $dataTaxonomy->slug
                        ];
                    }
                }
                //$srcSet = $this->helperService->getAttachmentsByPostId($dataItem->ID);

                $link = 'movie/' . $dataItem->post_name;
                $movie = [
                    'id' => $dataItem->ID,
                    'year' => $releaseDate,
                    'genres' => $genres,
                    'title' => $dataItem->post_title,
                    'originalTitle' => $originalTitle,
                    'description' => $dataItem->post_content,
                    'link' => $link,
                    'slug' => $dataItem->post_name,
                    'src' => $src,
                    'srcSet' => $srcSet,
                    'duration' => $movieRunTime,
                    'outlink' => $outlink
                ];
                $items[] = $movie;
            }
        }
        return $items;
    }

    /**
     * Return array with format postId => ['year', 'duration', 'originalTitle', 'src', 'srcSet']
     * @param array $postIds
     * @param array $fields
     * @param array $options
     * @return array
     */
    public function getMoviesMetadata(array $postIds = [], array $fields = [], array $options = []) {
        $data = [];

        if (empty($fields)) {
            $fields = [
                '_movie_release_date', 
                '_movie_run_time', 
                '_movie_original_title', 
                '_thumbnail_id'
            ];
        }

        $queryMeta = 'SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (' . \implode(',', $postIds) . ') AND meta_key IN (\'' . \implode('\',\'', $fields) . '\') GROUP BY post_id, meta_key LIMIT ' . (\count($postIds) * \count($fields));
        $metaData = DB::select($queryMeta);

        // Options
        $thumbnailVerticalOnly = \in_array('thumbnailVerticalOnly', $options);

        foreach ($metaData as $value) {
            $postId = (int) $value->post_id;
            if (!\array_key_exists($postId, $data)) {
                $data[$postId] = [];
            }
            
            if ($value->meta_key == '_movie_release_date') {
                if (\ctype_digit($value->meta_value)) {
                    $data[$postId]['year'] = \date('Y', (int) $value->meta_value);
                } else {
                    $year = \substr($value->meta_value, 0, 4);
                    $data[$postId]['year'] = \ctype_digit($year) ? $year : '';
                }
            } 
            elseif ($value->meta_key == '_movie_run_time') {
                $data[$postId]['duration'] = $value->meta_value;
            }
            elseif ($value->meta_key == '_movie_original_title') {
                $data[$postId]['originalTitle'] = $value->meta_value;
            }
            elseif ($value->meta_key == '_thumbnail_id') {
                $thumbnails = $this->getMovieThumbnail((int) $value->meta_value, $thumbnailVerticalOnly);
                $data[$postId] += $thumbnails;
            }
        }

        return $data;
    }

    /**
     * @param int $postmetaId
     * @param bool $verticalOnly
     * @return array
     */
    private function getMovieThumbnail(int $postmetaId, bool $verticalOnly = false) {
        $data = [
            'src' => '',
            'srcSet' => ''
        ];
        $sql = 'SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ' . $postmetaId . ' AND meta_key IN (\'_wp_attached_file\', \'_wp_attachment_metadata\') ORDER BY meta_id DESC LIMIT 2';
        $metaData = DB::select($sql);
        foreach ($metaData as $value) {
            if ($value->meta_key == '_wp_attached_file') {
                $data['src'] = $this->imageUrlUpload . $value->meta_value;
            }
            elseif ($value->meta_key == '_wp_attachment_metadata') {
                $srcSetVal = \unserialize($value->meta_value);
                if (!isset($monthYear)) {
                    $monthYear = \substr($srcSetVal['file'], 0, (\strpos($srcSetVal['file'], 'image_webp') !== false) ? 19 : 8);
                }

                $srcSet = $this->imageUrlUpload . $srcSetVal['file'] . ' ' . $srcSetVal['width'] . 'w';
                if (isset($srcSetVal['sizes'])) {
                    foreach ($srcSetVal['sizes'] as $size) {
                        if (!$verticalOnly || $size['height'] > $size['width']) {
                            $srcSet .= ', ' . $this->imageUrlUpload . $monthYear . $size['file'] . ' ' . $size['width'] . 'w';
                        }
                    }
                }
                $data['srcSet'] = $srcSet;
            }
        }

        return $data;
    }

    /**
     * @param int $postId
     * @return array
     */
    public function getCastsOfPost(int $postId)
    {
        $data = [];
        $sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = {$postId} AND meta_key = '_cast' LIMIT 1";
        $castsMeta = DB::selectOne($sql);
        $casts = empty($castsMeta) ? [] : @\unserialize($castsMeta->meta_value);
        if (empty($casts)) {
            return $data;
        }
        
        $newCasts = [];
        $casts = array_slice($casts, 0, 5, true);
        foreach($casts as $cast) {
            $sql = "SELECT DISTINCT ID as id, post_name as slug, post_title as name FROM wp_posts WHERE ID=".$cast['id']." AND post_status = 'publish' LIMIT 1";
            $dataCast = DB::select($sql);
            if( count($dataCast) > 0 ) {
                array_push($newCasts, $dataCast[0]);
            }
        }
        return $newCasts;
    }

    /**
     * Return array with format postId => [['name', 'slug'], ['name', 'slug']]
     * @param array $postIds
     * @return array
     */
    public function getMoviesGenres(array $postIds, string $genre='') {
        $data = [];

        if( $genre == '' ) {
            $queryByType = '';
        } else {
            $queryByType =  " AND c.slug IN (" . $genre . ") " ;
        }

        $sql = 'SELECT a.object_id , c.name, c.slug 
        FROM wp_term_relationships a 
        LEFT JOIN wp_term_taxonomy b ON a.term_taxonomy_id = b.term_taxonomy_id 
        LEFT JOIN wp_terms c ON b.term_id = c.term_id 
        WHERE a.object_id IN (' . \implode(',', $postIds) . ') AND b.taxonomy = \'movie_genre\' AND c.name != \'featured\' AND c.name != \'\'' . $queryByType;
        $queryData = DB::select($sql);
        foreach ($queryData as $value) {
            if (!isset($data[$value->object_id])) {
                $data[$value->object_id] = [];
            }
            $data[(int) $value->object_id][] = [
                'name' => $value->name,
                'slug' => $value->slug
            ];
        }

        return $data;
    }

    /**
     * @param int $postId
     * @param array $genres
     * @return array
     */
    public function getRelatedMovies(int $postId, array $genres)
    {
        if(!empty($genres)) {
            $genres = \implode("','", $genres);
            $sql = "SELECT DISTINCT p.ID as id, p.post_name as slug, p.post_status, p.post_title as title FROM `wp_posts` p
                left join wp_term_relationships t_r on t_r.object_id = p.ID
                left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'movie_genre'
                left join wp_terms t on tx.term_id = t.term_id
                where t.name != 'featured' AND p.post_status = 'publish' AND t.name != '' AND t.slug IN ('" . $genres . "') AND p.ID != " . $postId . " ORDER BY p.post_date DESC LIMIT 8";
        } else {
            $sql = "SELECT DISTINCT p.ID as id, p.post_name as slug, p.post_status, p.post_title as title FROM `wp_posts` p
                left join wp_term_relationships t_r on t_r.object_id = p.ID
                left join wp_term_taxonomy tx on t_r.term_taxonomy_id = tx.term_taxonomy_id AND tx.taxonomy = 'movie_genre'
                left join wp_terms t on tx.term_id = t.term_id
                where t.name != 'featured' AND t.name != '' AND p.post_status = 'publish' AND p.ID != ". $postId ." ORDER BY p.post_date DESC LIMIT 8";
        }

        $data = DB::select($sql);
        return $data;
    }
}