<?php

namespace Coyote\Http\Controllers\Microblog;

use Coyote\Http\Controllers\Controller;
use Coyote\Repositories\Eloquent\MicroblogRepository as Microblog;
use Coyote\Repositories\Eloquent\UserRepository as User;
use Illuminate\Http\Request;

/**
 * Class SubmitController
 * @package Coyote\Http\Controllers\Microblog
 */
class SubmitController extends Controller
{
    private $microblog;
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
     * Publikowanie wpisu na mikroblogu
     *
     * @param null|int $id
     * @return $this
     */
    public function index($id = null)
    {
        $this->validate(request(), [
            'text'          => 'required|string',
            'parent_id'     => 'sometimes|integer|exists:microblogs,id'
        ]);

        $microblog = $this->microblog->firstOrNew(['id' => $id]);
        $data = request()->all();

        if ($id === null) {
            $user = auth()->user();
            $data['user_id'] = $user->id;

            $media = ['image' => []];
        } else {
            $user = $this->user->find($microblog->user_id, ['id', 'name', 'is_blocked', 'is_active', 'photo']);
            $media = $microblog->media;

            if (empty($media['image'])) {
                $media = ['image' => []];
            }
        }

        if (request()->has('thumbnail') || count($media['image']) > 0) {
            $delete = array_diff($media['image'], (array) request()->get('thumbnail'));

            foreach ($delete as $name) {
                unlink(public_path('storage/microblog/' . $name));
                unset($media['image'][array_search($name, $media['image'])]);
            }

            $insert = array_diff((array) request()->get('thumbnail'), $media['image']);

            foreach ($insert as $name) {
                rename(public_path('storage/tmp/' . $name), public_path('storage/microblog/' . $name));
                $media['image'][] = $name;
            }

            $microblog->media = $media;
        }

        $microblog->fill($data);
        $microblog->save();

        return view('microblog._microblog', [
            'user_id'               => $user->id,
            'name'                  => $user->name,
            'is_blocked'            => $user->is_blocked,
            'is_active'             => $user->is_active,
            'photo'                 => $user->photo
        ])->with($microblog->toArray());
    }

    /**
     * Edycja wpisu na mikroblogu. Odeslanie formularza zawierajacego tresc + zalaczniki
     *
     * @param int $id
     * @return $this
     */
    public function edit($id)
    {
        $microblog = $this->microblog->findOrFail($id);
        $thumbnails = [];

        if (isset($microblog->media['image'])) {
            foreach ($microblog->media['image'] as $name) {
                $thumbnails[$name] = url('storage/microblog/' . $name);
            }
        }

        return view('microblog._edit', ['thumbnails' => $thumbnails])->with($microblog->toArray());
    }

    /**
     * Upload pliku na serwer wraz z wczesniejsza walidacja
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $this->validate($request, [
            'photo'             => 'required|image'
        ]);

        if ($request->file('photo')->isValid()) {
            $fileName = uniqid() . '.' . $request->file('photo')->getClientOriginalExtension();
            $request->file('photo')->move(public_path() . '/storage/tmp/', $fileName);

            return response()->json([
                'url' => url('storage/tmp/' . $fileName),
                'name' => $fileName
            ]);
        }
    }
}