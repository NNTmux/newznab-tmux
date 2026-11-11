<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use App\Models\RolePromotion;
use App\Models\ServiceIncident;
use App\Models\ServiceStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSharedFormPartialsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withViewErrors([]);
    }

    public function test_incident_form_posts_to_the_store_route_with_defaults_when_creating(): void
    {
        $view = $this->view('admin.status.partials.form', ['incident' => null, 'services' => collect()]);

        $view->assertSee('action="'.route('admin.status.store').'"', false)
            ->assertDontSee('name="_method"', false)
            ->assertSee('Resolved at (optional)')
            ->assertSee('value="'.IncidentImpactEnum::Minor->value.'" selected', false)
            ->assertSee('>Create</button>', false);
    }

    public function test_incident_form_puts_to_the_update_route_with_stored_values_when_editing(): void
    {
        $service = (new ServiceStatus)->forceFill(['id' => 3, 'name' => 'Indexer']);
        $incident = (new ServiceIncident)->forceFill([
            'id' => 9,
            'title' => 'Search outage',
            'description' => 'Index rebuild in progress',
            'impact' => IncidentImpactEnum::Major,
            'status' => IncidentStatusEnum::Monitoring,
            'started_at' => Carbon::parse('2026-11-01 08:30:00'),
            'resolved_at' => null,
        ]);
        $incident->setRelation('services', collect([$service]));

        $view = $this->view('admin.status.partials.form', ['incident' => $incident, 'services' => collect([$service])]);

        $view->assertSee('action="'.route('admin.status.update', $incident).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Search outage"', false)
            ->assertSee('Index rebuild in progress')
            ->assertSee('value="2026-11-01T08:30"', false)
            ->assertSee('value="'.IncidentImpactEnum::Major->value.'" selected', false)
            ->assertSee('value="'.IncidentStatusEnum::Monitoring->value.'" selected', false)
            ->assertSee('>Save</button>', false);
    }

    public function test_promotion_form_posts_to_the_store_route_as_active_when_creating(): void
    {
        $view = $this->view('admin.promotions.partials.form', ['promotion' => null, 'customRoles' => collect()]);

        $view->assertSee('action="'.route('admin.promotions.store').'"', false)
            ->assertDontSee('name="_method"', false)
            ->assertSee('name="additional_days" id="additional_days" value="0"', false)
            ->assertSee('Uncheck to create as inactive')
            ->assertSee('Create Promotion');
    }

    public function test_promotion_form_puts_to_the_update_route_with_stored_values_when_editing(): void
    {
        $promotion = (new RolePromotion)->forceFill([
            'id' => 5,
            'name' => 'Winter bonus',
            'description' => 'Extra days for supporters',
            'additional_days' => 30,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'is_active' => false,
            'applicable_roles' => [],
        ]);

        $view = $this->view('admin.promotions.partials.form', ['promotion' => $promotion, 'customRoles' => collect()]);

        $view->assertSee('action="'.route('admin.promotions.update', 5).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Winter bonus"', false)
            ->assertSee('name="additional_days" id="additional_days" value="30"', false)
            ->assertSee('value="2026-12-01"', false)
            ->assertSee('Uncheck to deactivate this promotion')
            ->assertSee('Update Promotion');
    }
}
