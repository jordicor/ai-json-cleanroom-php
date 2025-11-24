<?php
declare(strict_types=1);

/**
 * ai_json_cleanroom.php (UTF-8 CORRECTED VERSION)
 *
 * A PHP port of the AI JSON Cleanroom helper originally implemented in Python.
 * The goal is identical: extract JSON-like payloads from AI responses, repair
 * common issues, validate via schema/expectations, and never throw upstream.
 *
 * REQUIREMENTS:
 * - PHP 8.1+ (for enums, mixed type, array_is_list)
 * - ext-mbstring REQUIRED for proper UTF-8 multibyte character handling
 *
 * FIXES APPLIED (v1.1):
 * - ✅ All string iteration now uses mb_str_split() for proper UTF-8 handling
 * - ✅ Replaced ctype_alpha/ctype_alnum with Unicode-aware regex patterns
 * - ✅ Added mb_strlen() and mb_substr() where needed
 * - ✅ Improved array detection with array_is_list() for PHP 8.1+
 * - ✅ Added proper encoding validation
 *
 * Public entry point: validate_ai_json($input, $schema = null, $expectations = null, ?ValidateOptions $options = null)
 *
 * @version 1.1.0-fixed
 * @author PHP port with UTF-8 fixes
 */

// Check requirements
if (!extension_loaded('mbstring')) {
    throw new RuntimeException('ai_json_cleanroom_fixed requires ext-mbstring. Please enable/install the extension before using this library.');
}

// --------------------------- JSON Engine ---------------------------

class JSONEngine
{
    /**
     * Decode JSON text with optional curly-quote sanitizing.
     *
     * @throws JsonException
     */
    public function decode(string $text, bool $sanitizeCurlyQuotes = true): mixed
    {
        if ($sanitizeCurlyQuotes) {
            $text = sanitize_curly_quotes($text);
        }
        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Same as decode but returns backend tag.
     *
     * @return array{0:mixed,1:string}
     * @throws JsonException
     */
    public function decodeWithBackend(string $text, bool $sanitizeCurlyQuotes = true): array
    {
        return [$this->decode($text, $sanitizeCurlyQuotes), 'json'];
    }

    /**
     * Encode data into JSON.
     */
    public function encode(mixed $data, bool $sortKeys = false, bool $ensureAscii = false, ?int $indent = null): string
    {
        if ($sortKeys) {
            $data = sort_keys_recursive($data);
        }
        $options = 0;
        if (!$ensureAscii) {
            $options |= JSON_UNESCAPED_UNICODE;
        }
        if ($indent !== null && $indent >= 0) {
            $options |= JSON_PRETTY_PRINT;
        }
        $encoded = json_encode($data, $options);
        if ($encoded === false) {
            throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        return $encoded;
    }

    /**
     * Same as encode but returns backend tag.
     *
     * @return array{0:string,1:string}
     */
    public function encodeWithBackend(mixed $data, bool $sortKeys = false, bool $ensureAscii = false, ?int $indent = null): array
    {
        return [$this->encode($data, $sortKeys, $ensureAscii, $indent), 'json'];
    }
}

$json_engine = new JSONEngine();

// ----------------------------- Result Types -----------------------------

enum ErrorCode: string
{
    case PARSE_ERROR = 'parse_error';
    case TRUNCATED = 'truncated';
    case MISSING_REQUIRED = 'missing_required';
    case TYPE_MISMATCH = 'type_mismatch';
    case ENUM_MISMATCH = 'enum_mismatch';
    case CONST_MISMATCH = 'const_mismatch';
    case NOT_ALLOWED_EMPTY = 'not_allowed_empty';
    case ADDITIONAL_PROPERTY = 'additional_property';
    case ADDITIONAL_ITEMS = 'additional_items';
    case PATTERN_MISMATCH = 'pattern_mismatch';
    case MIN_LENGTH = 'min_length';
    case MAX_LENGTH = 'max_length';
    case MIN_ITEMS = 'min_items';
    case MAX_ITEMS = 'max_items';
    case UNIQUE_ITEMS = 'unique_items';
    case MINIMUM = 'minimum';
    case MAXIMUM = 'maximum';
    case EXCLUSIVE_MINIMUM = 'exclusive_minimum';
    case EXCLUSIVE_MAXIMUM = 'exclusive_maximum';
    case MULTIPLE_OF = 'multiple_of';
    case ANY_OF_FAILED = 'any_of_failed';
    case ALL_OF_FAILED = 'all_of_failed';
    case ONE_OF_FAILED = 'one_of_failed';
    case ITEM_VALIDATION_FAILED = 'item_validation_failed';
    case PATH_NOT_FOUND = 'path_not_found';
    case EXPECTATION_FAILED = 'expectation_failed';
    case UNKNOWN = 'unknown';
    case REPAIRED = 'repaired';
}

class ValidationIssue
{
    public ErrorCode $code;
    public string $path;
    public string $message;
    /** @var array<string,mixed> */
    public array $detail;

    /**
     * @param array<string,mixed>|null $detail
     */
    public function __construct(ErrorCode $code, string $path, string $message, ?array $detail = null)
    {
        $this->code = $code;
        $this->path = $path;
        $this->message = $message;
        $this->detail = $detail ?? [];
    }

    /**
     * @return array{code:string,path:string,message:string,detail:array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'path' => $this->path,
            'message' => $this->message,
            'detail' => $this->detail,
        ];
    }
}

class ValidationResult
{
    public bool $jsonValid;
    public bool $likelyTruncated;
    /** @var list<ValidationIssue> */
    public array $errors;
    /** @var list<ValidationIssue> */
    public array $warnings;
    public mixed $data;
    /** @var array<string,mixed> */
    public array $info;

    /**
     * @param list<ValidationIssue> $errors
     * @param list<ValidationIssue> $warnings
     * @param mixed $data
     * @param array<string,mixed> $info
     */
    public function __construct(
        bool $jsonValid,
        bool $likelyTruncated,
        array $errors = [],
        array $warnings = [],
        mixed $data = null,
        array $info = []
    ) {
        $this->jsonValid = $jsonValid;
        $this->likelyTruncated = $likelyTruncated;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->data = $data;
        $this->info = $info;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'json_valid' => $this->jsonValid,
            'likely_truncated' => $this->likelyTruncated,
            'errors' => array_map(fn(ValidationIssue $issue) => $issue->toArray(), $this->errors),
            'warnings' => array_map(fn(ValidationIssue $issue) => $issue->toArray(), $this->warnings),
            'data' => $this->data,
            'info' => $this->info,
        ];
    }
}

class ValidateOptions
{
    public bool $strict = false;
    public bool $extractJson = true;
    public bool $tolerateTrailingCommas = true;
    public bool $allowJsonInCodeFences = true;
    public bool $allowBareTopLevelScalars = false;
    public bool $stopOnFirstError = false;

    public bool $enableSafeRepairs = true;
    public bool $allowJson5Like = true;
    public bool $replaceConstants = true;
    public bool $replaceNansInfinities = true;
    public int $maxTotalRepairs = 200;
    public float $maxRepairsPercent = 0.02;

    public string $normalizeCurlyQuotes = 'always';
    public bool $fixSingleQuotes = true;
    public bool $quoteUnquotedKeys = true;
    public bool $stripJsComments = true;

    /** @var list<callable(string,ValidateOptions):array{0:string,1:int,2:array<string,mixed>}>|null */
    public ?array $customRepairHooks = null;

    public function __construct(?array $values = null)
    {
        if ($values) {
            foreach ($values as $key => $value) {
                $property = $this->snakeToCamel($key);
                if (property_exists($this, $property)) {
                    $this->{$property} = $value;
                }
            }
        }
        if ($this->strict && !$this->stopOnFirstError) {
            $this->stopOnFirstError = true;
        }
    }

    private function snakeToCamel(string $key): string
    {
        $parts = explode('_', $key);
        $camel = array_shift($parts);
        foreach ($parts as $part) {
            $camel .= ucfirst($part);
        }
        return $camel ?? $key;
    }
}

// ----------------------------- Utilities -----------------------------

function is_integer_value(mixed $value): bool
{
    return is_int($value);
}

function is_number_value(mixed $value): bool
{
    return is_int($value) || is_float($value);
}

/**
 * @param mixed $value
 * @param string|list<string> $expected
 */
function types_match(mixed $value, string|array $expected): bool
{
    if (is_array($expected)) {
        foreach ($expected as $exp) {
            if (types_match($value, $exp)) {
                return true;
            }
        }
        return false;
    }
    $expected = strtolower($expected);
    return match ($expected) {
        'integer' => is_integer_value($value),
        'number' => is_number_value($value),
        'boolean' => is_bool($value),
        'object' => is_array($value) && is_assoc_array($value),
        'array' => is_array($value),
        'string' => is_string($value),
        'null' => $value === null,
        default => false,
    };
}

function is_empty_value(mixed $value): bool
{
    if ($value === null) {
        return true;
    }
    if (is_string($value)) {
        return trim($value) === '';
    }
    if (is_array($value)) {
        return count($value) === 0;
    }
    return false;
}

/**
 * ✅ FIXED: Now uses array_is_list() for PHP 8.1+
 */
function is_list_array(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }
    // Fallback for older PHP (though we require 8.1+)
    return array_keys($value) === range(0, count($value) - 1);
}

