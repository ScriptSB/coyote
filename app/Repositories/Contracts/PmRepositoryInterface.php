<?php

namespace Coyote\Repositories\Contracts;

use Illuminate\Http\Request;

interface PmRepositoryInterface extends RepositoryInterface
{
    /**
     * Get last messages
     *
     * @param int $userId
     * @param int $limit
     * @return mixed
     */
    public function takeForUser($userId, $limit = 10);

    /**
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate($userId, $perPage = 10);

    /**
     * Gets conversation
     *
     * @param int $userId
     * @param string $rootId
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function talk($userId, $rootId, $limit = 10, $offset = 0);

    /**
     * Submit a new message
     *
     * @param \Coyote\User $user
     * @param array $payload
     * @throws \Exception
     */
    public function submit(\Coyote\User $user, array $payload);

    /**
     * Mark notifications as read
     *
     * @param int $id
     */
    public function markAsRead($id);
}
