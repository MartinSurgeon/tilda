<?php
declare(strict_types=1);

namespace App\Services;

/**
 * At least 12 characters, at least 3 of 4 character types, not a common
 * password, and not built from the user's own name or email.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    private const COMMON = [
        'password1234', 'password123!', 'qwerty123456', '123456789012', 'welcome12345',
        'administrator', 'letmein12345', 'iloveyou1234', 'hospital1234', 'changeme1234',
        'ruma@2026!', 'rumahospital', 'ruma12345678', 'p@ssw0rd1234', 'passw0rd1234',
    ];

    public static function rulesText(): array
    {
        return [
            'At least ' . self::MIN_LENGTH . ' characters',
            'A mix of at least 3: lowercase, uppercase, numbers, symbols',
            'Not your name, email or a common password',
        ];
    }

    /** @return string[] problems (empty when the password is acceptable) */
    public static function check(string $password, array $user = []): array
    {
        $problems = [];
        if (mb_strlen($password) < self::MIN_LENGTH) {
            $problems[] = 'Use at least ' . self::MIN_LENGTH . ' characters.';
        }

        $classes = (int) preg_match('/[a-z]/', $password)
            + (int) preg_match('/[A-Z]/', $password)
            + (int) preg_match('/\d/', $password)
            + (int) preg_match('/[^a-zA-Z\d]/', $password);
        if ($classes < 3) {
            $problems[] = 'Mix at least 3 of: lowercase, uppercase, numbers and symbols.';
        }

        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true)) {
            $problems[] = 'This password is too common. Choose something less predictable.';
        }

        $personal = array_filter([
            strtok((string) ($user['email'] ?? ''), '@'),
            ...preg_split('/\s+/', mb_strtolower((string) ($user['full_name'] ?? ''))),
        ], static fn ($p) => is_string($p) && mb_strlen($p) >= 4);
        foreach ($personal as $part) {
            if (str_contains($lower, mb_strtolower($part))) {
                $problems[] = 'Do not include your name or email in your password.';
                break;
            }
        }

        return $problems;
    }
}
