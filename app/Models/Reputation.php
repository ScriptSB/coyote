<?php

namespace Coyote;

use Illuminate\Database\Eloquent\Model;

class Reputation extends Model
{
    const POST_VOTE = 1;
    const POST_ACCEPT = 2;
    const MICROBLOG = 3;
    const MICROBLOG_VOTE = 4;
    const WIKI_CREATE = 5;
    const WIKI_EDIT = 6;
    const CUSTOM = 7;
    const WIKI_RATE = 8;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type_id', 'user_id', 'value', 'excerpt', 'url', 'metadata'];

    /**
     * @var bool
     */
    public $timestamps = false;

    public function getMetadataAttribute($metadata)
    {
        return json_decode($metadata, true);
    }

    public function setMetadataAttribute($metadata)
    {
        $this->attributes['metadata'] = json_encode($metadata);
    }
}
