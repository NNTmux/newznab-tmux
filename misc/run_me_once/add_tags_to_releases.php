<?php

use App\Models\Release;
use App\Models\Category;
use Blacklight\ColorCLI;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$colorCli = new ColorCLI();
$releases = Release::query()->select(['id', 'categories_id'])->get();

$count = $releases->count();
$relstring = $count === 1 ? ' release' : ' releases';

$colorCli->info('Tagging '.$count.$relstring);

foreach ($releases as $release) {
    switch ($release->categories_id) {
        case 10:
            $release->retag(Category::TAG_OTHER_MISC);
            break;
        case 20:
            $release->retag(Category::TAG_OTHER_HASHED);
            break;
        case 1010:
            $release->retag(Category::TAG_GAME_NDS);
            break;
        case 1020:
            $release->retag(Category::TAG_GAME_PSP);
            break;
        case 1030:
            $release->retag(Category::TAG_GAME_WII);
            break;
        case 1040:
            $release->retag(Category::TAG_GAME_XBOX);
            break;
        case 1050:
            $release->retag(Category::TAG_GAME_XBOX360);
            break;
        case 1060:
            $release->retag(Category::TAG_GAME_WIIWARE);
            break;
        case 1070:
            $release->retag(Category::TAG_GAME_XBOX360DLC);
            break;
        case 1080:
            $release->retag(Category::TAG_GAME_PS3);
            break;
        case 1110:
            $release->retag(Category::TAG_GAME_3DS);
            break;
        case 1120:
            $release->retag(Category::TAG_GAME_PSVITA);
            break;
        case 1130:
            $release->retag(Category::TAG_GAME_WIIU);
            break;
        case 1140:
            $release->retag(Category::TAG_GAME_XBOXONE);
            break;
        case 1180:
            $release->retag(Category::TAG_GAME_PS4);
            break;
        case 1999:
            $release->retag(Category::TAG_GAME_OTHER);
            break;
        case 2010:
            $release->retag(Category::TAG_MOVIE_FOREIGN);
            break;
        case 2030:
            $release->retag(Category::TAG_MOVIE_SD);
            break;
        case 2040:
            $release->retag(Category::TAG_MOVIE_HD);
            break;
        case 2045:
            $release->retag(Category::TAG_MOVIE_UHD, Category::TAG_MOVIE_HD);
            break;
        case 2050:
            $release->retag(Category::TAG_MOVIE_3D);
            break;
        case 2060:
            $release->retag(Category::TAG_MOVIE_BLURAY);
            break;
        case 2070:
            $release->retag(Category::TAG_MOVIE_DVD);
            break;
        case 2080:
            $release->retag(Category::TAG_MOVIE_WEBDL, Category::TAG_MOVIE_HD);
            break;
        case 2090:
            $release->retag(Category::TAG_MOVIE_X265, Category::TAG_MOVIE_HD);
            break;
        case 2999:
            $release->retag(Category::TAG_MOVIE_OTHER);
            break;
        case 3010:
            $release->retag(Category::TAG_MUSIC_MP3);
            break;
        case 3020:
            $release->retag(Category::TAG_MUSIC_VIDEO);
            break;
        case 3030:
            $release->retag(Category::TAG_MUSIC_AUDIOBOOK);
            break;
        case 3040:
            $release->retag(Category::TAG_MUSIC_LOSSLESS);
            break;
        case 3060:
            $release->retag(Category::TAG_MUSIC_FOREIGN);
            break;
        case 3999:
            $release->retag(Category::TAG_MUSIC_OTHER);
            break;
        case 4010:
            $release->retag(Category::TAG_PC_0DAY);
            break;
        case 4020:
            $release->retag(Category::TAG_PC_ISO);
            break;
        case 4030:
            $release->retag(Category::TAG_PC_MAC);
            break;
        case 4040:
            $release->retag(Category::TAG_PC_PHONE_OTHER);
            break;
        case 4050:
            $release->retag(Category::TAG_PC_GAMES);
            break;
        case 4060:
            $release->retag(Category::TAG_PC_PHONE_IOS);
            break;
        case 4070:
            $release->retag(Category::TAG_PC_PHONE_ANDROID);
            break;
        case 5010:
            $release->retag(Category::TAG_TV_WEBDL, Category::TAG_TV_HD);
            break;
        case 5020:
            $release->retag(Category::TAG_TV_FOREIGN);
            break;
        case 5030:
            $release->retag(Category::TAG_TV_SD);
            break;
        case 5040:
            $release->retag(Category::TAG_TV_HD);
            break;
        case 5045:
            $release->retag(Category::TAG_TV_UHD, Category::TAG_TV_HD);
            break;
        case 5060:
            $release->retag(Category::TAG_TV_SPORT);
            break;
        case 5070:
            $release->retag(Category::TAG_TV_ANIME);
            break;
        case 5080:
            $release->retag(Category::TAG_TV_DOCU);
            break;
        case 5090:
            $release->retag(Category::TAG_TV_X265, Category::TAG_TV_HD);
            break;
        case 5999:
            $release->retag(Category::TAG_TV_OTHER);
            break;
        case 6010:
            $release->retag(Category::TAG_XXX_DVD);
            break;
        case 6020:
            $release->retag(Category::TAG_XXX_WMV);
            break;
        case 6030:
            $release->retag(Category::TAG_XXX_XVID);
            break;
        case 6040:
            $release->retag(Category::TAG_XXX_X264);
            break;
        case 6041:
            $release->retag(Category::TAG_XXX_CLIPHD, Category::TAG_XXX_X264);
            break;
        case 6042:
            $release->retag(Category::TAG_XXX_CLIPSD, Category::TAG_XXX_SD);
            break;
        case 6045:
            $release->retag(Category::TAG_XXX_UHD, Category::TAG_XXX_X264);
            break;
        case 6050:
            $release->retag(Category::TAG_XXX_PACK);
            break;
        case 6060:
            $release->retag(Category::TAG_XXX_IMAGESET);
            break;
        case 6080:
            $release->retag(Category::TAG_XXX_SD);
            break;
        case 6090:
            $release->retag(Category::TAG_XXX_WEBDL);
            break;
        case 6999:
            $release->retag(Category::TAG_XXX_OTHER);
            break;
        case 7010:
            $release->retag(Category::TAG_BOOKS_MAGAZINES);
            break;
        case 7020:
            $release->retag(Category::TAG_BOOKS_EBOOK);
            break;
        case 7030:
            $release->retag(Category::TAG_BOOKS_COMICS);
            break;
        case 7040:
            $release->retag(Category::TAG_BOOKS_TECHNICAL);
            break;
        case 7060:
            $release->retag(Category::TAG_BOOKS_FOREIGN);
            break;
        case 7999:
            $release->retag(Category::TAG_BOOKS_UNKNOWN);
            break;
    }

    $colorCli->headerOver('.');
}

$colorCli->header('Finished tagging releases with proper tags', true);