function is_assoc_array(array $value): bool
{
    return !is_list_array($value);
}

const DOUBLE_CURLY_QUOTES = ["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{00AB}", "\u{00BB}", "\u{2033}"];
const SINGLE_CURLY_QUOTES = ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{2032}", "\u{2039}", "\u{203A}"];

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 character iteration
 */
function sanitize_curly_quotes(string $text): string
{
    // Convert to array of UTF-8 characters
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $inString = false;
    $escape = false;
    $quoteKind = null; // "straight" or "curly"
    $length = count($chars);

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                if ($quoteKind === 'straight') {
                    $out[] = '"';
                    $inString = false;
                    $quoteKind = null;
                } else {
                    $out[] = '\\"';
                }
                continue;
            }
            if (in_array($ch, DOUBLE_CURLY_QUOTES, true)) {
                if ($quoteKind === 'curly') {
                    [$nextChar, ] = next_non_ws_mb($chars, $i + 1);
                    if ($nextChar === null || in_array($nextChar, [',', '}', ']', ':'], true)) {
                        $out[] = '"';
                        $inString = false;
                        $quoteKind = null;
                    } else {
                        $out[] = '\\"';
                    }
                } else {
                    $out[] = '\\"';
                }
                continue;
            }
            if (in_array($ch, SINGLE_CURLY_QUOTES, true)) {
                $out[] = "'";
                continue;
            }
            $out[] = $ch;
        } else {
            if ($ch === '"') {
                $out[] = '"';
                $inString = true;
                $quoteKind = 'straight';
                continue;
            }
            if (in_array($ch, DOUBLE_CURLY_QUOTES, true)) {
                $out[] = '"';
                $inString = true;
                $quoteKind = 'curly';
                continue;
            }
            if (in_array($ch, SINGLE_CURLY_QUOTES, true)) {
                $out[] = "'";
                continue;
            }
            $out[] = $ch;
        }
    }
    return implode('', $out);
}

/**
 * @return array{0:mixed,1:bool}
 */
function normalize_curly_quotes_in_data(mixed $value): array
{
    $changed = false;
    if (is_string($value)) {
        $newValue = $value;
        foreach (DOUBLE_CURLY_QUOTES as $quote) {
            if (str_contains($newValue, $quote)) {
                $newValue = str_replace($quote, '"', $newValue);
                $changed = true;
            }
        }
        foreach (SINGLE_CURLY_QUOTES as $quote) {
            if (str_contains($newValue, $quote)) {
                $newValue = str_replace($quote, "'", $newValue);
                $changed = true;
            }
        }
        return [$newValue, $changed];
    }
    if (is_array($value)) {
        $isList = is_list_array($value);
        foreach ($value as $key => $item) {
            [$newItem, $itemChanged] = normalize_curly_quotes_in_data($item);
            if ($itemChanged) {
                $value[$key] = $newItem;
                $changed = true;
            }
        }
        if ($isList) {
            return [array_values($value), $changed];
        }
        return [$value, $changed];
    }
    return [$value, false];
}

/**
 * @param array<string,mixed> $schema
 * @param list<string> $candidates
 */
function schema_kw(array $schema, array $candidates, mixed $default = null): mixed
{
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $schema)) {
            return $schema[$candidate];
        }
    }
    $mapping = [
        'minLength' => 'min_length',
        'maxLength' => 'max_length',
        'minItems' => 'min_items',
        'maxItems' => 'max_items',
        'exclusiveMinimum' => 'exclusive_minimum',
        'exclusiveMaximum' => 'exclusive_maximum',
        'additionalProperties' => 'additional_properties',
        'patternProperties' => 'pattern_properties',
        'uniqueItems' => 'unique_items',
        'allowEmpty' => 'allow_empty',
        'additionalItems' => 'additional_items',
    ];
    foreach ($candidates as $candidate) {
        if (isset($mapping[$candidate]) && array_key_exists($mapping[$candidate], $schema)) {
            return $schema[$mapping[$candidate]];
        }
    }
    return $default;
}

function sort_keys_recursive(mixed $value): mixed
{
    if (is_array($value)) {
        $isList = is_list_array($value);
        if ($isList) {
            $result = [];
            foreach ($value as $item) {
                $result[] = sort_keys_recursive($item);
            }
            return $result;
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = sort_keys_recursive($item);
        }
        return $value;
    }
    return $value;
}

/**
 * ✅ FIXED: New multibyte-safe version using character array
 * @param list<string> $chars Array of UTF-8 characters
 * @return array{0:string|null,1:int}
 */
function next_non_ws_mb(array $chars, int $index): array
{
    $length = count($chars);
    $i = $index;
    while ($i < $length && in_array($chars[$i], [' ', "\t", "\r", "\n"], true)) {
        $i++;
    }
    return [$i < $length ? $chars[$i] : null, $i];
}

/**
 * ✅ FIXED: New multibyte-safe version using character array
 * @param list<string> $chars Array of UTF-8 characters
 * @return array{0:string|null,1:int}
 */
function prev_non_ws_mb(array $chars, int $index): array
{
    $i = $index;
    while ($i >= 0 && in_array($chars[$i], [' ', "\t", "\r", "\n"], true)) {
        $i--;
    }
    return [$i >= 0 ? $chars[$i] : null, $i];
}

/**
 * ✅ FIXED: Kept for backward compatibility with non-MB aware code
 * @return array{0:string|null,1:int}
 */
function next_non_ws(string $text, int $index): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    return next_non_ws_mb($chars, $index);
}

/**
 * ✅ FIXED: Kept for backward compatibility
 * @return array{0:string|null,1:int}
 */
function prev_non_ws(string $text, int $index): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    return prev_non_ws_mb($chars, $index);
}

function wrap_schema_pattern(string $pattern): string
{
    $delimiter = '/';
    $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);
    return $delimiter . $escaped . $delimiter . 'u';
}

/**
 * ✅ FIXED: Helper to check if character is Unicode letter
 */
function is_unicode_letter(string $char): bool
{
    return preg_match('/\p{L}/u', $char) === 1;
}

/**
 * ✅ FIXED: Helper to check if character is Unicode letter or digit
 */
function is_unicode_alnum(string $char): bool
{
    return preg_match('/[\p{L}\p{N}]/u', $char) === 1;
}

// ----------------------- JSON Extraction & Parsing -----------------------

const CODE_FENCE_PATTERN = '/```(\w+)?\s*([\s\S]*?)```/m';

/**
 * @return array{0:string|null,1:array<string,mixed>}
 */
