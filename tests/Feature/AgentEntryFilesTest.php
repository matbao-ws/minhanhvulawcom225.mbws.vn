<?php

namespace Tests\Feature;

use App\Support\CoreManifest;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every AI tool reads a different entry file: Claude Code CLAUDE.md, Codex and Antigravity
 * AGENTS.md, Gemini GEMINI.md. They must all lead to the same contract, the same skills and the
 * same mandatory checks, and travel with the core like the contract does.
 */
class AgentEntryFilesTest extends TestCase
{
    private const ENTRY_FILES = ['CLAUDE.md', 'AGENTS.md', 'GEMINI.md'];

    private const ROUTED_FILES = ['CLAUDE.md', 'AGENTS.md'];

    public function test_every_entry_file_exists_and_ships_with_the_core(): void
    {
        foreach (self::ENTRY_FILES as $file) {
            $this->assertFileExists(base_path($file));
            $this->assertContains($file, CoreManifest::CORE, "{$file} must be a core file: a site keeping an old one would follow old rules");
        }
    }

    public function test_the_routing_files_point_to_the_same_contract_skills_and_checks(): void
    {
        foreach (self::ROUTED_FILES as $file) {
            $text = File::get(base_path($file));

            foreach ([
                'AI_BUILD_PROMPT.md',
                '.agents/skills/maintain-ecommerce-core/SKILL.md',
                '.agents/skills/connect-ecommerce-frontend/SKILL.md',
                'php artisan client:editable-audit',
                'php artisan test --compact',
            ] as $required) {
                $this->assertStringContainsString($required, $text, "{$file} must mention {$required}");
            }
            $this->assertFileExists(base_path('.agents/skills/maintain-ecommerce-core/SKILL.md'));
            $this->assertFileExists(base_path('.agents/skills/connect-ecommerce-frontend/SKILL.md'));
        }
    }

    public function test_gemini_points_to_agents(): void
    {
        $this->assertStringContainsString('AGENTS.md', File::get(base_path('GEMINI.md')));
    }

    public function test_the_theme_skill_makes_the_audit_part_of_done(): void
    {
        $skill = File::get(base_path('.agents/skills/connect-ecommerce-frontend/SKILL.md'));

        $this->assertStringContainsString('php artisan client:editable-audit', $skill);
        $this->assertStringContainsString('RESULT: PASS', $skill);
    }

    public function test_the_internal_editor_skill_sends_theme_work_elsewhere(): void
    {
        $skill = File::get(base_path('.agents/skills/build-inline-storefront-editing/SKILL.md'));
        preg_match('/^description:\s*(.+)$/m', $skill, $m);

        $this->assertStringContainsString('connect-ecommerce-frontend', $m[1] ?? '', 'an agent choosing skills by description must not pick this one to cut a theme');
        $this->assertStringContainsString('ALREADY', $m[1] ?? '');
        $this->assertStringContainsString('connect-ecommerce-frontend', File::get(base_path('.agents/skills/build-inline-storefront-editing/agents/openai.yaml')));
    }
}
