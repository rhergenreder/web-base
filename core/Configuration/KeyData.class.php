<?php

namespace Configuration;

class KeyData {

    protected string $key;

    public function __construct(string $key) {
        $this->key = $key;
    }

    public function getKey() {
        return $this->key;
    }

}