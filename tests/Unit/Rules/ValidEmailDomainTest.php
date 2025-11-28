<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidEmailDomain;
use Tests\TestCase;

class ValidEmailDomainTest extends TestCase
{
    /**
     * Test that disposable email domains are rejected
     */
    public function test_rejects_disposable_email_domains(): void
    {
        $rule = new ValidEmailDomain();

        $disposableEmails = [
            'test@guerrillamail.com',
            'test@10minutemail.com',
            'test@mailinator.com',
            'test@tempmail.com',
            'test@yopmail.com',
            'test@throwawaymail.com',
        ];

        foreach ($disposableEmails as $email) {
            $failed = false;
            $rule->validate('email', $email, function($message) use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Expected {$email} to be rejected");
        }
    }

    /**
     * Test that legitimate email domains are accepted
     */
    public function test_accepts_legitimate_email_domains(): void
    {
        $rule = new ValidEmailDomain();

        $legitimateEmails = [
            'test@gmail.com',
            'test@yahoo.com',
            'test@outlook.com',
            'test@protonmail.com',
        ];

        foreach ($legitimateEmails as $email) {
            $failed = false;
            $rule->validate('email', $email, function($message) use (&$failed) {
                $failed = true;
            });

            $this->assertFalse($failed, "Expected {$email} to be accepted");
        }
    }

    /**
     * Test that emails with suspicious patterns are rejected
     */
    public function test_rejects_emails_with_suspicious_patterns(): void
    {
        $rule = new ValidEmailDomain();

        $suspiciousEmails = [
            'test@tempdomainexample.com',
            'test@throwawaystuff.net',
            'test@disposableemail.org',
        ];

        foreach ($suspiciousEmails as $email) {
            $failed = false;
            $rule->validate('email', $email, function($message) use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Expected {$email} to be rejected due to pattern match");
        }
    }

    /**
     * Test that emails with invalid domains are rejected
     */
    public function test_rejects_emails_with_invalid_domains(): void
    {
        $rule = new ValidEmailDomain();

        $invalidEmails = [
            'test@nonexistentdomain12345xyz.com',
            'test@fakeinvaliddomain999.net',
        ];

        foreach ($invalidEmails as $email) {
            $failed = false;
            $rule->validate('email', $email, function($message) use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Expected {$email} to be rejected due to DNS validation");
        }
    }

    /**
     * Test that malformed emails are rejected
     * Note: Basic format validation (like @domain.com) should be caught by Laravel's 'email' rule
     * before our custom rule runs. We test multiple @ signs which might slip through.
     */
    public function test_rejects_malformed_emails(): void
    {
        $rule = new ValidEmailDomain();

        $malformedEmails = [
            'notanemail',
            'noat.com',
            'multiple@@at.com',
        ];

        foreach ($malformedEmails as $email) {
            $failed = false;
            $rule->validate('email', $email, function($message) use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "Expected {$email} to be rejected as malformed");
        }
    }
}

