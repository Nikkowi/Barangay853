<?php
/**
 * utils/nameValidator.php
 *
 * Shared name and email validation helpers.
 * Include this in any PHP file that needs to validate names or emails.
 *
 * Usage:
 *   require_once '../utils/nameValidator.php';
 *   $result = validatePersonName('staff1');
 *   if (!$result['valid']) echo $result['error'];
 */

const BANNED_KEYWORDS = [
    'admin', 'administrator', 'staff', 'user', 'test', 'demo',
    'resident', 'system', 'root', 'superuser', 'moderator', 'manager',
    'operator', 'support', 'service', 'bot', 'null', 'undefined',
    'guest', 'anonymous', 'account', 'login', 'barangay', 'brgy',
    'kagawad', 'kapitan', 'chairman', 'secretary', 'treasurer',
];

/**
 * Validates a real person name.
 * @param string $name
 * @return array { valid: bool, error: string|null }
 */
function validatePersonName(string $name): array {
    if (empty($name)) {
        return ['valid' => false, 'error' => 'Name is required.'];
    }

    $trimmed = trim($name);

    if (mb_strlen($trimmed) < 2) {
        return ['valid' => false, 'error' => 'Name must be at least 2 characters long.'];
    }

    if (mb_strlen($trimmed) > 100) {
        return ['valid' => false, 'error' => 'Name must be 100 characters or less.'];
    }

    // No digits in a real name
    if (preg_match('/\d/', $trimmed)) {
        return ['valid' => false, 'error' => 'Name cannot contain numbers. Please enter your real full name.'];
    }

    // Only letters (including Filipino/accented), spaces, hyphens, apostrophes, periods
    if (!preg_match('/^[a-zA-ZÀ-ÖØ-öø-ÿÑñ\s\'\-\.]+$/u', $trimmed)) {
        return ['valid' => false, 'error' => "Name can only contain letters, spaces, hyphens ( - ), apostrophes ( ' ), or periods ( . )."];
    }

    // Must have at least one letter
    if (!preg_match('/[a-zA-ZÀ-ÖØ-öø-ÿÑñ]/u', $trimmed)) {
        return ['valid' => false, 'error' => 'Name must contain at least one letter.'];
    }

    // No consecutive special characters like "--" or "''"
    if (preg_match('/[\-\'\.]{2,}/', $trimmed)) {
        return ['valid' => false, 'error' => 'Name contains invalid consecutive special characters.'];
    }

    // Check each word of the name against banned keywords
    $lowerName  = mb_strtolower($trimmed);
    $nameParts  = preg_split('/[\s\-]+/', $lowerName);
    foreach (BANNED_KEYWORDS as $keyword) {
        if (in_array($keyword, $nameParts, true)) {
            return [
                'valid' => false,
                'error' => "\"$keyword\" is not allowed as a name. Please enter your real full name (e.g. \"Juan dela Cruz\").",
            ];
        }
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Validates the email local part for banned role keywords.
 * @param string $email
 * @return array { valid: bool, error: string|null }
 */
function validateEmailAddress(string $email): array {
    if (empty($email)) {
        return ['valid' => false, 'error' => 'Email is required.'];
    }

    $trimmed = strtolower(trim($email));

    if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Please enter a valid email address.'];
    }

    $localPart  = explode('@', $trimmed)[0];

    // Strip digits and separators to isolate word tokens
    $localWords = preg_split('/\s+/', trim(preg_replace('/[\d\._\-\+]+/', ' ', $localPart)));

    foreach (BANNED_KEYWORDS as $keyword) {
        // Exact match on any token
        if (in_array($keyword, $localWords, true)) {
            return ['valid' => false, 'error' => "Email addresses containing \"$keyword\" are not allowed. Please use your personal email address."];
        }
        // Whole local part is the keyword
        if ($localPart === $keyword) {
            return ['valid' => false, 'error' => "Email addresses containing \"$keyword\" are not allowed. Please use your personal email address."];
        }
        // Keyword immediately followed by digits: "admin1@", "staff2@"
        if (preg_match('/^' . preg_quote($keyword, '/') . '\d/', $localPart)) {
            return ['valid' => false, 'error' => "Email addresses like \"{$keyword}1@...\" are not allowed. Please use your personal email address."];
        }
    }

    return ['valid' => true, 'error' => null];
}