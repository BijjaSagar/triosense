<?php

declare(strict_types=1);

namespace App\Services\Announcement;

use App\Domain\Announcement\TemplateRenderer;
use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Mode;
use App\Domain\Fifo\Status;
use App\Models\Announcement;
use App\Models\CutoffEvent;
use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PA announcement engine — renders templates and dispatches to PA controller.
 */
final class AnnouncementService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function announceStatusChange(
        Location $location,
        Status $previousStatus,
        Decision $decision,
        LiveState $state,
        ?CutoffEvent $cutoffEvent = null,
    ): void {
        if ($location->mode !== Mode::LIVE->value) {
            Log::debug('AnnouncementService.skipped_shadow', [
                'location_id' => $location->location_id,
            ]);

            return;
        }

        $templateCode = $this->resolveTemplateCode($previousStatus, $decision->status);

        if ($templateCode === null) {
            return;
        }

        $placeholders = [
            'cutoff_position' => $decision->cutoffPosition,
            'tokens_remaining' => $state->tokensRemaining(),
            'location_name' => $location->name,
        ];

        $rendered = $this->renderer->renderAllLanguages(
            (int) $location->tenant_id,
            $templateCode,
            $placeholders,
        );

        if ($rendered === []) {
            Log::warning('AnnouncementService.no_templates', [
                'location_id' => $location->location_id,
                'code' => $templateCode,
            ]);

            return;
        }

        foreach ($rendered as $item) {
            $this->dispatchToPaController($location, $item, $cutoffEvent);
        }
    }

    /**
     * @param  array{language: string, text: string, audio_file_path: string|null}  $item
     */
    private function dispatchToPaController(
        Location $location,
        array $item,
        ?CutoffEvent $cutoffEvent,
    ): void {
        $audioPath = $item['audio_file_path']
            ?? config('triosense.pa.default_audio_path', '/var/lib/triosense/pa/default.mp3');

        Log::info('AnnouncementService.dispatch', [
            'location_id' => $location->location_id,
            'language' => $item['language'],
            'text' => $item['text'],
            'audio_path' => $audioPath,
        ]);

        $announcement = Announcement::query()->create([
            'tenant_id' => $location->tenant_id,
            'location_id' => $location->location_id,
            'trigger_type' => 'automatic',
            'text_played' => $item['text'],
            'language' => $item['language'],
            'cutoff_event_id' => $cutoffEvent?->cutoff_event_id,
            'status' => 'queued',
            'created_at' => now(),
        ]);

        $paUrl = config('triosense.pa.controller_url');

        if ($paUrl === null || $paUrl === '') {
            Log::debug('AnnouncementService.pa_stub', [
                'announcement_id' => $announcement->announcement_id,
            ]);
            $announcement->update([
                'status' => 'played',
                'played_at' => now(),
            ]);

            return;
        }

        try {
            $response = Http::timeout(5)->post($paUrl, [
                'location_id' => $location->location_id,
                'language' => $item['language'],
                'text' => $item['text'],
                'audio_file_path' => $audioPath,
            ]);

            if ($response->successful()) {
                $announcement->update([
                    'status' => 'played',
                    'played_at' => now(),
                ]);
            } else {
                $announcement->update([
                    'status' => 'failed',
                    'failure_reason' => 'HTTP '.$response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('AnnouncementService.pa_failed', [
                'announcement_id' => $announcement->announcement_id,
                'error' => $e->getMessage(),
            ]);
            $announcement->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTemplateCode(Status $previous, Status $current): ?string
    {
        if ($current === Status::APPROACHING_CUTOFF && $previous === Status::OPEN) {
            return 'quota_75pct';
        }

        if ($current === Status::CUTOFF_DECLARED) {
            return 'cutoff_declared';
        }

        if ($current === Status::CLOSED) {
            return 'counter_closed';
        }

        return null;
    }
}
