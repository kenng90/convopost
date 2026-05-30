<?php

namespace Tests\Unit;

use Modules\Wpbox\Http\Controllers\CampaignsController;
use ReflectionMethod;
use Tests\TestCase;

class FileBroadcastParamMatchTest extends TestCase
{
    public function test_build_file_broadcast_param_match_marks_mapped_variables_as_static(): void
    {
        $method = new ReflectionMethod(CampaignsController::class, 'buildFileBroadcastParamMatch');
        $method->setAccessible(true);

        $result = $method->invoke(new CampaignsController(), [], [
            'body' => [
                '1' => 'First Name',
                '2' => 'City',
            ],
        ]);

        $this->assertSame('-2', $result['body']['1']);
        $this->assertSame('-2', $result['body']['2']);
    }
}
