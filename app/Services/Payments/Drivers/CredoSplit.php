<?php

namespace App\Services\Payments\Drivers;

class CredoSplit extends CredoInstallment
{
    public function configKey(): string
    {
        return 'credo.split';
    }

    public function driverName(): string
    {
        return 'credo-split';
    }

    public function formRoute(): string
    {
        return 'payment.credo_split.form';
    }
}
