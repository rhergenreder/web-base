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
  class File {
    private $name;
    private $content = '';
    private $fileHandle = false;
    private $lastModificationTimestamp;
    protected $fileSize = 0;
    protected $compressedSize = 0;
    private $offset = 0;
    private $bitField = 0;
    protected $useCompression = true;
    private $deflateState = null;

    //check for duplications //currently not used
    protected $crc32 = null;
    protected $sha256;

    public const BIT_NO_SIZE_IN_HEADER = 0b0000000000001000;
    public const BIT_UTF8_NAMES = 0b0000100000000000;

    public function __construct($name) {
      $this->name = $name;
      $this->lastModificationTimestamp = time();
      $this->crc32 = hash('crc32b', '', true);
      $this->compressedSize = 0;
      $this->fileSize = 0;

      $this->bitField = 0;
      $this->bitField |= self::BIT_NO_SIZE_IN_HEADER;
      $this->bitField |= self::BIT_UTF8_NAMES;

      $this->deflateState = deflate_init(ZLIB_ENCODING_RAW);
    }

    public function disableCompression() {
      $this->useCompression = false;
    }

    public function setContent($content) {
      $this->crc32 = hash('crc32b', $content, true);
      $this->sha256 = hash('sha256', $content);
      $this->content = $content;
      $this->fileSize = strlen($content);
      $this->fileHandle = false;
    }

    public function loadFromFile($filename) {
      $this->crc32 = hash_file('crc32b', $filename, true);
      $this->sha256 = hash_file('sha256', $filename);
      $this->fileSize = filesize($filename);
      $this->fileHandle = fopen($filename, 'rb');
    }

    public function name() {
      return $this->name;
    }

    public function sha256() {
      return $this->sha256;
    }

    private function unixTimeToDosTime($timestamp) {
      $hour = intval(date('H', $timestamp));
      $min = intval(date('i', $timestamp));
      $sec = intval(date('s', $timestamp));
      return ($hour << 11) |
        ($min << 5) |
        ($sec >> 1);
    }

    private function unixTimeToDosDate($timestamp) {
      $year = intval(date('Y', $timestamp));
      $month = intval(date('m', $timestamp));
      $day = intval(date('d', $timestamp));
      return (($year - 1980) << 9) |
        ($month << 5) |
        ($day);
    }

    public function readLocalFileHeader(bool $zip64 = false) {
      if (!$this->useCompression) {
        $this->compressedSize = $this->fileSize;
      }
      
      $header = "";
      $header .= "\x50\x4b\x03\x04";
      $header .= $zip64 ? "\x2d\x00" : "\x14\x00"; //version 2.0 and MS-DOS compatible
      $header .= pack("v", $this->bitField); //general purpose bit flag
      if ($this->useCompression) {
        $header .= "\x08\x00"; //compression Method - deflate
      } else {
        $header .= "\x00\x00"; //compression Method - no
      }
      $header .= pack("v", $this->unixTimeToDosTime($this->lastModificationTimestamp)); //dos time
      $header .= pack("v", $this->unixTimeToDosDate($this->lastModificationTimestamp)); //dos date

      if ($zip64) {
        if ($this->bitField & self::BIT_NO_SIZE_IN_HEADER) {
          $header .= pack("V", 0); //crc32
        } else {
          $header .= strrev($this->crc32);
        }
        $header .= "\xFF\xFF\xFF\xFF"; //compressed Size
        $header .= "\xFF\xFF\xFF\xFF"; //uncompressed Size
      } else {
        if ($this->bitField & self::BIT_NO_SIZE_IN_HEADER) {
          $header .= pack("V", 0); //crc32
          $header .= pack("V", 0); //compressed Size
          $header .= pack("V", 0); //uncompressed Size
        } else {
          $header .= strrev($this->crc32);
          $header .= pack("V", $this->compressedSize); //compressed Size
          $header .= pack("V", $this->fileSize); //uncompressed Size
        }
      }

      $header .= pack("v", strlen($this->name)); //filename
      if ($zip64) {
        $header .= pack("v", 16+4); //extra field length (signatures + data)
        $header .= $this->name;
        $header .= pack("v", 0x0001); # Zip64 extended information extra field
        $header .= pack("v", 16); // 2 * 8 byte
        if ($this->bitField & self::BIT_NO_SIZE_IN_HEADER) {
          $header .= pack("P", 0);
          $header .= pack("P", 0);
        } else {
          $header .= pack("P", $this->compressedSize);
          $header .= pack("P", $this->fileSize);
        }
      } else {
        $header .= "\x00\x00"; //extra field length
        $header .= $this->name;
      }

      return $header;
    }

    public function readDataDescriptor(bool $zip64 = false) {

      if (!$this->useCompression) {
        $this->compressedSize = $this->fileSize;
      }

      $data = "";
      $data .= "\x50\x4b\x07\x08";
      $data .= strrev($this->crc32);
      $data .= $zip64 ? pack("P", $this->compressedSize) : pack("V", $this->compressedSize); //compressed Size
      $data .= $zip64 ? pack("P", $this->fileSize) : pack("V", $this->fileSize); //uncompressed Size
      return $data;
    }

    public function readFileDataImp() {
      $ret = null;
      if ($this->fileHandle !== false) {
        $block = fread($this->fileHandle, 65536);
        if (!empty($block)) {
          $ret = $block;
        }
      } else {
        $ret = $this->content;
        $this->content = null;
      }
      return $ret;
    }

    protected function compress($block) {

      $ret = null;
      if ($this->deflateState !== null) {
        if (!empty($block)) {
          $ret = deflate_add($this->deflateState, $block, ZLIB_NO_FLUSH);
        } else {
          $ret = deflate_add($this->deflateState, '', ZLIB_FINISH);
          $this->deflateState = null;
        }

        $this->compressedSize += strlen($ret);
      }

      return $ret;
    }

    public function readFileData() {
      $ret = null;
      if ($this->useCompression) {
        $block = $this->readFileDataImp();
        $ret = $this->compress($block);
      } else {
        $ret = $this->readFileDataImp();
      }
      return $ret;
    }

    public function setOffset($offset) {
      $this->offset = $offset;
    }

    public function readCentralDirectoryHeader(bool $zip64 = false) {

      $maxInt32 = 0xFFFFFFFF;
      $extraFields = "";

      // Compressed Size
      if ($zip64 && $this->compressedSize >= $maxInt32) {
        $compressedSize = "\xFF\xFF\xFF\xFF";
        $extraFields .= pack("P", $this->compressedSize);
      } else {
        $compressedSize = pack("V", $this->compressedSize);
      }

      // Uncompressed Size
      if ($zip64 && $this->fileSize >= $maxInt32) {
        $fileSize = "\xFF\xFF\xFF\xFF";
        $extraFields .= pack("P", $this->fileSize);
      } else {
        $fileSize = pack("V", $this->fileSize);
      }

      // Offset
      if ($zip64 && $this->offset >= $maxInt32) {
        $offset = "\xFF\xFF\xFF\xFF";
        $extraFields .= pack("P", $this->offset);
      } else {
        $offset = pack("V", $this->offset);
      }

      $header = "";
      $header .= "\x50\x4b\x01\x02";
      $header .= $zip64 ? "\x2d\x00" : "\x14\x00"; //version 2.0 and MS-DOS compatible
      $header .= $zip64 ? "\x2d\x00" : "\x14\x00"; //version 2.0 and MS-DOS compatible
      $header .= pack("v", $this->bitField); //general purpose bit flag
      $header .= $this->useCompression ? "\x08\x00" : "\x00\x00"; //compression Method - no
      $header .= pack("v", $this->unixTimeToDosTime($this->lastModificationTimestamp)); //dos time
      $header .= pack("v", $this->unixTimeToDosDate($this->lastModificationTimestamp)); //dos date
      $header .= strrev($this->crc32);
      $header .= $compressedSize; //compressed Size
      $header .= $fileSize; //uncompressed Size
      $header .= pack("v", strlen($this->name)); //filename
      $header .= (strlen($extraFields) > 0) ? pack('v', strlen($extraFields) + 4) : "\x00\x00"; //extra field length
      $header .= "\x00\x00"; //comment length
      $header .= "\x00\x00"; //disk num start
      $header .= "\x00\x00"; //int file attr
      $header .= "\x00\x00\x00\x00"; //ext file attr
      $header .= $offset; //relative offset
      $header .= $this->name;

      if (strlen($extraFields) > 0) {
        $header .= pack("v", 0x0001); # Zip64 extended information extra field
        $header .= pack("v", strlen($extraFields));
        $header .= $extraFields;
      }

      return $header;
    }

    public function closeHandle() {
      if ($this->fileHandle) {
        fclose($this->fileHandle);
      }
    }
  }
}