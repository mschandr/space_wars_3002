<?php

namespace App\Services\Vendor;

use App\Models\VendorDialogue;

/**
 * Server-side validation for dialogue lines submitted by the Go generator.
 *
 * Mirrors the validation rules enforced by the Go service before submission,
 * providing defense-in-depth. Both sides must reject lines outside these bounds.
 *
 * Validation rules (source: docs/design/vendor_dialogue_joint_design_go_php.md § 9.6):
 * - Min/max word count
 * - Max character length
 * - No control characters
 * - No meta-commentary phrases
 * - No duplicate lines within the same submission batch
 */
class DialogueValidationService
{
    /**
     * Regex patterns that indicate meta-commentary rather than in-universe dialogue.
     * These are phrases an LLM uses when describing its own output.
     */
    private const META_COMMENTARY_PATTERNS = [
        '/\bhere are\b/i',
        '/\bhere\'s\b/i',
        '/\bcertainly\b/i',
        '/\bof course\b/i',
        '/\bas requested\b/i',
        '/\bI\'ll generate\b/i',
        '/\bsure,?\s+here\b/i',
        '/\bline\s+\d+[:.]/i',
        '/\bsure thing\b/i',
        '/\bno problem\b/i',
        '/\bI understand\b/i',
        '/\bI\'d be happy\b/i',
    ];

    /**
     * Validate a list of dialogue lines.
     *
     * @param  array  $lines  Raw strings from the Go generator
     * @return array  Failure entries: [['line' => string, 'reason' => string], ...]
     *                Empty array means all lines passed.
     */
    public function validateLines(array $lines): array
    {
        $minWords = (int) config('vendor_dialogue.validation.min_words', 6);
        $maxWords = (int) config('vendor_dialogue.validation.max_words', 20);
        $maxChars = (int) config('vendor_dialogue.validation.max_characters', 255);

        $failures = [];
        $seen     = [];

        foreach ($lines as $line) {
            if (! is_string($line)) {
                $failures[] = ['line' => (string) $line, 'reason' => 'Not a string'];
                continue;
            }

            // Character length
            if (strlen($line) > $maxChars) {
                $failures[] = ['line' => $line, 'reason' => "Exceeds {$maxChars} characters (" . strlen($line) . ")"];
                continue;
            }

            // Word count
            $wordCount = str_word_count($line);
            if ($wordCount < $minWords) {
                $failures[] = ['line' => $line, 'reason' => "Too short ({$wordCount} words, min {$minWords})"];
                continue;
            }
            if ($wordCount > $maxWords) {
                $failures[] = ['line' => $line, 'reason' => "Too long ({$wordCount} words, max {$maxWords})"];
                continue;
            }

            // Control characters
            if ($this->hasControlCharacters($line)) {
                $failures[] = ['line' => $line, 'reason' => 'Contains control characters'];
                continue;
            }

            // Meta-commentary
            if ($this->isMetaCommentary($line)) {
                $failures[] = ['line' => $line, 'reason' => 'Contains meta-commentary'];
                continue;
            }

            // Within-batch duplicate check
            $normalized = mb_strtolower(trim($line));
            if (isset($seen[$normalized])) {
                $failures[] = ['line' => $line, 'reason' => 'Duplicate line in submission'];
                continue;
            }
            $seen[$normalized] = true;
        }

        return $failures;
    }

    /**
     * Check whether an exact line already exists in the DB for a given vendor + line_type.
     * Optional cross-generation deduplication — slower, use only when needed.
     */
    public function isDuplicateInDatabase(int $galaxyVendorProfileId, string $lineType, string $lineText): bool
    {
        return VendorDialogue::where('galaxy_vendor_profile_id', $galaxyVendorProfileId)
            ->where('line_type', $lineType)
            ->where('line_text', $lineText)
            ->exists();
    }

    /**
     * Returns true if the string contains ASCII control characters
     * that should never appear in dialogue output.
     */
    private function hasControlCharacters(string $line): bool
    {
        // Allow \t (0x09), \n (0x0A), \r (0x0D) but reject all other control chars
        return (bool) preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line);
    }

    /**
     * Returns true if the line contains phrases typical of LLM meta-commentary.
     */
    private function isMetaCommentary(string $line): bool
    {
        foreach (self::META_COMMENTARY_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }
}
