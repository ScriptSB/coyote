<?php

namespace Coyote\Http\Controllers\Microblog;

use Coyote\Http\Controllers\Controller;
use Coyote\Repositories\Contracts\MicroblogRepositoryInterface as Microblog;
use Coyote\Repositories\Contracts\UserRepositoryInterface as User;

class CommentController extends Controller
{
    /**
     * @var Microblog
     */
    private $microblog;

    /**
     * @var User
     */
    private $user;

    /**
     * Nie musze tutaj wywolywac konstruktora klasy macierzystej. Nie potrzeba...
     *
     * @param Microblog $microblog
     * @param User $user
     */
    public function __construct(Microblog $microblog, User $user)
    {
        $this->microblog = $microblog;
        $this->user = $user;
    }

    /**
     * Publikowanie komentarza na mikroblogu
     *
     * @param null|int $id
     * @return $this
     */
    public function save($id = null)
    {
        $this->validate(request(), [
            'text'          => 'required|string',
            'parent_id'     => 'sometimes|integer|exists:microblogs,id'
        ]);

        $microblog = $this->microblog->findOrNew($id);

        if ($id === null) {
            $user = auth()->user();
            $data = request()->only(['text', 'parent_id']) + ['user_id' => $user->id];
        } else {
            $this->authorize('update', $microblog);

            $user = $this->user->find($microblog->user_id, ['id', 'name', 'is_blocked', 'is_active', 'photo']);
            $data = request()->only(['text']);
        }

        $microblog->fill($data);
        $microblog->save();

        foreach (['name', 'is_blocked', 'is_active', 'photo'] as $key) {
            $microblog->$key = $user->$key;
        }

        return view('microblog._comment')->with('comment', $microblog)->with('microblog', ['id' => $microblog->parent_id]);
    }

    /**
     * Edycja komentarza na mikroblogu.
     *
     * @param int $id
     * @return string
     */
    public function edit($id)
    {
        $microblog = $this->microblog->findOrFail($id);
        $this->authorize('update', $microblog);

        return response($microblog->text);
    }

    /**
     * Usuniecie komentarza z mikrobloga
     *
     * @param int $id
     */
    public function delete($id)
    {
        $microblog = $this->microblog->findOrFail($id, ['id', 'user_id']);
        $this->authorize('delete', $microblog);

        $microblog->delete();
    }

    /**
     * Pokaz pozostale komentarze do wpisu
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id)
    {
        $comments = $this->microblog->getComments([$id]);
        return view('microblog._comments', ['id' => $id, 'comments' => $comments->slice(0, -2)]);
    }
}