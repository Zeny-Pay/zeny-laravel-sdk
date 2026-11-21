<?php

declare(strict_types=1);

namespace Zeny\Scripts;

/**
 * Finds the property names whose value is a JSON object with no declared
 * properties: a map (`additionalProperties`, such as `metadata`) or a
 * free-form object (such as `card`). PHP decodes an empty one as `[]`, so the
 * SDK needs these names to send it back as `{}`.
 */
final class ObjectFields
{
    /** @param array<string, mixed> $schemas the contract's component schemas */
    public function __construct(private readonly array $schemas) {}

    /** @return list<string> sorted; throws when a name is an object in one schema and not in another */
    public function names(): array
    {
        $kinds = [];
        foreach ($this->schemas as $schema) {
            foreach (Json::object(Json::object($schema)['properties'] ?? null) as $name => $property) {
                $kinds[$name][] = $this->isOpenObject(Json::object($property));
            }
        }
        $names = [];
        foreach ($kinds as $name => $isObject) {
            if (in_array(true, $isObject, true) && in_array(false, $isObject, true)) {
                throw new \RuntimeException("property {$name} is a map in one schema and not in another");
            }
            if (in_array(true, $isObject, true)) {
                $names[] = (string) $name;
            }
        }
        sort($names);

        return $names;
    }

    /** @param array<string, mixed> $schema */
    private function isOpenObject(array $schema): bool
    {
        $arms = array_filter(Json::list($schema['anyOf'] ?? [$schema]), fn (mixed $arm): bool => (Json::object($arm)['type'] ?? null) !== 'null');
        foreach ($arms as $arm) {
            $resolved = $this->resolve(Json::object($arm));
            if (($resolved['type'] ?? null) === 'object' && Json::object($resolved['properties'] ?? null) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function resolve(array $schema): array
    {
        $ref = Json::string($schema['$ref'] ?? '');

        return $ref === '' ? $schema : Json::object($this->schemas[substr($ref, strlen('#/components/schemas/'))] ?? null);
    }
}