function extract_json_payload(string $text, ?ValidateOptions $options = null): array
{
    $options ??= new ValidateOptions();
    $info = ['source' => null, 'extraction' => []];

    $directPayload = trim($text);
    if ($directPayload !== '') {
        [$payload, $ok] = try_parseable_as_is($directPayload, $options);
        if ($ok) {
            $info['source'] = 'raw';
            return [$payload, $info];
        }
    }

    if (!$options->extractJson) {
        $info['source'] = 'raw';
        $info['extraction'] = ['extract_json' => false];
        return [$directPayload, $info];
    }

    if ($options->allowJsonInCodeFences) {
        $matchCount = preg_match_all(CODE_FENCE_PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($matchCount && $matchCount > 0) {
            foreach ($matches as $match) {
                $lang = strtolower($match[1][0] ?? '');
                $body = trim($match[2][0] ?? '');
                if ($body === '') {
                    continue;
                }
                if ($lang === 'json') {
                    [$payload, $ok] = try_parseable_as_is($body, $options);
                    if ($ok) {
                        $info['source'] = 'code_fence';
                        $info['extraction'] = ['lang' => $lang, 'span' => [$match[0][1], $match[0][1] + strlen($match[0][0])]];
                        return [$payload, $info];
                    }
                }
            }
            foreach ($matches as $match) {
                $body = trim($match[2][0] ?? '');
                if ($body === '') {
                    continue;
                }
                [$payload, $ok] = try_parseable_as_is($body, $options);
                if ($ok) {
                    $info['source'] = 'code_fence';
                    $info['extraction'] = ['lang' => strtolower($match[1][0] ?? ''), 'span' => [$match[0][1], $match[0][1] + strlen($match[0][0])]];
                    return [$payload, $info];
                }
            }
        }
    }

    [$balanced, $span, $truncated] = extract_first_balanced_block($text);
    if ($balanced !== null) {
        [$payload, $ok] = try_parseable_as_is($balanced, $options);
        $info['source'] = 'balanced_block';
        $info['extraction'] = ['span' => [$span[0], $span[1]], 'truncated_at_end' => $truncated];
        if ($ok) {
            return [$payload, $info];
        }
        return [$balanced, $info];
    }

    $info['extraction'] = ['reason' => 'no_json_found'];
    return [null, $info];
}

/**
 * @return array{0:string,1:bool}
 */
function try_parseable_as_is(string $text, ValidateOptions $options): array
{
    if (loads_ok($text, $options)) {
        return [$text, true];
    }
    if ($options->tolerateTrailingCommas) {
        $normalized = remove_trailing_commas($text);
        if ($normalized !== $text && loads_ok($normalized, $options)) {
            return [$normalized, true];
        }
    }
    return [$text, false];
}

/**
 * @return array{0:mixed,1:string,2:bool}
 */
function parse_with_options(string $text, ValidateOptions $options): array
{
    global $json_engine;
    $mode = strtolower($options->normalizeCurlyQuotes ?? 'always');
    if ($mode === 'always') {
        [$obj, $backend] = $json_engine->decodeWithBackend($text, true);
        return [$obj, $backend, true];
    }
    if ($mode === 'never') {
        [$obj, $backend] = $json_engine->decodeWithBackend($text, false);
        return [$obj, $backend, false];
    }
    if ($mode === 'auto') {
        try {
            [$obj, $backend] = $json_engine->decodeWithBackend($text, false);
            return [$obj, $backend, false];
        } catch (Throwable) {
            [$obj, $backend] = $json_engine->decodeWithBackend($text, true);
            return [$obj, $backend, true];
        }
    }
    [$obj, $backend] = $json_engine->decodeWithBackend($text, true);
    return [$obj, $backend, true];
}

function loads_ok(string $text, ValidateOptions $options): bool
{
    try {
        parse_with_options($text, $options);
        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 */
function remove_trailing_commas(string $text): string
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $inString = false;
    $escape = false;
    $length = count($chars);

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];
        $out[] = $ch;

        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            continue;
        }

        if ($ch === ',') {
            $j = $i + 1;
            while ($j < $length && in_array($chars[$j], [' ', "\r", "\n", "\t"], true)) {
                $j++;
            }
            if ($j < $length && in_array($chars[$j], [']', '}'], true)) {
                array_pop($out); // Remove the comma
            }
        }
    }
    return implode('', $out);
}

/**
 * @return array{0:string,1:int}
 */
function remove_trailing_commas_with_count(string $text): array
{
    $cleaned = remove_trailing_commas($text);
    $removed = mb_strlen($text, 'UTF-8') - mb_strlen($cleaned, 'UTF-8');
    return [$cleaned, max(0, $removed)];
}

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 * @return array{0:string|null,1:array{0:int,1:int},2:bool}
 */
function extract_first_balanced_block(string $text): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $length = count($chars);
    $start = null;

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];
        if ($ch === '{' || $ch === '[') {
            $start = $i;
            break;
        }
    }

    if ($start === null) {
        return [null, [0, 0], false];
    }

    $opening = $chars[$start];
    $closing = $opening === '{' ? '}' : ']';
    $stack = [$opening];
    $inString = false;
    $escape = false;

    for ($i = $start + 1; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            continue;
        }

        if ($ch === $opening) {
            $stack[] = $ch;
        } elseif ($ch === $closing) {
            array_pop($stack);
            if (empty($stack)) {
                $block = implode('', array_slice($chars, $start, $i - $start + 1));
                return [$block, [$start, $i + 1], false];
            }
        }
    }

    // Unbalanced - likely truncated
    $block = implode('', array_slice($chars, $start));
    return [$block, [$start, $length], true];
}

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 * @return array{0:bool,1:list<string>}
 */
function detect_truncation(string $text): array
{
    $reasons = [];
    if ($text === '') {
        return [false, $reasons];
    }

    $chars = mb_str_split($text, 1, 'UTF-8');
    $length = count($chars);
    $inString = false;
    $escape = false;
    $stack = [];

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $escape = false;
            } elseif ($ch === '\\') {
                $escape = true;
            } elseif ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
        } elseif ($ch === '{' || $ch === '[') {
            $stack[] = $ch;
        } elseif ($ch === '}' || $ch === ']') {
            if (!empty($stack)) {
                $opening = array_pop($stack);
                if (($opening === '{' && $ch !== '}') || ($opening === '[' && $ch !== ']')) {
                    $reasons[] = 'mismatched_brackets';
                }
            }
        }
    }

    if ($inString) {
        $reasons[] = 'unclosed_string';
    }
    if (!empty($stack)) {
        $reasons[] = 'unclosed_braces_or_brackets';
    }

    $stripped = rtrim($text);
    if ($stripped !== '') {
        $lastChar = mb_substr($stripped, -1, 1, 'UTF-8');
        if (in_array($lastChar, [',', ':', '{', '[', '\\'], true)) {
            $reasons[] = 'suspicious_trailing_character';
        }
    }
    if (str_ends_with($stripped, '...')) {
        $reasons[] = 'ellipsis_or_continuation_marker';
    }

    return [count($reasons) > 0, $reasons];
}

// --------------------------- Safe Repair Utilities ---------------------------

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 * @return array{0:string,1:int,2:array<string,int>}
 */
function repair_escape_inner_quotes_and_controls(string $text): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $length = count($chars);
    $inString = false;
    $escape = false;
    $changes = 0;
    $counts = [
        'escaped_inner_quotes' => 0,
        'escaped_newlines' => 0,
        'escaped_tabs' => 0,
        'escaped_carriage_returns' => 0,
    ];

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                [$nextChar, ] = next_non_ws_mb($chars, $i + 1);
                if ($nextChar !== null && !in_array($nextChar, [',', '}', ']', ':'], true)) {
                    $out[] = '\\"';
                    $changes++;
                    $counts['escaped_inner_quotes']++;
                    continue;
                }
                $out[] = '"';
                $inString = false;
                continue;
            }
            if ($ch === "\n") {
                $out[] = '\\n';
                $changes++;
                $counts['escaped_newlines']++;
                continue;
            }
            if ($ch === "\t") {
                $out[] = '\\t';
                $changes++;
                $counts['escaped_tabs']++;
                continue;
            }
            if ($ch === "\r") {
                $out[] = '\\r';
                $changes++;
                $counts['escaped_carriage_returns']++;
                continue;
            }
            $out[] = $ch;
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            $out[] = $ch;
            continue;
        }
        $out[] = $ch;
    }

    return [implode('', $out), $changes, $counts];
}

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 * @return array{0:string,1:int,2:int}
 */
function repair_convert_single_quoted_strings(string $text): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $length = count($chars);
    $inString = false;
    $escape = false;
    $changes = 0;
    $converted = 0;

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $out[] = '"';
                $inString = false;
                continue;
            }
            $out[] = $ch;
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            $out[] = '"';
            continue;
        }

        if ($ch === "'") {
            [$prev, ] = prev_non_ws_mb($chars, $i - 1);
            if ($prev === null || in_array($prev, ['{', '[', ',', ':'], true)) {
                $j = $i + 1;
                $buf = [];
                $escSq = false;
                $ok = false;

                while ($j < $length) {
                    $cj = $chars[$j];
                    if ($escSq) {
                        $buf[] = $cj;
                        $escSq = false;
                        $j++;
                        continue;
                    }
                    if ($cj === '\\') {
                        $buf[] = $cj;
                        $escSq = true;
                        $j++;
                        continue;
                    }
                    if ($cj === "'") {
                        $ok = true;
                        break;
                    }
                    if ($cj === "\n") {
                        $buf[] = '\\n';
                        $changes++;
                        $j++;
                        continue;
                    }
                    if ($cj === "\t") {
                        $buf[] = '\\t';
                        $changes++;
                        $j++;
                        continue;
                    }
                    if ($cj === "\r") {
                        $buf[] = '\\r';
                        $changes++;
                        $j++;
                        continue;
                    }
                    $buf[] = $cj;
                    $j++;
                }

                if ($ok) {
                    $content = str_replace('"', '\\"', implode('', $buf));
                    $out[] = '"';
                    $out[] = $content;
                    $out[] = '"';
                    $changes += 2;
                    $converted++;
                    $i = $j;
                    continue;
                }
            }
        }
        $out[] = $ch;
    }

    return [implode('', $out), $changes, $converted];
}

