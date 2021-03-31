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

namespace External\ZipStream {
  class ZipStream {
    private $writer = null;
    private $files = [];

    public function __construct($writer) {
      $this->writer = $writer;
    }

    public function saveFile($file) {
      $isSymlink = false; //currently not used
      foreach ($this->files as $f) {
        if ($f->name() == $file->name()) {
          return false;
        }
        if ($f->sha256() == $file->sha256()) {
          $isSymlink = true;
        }
      }
      $file->setOffset($this->writer->offset());
      $this->writer->write($file->readLocalFileHeader());
      while (($buffer = $file->readFileData()) !== null) {
        $this->writer->write($buffer);
      }
      $this->writer->write($file->readDataDescriptor());
      $this->files[] = $file;
      $file->closeHandle();
      return true;
    }

    public function close() {
      $size = 0;
      $offset = $this->writer->offset();
      foreach ($this->files as $file) {
        $size += $this->writer->write($file->readCentralDirectoryHeader());
      }

      $data = "";
      $data .= "\x50\x4b\x05\x06";
      $data .= "\x00\x00"; //number of disks
      $data .= "\x00\x00"; //number of the disk with the start of the central directory
      $data .= pack("v", count($this->files)); //total number of entries in the central directory on this disk
      $data .= pack("v", count($this->files)); //total number of entries in the central directory
      $data .= pack("V", $size); //size of the central directory
      $data .= pack("V", $offset); //offset of start of central directory with respect to the starting disk number
      $data .= "\x0\x0"; //comment length
      $this->writer->write($data);
    }
  }
}