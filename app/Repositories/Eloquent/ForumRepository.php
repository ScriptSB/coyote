<?php

namespace Coyote\Repositories\Eloquent;

use Coyote\Repositories\Contracts\ForumRepositoryInterface;
use Coyote\Forum\Track as Forum_Track;
use Coyote\Topic\Track as Topic_Track;
use Coyote\Topic;
use Illuminate\Support\Collection;

class ForumRepository extends Repository implements ForumRepositoryInterface
{
    /**
     * @return \Coyote\Forum
     */
    public function model()
    {
        return 'Coyote\Forum';
    }

    /**
     * Gets categories grouped by sections. You need to pass either $userId or $sessionId (for anonymous users)
     *
     * @param int $userId
     * @param string $sessionId
     * @param null|int $parentId
     * @return mixed
     */
    public function groupBySections($userId, $sessionId, $parentId = null)
    {
        $this->applyCriteria();

        $sql = $this->model
                    ->select([
                        'forums.*',
                        'forum_track.marked_at AS forum_marked_at',
                        'topic_track.marked_at AS topic_marked_at',
                        'subject',
                        'topics.id AS topic_id',
                        'topics.slug AS topic_slug',
                        'posts.user_id',
                        'posts.created_at',
                        'posts.user_name AS anonymous_name',
                        'users.name AS user_name',
                        'users.photo',
                        'is_active',
                        'is_confirm'
                    ])
                    ->leftJoin('forum_track', function ($join) use ($userId, $sessionId) {
                        $join->on('forum_track.forum_id', '=', 'forums.id');

                        if ($userId) {
                            $join->on('forum_track.user_id', '=', $this->raw($userId));
                        } else {
                            $join->on('forum_track.session_id', '=', $this->raw("'" . $sessionId . "'"));
                        }
                    })
                    ->leftJoin('posts', 'posts.id', '=', 'forums.last_post_id')
                    ->leftJoin('users', 'users.id', '=', 'posts.user_id')
                    ->leftJoin('topics', 'topics.id', '=', 'posts.topic_id')
                    ->leftJoin('topic_track', function ($join) use ($userId, $sessionId) {
                        $join->on('topic_track.topic_id', '=', 'topics.id');

                        if ($userId) {
                            $join->on('topic_track.user_id', '=', $this->raw($userId));
                        } else {
                            $join->on('topic_track.session_id', '=', $this->raw("'" . $sessionId . "'"));
                        }
                    });

        if ($parentId) {
            $sql->where('parent_id', $parentId);
        }

        $result = $sql->get();

        foreach ($result as &$row) {
            $row->forum_unread = $row->created_at > $row->forum_marked_at;
            $row->topic_unread = $row->created_at > $row->topic_marked_at && $row->created_at > $row->forum_marked_at;
            $row->route = route('forum.topic', [$row->slug, $row->topic_id, $row->topic_slug]);
        }

        // execute query and fetch all forum categories
        $parents = $this->buildTree($result, $parentId);
        $parents = $this->fillUpSectionNames($parents);

        foreach ($parents as &$parent) {
            if (isset($parent->subs)) {
                foreach ($parent->subs as $child) {
                    $parent->topics += $child->topics;
                    $parent->posts += $child->posts;

                    if ($child->created_at > $parent->created_at) {
                        if ($child->forum_unread) {
                            $parent->forum_unread = true;
                        }

                        $parent->last_post_id = $child->last_post_id;
                        $parent->created_at = $child->created_at;
                        $parent->user_id = $child->user_id;
                        $parent->photo = $child->photo;
                        $parent->is_active = $child->is_active;
                        $parent->is_confirm = $child->is_confirm;
                        $parent->user_name = $child->user_name;
                        $parent->anonymous_name = $child->anonymous_name;
                        $parent->subject = $child->subject;
                        $parent->route = $child->route;
                    }
                }
            }
        }

        // finally... group by sections
        return $parents->groupBy('section');
    }