/**
 * ✅ FIXED: Now uses mb_str_split() and Unicode-aware character checks
 * @return array{0:string,1:int,2:int}
 */
function repair_quote_unquoted_keys(string $text): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $length = count($chars);
    $inString = false;
    $escape = false;
    $changes = 0;
    $quoted = 0;

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            $out[] = $ch;
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            $out[] = '"';
            continue;
        }

        // ✅ FIXED: Use Unicode-aware letter check instead of ctype_alpha
        if (is_unicode_letter($ch) || $ch === '_') {
            $j = $i + 1;
            $token = [$ch];

            // ✅ FIXED: Use Unicode-aware alphanumeric check
            while ($j < $length && (is_unicode_alnum($chars[$j]) || $chars[$j] === '_')) {
                $token[] = $chars[$j];
                $j++;
            }

            $tokenStr = implode('', $token);
            [$nextChar, $k] = next_non_ws_mb($chars, $j);

            if ($nextChar === ':') {
                [$prevChar, ] = prev_non_ws_mb($chars, $i - 1);
                if ($prevChar === '{' || $prevChar === ',') {
                    $out[] = '"';
                    $out[] = $tokenStr;
                    $out[] = '"';
                    $changes += 2;
                    $quoted++;
                    $i = $j - 1;
                    continue;
                }
            }

            $out[] = $tokenStr;
            $i = $j - 1;
            continue;
        }

        $out[] = $ch;
    }

    return [implode('', $out), $changes, $quoted];
}

/**
 * ✅ FIXED: Now uses mb_str_split() and Unicode-aware character checks
 * @return array{0:string,1:int,2:array<string,int>}
 */
function repair_replace_constants(string $text, bool $replaceNaNsInfinities = true): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $length = count($chars);
    $inString = false;
    $escape = false;
    $changes = 0;
    $counts = ['true_false_none' => 0, 'nans_infinities' => 0];

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            $out[] = $ch;
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            $out[] = '"';
            continue;
        }

        // ✅ FIXED: Use Unicode-aware checks for identifier start
        if (is_unicode_letter($ch) || $ch === '-') {
            $j = $i;
            $token = [];

            // Build token with alphanumeric, underscore, and dash
            while ($j < $length && (is_unicode_alnum($chars[$j]) || in_array($chars[$j], ['_', '-'], true))) {
                $token[] = $chars[$j];
                $j++;
            }

            $tokenStr = implode('', $token);
            $replacement = null;

            if (in_array($tokenStr, ['True', 'False', 'None'], true)) {
                $replacement = ['True' => 'true', 'False' => 'false', 'None' => 'null'][$tokenStr];
                $counts['true_false_none']++;
            } elseif ($replaceNaNsInfinities && in_array($tokenStr, ['NaN', 'Infinity', '-Infinity'], true)) {
                $replacement = 'null';
                $counts['nans_infinities']++;
            }

            if ($replacement !== null) {
                $out[] = $replacement;
                $changes++;
                $i = $j - 1;
                continue;
            }

            $out[] = $tokenStr;
            $i = $j - 1;
            continue;
        }

        $out[] = $ch;
    }

    return [implode('', $out), $changes, $counts];
}

/**
 * ✅ FIXED: Now uses mb_str_split() for proper UTF-8 handling
 * @return array{0:string,1:int,2:array<string,int>}
 */
function repair_strip_js_comments(string $text): array
{
    $chars = mb_str_split($text, 1, 'UTF-8');
    $out = [];
    $length = count($chars);
    $inString = false;
    $escape = false;
    $removed = 0;
    $counts = ['line' => 0, 'block' => 0];

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($inString) {
            if ($escape) {
                $out[] = $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out[] = $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            $out[] = $ch;
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            $out[] = '"';
            continue;
        }

        if ($ch === '/' && $i + 1 < $length) {
            $next = $chars[$i + 1];

            if ($next === '/') {
                // Line comment
                $i += 2;
                while ($i < $length && !in_array($chars[$i], ["\n", "\r"], true)) {
                    $i++;
                }
                $removed++;
                $counts['line']++;
                $i--; // Adjust for loop increment
                continue;
            }

            if ($next === '*') {
                // Block comment
                $i += 2;
                while ($i + 1 < $length) {
                    if ($chars[$i] === '*' && $chars[$i + 1] === '/') {
                        $i += 2;
                        break;
                    }
                    $i++;
                }
                $removed++;
                $counts['block']++;
                $i--; // Adjust for loop increment
                continue;
            }
        }

        $out[] = $ch;
    }

    return [implode('', $out), $removed, $counts];
}

/**
 * @return array{0:string|null,1:array<string,mixed>}
 */
function attempt_safe_json_repair(string $payload, ValidateOptions $options): array
{
    $info = ['applied' => [], 'counts' => [], 'skipped' => null];
    [$truncated, $reasons] = detect_truncation($payload);
    if ($truncated) {
        $info['skipped'] = ['reason' => 'likely_truncated', 'reasons' => $reasons];
        return [null, $info];
    }

    $percentLimit = (int) (mb_strlen($payload, 'UTF-8') * $options->maxRepairsPercent);
    if ($percentLimit <= 0 || $percentLimit < 25) {
        $percentLimit = $options->maxTotalRepairs;
    }
    $maxChanges = max(1, min($options->maxTotalRepairs, $percentLimit));
    $totalChanges = 0;
    $candidate = $payload;

    $tryParse = function (string $txt) use ($options): bool {
        try {
            parse_with_options($txt, $options);
            return true;
        } catch (Throwable) {
            return false;
        }
    };

    [$repaired, $changes, $counts] = repair_escape_inner_quotes_and_controls($candidate);
    if ($changes) {
        $totalChanges += $changes;
        if ($totalChanges > $maxChanges) {
            $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'inner_quotes'];
            return [null, $info];
        }
        $candidate = $repaired;
        $info['applied'][] = 'escape_inner_quotes_and_controls';
        $info['counts']['escape_inner_quotes_and_controls'] = $counts;
        if ($tryParse($candidate)) {
            return [$candidate, $info];
        }
    }

    if ($options->allowJson5Like && $options->fixSingleQuotes) {
        [$repaired, $changes, $converted] = repair_convert_single_quoted_strings($candidate);
        if ($changes) {
            $totalChanges += $changes;
            if ($totalChanges > $maxChanges) {
                $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'single_quotes'];
                return [null, $info];
            }
            $candidate = $repaired;
            $info['applied'][] = 'single_quoted_to_double_quoted';
            $info['counts']['single_quoted_strings_converted'] = $converted;
            if ($tryParse($candidate)) {
                return [$candidate, $info];
            }
        }
    }

    if ($options->allowJson5Like && $options->stripJsComments) {
        [$repaired, $removed, $commCounts] = repair_strip_js_comments($candidate);
        if ($removed) {
            $totalChanges += $removed;
            if ($totalChanges > $maxChanges) {
                $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'comments'];
                return [null, $info];
            }
            $candidate = $repaired;
            $info['applied'][] = 'strip_js_comments';
            $info['counts']['comments_removed'] = $commCounts;
            if ($tryParse($candidate)) {
                return [$candidate, $info];
            }
        }
    }

    if ($options->allowJson5Like && $options->quoteUnquotedKeys) {
        [$repaired, $changes, $quoted] = repair_quote_unquoted_keys($candidate);
        if ($changes) {
            $totalChanges += $changes;
            if ($totalChanges > $maxChanges) {
                $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'unquoted_keys'];
                return [null, $info];
            }
            $candidate = $repaired;
            $info['applied'][] = 'quote_unquoted_keys';
            $info['counts']['unquoted_keys'] = $quoted;
            if ($tryParse($candidate)) {
                return [$candidate, $info];
            }
        }
    }

    if ($options->tolerateTrailingCommas) {
        [$repaired, $removedCommas] = remove_trailing_commas_with_count($candidate);
        if ($removedCommas) {
            $totalChanges += $removedCommas;
            if ($totalChanges > $maxChanges) {
                $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'trailing_commas'];
                return [null, $info];
            }
            $candidate = $repaired;
            $info['applied'][] = 'remove_trailing_commas';
            $info['counts']['trailing_commas_removed'] = $removedCommas;
            if ($tryParse($candidate)) {
                return [$candidate, $info];
            }
        }
    }

    if ($options->replaceConstants) {
        [$repaired, $changes, $constCounts] = repair_replace_constants($candidate, $options->replaceNansInfinities);
        if ($changes) {
            $totalChanges += $changes;
            if ($totalChanges > $maxChanges) {
                $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'constants'];
                return [null, $info];
            }
            $candidate = $repaired;
            $info['applied'][] = 'replace_constants';
            $info['counts']['replace_constants'] = $constCounts;
            if ($tryParse($candidate)) {
                return [$candidate, $info];
            }
        }
    }

    if ($options->customRepairHooks) {
        foreach ($options->customRepairHooks as $hook) {
            try {
                [$repaired, $changes, $meta] = $hook($candidate, $options);
            } catch (Throwable $ex) {
                $info['hooks_errors'][] = ['hook' => is_string($hook) ? $hook : 'callable', 'error' => $ex->getMessage()];
                continue;
            }
            if ($changes) {
                $totalChanges += $changes;
                if ($totalChanges > $maxChanges) {
                    $info['skipped'] = ['reason' => 'too_many_modifications', 'threshold' => $maxChanges, 'after_pass' => 'custom_hook'];
                    return [null, $info];
                }
                $candidate = $repaired;
                $tag = 'custom_hook';
                $info['applied'][] = $tag;
                $info['counts'][$tag] = $meta ?? [];
                if ($tryParse($candidate)) {
                    return [$candidate, $info];
                }
            }
        }
    }

    if ($candidate !== $payload && $tryParse($candidate)) {
        return [$candidate, $info];
    }

    return [null, $info];
}

