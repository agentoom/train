<?php

namespace App\Livewire\Dashboard;

use App\Enums\DatasetStatus;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\GenerationUsage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Overview extends Component
{
    public function render(): View
    {
        $userId = Auth::id();

        $runningJobs = DatasetProject::where('user_id', $userId)
            ->where('status', DatasetStatus::Running)
            ->count();

        $completedDatasets = DatasetProject::where('user_id', $userId)
            ->where('status', DatasetStatus::Completed)
            ->count();

        $totalDatasets = DatasetProject::where('user_id', $userId)->count();

        $todayUsage = GenerationUsage::whereHas('datasetProject', fn ($q) => $q->where('user_id', $userId))
            ->whereDate('created_at', today())
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(estimated_cost), 0) as total_cost')
            ->first();

        $providers = AIProvider::where('user_id', $userId)
            ->where('is_enabled', true)
            ->get();

        $providerHealth = $providers->map(fn (AIProvider $provider) => [
            'label' => $provider->label,
            'type'  => $provider->type->value,
        ]);

        $recentProjects = DatasetProject::where('user_id', $userId)
            ->with('aiProvider')
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.dashboard.overview', [
            'runningJobs'       => $runningJobs,
            'completedDatasets' => $completedDatasets,
            'totalDatasets'     => $totalDatasets,
            'todayTokens'       => (int) ($todayUsage->total_tokens ?? 0),
            'todayCost'         => (float) ($todayUsage->total_cost ?? 0.0),
            'providerHealth'    => $providerHealth,
            'recentProjects'    => $recentProjects,
        ])->layout('layouts.app', ['title' => __('Dashboard')]);
    }
}
