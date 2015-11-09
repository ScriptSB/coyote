<?php

namespace Coyote;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    /**
     * Pobiera liste sesji uzytkownikow ktorzy odwiedzaja dana strone
     *
     * @param null $path
     * @return mixed
     */
    public function getViewers($path = null)
    {
        $sql = $this->select(['user_id', 'url', 'robot', 'users.name AS name', 'groups.name AS group'])
                    ->leftJoin('users', 'users.id', '=', \DB::raw('user_id'))
                    ->leftJoin('groups', 'groups.id', '=', \DB::raw('group_id'));

        if ($path) {
            $sql->where('url', 'LIKE', '%' . $path . '%');
        }

        return $sql->get();
    }
}