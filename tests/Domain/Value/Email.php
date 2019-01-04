<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

class Email extends Value
{
    protected $email;

    public function __construct(string $email)
    {
        parent::__construct();
        $this->email = $email;
    }

    public function change(string $email)
    {
        return $this->with(['email' => $email]);
    }

    public function get()
    {
        return $this->email;
    }
}
