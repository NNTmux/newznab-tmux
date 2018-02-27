<?php

use Blacklight\XXX;
use App\Models\User;
use App\Models\Predb;
use App\Models\Video;
use Blacklight\AniDB;
use Blacklight\Books;
use Blacklight\Games;
use Blacklight\Movie;
use Blacklight\Music;
use App\Models\Release;
use Blacklight\Console;
use App\Models\Settings;
use Blacklight\Releases;
use App\Models\ReleaseNfo;
use App\Models\DnzbFailure;
use App\Models\ReleaseFile;
use App\Models\ReleaseRegex;
use Blacklight\ReleaseExtra;
use App\Models\ReleaseComment;

if (! User::isLoggedIn()) {
    $page->show403();
}

if ($page->request->has('id')) {
    $releases = new Releases(['Settings' => $page->settings]);
    $re = new ReleaseExtra;
    $data = Release::getByGuid($page->request->input('id'));
    $user = User::find(User::currentUserId());
    $cpapi = $user['cp_api'];
    $cpurl = $user['cp_url'];
    $releaseRegex = ReleaseRegex::query()->where('releases_id', '=', $data['id'])->first();

    if (! $data) {
        $page->show404();
    }

    if ($page->isPostBack()) {
        ReleaseComment::addComment($data['id'], $data['gid'], $page->request->input('txtAddComment'), User::currentUserId(), $_SERVER['REMOTE_ADDR']);
    }

    $nfo = ReleaseNfo::getReleaseNfo($data['id']);
    $reVideo = $re->getVideo($data['id']);
    $reAudio = $re->getAudio($data['id']);
    $reSubs = $re->getSubs($data['id']);
    $comments = ReleaseComment::getComments($data['id']);
    $similars = $releases->searchSimilar(
        $data['id'],
        $data['searchname'],
        6,
        $page->userdata['categoryexclusions']
    );
    $failed = DnzbFailure::getFailedCount($data['id']);

    $showInfo = '';
    if ($data['videos_id'] > 0) {
        $showInfo = Video::getByVideoID($data['videos_id']);
    }

    $mov = '';
    if ($data['imdbid'] !== '' && $data['imdbid'] !== 0000000) {
        $movie = new Movie(['Settings' => $page->settings]);
        $mov = $movie->getMovieInfo($data['imdbid']);
        if (! empty($mov['title'])) {
            $mov['title'] = str_replace(['/', '\\'], '', $mov['title']);
            $mov['actors'] = makeFieldLinks($mov, 'actors', 'movies');
            $mov['genre'] = makeFieldLinks($mov, 'genre', 'movies');
            $mov['director'] = makeFieldLinks($mov, 'director', 'movies');
            if (Settings::settingValue('site.trailers.trailers_display')) {
                $trailer = empty($mov['trailer']) || $mov['trailer'] === '' ? $movie->getTrailer($data['imdbid']) : $mov['trailer'];
                if ($trailer) {
                    $mov['trailer'] = sprintf(
                        '<iframe width="%d" height="%d" src="%s"></iframe>',
                        Settings::settingValue('site.trailers.trailers_size_x'),
                        Settings::settingValue('site.trailers.trailers_size_y'),
                        $trailer
                    );
                }
            }
        }
    }

    $xxx = '';
    if ($data['xxxinfo_id'] !== '' && $data['xxxinfo_id'] !== 0) {
        $x = new XXX();
        $xxx = $x->getXXXInfo($data['xxxinfo_id']);

        if (isset($xxx['trailers'])) {
            $xxx['trailers'] = $x->insertSwf($xxx['classused'], $xxx['trailers']);
        }

        if ($xxx && isset($xxx['title'])) {
            $xxx['title'] = str_replace(['/', '\\'], '', $xxx['title']);
            $xxx['actors'] = makeFieldLinks($xxx, 'actors', 'xxx');
            $xxx['genre'] = makeFieldLinks($xxx, 'genre', 'xxx');
            $xxx['director'] = makeFieldLinks($xxx, 'director', 'xxx');
        } else {
            $xxx = false;
        }
    }

    $game = '';
    if ($data['gamesinfo_id'] !== '') {
        $g = new Games();
        $game = $g->getGamesInfoById($data['gamesinfo_id']);
    }

    $mus = '';
    if ($data['musicinfo_id'] !== '') {
        $music = new Music(['Settings' => $page->settings]);
        $mus = $music->getMusicInfo($data['musicinfo_id']);
    }

    $book = '';
    if ($data['bookinfo_id'] !== '') {
        $b = new Books();
        $book = $b->getBookInfo($data['bookinfo_id']);
    }

    $con = '';
    if ($data['consoleinfo_id'] !== '') {
        $c = new Console();
        $con = $c->getConsoleInfo($data['consoleinfo_id']);
    }

    $AniDBAPIArray = '';
    if ($data['anidbid'] > 0) {
        $AniDB = new AniDB(['Settings' => $releases->pdo]);
        $AniDBAPIArray = $AniDB->getAnimeInfo($data['anidbid']);
    }

    $pre = Predb::getForRelease($data['predb_id']);

    $releasefiles = ReleaseFile::getReleaseFiles($data['id']);

    $page->smarty->assign('releasefiles', $releasefiles);
    $page->smarty->assign('release', $data);
    $page->smarty->assign('reVideo', $reVideo);
    $page->smarty->assign('reAudio', $reAudio);
    $page->smarty->assign('reSubs', $reSubs);
    $page->smarty->assign('nfo', $nfo);
    $page->smarty->assign('show', $showInfo);
    $page->smarty->assign('movie', $mov);
    $page->smarty->assign('xxx', $xxx);
    $page->smarty->assign('anidb', $AniDBAPIArray);
    $page->smarty->assign('music', $mus);
    $page->smarty->assign('con', $con);
    $page->smarty->assign('game', $game);
    $page->smarty->assign('book', $book);
    $page->smarty->assign('predb', $pre);
    $page->smarty->assign('comments', $comments);
    $page->smarty->assign('searchname', getSimilarName($data['searchname']));
    $page->smarty->assign('similars', $similars);
    $page->smarty->assign('privateprofiles', (int) Settings::settingValue('..privateprofiles') === 1);
    $page->smarty->assign('failed', $failed);
    $page->smarty->assign('cpapi', $cpapi);
    $page->smarty->assign('cpurl', $cpurl);
    $page->smarty->assign('regex', $releaseRegex);

    $page->meta_title = 'View NZB';
    $page->meta_keywords = 'view,nzb,description,details';
    $page->meta_description = 'View NZB for'.$data['searchname'];

    $page->content = $page->smarty->fetch('viewnzb.tpl');
    $page->render();
}
