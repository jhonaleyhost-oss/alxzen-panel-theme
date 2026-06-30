<?php

namespace Pterodactyl\Extensions\Laravel\Sanctum;

use Pterodactyl\Models\ApiKey;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @property ApiKey $accessToken
 */
class NewAccessToken implements Arrayable, Jsonable
{
    public ApiKey $accessToken;
    public string $plainTextToken;

    /**
     * NewAccessToken constructor.
     */
    public function __construct(ApiKey $accessToken, string $plainTextToken)
    {
        $this->accessToken = $accessToken;
        $this->plainTextToken = $plainTextToken;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'plainTextToken' => $this->plainTextToken,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
