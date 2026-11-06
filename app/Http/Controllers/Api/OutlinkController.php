<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OutlinkController extends Controller
{
    /**
     * @return \Illuminate\Http\Response
     */
    public function getOutlink(Request $request) {
        $postId = $request->get('postId', null);
        if (!$postId) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = "outlink_$postId";
        if (Cache::has($cacheKey)) {
            $data = Cache::get($cacheKey);
        } else {
            $data = [];
            $sql = "SELECT p.post_title, pm.meta_value, p.post_type, p.post_name FROM `wp_posts` as p INNER JOIN wp_postmeta as pm ON pm.post_id = p.ID and pm.meta_key IN ('_episode_url_link','_movie_url_link') WHERE p.ID = {$postId} LIMIT 1";
            $result = DB::selectOne($sql);
            $links = $result->meta_value;
            $title = $result->post_title;

            \preg_match_all('#(<Part \d>|)\bhttps?:\/\/[^\s()<>]+(?:\([\w\d]+\)|([^\s!"$%&()*+,\-./:;<=>?@[\]^`{|}~]|\/|[^\s!"\]$%&()*+<></]))#', $links, $matches);
            if (!empty($matches[0])) {
                $sortedLinks = [];
                foreach ($matches[0] as $value) {
                    if (!isset($sortedLinks[$value])) {
                        if (\strpos($value, 'videojs.vidground.com') !== false ) {
                            $sortedLinks[$value] = 1;
                        }
                        elseif (\strpos($value, 'short.ink') !== false) {
                            $sortedLinks[$value] = 2;
                        } 
                        elseif (\strpos($value, 'asianembed') !== false || \strpos($value, 'dembed1.com') !== false || \strpos($value, 'youtu') !== false || \strpos($value, 'naver') !== false) {
                            $sortedLinks[$value] = 3;
                        } else {
                            $sortedLinks[$value] = 4;
                        }
                    }
                }
                asort($sortedLinks);
                $sortedLinks = \array_keys($sortedLinks);
                $data = [
                    "title" => $title,
                    "link"  => $sortedLinks,
                    "backlink" => "https://moviehqu.com/{$result->post_type}/{$result->post_name}"
                ];

                Cache::forever($cacheKey, $data);
            }
        }

        return response()->json($data, Response::HTTP_OK);
    }
}
