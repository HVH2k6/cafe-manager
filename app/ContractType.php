<?php

namespace App;

/**
 * Mirrors the `employees.contract_type` column in cafe_schema_final.sql.
 */
enum ContractType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Toàn thời gian',
            self::PartTime => 'Bán thời gian',
            self::Contract => 'Hợp đồng',
            self::Intern => 'Thực tập',
        };
    }
}
