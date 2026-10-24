<?php

declare(strict_types=1);

/**
 * Input validation helpers. Each method returns a cleaned value or adds a
 * message to $errors under the given field name.
 */
final class Validator
{
    public const ISSUE_MIN_LENGTH = 10;
    public const ISSUE_MAX_LENGTH = 2000;
    public const STATUSES = ['open', 'resolved', 'unresolved', 'escalated'];

    /** Removes control characters (keeps line breaks and tabs) and trims. */
    public static function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[^\P{C}\n\t]/u', '', $value) ?? '';
        return trim($value);
    }

    public static function issueText(mixed $value, array &$errors): string
    {
        if (!is_string($value) || trim($value) === '') {
            $errors['issue_text'] = 'Please describe the issue.';
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $errors['issue_text'] = 'The issue description contains invalid characters.';
            return '';
        }
        $text = self::cleanText($value);
        $length = mb_strlen($text);
        if ($length < self::ISSUE_MIN_LENGTH) {
            $errors['issue_text'] = sprintf('Please add more detail (at least %d characters).', self::ISSUE_MIN_LENGTH);
        } elseif ($length > self::ISSUE_MAX_LENGTH) {
            $errors['issue_text'] = sprintf('The description is too long (maximum %d characters).', self::ISSUE_MAX_LENGTH);
        } elseif (!preg_match('/\p{L}{2,}/u', $text)) {
            $errors['issue_text'] = 'Please describe the issue in words.';
        }
        return $text;
    }

    public static function optionalSlug(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[a-z0-9-]{1,60}$/', $value)) {
            $errors[$field] = 'Invalid category.';
            return null;
        }
        return $value;
    }

    public static function status(mixed $value, array &$errors, bool $required = true): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                $errors['status'] = 'Status is required.';
            }
            return null;
        }
        if (!is_string($value) || !in_array($value, self::STATUSES, true)) {
            $errors['status'] = 'Status must be one of: ' . implode(', ', self::STATUSES) . '.';
            return null;
        }
        return $value;
    }

    public static function optionalNote(mixed $value, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            $errors['note'] = 'Note must be text.';
            return null;
        }
        $note = self::cleanText($value);
        if (mb_strlen($note) > 500) {
            $errors['note'] = 'Note is too long (maximum 500 characters).';
        }
        return $note === '' ? null : $note;
    }

    public static function positiveId(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1 || strlen($value) > 10) {
            throw ApiException::notFound();
        }
        return (int) $value;
    }

    public static function intInRange(?string $value, int $default, int $min, int $max): int
    {
        if ($value === null || $value === '' || !preg_match('/^\d{1,6}$/', $value)) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    public static function search(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = mb_substr(self::cleanText($value), 0, 100);
        return $value === '' ? null : $value;
    }

    public static function bool(?string $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }
}
