<?php

namespace App\Services\Flowmaker;

class FlowTemplateEnricher
{
    /**
     * @param  array<string, array<string, mixed>>  $templates
     * @return array<string, array<string, mixed>>
     */
    public static function enrich(array $templates): array
    {
        foreach ($templates as $key => $template) {
            if (! isset($template['flow_data']['nodes'])) {
                continue;
            }

            $templates[$key]['flow_data']['nodes'] = self::enrichNodes($template['flow_data']['nodes']);
        }

        return $templates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private static function enrichNodes(array $nodes): array
    {
        return array_map(function (array $node) {
            if (($node['type'] ?? '') !== 'openai') {
                return $node;
            }

            $settings = $node['data']['settings'] ?? [];
            $llmKey = isset($settings['llm']) ? 'llm' : (isset($settings['openai']) ? 'openai' : 'llm');

            if (! isset($settings[$llmKey])) {
                $settings[$llmKey] = [];
            }

            if (! array_key_exists('autoSendMessage', $settings[$llmKey])) {
                $settings[$llmKey]['autoSendMessage'] = true;
            }

            if (! isset($settings[$llmKey]['variableName']) || $settings[$llmKey]['variableName'] === '') {
                $settings[$llmKey]['variableName'] = 'ai_response';
            }

            $node['data']['settings'] = $settings;

            return $node;
        }, $nodes);
    }
}
