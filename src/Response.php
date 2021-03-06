<?php

namespace MakeWeb\Updater;

class Response
{
    public $code;

    public $message;

    public $body;

    public $version;

    public function withCode($code)
    {
        return $this->setFluently('code', $code);
    }

    public function withMessage($message)
    {
        return $this->setFluently('message', $message);
    }

    public function withBody($body)
    {
        return $this->setFluently('body', $body);
    }

    public function withVersion($version)
    {
        return $this->setFluently('version', $version);
    }

    public function setFluently($propertyName, $value)
    {
        $this->$propertyName = $value;

        return $this;
    }

    public function isError()
    {
        return (int) $this->code >= 400;
    }
}
