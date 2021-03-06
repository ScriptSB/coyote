<?php

namespace Coyote\Http\Controllers\Microblog;

use Coyote\Http\Controllers\Controller;
use Coyote\Services\Parser\Helpers\Login as LoginHelper;
use Coyote\Services\Parser\Helpers\Hash as HashHelper;
use Coyote\Repositories\Contracts\MicroblogRepositoryInterface as MicroblogRepository;
use Coyote\Repositories\Contracts\UserRepositoryInterface as UserRepository;
use Coyote\Services\Alert\Container;
use Coyote\Services\Stream\Activities\Create as Stream_Create;
use Coyote\Services\Stream\Activities\Update as Stream_Update;
use Coyote\Services\Stream\Activities\Delete as Stream_Delete;
use Coyote\Services\Stream\Objects\Microblog as Stream_Microblog;
use Coyote\Services\Stream\Objects\Comment as Stream_Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * @var MicroblogRepository
     */
    private $microblog;

    /**
     * @var UserRepository
     */
    private $user;

    /**
     * Nie musze tutaj wywolywac konstruktora klasy macierzystej. Nie potrzeba...
     *
     * @param MicroblogRepository $microblog
     * @param UserRepository $user
     */
    public function __construct(MicroblogRepository $microblog, UserRepository $user)
    {
        $this->microblog = $microblog;
        $this->user = $user;
    }

    /**
     * Publikowanie komentarza na mikroblogu
     *
     * @param Request $request
     * @param \Coyote\Microblog $microblog
     * @return \Illuminate\Http\JsonResponse
     */
    public function save(Request $request, $microblog)
    {
        $this->validate($request, [
            'parent_id'     => 'sometimes|integer|exists:microblogs,id',
            'text'          => 'required|string|max:5000|throttle:' . $microblog->id
        ]);

        if (empty($microblog->id)) {
            $user = auth()->user();
            $data = $request->only(['text', 'parent_id']) + ['user_id' => $user->id];
        } else {
            $this->authorize('update', $microblog);

            $user = $this->user->find($microblog->user_id, ['id', 'name', 'is_blocked', 'is_active', 'photo']);
            $data = $request->only(['text']);
        }

        $microblog->fill($data);
        $isSubscribed = false;

        $this->transaction(function () use ($microblog, $user, &$isSubscribed) {
            $microblog->save();

            // we need to get parent entry only for notification
            /** @var \Coyote\Microblog $parent */
            $parent = $microblog->parent()->first();

            // we need to parse text first (and store it in cache)
            $microblog->text = app('parser.microblog.comment')->parse($microblog->text);
            // get parsed content from cache
            $parent->text = app('parser.microblog')->parse($parent->text);

            if ($microblog->wasRecentlyCreated) {
                $subscribers = $parent->subscribers()->lists('user_id')->toArray();
                $alert = new Container();

                // we need to send alerts AFTER saving comment to database because we need ID of comment
                $alertData = [
                    'microblog_id'=> $microblog->parent_id, // <-- parent_id NOT id (to generate currect alerts object_id)
                    'content'     => $microblog->text,
                    'excerpt'     => excerpt($microblog->text),
                    'sender_id'   => $user->id,
                    'sender_name' => $user->name,
                    'subject'     => excerpt($parent->text), // original exerpt of parent entry
                    'url'         => route('microblog.view', [$parent->id], false) . '#comment-' . $microblog->id
                ];

                if ($subscribers) {
                    // new comment. should we send a notification?
                    $alert->attach(
                        app('alert.microblog.subscriber')->with($alertData)->setUsersId($subscribers)
                    );
                }

                $helper = new LoginHelper();
                // get id of users that were mentioned in the text
                $usersId = $helper->grab($microblog->text);

                if (!empty($usersId)) {
                    $alert->attach(app('alert.microblog.login')->with($alertData)->setUsersId($usersId));
                }

                // send a notify
                $alert->notify();

                // now we can add user to subscribers list (if he's not in there yet)
                // after that he will receive notification about other users comments
                if (!in_array($user->id, $subscribers)) {
                    $count = $this->microblog->where('parent_id', $parent->id)->where('user_id', $user->id)->count();

                    if ($count == 1) {
                        $parent->subscribers()->create(['user_id' => $user->id]);
                        $isSubscribed = true;
                    }
                } else {
                    $isSubscribed = true;
                }

                $activity = Stream_Create::class;
            } else {
                $activity = Stream_Update::class;
            }

            $helper = new HashHelper();
            $microblog->setTags($helper->grab($microblog->text));

            // map microblog object into stream activity object
            $object = (new Stream_Comment())->map($microblog);
            $target = (new Stream_Microblog())->map($parent);

            // put item into stream activity
            stream($activity, $object, $target);
        });

        foreach (['name', 'is_blocked', 'is_active', 'photo'] as $key) {
            $microblog->$key = $user->$key;
        }

        $view = view('microblog.comment', ['comment' => $microblog, 'microblog' => ['id' => $microblog->parent_id]]);

        return response()->json([
            'html' => $view->render(),
            'subscribe' => (int) $isSubscribed
        ]);
    }

    /**
     * Edycja komentarza na mikroblogu.
     *
     * @param \Coyote\Microblog
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit($microblog)
    {
        $this->authorize('update', $microblog);

        return response($microblog->text);
    }

    /**
     * Usuniecie komentarza z mikrobloga
     *
     * @param \Coyote\Microblog $microblog
     */
    public function delete($microblog)
    {
        $this->authorize('delete', $microblog);

        $this->transaction(function () use ($microblog) {
            $microblog->delete();

            $parent = $microblog->parent()->first();
            $object = (new Stream_Comment())->map($microblog);
            $target = (new Stream_Microblog())->map($parent);

            stream(Stream_Delete::class, $object, $target);
        });
    }

    /**
     * Pokaz pozostale komentarze do wpisu
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id)
    {
        $parser = app('parser.microblog.comment');
        $comments = $this->microblog->getComments([$id])->slice(0, -2);

        foreach ($comments as &$comment) {
            $comment->text = $parser->parse($comment->text);
        }
        return view('microblog.comments', ['id' => $id, 'comments' => $comments]);
    }
}
