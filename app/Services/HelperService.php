<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Post;
use App\Models\PostMeta;
use Telegram\Bot\Laravel\Facades\Telegram;
class HelperService {
    protected $imageUrlUpload;
    public function __construct()
    {
        $this->imageUrlUpload = env('IMAGE_URL_UPLOAD');
    }

    public function getSliderItems($query='')
    {
        $sliders = [];
        $srcSet = [];
        $src = '';
        $dataEpisodeName = '';
        $titleSlider = '';
        $seasonNumber = '';
        $episodeNumber = '';
        $year = '';
        $linkSlider = '';
        $postType = '';
        $sliderDatas = DB::select($query);
        foreach ( $sliderDatas as $sliderData ) {
            $dataQuery = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
            LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $sliderData->ID .";";

            $dataResult = DB::select($dataQuery);
            if( count($dataResult) > 0 ) {
                $src = $dataResult[0]->meta_value;
            }
            $titleSlider = $sliderData->post_title;
            $dataEpisodeName = $sliderData->post_name;
            $linkSlider = 'movie/' . $sliderData->post_title;
            
            if( $sliderData->post_type == 'movie' ) $postType = 'movie';

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
            if( $sliderData->post_type == 'tv_show' ) {

                $postType = 'tv_show';
                $queryEpisode = "SELECT meta_key, meta_value FROM `wp_postmeta` WHERE meta_key = '_seasons' AND post_id =". $sliderData->ID . " LIMIT 1;";
                $dataEpisode = DB::select($queryEpisode);
                
                $episodeData = $dataEpisode[0]->meta_value;
                $episodeData = unserialize($episodeData);
    
                $lastSeason = end($episodeData);
                $seasonNumber = $lastSeason['name'];

                $episodeId = end($lastSeason['episodes']);
                
                $select = "SELECT p.ID, p.post_title, p.post_name, p.original_title, p.post_content, p.post_date_gmt FROM wp_posts p ";
                $where = " WHERE  ((p.post_type = 'episode' AND (p.post_status = 'publish'))) ";
                $whereTitle = " AND p.ID='". $episodeId ."' ";
    
                $where = $where . $whereTitle;
                $query = $select . $where;
                $dataEpisoSlider = DB::select($query);
                
                if( count($dataEpisoSlider) > 0 ) {
                    $linkSlider = 'episode/' . $dataEpisoSlider[0]->post_title;
                    $dataEpisodeName = $dataEpisoSlider[0]->post_name;
                }

                $queryEpisodeNumber = "SELECT meta_value FROM wp_postmeta WHERE meta_key = '_episode_number' AND post_id = " . $episodeId . ";";
                $dataEpisodeNumber = DB::select($queryEpisodeNumber);
                $episodeNumber = $dataEpisodeNumber[0]->meta_value;

                if( $seasonNumber != '시즌 1' ) {
                    $titleSlider = $titleSlider . ' ' .  $seasonNumber;
                }

            }
            //$srcSet = $this->getAttachmentsByPostId($sliderData->ID);
            $sliders[] = [
                'id' => $sliderData->ID,
                'year' => $year,
                'title' => $titleSlider,
                'link' => $linkSlider,
                'slug' => $dataEpisodeName,
                'src' => $this->imageUrlUpload.$src,
                'srcSet' => $srcSet,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
                'postType' => $postType
            ];
        }
        return $sliders;
    }

    public function getAttachmentsByPostId($id) {
        $queryImage = "SELECT am.meta_value FROM wp_posts p LEFT JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' 
        LEFT JOIN wp_postmeta am ON am.post_id = pm.meta_value AND am.meta_key = '_wp_attached_file' WHERE p.post_status = 'publish' and p.ID =". $id .";";
        $dataImage = DB::select($queryImage);
        if ( count($dataImage) > 0 ) {
            $imgUrl = $dataImage[0]->meta_value;
        } else {
            $imgUrl = '';
        }
        $query = "SELECT meta_value FROM `wp_postmeta` WHERE meta_key = '_wp_attachment_metadata' AND post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'attachment' AND post_parent = " . $id . ") LIMIT 1;";
        $srcSet[$id] = [];
        $attachments = DB::select($query);
        if ( count($attachments) > 0 ) {
            $attachmentsData = unserialize($attachments[0]->meta_value);
            $fileDir = explode('/', $attachmentsData['file']);

            if ( $fileDir[0] == 'image_webp' ) {
                $fileDirReal = $fileDir[0] . '/' . $fileDir[1] . '/' . $fileDir[2] . "/";
            } else {
                $fileDirReal = $fileDir[0] . '/' . $fileDir[1] . "/";
            }
            
            if ( $imgUrl != 'image_webp/' . $attachmentsData['file'] ) {
                array_push($srcSet[$id], $this->imageUrlUpload.$imgUrl. " " . '300' . 'w');
            } else if ( $imgUrl != $attachmentsData['file'] ) {
                array_push($srcSet[$id], $this->imageUrlUpload.$imgUrl. " " . '300' . 'w');
            } else {
                array_push($srcSet[$id], $this->imageUrlUpload.$attachmentsData['file']. " " . '300' . 'w');
            }
            
            if ( isset($attachmentsData['sizes']) ) {
                foreach( $attachmentsData['sizes'] as $attachment ) {
                    if ($attachment['width'] != 300 && ($attachment['width'] < $attachment['height'])) {
                        array_push($srcSet[$id], $this->imageUrlUpload.$fileDirReal.$attachment['file']. " " . $attachment['width'] . 'w');
                    }
                }
            }
        }
        $srcSet = join(", ", $srcSet[$id]);
        
        return $srcSet;
    }

