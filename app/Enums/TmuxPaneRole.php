<?php

declare(strict_types=1);

namespace App\Enums;

enum TmuxPaneRole: string
{
    case Monitor = 'monitor';
    case Binaries = 'binaries';
    case Backfill = 'backfill';
    case Releases = 'releases';
    case Sequential = 'sequential';
    case FixNames = 'fix_names';
    case RemoveCrap = 'remove_crap';
    case PostAdditional = 'post_additional';
    case PostTv = 'post_tv';
    case PostMetadata = 'post_metadata';
    case PostMovies = 'post_movies';
    case IrcScraper = 'irc_scraper';
    case Htop = 'htop';
    case Nmon = 'nmon';
    case Vnstat = 'vnstat';
    case Tcptrack = 'tcptrack';
    case BandwidthMonitor = 'bandwidth_monitor';
    case Mytop = 'mytop';
    case Redis = 'redis';
    case Console = 'console';
}
