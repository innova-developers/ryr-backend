<?php

namespace App\Contexts\Users\Application\DTO;

class UpdateUserDTO
{
    public int $id;
    public string $name;
    public string $email;
    public ?string $password;
    public string $role;
    public int $branch_id;

    public function __construct(int $id, string $name, string $email, string $password = null, string $role, int $branch_id = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->branch_id = $branch_id;
    }
}
