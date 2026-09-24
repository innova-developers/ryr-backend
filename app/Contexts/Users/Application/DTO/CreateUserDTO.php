<?php

namespace App\Contexts\Users\Application\DTO;

class CreateUserDTO
{
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public ?int $branch_id;
    public ?float $base_salary;
    public ?float $income_percentage;
    public ?float $commission_percentage;
    public string $contract_type;
    public ?float $payment_per_pickup;

    public function __construct(string $name, string $email, string $password, string $role, ?int $branch_id = null, ?float $base_salary = null, ?float $income_percentage = null, ?float $commission_percentage = null, string $contract_type = 'fixed_salary', ?float $payment_per_pickup = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->branch_id = $branch_id;
        $this->base_salary = $base_salary;
        $this->income_percentage = $income_percentage;
        $this->commission_percentage = $commission_percentage;
        $this->contract_type = $contract_type;
        $this->payment_per_pickup = $payment_per_pickup;
    }
}
