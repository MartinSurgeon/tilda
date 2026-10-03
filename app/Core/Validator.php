<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rules: required, email, min:n, max:n, int, in:a,b,c, exists:table,column
 * Messages are written for non-technical staff.
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data, private array $labels = [])
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $labels);
        foreach ($rules as $field => $fieldRules) {
            $v->check($field, is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules));
        }
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function check(string $field, array $rules): void
    {
        $value = $this->data[$field] ?? '';
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $empty = $value === '' || $value === null || $value === [];

        if (in_array('required', $rules, true) && $empty) {
            $this->addError($field, "Please enter {$this->article($label)}.");
            return;
        }
        if ($empty) {
            return;
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $message = match ($name) {
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null
                    : 'That email address does not look right. Check it and try again.',
                'min' => mb_strlen((string) $value) >= (int) $arg ? null
                    : "{$label} needs at least {$arg} characters.",
                'max' => mb_strlen((string) $value) <= (int) $arg ? null
                    : "{$label} can be at most {$arg} characters.",
                'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null
                    : "Please choose a valid {$label}.",
                'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null
                    : "Please choose a valid {$label}.",
                'exists' => $this->exists($arg, $value) ? null
                    : "Please choose a valid {$label}.",
                default => null,
            };
            if ($message !== null) {
                $this->addError($field, $message);
                return;
            }
        }
    }

    private function exists(?string $arg, mixed $value): bool
    {
        [$table, $column] = explode(',', (string) $arg);
        // Table/column come from code, never from input; still allow-list the characters.
        if (!preg_match('/^\w+$/', $table) || !preg_match('/^\w+$/', $column)) {
            throw new \InvalidArgumentException('Bad exists rule');
        }
        return DB::value("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1", [$value]) !== null;
    }

    private function article(string $label): string
    {
        $lower = mb_strtolower($label);
        return (preg_match('/^[aeiou]/', $lower) ? 'an ' : 'a ') . $lower;
    }
}
