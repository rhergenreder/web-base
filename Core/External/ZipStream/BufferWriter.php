<?php

/**
 * Copyright (c) Borago 2019
 *
 * This software is provided 'as-is', without any express or implied
 * warranty. In no event will the authors be held liable for any damages
 * arising from the use of this software.
 *
 * Permission is granted to anyone to use this software for any purpose,
 * including commercial applications, and to alter it and redistribute it
 * freely, subject to the following restrictions:
 *
 * 1. The origin of this software must not be misrepresented; you must not
 *    claim that you wrote the original software. If you use this software
 *    in a product, an acknowledgment in the product documentation would be
 *    appreciated but is not required.
 * 2. Altered source versions must be plainly marked as such, and must not be
 *    misrepresented as being the original software.
 * 3. This notice may not be removed or altered from any source distribution.
 **/

namespace Core\External\ZipStream {
  class BufferWriter implements Writer {
    private $stream = '';
    private $offset = 0;
    private $callback = null;

    public function __construct() {

    }

    public function registerCallback($callback) {
      $this->callback = $callback;
    }

    public function write($data) {
      $this->offset += strlen($data);
      $this->stream .= $data;
      if ($this->callback !== null) {
        call_user_func($this->callback, $this);
      }
      return strlen($data);
    }

    public function read() {
      $data = $this->stream;
      $this->stream = '';
      return $data;
    }

    public function offset() {
      return $this->offset;
    }

    public function close() {
      if ($this->callback !== null) {
        call_user_func($this->callback, $this);
      }
    }
  }
}