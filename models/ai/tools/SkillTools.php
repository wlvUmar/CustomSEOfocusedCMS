<?php
// path: ./models/ai/tools/SkillTools.php
// On-demand skill reads. AGENTS.md auto-injects via SkillLoader.

require_once BASE_PATH . '/models/ai/SkillLoader.php';

class SkillTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_skills',
                    'description' => 'List available skill playbooks (name + one-line description). Call before read_skill. Skills are cheap; prefer them over guessing markup or workflow.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_skill',
                    'description' => 'Read one skill playbook by name (from list_skills). Follow its steps and output shapes exactly.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['name' => ['type' => 'string', 'description' => 'Skill name, e.g. "monthly-report".']],
                        'required' => ['name'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name === 'list_skills') { $skills = SkillLoader::listSkills(); return ['skills' => $skills, 'count' => count($skills)]; }
        if ($name === 'read_skill') {
            $skill = trim((string)($args['name'] ?? ''));
            if ($skill === '') throw new InvalidArgumentException('name is required — call list_skills first.');
            $text = SkillLoader::readSkill($skill);
            return ['name' => $skill, 'skill' => $text, 'chars' => mb_strlen($text)];
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }
}
