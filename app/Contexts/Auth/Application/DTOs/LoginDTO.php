<?php

namespace App\Contexts\Auth\Application\DTOs;

class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $scope
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            scope: $data['scope']
        );
    }

    /**
     * @return array<string, string>
     */
    public function getCredentials(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }

    public function isWebScope(): bool
    {
        return $this->scope === 'web';
    }

    public function isAppScope(): bool
    {
        return $this->scope === 'app';
    }
}
