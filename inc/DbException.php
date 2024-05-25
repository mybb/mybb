<?php

class DbException extends Exception
{
    protected ?string $query = null;
    protected $code = null;

    public function __construct(string $message = '', mixed $code = '', string $query = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->query = $query;
        $this->code = $code;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }
}
