<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use App\Enums\RegistrationStatus;
use App\Enums\ServiceStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatusBadgeClassesTest extends TestCase
{
    #[Test]
    public function every_status_has_its_own_badge_colours(): void
    {
        foreach ([RegistrationStatus::class, ServiceStatusEnum::class, IncidentImpactEnum::class, IncidentStatusEnum::class] as $enum) {
            $classes = array_map(static fn ($case): string => $case->badgeClasses(), $enum::cases());

            $this->assertNotContains('', $classes, $enum);
            $this->assertSame($classes, array_unique($classes), "{$enum}: two statuses must not look the same");
        }
    }

    #[Test]
    public function the_script_maps_are_keyed_by_the_stored_enum_value(): void
    {
        $this->assertSame(RegistrationStatus::Invite->badgeClasses(), RegistrationStatus::badgeClassMap()[RegistrationStatus::Invite->value]);
        $this->assertSame(ServiceStatusEnum::MajorOutage->badgeClasses(), ServiceStatusEnum::badgeClassMap()['major_outage']);
        $this->assertSame(IncidentImpactEnum::Critical->badgeClasses(), IncidentImpactEnum::badgeClassMap()['critical']);
    }

    #[Test]
    public function tailwind_scans_the_enums_that_hold_class_names(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (['RegistrationStatus', 'ServiceStatusEnum', 'IncidentImpactEnum', 'IncidentStatusEnum'] as $enum) {
            $this->assertStringContainsString('@source "../../app/Enums/'.$enum.'.php";', $stylesheet, "Without this the {$enum} badge colours are purged from the build");
        }
    }
}
