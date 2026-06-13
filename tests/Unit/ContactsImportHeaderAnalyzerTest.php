<?php

namespace Tests\Unit;

use Modules\Contacts\Support\ContactsImportHeaderAnalyzer;
use Tests\TestCase;

class ContactsImportHeaderAnalyzerTest extends TestCase
{
    public function test_find_duplicate_heading_groups_detects_case_insensitive_matches(): void
    {
        $duplicates = ContactsImportHeaderAnalyzer::findDuplicateHeadingGroups([
            'id',
            'name',
            'phone',
            'avatar',
            'email',
            'Name',
            'Email',
            'Phone Number',
        ]);

        $this->assertArrayHasKey('name', $duplicates);
        $this->assertSame(['name', 'Name'], $duplicates['name']);
        $this->assertArrayHasKey('email', $duplicates);
        $this->assertSame(['email', 'Email'], $duplicates['email']);
        $this->assertArrayNotHasKey('phone', $duplicates);
        $this->assertArrayNotHasKey('id', $duplicates);
    }

    public function test_find_duplicate_heading_groups_ignores_empty_headers(): void
    {
        $duplicates = ContactsImportHeaderAnalyzer::findDuplicateHeadingGroups([
            'name',
            '',
            null,
            'phone',
        ]);

        $this->assertSame([], $duplicates);
    }

    public function test_format_duplicate_message_lists_conflicting_columns(): void
    {
        $message = ContactsImportHeaderAnalyzer::formatDuplicateMessage([
            'name' => ['name', 'Name'],
            'email' => ['email', 'Email'],
        ]);

        $this->assertStringContainsString('name', $message);
        $this->assertStringContainsString("'name', 'Name'", $message);
        $this->assertStringContainsString("'email', 'Email'", $message);
    }
}