// --------------------------- Schema Validation ---------------------------

class SimpleJSONSchemaValidator
{
    private ValidateOptions $options;
    /** @var list<ValidationIssue> */
    public array $errors = [];
    private bool $stopOnFirstError;

    public function __construct(?ValidateOptions $options = null)
    {
        $this->options = $options ?? new ValidateOptions();
        $this->stopOnFirstError = (bool) ($this->options->stopOnFirstError || $this->options->strict);
    }

    /**
        * @param array<string,mixed> $schema
        * @return list<ValidationIssue>
        */
    public function validate(mixed $data, array $schema, string $path = '$'): array
    {
        $this->errors = [];
        $this->validateNode($data, $schema, $path);
        return $this->errors;
    }

    private function validateNode(mixed $value, array $schema, string $path): void
    {
        if ($this->stopOnFirstError && !empty($this->errors)) {
            return;
        }

        foreach (['anyOf', 'oneOf', 'allOf'] as $comb) {
            if (array_key_exists($comb, $schema)) {
                $method = 'validate' . ucfirst($comb);
                if (method_exists($this, $method)) {
                    $this->{$method}($value, $schema[$comb], $path);
                }
            }
        }

        if ($this->stopOnFirstError && !empty($this->errors)) {
            return;
        }

        $typeSpec = $schema['type'] ?? null;
        $nullable = !empty($schema['nullable']);
        if ($typeSpec !== null) {
            if ($value === null) {
                if (!$nullable && !(is_array($typeSpec) && in_array('null', $typeSpec, true))) {
                    $this->err(ErrorCode::TYPE_MISMATCH, $path, "Expected type {$this->stringify($typeSpec)}, got null.");
                    return;
                }
            } else {
                if (is_array($typeSpec)) {
                    $match = false;
                    foreach ($typeSpec as $spec) {
                        if (types_match($value, (string) $spec)) {
                            $match = true;
                            break;
                        }
                    }
                    if (!$match) {
                        $this->err(ErrorCode::TYPE_MISMATCH, $path, "Expected one of types " . json_encode($typeSpec) . ", got " . gettype($value) . '.');
                        return;
                    }
                } else {
                    if (!types_match($value, (string) $typeSpec)) {
                        $this->err(ErrorCode::TYPE_MISMATCH, $path, "Expected type {$typeSpec}, got " . gettype($value) . '.');
                        return;
                    }
                }
            }
        }

        $allowEmpty = schema_kw($schema, ['allow_empty', 'allowEmpty'], true);
        if ($allowEmpty === false && is_empty_value($value)) {
            $this->err(ErrorCode::NOT_ALLOWED_EMPTY, $path, 'Value is empty but allow_empty is False.');
            if ($this->stopOnFirstError) {
                return;
            }
        }

        if (array_key_exists('enum', $schema) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $this->err(ErrorCode::ENUM_MISMATCH, $path, 'Value ' . $this->repr($value) . ' not in enum.');
            }
        }
        if (array_key_exists('const', $schema)) {
            if ($value !== $schema['const']) {
                $this->err(ErrorCode::CONST_MISMATCH, $path, 'Value ' . $this->repr($value) . ' != const ' . $this->repr($schema['const']) . '.');
            }
        }

