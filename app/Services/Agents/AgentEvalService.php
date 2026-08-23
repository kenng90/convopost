<?php

namespace App\Services\Agents;

use App\Models\Company;
use Modules\Wpbox\Models\Contact;

class AgentEvalService
{
    /**
     * @return array<int, array{input: string, expect_tools: array<int, string>, expect_handoff?: bool}>
     */
    public function cases(): array
    {
        return [
            [
                'input' => 'I want to talk to a human',
                'expect_tools' => ['handoff_to_human'],
                'expect_handoff' => true,
            ],
            [
                'input' => 'I want to buy a product from the catalog',
                'expect_tools' => ['search_catalog'],
                'expect_handoff' => false,
            ],
            [
                'input' => 'Please book me an appointment',
                'expect_tools' => ['list_services'],
                'expect_handoff' => false,
            ],
            [
                'input' => 'Charge me 500 for the consultation',
                'expect_tools' => ['send_payment'],
                'expect_handoff' => false,
            ],
        ];
    }

    /**
     * @return array{passed: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function run(Company $company, Contact $contact): array
    {
        $agent = app(ActionAgentService::class);
        $passed = 0;
        $failed = 0;
        $results = [];

        foreach ($this->cases() as $case) {
            $result = $agent->ruleBased($company, $contact, $case['input']);
            $missing = array_values(array_diff($case['expect_tools'], $result['tools_used'] ?? []));
            $handoffOk = ! array_key_exists('expect_handoff', $case)
                || (bool) $result['handoff'] === (bool) $case['expect_handoff'];
            $ok = $missing === [] && $handoffOk && filled($result['reply'] ?? null);

            if ($ok) {
                $passed++;
            } else {
                $failed++;
            }

            $results[] = [
                'input' => $case['input'],
                'ok' => $ok,
                'tools_used' => $result['tools_used'] ?? [],
                'missing_tools' => $missing,
                'handoff' => (bool) ($result['handoff'] ?? false),
            ];
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
