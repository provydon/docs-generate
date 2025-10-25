<?php

namespace SwaggerAuto\Tests\Unit\Commands;

use SwaggerAuto\Commands\GenerateDocs;
use SwaggerAuto\Tests\TestCase;

class ClosureValidationTest extends TestCase
{
    public function test_handles_closure_validation_rules()
    {
        $command = new GenerateDocs();
        
        // Test email closure validation
        $emailClosure = function ($attribute, $value, $fail) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $fail('The ' . $attribute . ' must be a valid email.');
            }
        };
        
        $rules = ['email' => $emailClosure];
        $schema = $command->convertRulesToSchema('email', $rules);
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertEquals('string', $schema['type']);
        $this->assertStringContainsString('Custom validation', $schema['description']);
    }

    public function test_handles_numeric_closure_validation()
    {
        $command = new GenerateDocs();
        
        // Test numeric closure validation
        $numericClosure = function ($attribute, $value, $fail) {
            if (!is_numeric($value)) {
                $fail('The ' . $attribute . ' must be numeric.');
            }
        };
        
        $rules = ['age' => $numericClosure];
        $schema = $command->convertRulesToSchema('age', $rules);
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertEquals('number', $schema['type']);
        $this->assertStringContainsString('Custom validation', $schema['description']);
    }

    public function test_handles_required_closure_validation()
    {
        $command = new GenerateDocs();
        
        // Test required closure validation
        $requiredClosure = function ($attribute, $value, $fail) {
            if (empty($value)) {
                $fail('The ' . $attribute . ' field is required.');
            }
        };
        
        $rules = ['name' => $requiredClosure];
        $schema = $command->convertRulesToSchema('name', $rules);
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertStringContainsString('Required field', $schema['description']);
        $this->assertStringContainsString('Custom validation', $schema['description']);
    }

    public function test_handles_mixed_closure_and_string_rules()
    {
        $command = new GenerateDocs();
        
        $emailClosure = function ($attribute, $value, $fail) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $fail('The ' . $attribute . ' must be a valid email.');
            }
        };
        
        $rules = ['email' => [$emailClosure, 'required', 'max:255']];
        $schema = $command->convertRulesToSchema('email', $rules);
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('string', $schema['type']);
        
        // For mixed rules, we should have either description or format
        $this->assertTrue(
            isset($schema['description']) || isset($schema['format']),
            'Schema should have either description or format for mixed rules'
        );
    }

    public function test_gracefully_handles_invalid_closure()
    {
        $command = new GenerateDocs();
        
        // Test with a closure that can't be analyzed
        $invalidClosure = function () {
            // This closure has no source code to analyze
        };
        
        $rules = ['field' => $invalidClosure];
        $schema = $command->convertRulesToSchema('field', $rules);
        
        // Should fall back to default string type
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('string', $schema['type']);
    }
}
