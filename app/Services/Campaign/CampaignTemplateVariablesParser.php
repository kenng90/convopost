<?php

namespace App\Services\Campaign;

use Modules\Wpbox\Models\Template;

class CampaignTemplateVariablesParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(Template $template): array
    {
        $jsonData = json_decode($template->components, true) ?? [];

        $variables = [];

        foreach ($jsonData as $item) {
            if ($item['type'] == 'HEADER' && $item['format'] == 'TEXT') {
                preg_match_all('/{{(\d+)}}/', $item['text'], $matches);
                if (! empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $exampleValue = '';
                        try {
                            $exampleValue = $item['example']['header_text'][$id - 1];
                        } catch (\Throwable $th) {
                        }
                        $variables['header'][] = ['id' => $id, 'exampleValue' => $exampleValue];
                    }
                }
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'DOCUMENT') {
                $variables['document'] = true;
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'IMAGE') {
                $variables['image'] = true;
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'VIDEO') {
                $variables['video'] = true;
            } elseif ($item['type'] == 'BODY') {
                preg_match_all('/{{(\d+)}}/', $item['text'], $matches);
                if (! empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $exampleValue = '';
                        try {
                            $exampleValue = $item['example']['body_text'][0][$id - 1];
                        } catch (\Throwable $th) {
                        }
                        $variables['body'][] = ['id' => $id, 'exampleValue' => $exampleValue];
                    }
                }
            } elseif ($item['type'] == 'BUTTONS') {
                foreach ($item['buttons'] as $keyBtn => $button) {
                    if ($button['type'] == 'URL') {
                        preg_match_all('/{{(\d+)}}/', $button['url'], $matches);

                        if (! empty($matches[1])) {
                            foreach ($matches[1] as $id) {
                                $exampleValue = $button['url'] ?? '';
                                $exampleValue = str_replace('{{1}}', '', $exampleValue);
                                $variables['buttons'][$id - 1][] = [
                                    'id' => $id,
                                    'exampleValue' => $exampleValue,
                                    'type' => $button['type'],
                                    'text' => $button['text'],
                                ];
                            }
                        }
                    }
                    if ($button['type'] == 'COPY_CODE') {
                        $exampleValue = $button['example'][0] ?? '';
                        $variables['buttons'][$keyBtn][] = [
                            'id' => $keyBtn,
                            'exampleValue' => $exampleValue,
                            'type' => $button['type'],
                            'text' => $button['text'],
                        ];
                    }
                }
            }
        }

        return $variables;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function components(Template $template): ?array
    {
        $decoded = json_decode($template->components, true);

        return is_array($decoded) ? $decoded : null;
    }
}
