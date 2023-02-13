<?php

class DbException extends Exception
{
    protected ?string $query = null;

    public function __construct(string $message = '', int $code = 0, string $query = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->query = $query;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }
}