    public function makeCacheFirst() {

        //make cache front-end
        $headers = [
            "Content-Type" => "application/json",
            "x-nuxt-multi-cache-token" => 'O5ilxqx5k1ZzFMjEVr'
        ];
        Http::withHeaders($headers)->post('http://localhost:3000/__nuxt_multi_cache/purge/all');
        Http::get("http://localhost:3000/api/modified/");

        //make cache back-end
        // Http::get(route('homepage.index'));
        // Http::get(route('movies.index',  ['orderBy' => 'date', 'page' => 1]));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1]));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'k-drama']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'k-show']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'k-sisa']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'u-drama']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'ott-web']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'netflix']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'disney']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'apple-tv']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'tving']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'wavve']));
        // Http::get(route('tvshows.index',  ['orderBy' => 'date', 'page' => 1, 'type' => 'amazon-prime-video']));
        // Http::get(route('casts',  ['page' => 1]));
        // Http::get(route('casts',  ['orderBy' => 'nameDesc', 'page' => 1]));
        // Http::get(route('casts',  ['orderBy' => 'nameAsc', 'page' => 1]));
        return true;
    }

    public function getOutLink() {
        $outlink = env('OUTLINK', '');
        $response = Http::get($outlink);
        if( $response->ok() ) {
            return $response;
        } else {
            // $text = $outlink . ' not working';
            // Telegram::sendMessage([
            //     'chat_id' => env('TELEGRAM_CHANNEL_ID', '5968853987'),
            //     'parse_mode' => 'HTML',
            //     'text' => $text
            // ]);
            return '';
        }
    }

    public function getKokoatvLink(int $postId) {
        $outlink = env('OUTLINKSTEP2', '');
        $response = Http::get($outlink, [ 'key' => 123456, 'post_id' => $postId ]);
        if( $response->ok() ) {
            return json_decode($response);
        } else {
            // $text = $outlink . ' not working';
            // Telegram::sendMessage([
            //     'chat_id' => env('TELEGRAM_CHANNEL_ID', '5968853987'),
            //     'parse_mode' => 'HTML',
            //     'text' => $text
            // ]);
            return '';
        }
    }

