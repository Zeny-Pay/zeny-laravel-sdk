<?php

declare(strict_types=1);

namespace Zeny\Scripts;

/**
 * Turns one OpenAPI schema into a PHPStan type. A `$ref` becomes the alias of
 * the schema it points to; an open string enum ("these values or any string")
 * stays `string`, because the API may add values; a closed enum becomes a union
 * of literals.
 */
final class SchemaTyper
{
    private const SCALARS = ['string' => 'string', 'integer' => 'int', 'number' => 'float|int', 'boolean' => 'bool', 'null' => 'null'];

    /** The alias a component schema gets: `type.v1.Money` → `S_type_v1_Money`. */
    public static function aliasOf(string $schemaName): string
    {
        return 'S_'.preg_replace('/[^A-Za-z0-9]/', '_', $schemaName);
    }

    /** @param array<string, mixed> $schema */
    public function type(array $schema, int $depth = 0): string
    {
        return match (true) {
            isset($schema['$ref']) => self::aliasOf(substr(Json::string($schema['$ref']), strlen('#/components/schemas/'))),
            isset($schema['anyOf']) || isset($schema['oneOf']) => $this->union(Json::list($schema['anyOf'] ?? $schema['oneOf']), $depth),
            isset($schema['allOf']) => $this->union(Json::list($schema['allOf']), $depth),
            array_key_exists('const', $schema) => self::literal($schema['const']),
            isset($schema['enum']) => self::collapse(array_map(self::literal(...), Json::list($schema['enum']))),
            is_array($schema['type'] ?? null) => $this->union(array_map(fn (mixed $type): array => ['type' => $type] + $schema, Json::list($schema['type'])), $depth),
            default => $this->single(Json::string($schema['type'] ?? ''), $schema, $depth),
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     */
    public function shape(array $properties, array $required, int $depth): string
    {
        if ($properties === []) {
            return 'array<string, mixed>';
        }
        $pad = str_repeat('    ', $depth + 1);
        $lines = [];
        foreach ($properties as $name => $property) {
            $optional = in_array($name, $required, true) ? '' : '?';
            $lines[] = $pad.self::key($name).$optional.': '.$this->type(Json::object($property), $depth + 1).',';
        }

        return "array{\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).'}';
    }

    /** @param array<string, mixed> $schema */
    private function single(string $type, array $schema, int $depth): string
    {
        return match ($type) {
            'object' => $this->object($schema, $depth),
            'array' => 'list<'.$this->type(Json::object($schema['items'] ?? []), $depth).'>',
            '' => 'mixed',
            default => self::SCALARS[$type] ?? throw new \RuntimeException("unknown schema type {$type}"),
        };
    }

    /** @param array<string, mixed> $schema */
    private function object(array $schema, int $depth): string
    {
        $additional = $schema['additionalProperties'] ?? null;
        if (! isset($schema['properties']) && is_array($additional)) {
            return 'array<string, '.$this->type(Json::object($additional), $depth).'>';
        }
        $required = array_map(Json::string(...), Json::list($schema['required'] ?? []));

        return $this->shape(Json::object($schema['properties'] ?? []), $required, $depth);
    }

    /** @param list<mixed> $variants */
    private function union(array $variants, int $depth): string
    {
        return self::collapse(array_map(fn (mixed $variant): string => $this->type(Json::object($variant), $depth), $variants));
    }

    /**
     * Joins union members once each. `string` absorbs string literals, so an
     * open enum stays `string`.
     *
     * @param  list<string>  $members
     */
    private static function collapse(array $members): string
    {
        $parts = array_values(array_unique(array_merge(...array_map(fn (string $member): array => explode('|', $member), $members))));
        if (in_array('string', $parts, true)) {
            $parts = array_values(array_filter($parts, fn (string $part): bool => ! str_starts_with($part, "'")));
        }

        return implode('|', $parts);
    }

    private static function literal(mixed $value): string
    {
        return is_string($value) ? "'".addcslashes($value, "'\\")."'" : (string) json_encode($value);
    }

    private static function key(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : "'".addcslashes($name, "'\\")."'";
    }
}
