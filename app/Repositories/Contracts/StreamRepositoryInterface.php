<?php

namespace Coyote\Repositories\Contracts;

interface StreamRepositoryInterface extends RepositoryInterface
{
    /**
     * Take X last activities
     *
     * @param $limit
     * @param int $offset
     * @param array $objects
     * @param array $verbs
     * @param array $targets
     * @return mixed
     */
    public function take($limit, $offset = 0, $objects = [], $verbs = [], $targets = []);

    /**
     * Find activities by object, id and actions (verbs)
     *
     * @param $objects
     * @param array $id
     * @param array $verbs
     * @return mixed
     */
    public function findByObject($objects, $id = [], $verbs = []);

    /**
     * @param int $topicId
     * @return mixed
     */
    public function takeForTopic($topicId);
}