    /**
     * @param int $userId
     * @return mixed
     */
    public function getOrderForUser($userId)
    {
        $this->applyCriteria();

        $sql = $this->model
                    ->select([
                        'forums.*',
                        'forum_orders.is_hidden',
                        $this->raw('CASE WHEN forum_orders.section IS NOT NULL THEN forum_orders.section ELSE forums.section END')
                    ])
                    ->leftJoin('forum_orders', function ($join) use ($userId) {
                        $join->on('forum_orders.forum_id', '=', 'forums.id')
                                ->on('forum_orders.user_id', '=', $this->raw($userId));
                    })
                    ->whereNull('parent_id')
                    ->orderByRaw('(CASE WHEN forum_orders.order IS NOT NULL THEN forum_orders.order ELSE forums.order END)');

        $parents = $sql->get();

        return $this->fillUpSectionNames($parents)->groupBy('section');
    }

    /**
     * @param array $parents
     * @return array
     */
    private function fillUpSectionNames($parents)
    {
        // we must fill section field in every row just to group rows by section name.
        $section = '';
        foreach ($parents as &$parent) {
            if ($parent->section) {
                $section = $parent->section;
            } else {
                $parent->section = $section;
            }
        }

        return $parents;
    }

    /**
     * @param Collection $rowset
     * @param int $parentId
     * @return Collection
     */
    private function buildTree($rowset, $parentId = null)
    {
        // extract only main categories
        $parents = $rowset->where('parent_id', $parentId)->keyBy('id');

        // extract only children categories
        $children = $rowset->filter(function ($item) use ($parentId) {
            return $item->parent_id != $parentId;
        })->groupBy('parent_id');

        // we merge children with parent element
        foreach ($children as $parentId => $child) {
            if (!empty($parents[$parentId])) {
                $parents[$parentId]->subs = $child;
            }
        }

        return $parents;
    }

    /**
     * Builds up a category list that can be shown in a <select>
     *
     * @param string $key
     * @return array
     */
    public function forumList($key = 'slug')
    {
        $this->applyCriteria();

        $list = [];
        $result = $this->model->select(['forums.id', 'name', 'slug', 'parent_id'])->get();
        $tree = $this->buildTree($result);

        foreach ($tree as $parent) {
            $list[$parent->$key] = $parent->name;

            if (isset($parent->subs)) {
                foreach ($parent->subs as $child) {
                    $list[$child->$key] = str_repeat('&nbsp;', 4) . $child->name;
                }
            }
        }

        return $list;
    }

    /**
     * @return array
     */
    public function getTagClouds()
    {
        return $this->tagModel()
                ->orderBy($this->raw('COUNT(*)'), 'DESC')
                ->limit(10)
                ->get()
                ->lists('count', 'name')
                ->toArray();
    }

    /**
     * @param array $tags
     * @return mixed
     */
    public function getTagsWeight(array $tags)
    {
        return $this->tagModel()
                    ->whereIn('tags.name', $tags)
                    ->get()
                    ->lists('count', 'name')
                    ->toArray();
    }

    /**
     * @return mixed
     */
    private function tagModel()
    {
        return $this->app->make(Topic\Tag::class)
                ->select(['name', $this->raw('COUNT(*) AS count')])
                ->join('tags', 'tags.id', '=', 'tag_id')
                ->join('topics', 'topics.id', '=', 'topic_id')
                    ->whereNull('topics.deleted_at')
                    ->whereNull('tags.deleted_at')
                ->groupBy('name');
    }

    /**
     * Mark forum as read
     *
     * @param $forumId
     * @param $userId
     * @param $sessionId
     */
    public function markAsRead($forumId, $userId, $sessionId)
    {
        // builds data to update
        $attributes = ['forum_id' => $forumId] + ($userId ? ['user_id' => $userId] : ['session_id' => $sessionId]);
        // execute a query...
        Forum_Track::updateOrCreate($attributes, $attributes + ['marked_at' => $this->raw('NOW()'), 'forum_id' => $forumId]);
        $track = new Topic_Track();

        foreach ($attributes as $key => $value) {
            $track = $track->where($key, $value);
        }

        $track->delete();
    }
}