        if (is_array($value) && is_assoc_array($value)) {
            $this->validateObject($value, $schema, $path);
        } elseif (is_array($value)) {
            $this->validateArray($value, $schema, $path);
        } elseif (is_string($value)) {
            $this->validateString($value, $schema, $path);
        } elseif (is_number_value($value)) {
            $this->validateNumber($value, $schema, $path);
        }
    }

    /**
     * @param array<string,mixed> $obj
     */
    private function validateObject(array $obj, array $schema, string $path): void
    {
        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $key) {
                if (!array_key_exists($key, $obj)) {
                    $this->err(ErrorCode::MISSING_REQUIRED, "{$path}.{$key}", "Required property '{$key}' is missing.");
                }
            }
        }

        $properties = $schema['properties'] ?? [];
        $patternProps = schema_kw($schema, ['patternProperties', 'pattern_properties'], []);
        $additionalProps = schema_kw($schema, ['additionalProperties', 'additional_properties'], true);

        $matchedKeys = [];
        if (is_array($properties)) {
            foreach ($properties as $key => $subschema) {
                if (array_key_exists($key, $obj) && is_array($subschema)) {
                    $matchedKeys[$key] = true;
                    $this->validateNode($obj[$key], $subschema, "{$path}.{$key}");
                }
            }
        }

        $compiledPatterns = [];
        if (is_array($patternProps)) {
            foreach ($patternProps as $pattern => $subschema) {
                $regex = wrap_schema_pattern((string) $pattern);
                if (@preg_match($regex, '') === false) {
                    $this->err(ErrorCode::PATTERN_MISMATCH, $path, "Invalid regex in patternProperties: {$pattern}");
                    continue;
                }
                $compiledPatterns[] = [$regex, $subschema];
            }
        }

        foreach ($obj as $key => $val) {
            if (isset($matchedKeys[$key])) {
                continue;
            }
            $matchedByPattern = false;
            foreach ($compiledPatterns as [$regex, $subschema]) {
                if (preg_match($regex, (string) $key)) {
                    $matchedByPattern = true;
                    if (is_array($subschema)) {
                        $this->validateNode($val, $subschema, "{$path}.{$key}");
                    }
                }
            }
            if (!$matchedByPattern) {
                if ($additionalProps === false) {
                    $this->err(ErrorCode::ADDITIONAL_PROPERTY, "{$path}.{$key}", "Additional property '{$key}' not allowed.");
                } elseif (is_array($additionalProps)) {
                    $this->validateNode($val, $additionalProps, "{$path}.{$key}");
                }
            }
        }

        $allowEmpty = schema_kw($schema, ['allow_empty', 'allowEmpty'], true);
        if ($allowEmpty === false && empty($obj)) {
            $this->err(ErrorCode::NOT_ALLOWED_EMPTY, $path, 'Object is empty but allow_empty is False.');
        }
    }

    /**
     * @param list<mixed> $arr
     */
    private function validateArray(array $arr, array $schema, string $path): void
    {
        $minItems = schema_kw($schema, ['minItems', 'min_items'], null);
        if ($minItems !== null && count($arr) < (int) $minItems) {
            $this->err(ErrorCode::MIN_ITEMS, $path, "Array has " . count($arr) . " items < minItems {$minItems}.");
        }
        $maxItems = schema_kw($schema, ['maxItems', 'max_items'], null);
        if ($maxItems !== null && count($arr) > (int) $maxItems) {
            $this->err(ErrorCode::MAX_ITEMS, $path, "Array has " . count($arr) . " items > maxItems {$maxItems}.");
        }

        $unique = schema_kw($schema, ['uniqueItems', 'unique_items'], false);
        if ($unique) {
            $seen = [];
            $duplicate = false;
            foreach ($arr as $item) {
                $key = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                if ($key === false) {
                    $key = serialize($item);
                }
                if (isset($seen[$key])) {
                    $duplicate = true;
                    break;
                }
                $seen[$key] = true;
            }
            if ($duplicate) {
                $this->err(ErrorCode::UNIQUE_ITEMS, $path, 'Array items are not unique (uniqueItems=True).');
            }
        }

        $itemsSchema = $schema['items'] ?? null;
        $additionalItems = schema_kw($schema, ['additionalItems', 'additional_items'], true);

        if (is_array($itemsSchema) && !is_assoc_array($itemsSchema)) {
            foreach ($itemsSchema as $idx => $itemSchema) {
                if ($idx < count($arr) && is_array($itemSchema)) {
                    $this->validateNode($arr[$idx], $itemSchema, "{$path}[{$idx}]");
                }
            }
            if (count($arr) > count($itemsSchema)) {
                if ($additionalItems === false) {
                    $this->err(ErrorCode::ADDITIONAL_ITEMS, $path, 'Additional array items not allowed.');
                } elseif (is_array($additionalItems)) {
                    for ($i = count($itemsSchema); $i < count($arr); $i++) {
                        $this->validateNode($arr[$i], $additionalItems, "{$path}[{$i}]");
                    }
                }
            }
        } elseif (is_array($itemsSchema)) {
            foreach ($arr as $idx => $item) {
                $this->validateNode($item, $itemsSchema, "{$path}[{$idx}]");
            }
        }

        $allowEmpty = schema_kw($schema, ['allow_empty', 'allowEmpty'], true);
        if ($allowEmpty === false && count($arr) === 0) {
            $this->err(ErrorCode::NOT_ALLOWED_EMPTY, $path, 'Array is empty but allow_empty is False.');
        }
    }

    private function validateString(string $value, array $schema, string $path): void
    {
        $minLen = schema_kw($schema, ['minLength', 'min_length'], null);
        if ($minLen !== null && mb_strlen($value, 'UTF-8') < (int) $minLen) {
            $this->err(ErrorCode::MIN_LENGTH, $path, "String length " . mb_strlen($value, 'UTF-8') . " < minLength {$minLen}.");
        }
        $maxLen = schema_kw($schema, ['maxLength', 'max_length'], null);
        if ($maxLen !== null && mb_strlen($value, 'UTF-8') > (int) $maxLen) {
            $this->err(ErrorCode::MAX_LENGTH, $path, "String length " . mb_strlen($value, 'UTF-8') . " > maxLength {$maxLen}.");
        }
        if (isset($schema['pattern'])) {
            $patternRaw = (string) $schema['pattern'];
            $pattern = wrap_schema_pattern($patternRaw);
            $match = @preg_match($pattern, $value);
            if ($match === false) {
                $this->err(ErrorCode::PATTERN_MISMATCH, $path, "Invalid regex in pattern: {$patternRaw}.");
            } elseif ($match === 0) {
                $this->err(ErrorCode::PATTERN_MISMATCH, $path, "String does not match pattern {$patternRaw}.");
            }
        }
        $allowEmpty = schema_kw($schema, ['allow_empty', 'allowEmpty'], true);
        if ($allowEmpty === false && trim($value) === '') {
            $this->err(ErrorCode::NOT_ALLOWED_EMPTY, $path, 'String is empty but allow_empty is False.');
        }
    }

    private function validateNumber(int|float $num, array $schema, string $path): void
    {
        $min = schema_kw($schema, ['minimum', 'min'], null);
        if ($min !== null && $num < (float) $min) {
            $this->err(ErrorCode::MINIMUM, $path, "Number {$num} < minimum {$min}.");
        }
        $max = schema_kw($schema, ['maximum', 'max'], null);
        if ($max !== null && $num > (float) $max) {
            $this->err(ErrorCode::MAXIMUM, $path, "Number {$num} > maximum {$max}.");
        }
        $exMin = schema_kw($schema, ['exclusiveMinimum', 'exclusive_minimum'], null);
        if ($exMin !== null && $num <= (float) $exMin) {
            $this->err(ErrorCode::EXCLUSIVE_MINIMUM, $path, "Number {$num} <= exclusiveMinimum {$exMin}.");
        }
        $exMax = schema_kw($schema, ['exclusiveMaximum', 'exclusive_maximum'], null);
        if ($exMax !== null && $num >= (float) $exMax) {
            $this->err(ErrorCode::EXCLUSIVE_MAXIMUM, $path, "Number {$num} >= exclusiveMaximum {$exMax}.");
        }
        $multipleOf = schema_kw($schema, ['multipleOf', 'multiple_of'], null);
        if ($multipleOf !== null) {
            $mul = (float) $multipleOf;
            if ($mul != 0) {
                $ratio = $num / $mul;
                if (abs($ratio - round($ratio)) > 1e-12) {
                    $this->err(ErrorCode::MULTIPLE_OF, $path, "Number {$num} is not a multipleOf {$mul}.");
                }
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $subs
     */
    private function validateAnyOf(mixed $value, array $subs, string $path): void
    {
        $ok = false;
        $failures = [];
        foreach ($subs as $sub) {
            $tmp = new SimpleJSONSchemaValidator($this->options);
            $subErrors = $tmp->validate($value, $sub, $path);
            if (count($subErrors) === 0) {
                $ok = true;
                break;
            }
            $failures[] = $subErrors;
        }
        if (!$ok) {
            $counts = array_map(fn($errs) => count($errs), $failures);
            $this->err(ErrorCode::ANY_OF_FAILED, $path, 'Value does not match anyOf ' . count($subs) . ' subschemas.', ['subschema_errors_counts' => $counts]);
        }
    }

    /**
     * @param list<array<string,mixed>> $subs
     */
    private function validateAllOf(mixed $value, array $subs, string $path): void
    {
        $allOk = true;
        $merged = [];
        foreach ($subs as $sub) {
            $tmp = new SimpleJSONSchemaValidator($this->options);
            $subErrors = $tmp->validate($value, $sub, $path);
            if (count($subErrors) !== 0) {
                $allOk = false;
                $merged = array_merge($merged, $subErrors);
                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }
        if (!$allOk) {
            $this->err(ErrorCode::ALL_OF_FAILED, $path, 'Value does not satisfy allOf subschemas.', ['errors_count' => count($merged)]);
        }
    }

    /**
     * @param list<array<string,mixed>> $subs
     */
    private function validateOneOf(mixed $value, array $subs, string $path): void
    {
        $matches = 0;
        foreach ($subs as $sub) {
            $tmp = new SimpleJSONSchemaValidator($this->options);
            $subErrors = $tmp->validate($value, $sub, $path);
            if (count($subErrors) === 0) {
                $matches++;
            }
        }
        if ($matches !== 1) {
            $this->err(ErrorCode::ONE_OF_FAILED, $path, "Value matches {$matches} subschemas; expected exactly 1.");
        }
    }

    private function err(ErrorCode $code, string $path, string $message, array $detail = []): void
    {
        $this->errors[] = new ValidationIssue($code, $path, $message, $detail);
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }
        return (string) $value;
    }

    private function repr(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: (string) $value;
    }
}

// --------------------------- Expectations Check ---------------------------

/**
 * ✅ FIXED: Now uses mb_str_split for proper UTF-8 handling
 * @return list<string>
 */
function split_path(string $path): array
{
    if ($path === '' || $path === '$') {
        return [];
    }

    $segments = [];
    $buf = '';
    $chars = mb_str_split($path, 1, 'UTF-8');
    $length = count($chars);

    for ($i = 0; $i < $length; $i++) {
        $ch = $chars[$i];

        if ($ch === '.') {
            if ($buf !== '') {
                $segments[] = $buf;
                $buf = '';
            }
            continue;
        }

        if ($ch === '[') {
            if ($buf !== '') {
                $segments[] = $buf;
                $buf = '';
            }
            $j = $i;
            while ($j < $length && $chars[$j] !== ']') {
                $j++;
            }
            if ($j < $length && $chars[$j] === ']') {
                $segments[] = implode('', array_slice($chars, $i, $j - $i + 1));
                $i = $j;
            } else {
                $segments[] = implode('', array_slice($chars, $i));
                break;
            }
            continue;
        }

        $buf .= $ch;
    }

    if ($buf !== '') {
        $segments[] = $buf;
    }

    if (!empty($segments) && $segments[0] === '$') {
        $segments = array_slice($segments, 1);
    }

    return array_values(array_filter($segments, fn($seg) => $seg !== ''));
}

/**
 * @return list<array{0:mixed,1:string}>
 */
function iterate_nodes(mixed $node, string $segment): array
{
    $results = [];
    if ($segment === '*') {
        if (is_array($node)) {
            if (is_assoc_array($node)) {
                foreach ($node as $key => $value) {
                    $results[] = [$value, ".{$key}"];
                }
            } else {
                foreach ($node as $idx => $value) {
                    $results[] = [$value, "[{$idx}]"];
                }
            }
        }
        return $results;
    }
    if ($segment === '[*]') {
        if (is_array($node) && !is_assoc_array($node)) {
            foreach ($node as $idx => $value) {
                $results[] = [$value, "[{$idx}]"];
            }
        }
        return $results;
    }
    if (str_starts_with($segment, '[') && str_ends_with($segment, ']')) {
        $idxStr = trim(mb_substr($segment, 1, -1, 'UTF-8'));
        if ($idxStr === '*') {
            if (is_array($node) && !is_assoc_array($node)) {
                foreach ($node as $idx => $value) {
                    $results[] = [$value, "[{$idx}]"];
                }
            }
            return $results;
        }
        if (ctype_digit(str_replace('-', '', $idxStr))) {
            $idx = (int) $idxStr;
            if (is_array($node) && !is_assoc_array($node) && array_key_exists($idx, $node)) {
                $results[] = [$node[$idx], "[{$idx}]"];
            }
        }
        return $results;
    }
    if (is_array($node) && is_assoc_array($node) && array_key_exists($segment, $node)) {
        $results[] = [$node[$segment], ".{$segment}"];
    }
    return $results;
}

function format_segment_for_path(string $segment): string
{
    if ($segment === '') {
        return '';
    }
    if (str_starts_with($segment, '[')) {
        return $segment;
    }
    if ($segment === '*') {
        return '.*';
    }
    return '.' . $segment;
}

/**
 * @return array{0:list<array{0:mixed,1:string}>,1:list<string>}
 */
function resolve_path_nodes(mixed $root, string $path): array
{
    $segments = split_path($path);
    if (empty($segments)) {
        return [[[$root, '']], []];
    }
    $frontier = [[ $root, '' ]];
    $missing = [];
    foreach ($segments as $seg) {
        $nextFrontier = [];
        foreach ($frontier as [$node, $suffix]) {
            $produced = false;
            foreach (iterate_nodes($node, $seg) as $childPair) {
                [$child, $childSuffix] = $childPair;
                $produced = true;
                $nextFrontier[] = [$child, $suffix . $childSuffix];
            }
            if (!$produced) {
                $missing[] = '$' . $suffix . format_segment_for_path($seg);
            }
        }
        $frontier = $nextFrontier;
        if (empty($frontier) && empty($missing)) {
            $missing[] = '$' . ltrim(format_segment_for_path($seg), '.');
            break;
        }
    }
    if ($frontier === []) {
        return [[], $missing];
    }
    return [$frontier, $missing];
}

/**
 * @param list<array<string,mixed>> $expectations
 * @return list<ValidationIssue>
 */
function validate_expectations(mixed $data, array $expectations, ?ValidateOptions $options = null): array
{
    $errors = [];
    $stopOnFirst = $options ? (bool) ($options->stopOnFirstError || $options->strict) : false;
    $record = function (ValidationIssue $issue) use (&$errors, $stopOnFirst): bool {
        $errors[] = $issue;
        return $stopOnFirst;
    };

    foreach ($expectations as $exp) {
        $path = $exp['path'] ?? '$';
        $required = $exp['required'] ?? true;
        [$matches, $missingPaths] = resolve_path_nodes($data, $path);
        if (empty($matches)) {
            if ($required) {
                if (!empty($missingPaths)) {
                    foreach ($missingPaths as $missingPath) {
                        if ($record(new ValidationIssue(
                            ErrorCode::PATH_NOT_FOUND,
                            $missingPath,
                            "Path '{$missingPath}' not found (required=True)."
                        ))) {
                            return $errors;
                        }
                    }
                } else {
                    $displayPath = str_starts_with($path, '$') ? $path : '$.' . $path;
                    if ($record(new ValidationIssue(
                        ErrorCode::PATH_NOT_FOUND,
                        $displayPath,
                        "Path '{$path}' not found (required=True)."
                    ))) {
                        return $errors;
                    }
                }
            }
            continue;
        } elseif ($required && !empty($missingPaths)) {
            foreach ($missingPaths as $missingPath) {
                if ($record(new ValidationIssue(
                    ErrorCode::PATH_NOT_FOUND,
                    $missingPath,
                    "Path '{$missingPath}' not found (required=True)."
                ))) {
                    return $errors;
                }
            }
        }

        $typeSpec = $exp['type'] ?? null;
        $allowEmpty = $exp['allow_empty'] ?? null;
        $equals = $exp['equals'] ?? null;
        $enum = $exp['in'] ?? null;
        $pattern = $exp['pattern'] ?? null;
        $minLength = $exp['min_length'] ?? null;
        $maxLength = $exp['max_length'] ?? null;
        $minItems = $exp['min_items'] ?? null;
        $maxItems = $exp['max_items'] ?? null;
        $minimum = $exp['minimum'] ?? null;
        $maximum = $exp['maximum'] ?? null;

        $regexPattern = $pattern !== null ? wrap_schema_pattern((string) $pattern) : null;

        foreach ($matches as [$node, $suffix]) {
            $fullPath = '$' . ($suffix ?: '');

            if ($typeSpec !== null) {
                if (is_array($typeSpec)) {
                    if (!array_reduce($typeSpec, fn($carry, $t) => $carry || types_match($node, (string) $t), false)) {
                        if ($record(new ValidationIssue(
                            ErrorCode::TYPE_MISMATCH,
                            $fullPath,
                            "Expected one of types " . json_encode($typeSpec) . ', got ' . gettype($node) . '.'
                        ))) {
                            return $errors;
                        }
                        continue;
                    }
                } else {
                    if (!types_match($node, (string) $typeSpec)) {
                        if ($record(new ValidationIssue(
                            ErrorCode::TYPE_MISMATCH,
                            $fullPath,
                            "Expected type {$typeSpec}, got " . gettype($node) . '.'
                        ))) {
                            return $errors;
                        }
                        continue;
                    }
                }
            }

            if ($allowEmpty === false && is_empty_value($node)) {
                if ($record(new ValidationIssue(
                    ErrorCode::NOT_ALLOWED_EMPTY,
                    $fullPath,
                    'Value is empty but allow_empty is False.'
                ))) {
                    return $errors;
                }
                continue;
            }

            if ($equals !== null && $node !== $equals) {
                if ($record(new ValidationIssue(
                    ErrorCode::EXPECTATION_FAILED,
                    $fullPath,
                    "Expected value == " . json_encode($equals) . ', got ' . json_encode($node) . '.'
                ))) {
                    return $errors;
                }
                continue;
            }

            if ($enum !== null && !in_array($node, (array) $enum, true)) {
                if ($record(new ValidationIssue(
                    ErrorCode::ENUM_MISMATCH,
                    $fullPath,
                    'Value ' . json_encode($node) . ' not in allowed set.'
                ))) {
                    return $errors;
                }
                continue;
            }

            if ($pattern !== null && is_string($node)) {
                $match = @preg_match($regexPattern, $node);
                if ($match === false || $match === 0) {
                    if ($record(new ValidationIssue(
                        ErrorCode::PATTERN_MISMATCH,
                        $fullPath,
                        "String does not match pattern {$pattern}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
            }

            if (is_string($node)) {
                if ($minLength !== null && mb_strlen($node, 'UTF-8') < (int) $minLength) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MIN_LENGTH,
                        $fullPath,
                        "String length " . mb_strlen($node, 'UTF-8') . " < min_length {$minLength}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
                if ($maxLength !== null && mb_strlen($node, 'UTF-8') > (int) $maxLength) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MAX_LENGTH,
                        $fullPath,
                        "String length " . mb_strlen($node, 'UTF-8') . " > max_length {$maxLength}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
            }

            if (is_array($node) && !is_assoc_array($node)) {
                if ($minItems !== null && count($node) < (int) $minItems) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MIN_ITEMS,
                        $fullPath,
                        "Array has " . count($node) . " items < min_items {$minItems}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
                if ($maxItems !== null && count($node) > (int) $maxItems) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MAX_ITEMS,
                        $fullPath,
                        "Array has " . count($node) . " items > max_items {$maxItems}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
            }

            if (is_number_value($node)) {
                if ($minimum !== null && $node < (float) $minimum) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MINIMUM,
                        $fullPath,
                        "Number {$node} < minimum {$minimum}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
                if ($maximum !== null && $node > (float) $maximum) {
                    if ($record(new ValidationIssue(
                        ErrorCode::MAXIMUM,
                        $fullPath,
                        "Number {$node} > maximum {$maximum}."
                    ))) {
                        return $errors;
                    }
                    continue;
                }
            }
        }
    }

    return $errors;
}

// ------------------------------ Main API ------------------------------

/**
 * @param string|array<mixed>|mixed $inputData
 * @param array<string,mixed>|null $schema
 * @param list<array<string,mixed>>|null $expectations
 */
function validate_ai_json(mixed $inputData, ?array $schema = null, ?array $expectations = null, ?ValidateOptions $options = null): ValidationResult
{
    $options ??= new ValidateOptions();
    $errors = [];
    $warnings = [];
    $info = [];
    $likelyTruncated = false;
    $parsed = null;
    $rawStr = null;

    if (is_array($inputData)) {
        $parsed = $inputData;
        $info['source'] = 'object';
        $info['parse_backend'] = 'php_array';
    } elseif (is_string($inputData)) {
        $rawStr = $inputData;
    } else {
        $errors[] = new ValidationIssue(
            ErrorCode::PARSE_ERROR,
            '$',
            'Unsupported input_data type: ' . gettype($inputData) . '. Provide string/array.'
        );
        return new ValidationResult(false, false, $errors, $warnings, null, $info);
    }

    if ($rawStr !== null) {
        [$payload, $extractionInfo] = extract_json_payload($rawStr, $options);
        $info = array_merge($info, $extractionInfo ?? []);
        if ($payload === null) {
            [$likelyTruncated, $reasons] = detect_truncation($rawStr);
            $errors[] = new ValidationIssue(
                ErrorCode::PARSE_ERROR,
                '$',
                'No JSON payload found in input.',
                ['truncation_reasons' => $reasons]
            );
            return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
        }
        try {
            [$parsed, $backend, $usedCq] = parse_with_options($payload, $options);
            $info['parse_backend'] = $backend;
            $info['curly_quotes_normalization_used'] = $usedCq;
        } catch (Throwable $ex) {
            [$likelyTruncated, $reasons] = detect_truncation($payload);
            if ($options->enableSafeRepairs && !$likelyTruncated) {
                [$repairedPayload, $repairInfo] = attempt_safe_json_repair($payload, $options);
                if ($repairedPayload !== null) {
                    try {
                        [$parsed, $backend, $usedCq] = parse_with_options($repairedPayload, $options);
                        $info['parse_backend'] = $backend;
                        $info['curly_quotes_normalization_used'] = $usedCq;
                        $info['repair'] = $repairInfo;
                        $warnings[] = new ValidationIssue(
                            ErrorCode::REPAIRED,
                            '$',
                            'Input JSON was repaired by conservative heuristics.',
                            ['applied' => $repairInfo['applied'] ?? [], 'counts' => $repairInfo['counts'] ?? []]
                        );
                    } catch (Throwable $repairEx) {
                        $errors[] = new ValidationIssue(
                            ErrorCode::PARSE_ERROR,
                            '$',
                            'JSON parse error: ' . $repairEx->getMessage(),
                            ['repair_attempt' => $repairInfo]
                        );
                        return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
                    }
                } else {
                    $errors[] = new ValidationIssue(
                        ErrorCode::PARSE_ERROR,
                        '$',
                        'JSON parse error: ' . $ex->getMessage(),
                        ['repair_attempt' => $repairInfo]
                    );
                    return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
                }
            } else {
                if ($likelyTruncated) {
                    $info['repair'] = $info['repair'] ?? [];
                    $info['repair']['skipped'] = 'truncation_detected';
                }
                $errors[] = new ValidationIssue(
                    ErrorCode::PARSE_ERROR,
                    '$',
                    'JSON parse error: ' . $ex->getMessage(),
                    ['truncation_reasons' => $reasons, 'repair_enabled' => $options->enableSafeRepairs]
                );
                return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
            }
        }
    }

    if ($parsed === null) {
        return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
    }

    if (!$options->allowBareTopLevelScalars && !is_array($parsed)) {
        $errors[] = new ValidationIssue(
            ErrorCode::PARSE_ERROR,
            '$',
            'Top-level value is not object/array (allow_bare_top_level_scalars=False).'
        );
        return new ValidationResult(false, $likelyTruncated, $errors, $warnings, null, $info);
    }

    if ($options->normalizeCurlyQuotes !== 'never') {
        [$parsed, $normalized] = normalize_curly_quotes_in_data($parsed);
        if ($normalized) {
            $info['curly_quotes_normalization_used'] = true;
        }
    }

    if (is_array($parsed) && empty($errors)) {
        if ($schema !== null) {
            $validator = new SimpleJSONSchemaValidator($options);
            $schemaErrors = $validator->validate($parsed, $schema, '$');
            $errors = array_merge($errors, $schemaErrors);
        }
        if (empty($errors) && $expectations !== null) {
            $expErrors = validate_expectations($parsed, $expectations, $options);
            $errors = array_merge($errors, $expErrors);
        }
    }

    if ($rawStr !== null) {
        [$truncatedRaw, $reasonsRaw] = detect_truncation($rawStr);
        if ($truncatedRaw) {
            $likelyTruncated = $likelyTruncated || $truncatedRaw;
            if (empty($errors)) {
                $warnings[] = new ValidationIssue(
                    ErrorCode::TRUNCATED,
                    '$',
                    'Original text looks truncated/suspicious, but extracted/repaired JSON parsed.',
                    ['truncation_reasons' => $reasonsRaw]
                );
            }
        }
    }

    $jsonValid = empty($errors);
    return new ValidationResult(
        $jsonValid,
        $likelyTruncated,
        $errors,
        $warnings,
        $jsonValid ? $parsed : null,
        $info
    );
}

// ------------------------------ CLI (optional) ------------------------------

function load_json_file(string $path): mixed
{
    global $json_engine;
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Unable to read file: {$path}");
    }
    return $json_engine->decode($content, true);
}

function load_text_or_file(string $textOrPath): string
{
    if ($textOrPath === '-') {
        return stream_get_contents(STDIN) ?: '';
    }
    if (is_file($textOrPath)) {
        $content = file_get_contents($textOrPath);
        if ($content === false) {
            throw new RuntimeException("Unable to read file: {$textOrPath}");
        }
        return $content;
    }
    return $textOrPath;
}

/**
 * @return array<string,mixed>
 */
function parse_cli_args(array $argv): array
{
    $result = [];
    $count = count($argv);
    for ($i = 0; $i < $count; $i++) {
        $arg = $argv[$i];
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        $value = true;
        if (str_contains($arg, '=')) {
            [$key, $val] = explode('=', $arg, 2);
            $arg = $key;
            $value = $val;
        } elseif ($i + 1 < $count && !str_starts_with($argv[$i + 1], '--')) {
            $value = $argv[$i + 1];
            $i++;
        }
        $result[$arg] = $value;
    }
    return $result;
}

function ai_json_cleanroom_cli(): void
{
    global $argv, $json_engine;
    $args = parse_cli_args(array_slice($argv, 1));
    if (!isset($args['input'])) {
        fwrite(STDERR, "Usage: php ai_json_cleanroom.php --input <path_or_text> [--schema file] [--expectations file]\n");
        exit(1);
    }

    $raw = load_text_or_file((string) $args['input']);
    $schema = isset($args['schema']) ? load_json_file((string) $args['schema']) : null;
    $expectations = isset($args['expectations']) ? load_json_file((string) $args['expectations']) : null;

    $indent = isset($args['indent']) ? (int) $args['indent'] : 2;
    $ensureAscii = isset($args['ensure-ascii']);

    $opts = new ValidateOptions([
        'strict' => isset($args['strict']),
        'extract_json' => !isset($args['no-extract']),
        'tolerate_trailing_commas' => !isset($args['no-trailing-commas']),
        'allow_bare_top_level_scalars' => isset($args['allow-scalars']),
        'enable_safe_repairs' => !isset($args['no-repair']),
        'allow_json5_like' => !isset($args['no-json5-like']),
        'replace_constants' => !isset($args['no-constants']),
        'replace_nans_infinities' => !isset($args['no-constants']),
        'max_total_repairs' => isset($args['max-repairs']) ? (int) $args['max-repairs'] : 200,
        'max_repairs_percent' => isset($args['repairs-percent']) ? (float) $args['repairs-percent'] : 0.02,
        'normalize_curly_quotes' => isset($args['normalize-curly-quotes']) ? (string) $args['normalize-curly-quotes'] : 'always',
        'fix_single_quotes' => !isset($args['no-fix-single-quotes']),
        'quote_unquoted_keys' => !isset($args['no-quote-unquoted-keys']),
        'strip_js_comments' => !isset($args['no-strip-comments']),
    ]);

    $result = validate_ai_json($raw, $schema, $expectations, $opts);
    [$out, $dumpBackend] = $json_engine->encodeWithBackend(
        $result->toArray(),
        sortKeys: false,
        ensureAscii: $ensureAscii,
        indent: $indent
    );
    echo $out, PHP_EOL;
    fwrite(STDERR, "[ai_json_cleanroom] dump_backend={$dumpBackend} | PHP " . PHP_VERSION . " | UTF-8 fixed version\n");
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ai_json_cleanroom_cli();
}
