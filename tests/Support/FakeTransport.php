<?php

declare(strict_types=1);

namespace Zeny\Tests\Support;

use Zeny\Domain\ResponseMeta;
use Zeny\Domain\ZenyObject;
use Zeny\Infrastructure\Http\HttpTransport;
use Zeny\Infrastructure\Http\RequestSpec;

/** Records every RequestSpec and answers each with `{"id":"obj_1"}`. */
final class FakeTransport implements HttpTransport
{
    /** @var list<RequestSpec> */
    public array $specs = [];

    public function request(RequestSpec $spec): ZenyObject
    {
        $this->specs[] = $spec;

        return new ZenyObject(['id' => 'obj_1'], new ResponseMeta(200, null, false, $spec->idempotencyKey, []));
    }

    public function last(): RequestSpec
    {
        return $this->specs[array_key_last($this->specs) ?? -1] ?? throw new \OutOfBoundsException('no request sent');
    }
}
