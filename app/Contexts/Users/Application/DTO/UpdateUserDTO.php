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
    public ?float $income_percentage;
    public ?float $commission_percentage;
    public string $contract_type;

    public function __construct(int $id, string $name, string $email, string $password = null, string $role, int $branch_id = 0, ?float $base_salary = null, ?float $income_percentage = null, ?float $commission_percentage = null, string $contract_type = 'fixed_salary')
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->branch_id = $branch_id;
        $this->base_salary = $base_salary;
        $this->income_percentage = $income_percentage;
        $this->commission_percentage = $commission_percentage;
        $this->contract_type = $contract_type;
    }
}
