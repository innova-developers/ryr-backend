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
    public ?float $base_salary;
    public ?float $commission_percentage;

    public function __construct(int $id, string $name, string $email, string $password = null, string $role, int $branch_id = 0, ?float $base_salary = null, ?float $commission_percentage = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->branch_id = $branch_id;
        $this->base_salary = $base_salary;
        $this->commission_percentage = $commission_percentage;
    }
}
