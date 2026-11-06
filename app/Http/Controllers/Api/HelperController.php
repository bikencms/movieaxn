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
class HelperController extends Controller
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

}