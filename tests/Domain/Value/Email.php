<?php
namespace Atlas\Transit\Domain\Value;

class Email extends Value
{
    protected $email;

    public function __construct($email)
    {
        parent::__construct();
        $this->email = $email;
    }

    public function change($email)
    {
        return $this->with(['email' => $email]);
    }

    public function get()
    {
        return $this->email;
    }
}
