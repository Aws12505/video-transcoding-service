<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'project_key' => 'courses_project',
                'project_name' => 'Courses Project',
            ],
        ];

        foreach ($projects as $project) {
            $apiKey = Str::random(64);
            
            Project::create([
                'project_key' => $project['project_key'],
                'project_name' => $project['project_name'],
                'api_key' => $apiKey,
                'is_active' => true,
            ]);

            $this->command->info("Created: {$project['project_name']}");
            $this->command->info("API Key: {$apiKey}");
            $this->command->line('---');
        }
    }
}
