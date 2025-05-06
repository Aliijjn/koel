<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\DeleteSongsRequest;
use App\Http\Requests\API\SongFetchLyricRequest;
use App\Http\Requests\API\SongListRequest;
use App\Http\Requests\API\SongUpdateRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Models\User;
use App\Repositories\AlbumRepository;
use App\Repositories\ArtistRepository;
use App\Repositories\SongRepository;
use App\Services\LibraryManager;
use App\Services\SongService;
use App\Values\SongUpdateData;
use Illuminate\Contracts\Auth\Authenticatable;

class SongController extends Controller
{
    /** @param User $user */
    public function __construct(
        private readonly SongService $songService,
        private readonly SongRepository $songRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly ArtistRepository $artistRepository,
        private readonly LibraryManager $libraryManager,
        private readonly ?Authenticatable $user
    ) {
    }

    public function index(SongListRequest $request)
    {
        return SongResource::collection(
            $this->songRepository->getForListing(
                sortColumns: $request->sort ? explode(',', $request->sort) : ['songs.title'],
                sortDirection: $request->order ?: 'asc',
                ownSongsOnly: $request->boolean('own_songs_only'),
                scopedUser: $this->user
            )
        );
    }

    public function show(Song $song)
    {
        $this->authorize('access', $song);

        return SongResource::make($this->songRepository->getOne($song->id, $this->user));
    }

    public function update(SongUpdateRequest $request)
    {
        // Don't use SongRepository::findMany() because it'd be already catered to the current user.
        Song::query()->findMany($request->songs)->each(fn (Song $song) => $this->authorize('edit', $song));

        $updatedSongs = $this->songService->updateSongs($request->songs, SongUpdateData::fromRequest($request));
        $albums = $this->albumRepository->getMany($updatedSongs->pluck('album_id')->toArray());

        $artists = $this->artistRepository->getMany(
            array_merge(
                $updatedSongs->pluck('artist_id')->all(),
                $updatedSongs->pluck('album_artist_id')->all()
            )
        );

        return response()->json([
            'songs' => SongResource::collection($updatedSongs),
            'albums' => AlbumResource::collection($albums),
            'artists' => ArtistResource::collection($artists),
            'removed' => $this->libraryManager->prune(),
        ]);
    }

    // public function updateLyrics(SongFetchLyricRequest $request)
    // {
    //     Log::info("In API :)");
    //     $error_code = Response::HTTP_OK;
    //     $error_count = 0;
    //     $error_song_title = "";

    //     foreach ($request->songs as $song_id) {
    //         $song = $this->songRepository->findOne($song_id);
    //         if ($song === null) {
    //             $error_code = Response::HTTP_NOT_FOUND;
    //             $error_count++;
    //             $error_song_title = "Unknown song";
    //         }
    //         $response_code = $this->songService->fetchLyrics($song);
    //         if ($response_code >= Response::HTTP_BAD_REQUEST) {
    //             $error_code = $response_code;
    //             $error_count++;
    //             $error_song_title = $song->name;
    //         }
    //     }

    //     $response_msg = "";
    //     switch ($error_count) {
    //         case 0:  $response_msg = "Updated lyrics";                             break;
    //         case 1:  $response_msg = "Failed fetching $error_song_title's lyrics"; break;
    //         default: $response_msg = "Failed fetching $error_count lyrics";        break;
    //     }
        
    //     return response($error_code)->json([
    //         'response_msg' => $response_msg
    //     ]);
    // }

    // currently only supports fetching the lyrics of one song, would be nice to add support for multiple songs later
    public function updateLyrics(SongFetchLyricRequest $request)
    {
        $song = $this->songRepository->findOne($request->songs[0]);

        if ($song === null) {
            return response(404);
        }

        return response($this->songService->fetchLyrics($song));
    }

    public function destroy(DeleteSongsRequest $request)
    {
        // Don't use SongRepository::findMany() because it'd be already catered to the current user.
        Song::query()->findMany($request->songs)->each(fn (Song $song) => $this->authorize('delete', $song));

        $this->songService->deleteSongs($request->songs);

        return response()->noContent();
    }
}
