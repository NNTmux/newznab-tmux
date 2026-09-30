<?php

declare(strict_types=1);

namespace App\Enums;

enum ConfigurationDomain: string
{
    case Site = 'site';
    case Registration = 'registration';
    case Ingestion = 'ingestion';
    case PostProcessing = 'post-processing';
    case Metadata = 'metadata';
    case Tmux = 'tmux';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Site',
            self::Registration => 'Registration',
            self::Ingestion => 'Ingestion',
            self::PostProcessing => 'Post Processing',
            self::Metadata => 'Metadata',
            self::Tmux => 'Tmux',
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Site => 'site_configurations',
            self::Registration => 'registration_configurations',
            self::Ingestion => 'ingestion_configurations',
            self::PostProcessing => 'post_processing_configurations',
            self::Metadata => 'metadata_configurations',
            self::Tmux => 'tmux_configurations',
        };
    }
}
