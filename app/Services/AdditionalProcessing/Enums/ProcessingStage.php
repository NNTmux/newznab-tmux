<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

enum ProcessingStage: string
{
    case WorkspacePreparation = 'workspace-preparation';
    case NzbParsing = 'nzb-parsing';
    case ReleaseInitialization = 'release-initialization';
    case MessageIdSelection = 'message-id-selection';
    case DirectDownloads = 'direct-downloads';
    case ArchiveDownloads = 'archive-downloads';
    case ExtractedFiles = 'extracted-files';
    case ArchiveFallbacks = 'archive-fallbacks';
    case TimeoutHandling = 'timeout-handling';
    case Finalization = 'finalization';
    case WorkspaceCleanup = 'workspace-cleanup';
}
