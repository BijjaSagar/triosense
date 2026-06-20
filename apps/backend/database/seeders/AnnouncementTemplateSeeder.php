<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AnnouncementTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['code' => 'quota_75pct', 'language' => 'te', 'text' => '75% టోకెన్లు జారీ చేయబడ్డాయి. దయచేసి వేగంగా క్యూలో చేరండి.'],
            ['code' => 'quota_75pct', 'language' => 'ta', 'text' => '75% டோக்கன்கள் வழங்கப்பட்டுள்ளன. தயவுசெய்து விரைவில் வரிசையில் சேரவும்.'],
            ['code' => 'quota_75pct', 'language' => 'hi', 'text' => '75% टोकन जारी हो चुके हैं। कृपया शीघ्र कतार में शामिल हों।'],
            ['code' => 'quota_75pct', 'language' => 'en', 'text' => '75% of tokens have been issued. Please join the queue promptly.'],
            ['code' => 'cutoff_declared', 'language' => 'te', 'text' => 'చివరి హామీ టోకెన్ స్థానం #{cutoff_position}. దయచేసి క్యూలో చేరండి.'],
            ['code' => 'cutoff_declared', 'language' => 'ta', 'text' => 'கடைசி உத்தரவாத டோக்கன் நிலை #{cutoff_position}. தயவுசெய்து வரிசையில் சேரவும்.'],
            ['code' => 'cutoff_declared', 'language' => 'hi', 'text' => 'अंतिम सुनिश्चित टोकन स्थिति #{cutoff_position}। कृपया कतार में शामिल हों।'],
            ['code' => 'cutoff_declared', 'language' => 'en', 'text' => 'Last guaranteed token position is #{cutoff_position}. Please join the queue.'],
            ['code' => 'counter_closed', 'language' => 'te', 'text' => 'ఈ రోజు టోకెన్లు అయిపోయాయి. కౌంటర్ మూసివేయబడింది.'],
            ['code' => 'counter_closed', 'language' => 'ta', 'text' => 'இன்றைய டோக்கன்கள் முடிந்துவிட்டன. கவுண்டர் மூடப்பட்டது.'],
            ['code' => 'counter_closed', 'language' => 'hi', 'text' => 'आज के टोकन समाप्त हो गए। काउंटर बंद है।'],
            ['code' => 'counter_closed', 'language' => 'en', 'text' => 'Today\'s tokens are exhausted. Counter is closed.'],
        ];

        foreach ($templates as $template) {
            DB::table('announcement_templates')->insertOrIgnore([
                'tenant_id' => 1,
                'code' => $template['code'],
                'language' => $template['language'],
                'text' => $template['text'],
                'audio_file_path' => null,
                'status' => 'approved',
                'approved_by' => null,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
