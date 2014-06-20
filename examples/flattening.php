<?php

namespace Papper\Examples\Flattening;

use Papper\Papper;

require_once __DIR__ . '/../vendor/autoload.php';

class User
{
	public $name;
	/**
	 * @var Company
	 */
	public $company;
	private $role;

	public function __construct($name, Company $company, Role $role)
	{
		$this->name = $name;
		$this->company = $company;
		$this->role = $role;
	}

	/**
	 * @return Role
	 */
	public function getRole()
	{
		return $this->role;
	}
}

class Company
{
	public $name;

	public function __construct($name)
	{
		$this->name = $name;
	}
}

class Role
{
	public $name;

	public function __construct($name)
	{
		$this->name = $name;
	}
}

class UserDTO
{
	public $name;
	public $companyName;
	public $roleName;
}

/** @var UserDTO $userDTO */
$userDTO = Papper::map(new User('John Smith', new Company('Acme Corporation'), new Role('Developer')))
	->toType('Papper\Examples\Flattening\UserDTO');

echo "Name: ", $userDTO->name, PHP_EOL;
echo "Company name: ", $userDTO->companyName, PHP_EOL;
echo "Role name: ", $userDTO->roleName, PHP_EOL;
