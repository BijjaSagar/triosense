<?php

declare(strict_types=1);

namespace App\Domain\Announcement;

use App\Models\AnnouncementTemplate;

/**
 * Renders announcement template text with placeholders.
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, string|int|null>  $placeholders
     */
    public function render(string $template, array $placeholders): string
    {
        $text = $template;

        foreach ($placeholders as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) ($value ?? ''), $text);
        }

        return $text;
    }

    /**
     * @param  array<string, string|int|null>  $placeholders
     * @return list<array{language: string, text: string, audio_file_path: string|null}>
     */
    public function renderAllLanguages(int $tenantId, string $code, array $placeholders): array
    {
        $templates = AnnouncementTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('status', 'approved')
            ->orderBy('language')
            ->get();

        $results = [];

        foreach ($templates as $template) {
            $results[] = [
                'language' => $template->language,
                'text' => $this->render($template->text, $placeholders),
                'audio_file_path' => $template->audio_file_path,
            ];
        }

        return $results;
    }
}
