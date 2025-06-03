<?php

namespace App\Contexts\Users\Application\DTO;

class CreateUserDTO
{
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public int $branch_id;

    public function __construct(string $name, string $email, string $password, string $role, int $branch_id = 0)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->branch_id = $branch_id;
    }
}