    public function clearCastDupplicate($items) {
        $itemDup = [];
        foreach($items as $item) {
            foreach($items as $newItem) {
                $itemSrc = str_replace('w66_and_h66_face', 'w300_and_h450_bestv2', $item->src);
                $itemSrc = str_replace('w300_and_h450_bestv2e', '/w300_and_h450_bestv2', $itemSrc);

                $newItemSrc = str_replace('w66_and_h66_face', 'w300_and_h450_bestv2', $newItem->src);
                $newItemSrc = str_replace('w300_and_h450_bestv2e', '/w300_and_h450_bestv2', $newItemSrc);
                if ( $itemSrc == $newItemSrc && (strlen($item->name) > strlen($newItem->name)) ) {
                    //change status duplicate for item
                    $postItem = Post::find($item->id);
                    $postItem->post_status = 'duplicate';
                    $postItem->save();

                    //transfer tv_show for new item
                    $postMetaItem = PostMeta::select('meta_value')->where('post_id', $item->id)->where('meta_key', '_tv_show_cast')->first();
                    if( $postMetaItem != '' ) {
                        $tvShow = unserialize($postMetaItem->meta_value);
                        if(count($tvShow) > 0 ) {
                            foreach( $tvShow as $tv ) {
                                $dataMovie =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $newItem->id])->where('meta_key', '_tv_show_cast')->first();
                                if($dataMovie != '' && $dataMovie->meta_value != '' ) {
                                    $movies = unserialize($dataMovie->meta_value);
                                    //check exist and update movie of cast
                                    if( !in_array($tv, $movies) ) {
                                        array_push($movies, $tv);
                                        $metaPost = PostMeta::find($dataMovie->meta_id);
                                        $metaPost->meta_value = serialize($movies);
                                        $metaPost->save();
                                    }
                                } else if( $dataMovie != '' && $dataMovie->meta_value == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $newItem->id;
                                    $metaPost->meta_key = '_tv_show_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                } else if ( $dataMovie == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $newItem->id;
                                    $metaPost->meta_key = '_tv_show_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                }
                            }    
                        }
                    }
                    //transfer movie for new item
                    $postMetaItem = PostMeta::select('meta_value')->where('post_id', $item->id)->where('meta_key', '_movie_cast')->first();
                    if( $postMetaItem != '' ) {
                        $tvShow = unserialize($postMetaItem->meta_value);
                        if(count($tvShow) > 0 ) {
                            foreach( $tvShow as $tv ) {
                                $dataMovie =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $newItem->id])->where('meta_key', '_movie_cast')->first();
                                if($dataMovie != '' && $dataMovie->meta_value != '' ) {
                                    $movies = unserialize($dataMovie->meta_value);
                                    //check exist and update movie of cast
                                    if( !in_array($tv, $movies) ) {
                                        array_push($movies, $tv);
                                        $metaPost = PostMeta::find($dataMovie->meta_id);
                                        $metaPost->meta_value = serialize($movies);
                                        $metaPost->save();
                                    }
                                } else if( $dataMovie != '' && $dataMovie->meta_value == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $newItem->id;
                                    $metaPost->meta_key = '_movie_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                } else if ( $dataMovie == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $newItem->id;
                                    $metaPost->meta_key = '_movie_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                }
                            }    
                        }
                    } 
                } else if ( $itemSrc == $newItemSrc && (strlen($item->name) < strlen($newItem->name)) ) {
                    //remove duplicate for new item
                    //change status duplicate for item
                    $postItem = Post::find($newItem->id);
                    $postItem->post_status = 'duplicate';
                    $postItem->save();

                    //transfer tv_show for new item
                    $postMetaItem = PostMeta::select('meta_value')->where('post_id', $newItem->id)->where('meta_key', '_tv_show_cast')->first();
                    if( $postMetaItem != '' ) {
                        $tvShow = unserialize($postMetaItem->meta_value);
                        if(count($tvShow) > 0 ) {
                            foreach( $tvShow as $tv ) {
                                $dataMovie =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $item->id])->where('meta_key', '_tv_show_cast')->first();
                                if($dataMovie != '' && $dataMovie->meta_value != '' ) {
                                    $movies = unserialize($dataMovie->meta_value);
                                    //check exist and update movie of cast
                                    if( !in_array($tv, $movies) ) {
                                        array_push($movies, $tv);
                                        $metaPost = PostMeta::find($dataMovie->meta_id);
                                        $metaPost->meta_value = serialize($movies);
                                        $metaPost->save();
                                    }
                                } else if( $dataMovie != '' && $dataMovie->meta_value == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $item->id;
                                    $metaPost->meta_key = '_tv_show_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                } else if ( $dataMovie == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $item->id;
                                    $metaPost->meta_key = '_tv_show_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                }
                            }    
                        }
                    }
                    //transfer movie for new item
                    $postMetaItem = PostMeta::select('meta_value')->where('post_id', $newItem->id)->where('meta_key', '_movie_cast')->first();
                    if( $postMetaItem != '' ) {
                        $tvShow = unserialize($postMetaItem->meta_value);
                        if(count($tvShow) > 0 ) {
                            foreach( $tvShow as $tv ) {
                                $dataMovie =  PostMeta::select('meta_id','meta_value')->where(['post_id' => $item->id])->where('meta_key', '_movie_cast')->first();
                                if($dataMovie != '' && $dataMovie->meta_value != '' ) {
                                    $movies = unserialize($dataMovie->meta_value);
                                    //check exist and update movie of cast
                                    if( !in_array($tv, $movies) ) {
                                        array_push($movies, $tv);
                                        $metaPost = PostMeta::find($dataMovie->meta_id);
                                        $metaPost->meta_value = serialize($movies);
                                        $metaPost->save();
                                    }
                                } else if( $dataMovie != '' && $dataMovie->meta_value == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $item->id;
                                    $metaPost->meta_key = '_movie_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                } else if ( $dataMovie == '' ) {
                                    $movies = [];
                                    array_push($movies, $tv);
                                    $metaPost = new PostMeta;
                                    $metaPost->post_id = $item->id;
                                    $metaPost->meta_key = '_movie_cast';
                                    $metaPost->meta_value = serialize($movies);
                                    $metaPost->save();
                                }
                            }    
                        }
                    } 
                } else if (  $itemSrc == $newItemSrc && (strlen($item->name) == strlen($newItem->name)) && $item->id != $newItem->id ) {
                    array_push($itemDup, $item->id);
                }
            }
        }
        for ($i = 0; $i < count($itemDup)/2; $i++) {
            $postItem = Post::find($itemDup[$i]);
            $postItem->post_status = 'duplicate';
            $postItem->save();
        }
    }
}
