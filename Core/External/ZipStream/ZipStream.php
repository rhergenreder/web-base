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
  class ZipStream {
    private $writer = null;
    private array $files = [];
    private bool $zip64;

    public function __construct($writer, $zip64 = false) {
      $this->writer = $writer;
      $this->zip64 = $zip64;
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
      $this->writer->write($file->readLocalFileHeader($this->zip64));

      if ($file instanceof FileStream) {
        $file->getStream()->setOutput(function ($chunk) use ($file) {
          $this->writer->write($file->processChunk($chunk));
        });
        $file->getStream()->start();
        $this->writer->write($file->finalize());
      } else {
        while (($buffer = $file->readFileData()) !== null) {
          $this->writer->write($buffer);
        }
      }

      $this->writer->write($file->readDataDescriptor($this->zip64));
      $this->files[] = $file;
      $file->closeHandle();
      return true;
    }

    // Write end of central directory record
    public function close() {
      $size = 0;
      $offset = $this->writer->offset();
      foreach ($this->files as $file) {
        $size += $this->writer->write($file->readCentralDirectoryHeader($this->zip64));
      }

      $data = "";
      if ($this->zip64) {
        // Size = SizeOfFixedFields + SizeOfVariableData - 12.
        $centralDirectorySize = 2*2 + 2*4 + 4*8;

        $data .= "\x50\x4b\x06\x06";
        $data .= pack("P", $centralDirectorySize);
        $data .= "\x2d\x00"; // version 2.0 and MS-DOS compatible
        $data .= "\x2d\x00"; // version 2.0 and MS-DOS compatible
        $data .= "\x00\x00\x00\x00"; //number of disks
        $data .= "\x00\x00\x00\x00"; //number of the disk with the start of the central directory
        $data .= pack("P", count($this->files)); //total number of entries in the central directory on this disk
        $data .= pack("P", count($this->files)); //total number of entries in the central directory
        $data .= pack("P", $size); // size of the central directory
        $data .= pack("P", $offset); //offset of start of central directory with respect to the starting disk number

        // end of central directory locator
        $data .= "\x50\x4b\x06\x07";
        $data .= "\x00\x00\x00\x00";
        $data .= pack("P", $this->writer->offset());
        $data .= pack('V', 1); //number of disks
      }

      $data .= "\x50\x4b\x05\x06";
      $data .= "\x00\x00"; //number of disks
      $data .= "\x00\x00"; //number of the disk with the start of the central directory
      $data .= $this->zip64 ? "\xFF\xFF" : pack("v", count($this->files)); //total number of entries in the central directory on this disk
      $data .= $this->zip64 ? "\xFF\xFF" : pack("v", count($this->files)); //total number of entries in the central directory
      $data .= $this->zip64 ? "\xFF\xFF\xFF\xFF" : pack("V", $size); // size of the central directory
      $data .= $this->zip64 ? "\xFF\xFF\xFF\xFF" : pack("V", $offset); //offset of start of central directory with respect to the starting disk number
      $data .= "\x00\x00"; //comment length

      $this->writer->write($data);
    }
  }
}