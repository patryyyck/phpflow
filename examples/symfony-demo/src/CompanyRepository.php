<?php

namespace App\Repository;

interface CompanyRepository
{
    public function save(object $company): void;
}
