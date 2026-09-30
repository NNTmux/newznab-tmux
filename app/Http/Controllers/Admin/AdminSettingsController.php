<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConfigurationDomain;
use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\UpdateDomainConfigurationRequest;
use App\Models\TmuxConfiguration;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\DomainConfigurationRepository;
use App\Services\SiteLogoService;
use App\Support\Configuration\SettingsPageCatalog;
use App\Support\SizeUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class AdminSettingsController extends BasePageController
{
    public function __construct(
        private readonly SettingsPageCatalog $catalog,
        private readonly DomainConfigurationRepository $repository,
        private readonly ConfigurationProvider $configurationProvider,
        private readonly SiteLogoService $siteLogoService,
    ) {
        parent::__construct();
    }

    public function show(string $domain): View
    {
        $configurationDomain = ConfigurationDomain::from($domain);
        $modelClass = $this->catalog->modelClass($configurationDomain);
        $model = $modelClass::query()->findOrFail(1);

        return view('admin.settings.show', [
            ...$this->viewData,
            'domain' => $configurationDomain,
            'domains' => ConfigurationDomain::cases(),
            'fields' => $this->catalog->fields($configurationDomain),
            'configuration' => $model,
            'sizeUnits' => SizeUnit::UNITS,
            'sizeValues' => $this->sizeValues($configurationDomain, $model->getAttributes()),
            'selectedCleanupRules' => $model instanceof TmuxConfiguration ? $model->cleanupRules()->pluck('rule')->all() : [],
            'selectedColorExclusions' => $model instanceof TmuxConfiguration ? $model->colorExclusions()->pluck('color')->all() : [],
            'title' => $configurationDomain->label().' Settings',
            'meta_title' => $configurationDomain->label().' Settings',
        ]);
    }

    public function legacySite(): RedirectResponse
    {
        return redirect()->route('admin.settings.show', ['domain' => ConfigurationDomain::Site->value]);
    }

    public function legacyTmux(): RedirectResponse
    {
        return redirect()->route('admin.settings.show', ['domain' => ConfigurationDomain::Tmux->value]);
    }

    public function update(UpdateDomainConfigurationRequest $request, string $domain): RedirectResponse
    {
        $configurationDomain = ConfigurationDomain::from($domain);
        $attributes = [];
        $storedLogoPath = null;
        $currentLogoPath = $configurationDomain === ConfigurationDomain::Site
            ? $this->configurationProvider->site(fresh: true)->logoPath
            : null;

        foreach ($this->catalog->fields($configurationDomain) as $field) {
            if (in_array($field->column, ['cleanup_rules', 'color_exclusions', 'site_logo'], true)) {
                continue;
            }

            if ($field->sensitive) {
                if ($request->boolean('clear_'.$field->column)) {
                    $attributes[$field->column] = null;
                } elseif ($request->filled($field->column)) {
                    $attributes[$field->column] = $request->string($field->column)->toString();
                }

                continue;
            }

            $value = $request->validated($field->column);
            if ($value === null && in_array($field->column, ['terms', 'dereferrer_link'], true)) {
                $value = '';
            }
            $attributes[$field->column] = $field->control === 'bytes'
                ? SizeUnit::toBytes($value, (string) $request->validated($field->column.'_unit'))
                : $value;
        }

        if ($configurationDomain === ConfigurationDomain::Site) {
            $uploadedLogo = $request->file('site_logo');
            if ($uploadedLogo instanceof UploadedFile) {
                $storedLogoPath = $this->siteLogoService->store($uploadedLogo);
                $attributes['site_logo'] = $storedLogoPath;
            } elseif ($request->boolean('remove_site_logo')) {
                $attributes['site_logo'] = null;
            }
        }

        try {
            DB::transaction(function () use ($configurationDomain, $attributes, $request): void {
                $this->repository->update(
                    $configurationDomain,
                    $attributes,
                    $configurationDomain === ConfigurationDomain::Tmux ? array_values($request->validated('cleanup_rules', [])) : null,
                    $configurationDomain === ConfigurationDomain::Tmux ? $request->colorExclusions() : null,
                );
            });
        } catch (Throwable $throwable) {
            if ($storedLogoPath !== null) {
                $this->siteLogoService->delete($storedLogoPath);
            }

            throw $throwable;
        }

        if ($configurationDomain === ConfigurationDomain::Site && array_key_exists('site_logo', $attributes) && $currentLogoPath !== $attributes['site_logo']) {
            $this->siteLogoService->delete($currentLogoPath);
        }

        return redirect()->route('admin.settings.show', ['domain' => $configurationDomain->value])
            ->with('success', $configurationDomain->label().' settings updated successfully.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, array{value: int|float, unit: string}>
     */
    private function sizeValues(ConfigurationDomain $domain, array $attributes): array
    {
        $values = [];
        foreach ($this->catalog->fields($domain) as $field) {
            if ($field->control === 'bytes') {
                $values[$field->column] = SizeUnit::fromBytes($attributes[$field->column] ?? 0);
            }
        }

        return $values;
    }
}
