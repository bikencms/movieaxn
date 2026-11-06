<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model
{
    protected $table = 'wp_posts';
    const CREATED_AT = 'post_date';
    const UPDATED_AT = 'post_modified';
    protected $primaryKey = 'ID';
    protected $fillable = [
        'post_title',
        'post_name',
        'post_content', 
        'post_status',
        'post_author',
        'comment_status',
        'ping_status',
        'guid', 
        'post_type', 
        'post_excerpt', 
        'to_ping', 
        'pinged',
        'post_content_filtered',
        'post_date',
        'post_date_gmt',
        'post_modified',
        'post_modified_gmt'
    ];
}
