<?php

require_once("functions.lib.php");

/***********************************************************
* filename     zipcreate.cls.php
* description  Create zip files on the fly
* project
* author       redmonkey
* version      0.5
* status       beta
* support      zipclass@redmonkey.com
* license      GPL
*
* depends      function unix2dostime() (found in supporting
*              function library (includes/functions.lib.php))
*
* notes        zip file format can be found at
*              http://www.pkware.com/company/standards/appnote/
*
* notes        the documented zip file format omits to detail
*              the required header signature for the data
*              descriptor (extended local file header) section
*              which is (0x08074b50). while many decompression
*              utilities will ignore this error, this signature
*              is vital for compatability with Stuffit Expander
*              for Mac if you have included the data descriptor
*
* notes        while using bzip2 compression offers a reduced
*              file size it does come at the expense of higher
*              system resources usage. the decompression
*              utility will also have to be compatabile with
*              at least v4.6 zip file format specification
*
* file history
* ============
* 01/01/2005   v0.1 initial version
*
* 02/01/2005   v0.2 added checking and handling for files of
*                   zero bytes in length
*
* 03/01/2005   v0.3 changed offset tracker updating method
*
* 15/05/2008   v0.4 temporary version to provide quick fix
*                   for bug with MAC OS X BOMarchiver not
*                   handling files archived with stored (no)
*                   compression type and with an extended
*                   local header
*
* 16/05/2008   v0.5 bug fix - windows xp's builtin
*                   decompression utility prompts for
*                   password when extracting directory
*                   entries
*
* 16/05/2008   v0.5 bug fix - mac os x's builtin
*                   decompression utility cannot handle
*                   archives using 'store' (no) compression
*                   method
*
* 16/05/2008   v0.5 bug fix - adobe digital editons (on mac)
*                   cannot open .epub archives compiled with
*                   ZipCreate
************************************************************/
class ZipCreate
{
  var $filedata; // file data
  var $cntrldir; // central directory record
  var $comment;  // zip file comment
  var $offset;   // local header offset tracker
  var $entries;  // counter for total entries within the zip
  var $ztype;    // current compression type

  /**
  * @return
  * @param   string _ztype  compression type to use, currently only supporting
  *                         gzip (deflated), bzip2, and store (no compression)
  * @desc                   constructor, initialise class variables and set compression
  *                         type (defaults to gzip (Deflated)) for files
  */
  function ZipCreate($_ztype = 'gzip')
  {
    $this->filedata = '';
    $this->cntrldir = '';
    $this->comment  = '';
    $this->offset   = 0;
    $this->entries  = 0;

    switch(strtolower($_ztype))
    {
      case 'gzip' :
        if (!function_exists('gzcompress'))
        {
          trigger_error('Your PHP installation does not support gzip compression', E_USER_ERROR);
        }

        $this->ztype = 'gzip';
        break;

      case 'bzip2':
        if (!function_exists('bzcompress'))
        {
          trigger_error('Your PHP installation does not support bzip2 compression', E_USER_ERROR);
        }

        $this->ztype = 'bzip2';
        break;

      case 'stored':
        $this->ztype = 'store';
        break;

      default      :
        // default to no (Stored) compression type for anything else
        $notice_msg  = 'Unsupported compression type (' . $_ztype . ') using Stored instead';
        $this->ztype = 'store';
        trigger_error($notice_msg, E_USER_NOTICE);
    }
  }

  /**
  * @return
  * @param  string  _path       directory path
  * @param  string  _timestamp  unix timestamp for dir last modified date and time
  * @desc                       adds a directory record to the archive
  */
  function add_dir($_path, $_timestamp = 0)
  {
    return $this->add_file(null, $_path, $_timestamp);
  }

  /**
  * @return
  * @param  string  _data       file contents
  * @param  string  _name       name of the file within the archive including path
  * @param  int     _timestamp  unix timestamp for file last modified date and time
  * @desc                       adds a file to the archive
  */
  function add_file($_data = null, $_name, $_timestamp = 0)
  {
    $_name = is_null($_data) ? $this->clean_path($_name, true)
                             : $this->clean_path($_name);

    if (is_null($_data))                // assume it's a directory
    {
      $z_type = 'store';                // set compression to none
      $ext_fa = 0x10;                   // external file attributes
      $_data  = '';                     // initialise $_data
      $crc32  = 0;                      // crc32 checksum always 0
    }
    elseif ($_data == '')               // assume a zero byte length file
    {
      $z_type = 'store';                // set compression to none
      $ext_fa = 0x20;                   // external file attributes
      $crc32  = 0;                      // crc32 checksum always 0
    }
    else                                // assume it's a file
    {
      $z_type = $this->ztype;
      $ext_fa = 0x20;                   // external file attributes
      $crc32  = crc32($_data);          // crc32 checksum of file
    }

    // set last modified time of file in required DOS format
    $mod_time = unix2dostime($_timestamp);

    switch($z_type)
    {
      case  'gzip':
        $min_ver = 0x14;                    // minimum version needed to extract (2.0)
        $zmethod = 0x08;                    // compression method
        $c_data  = gzcompress($_data);      // compress file
        $c_data  = substr($c_data, 2, -4);  // fix crc bug
        break;

      case 'bzip2':
        $min_ver = 0x2e;                    // minimum version needed to extract (4.6)
        $zmethod = 0x0c;                    // compression method
        $c_data  = bzcompress($_data);      // compress file
        break;

      default     :                         // default to stored (no) compression
        $min_ver = 0x0a;                    // minimum version needed to extract (1.0)
        $zmethod = 0x00;                    // compression method
        $c_data  = &$_data;
        break;
    }


    // file details
    $c_len    = strlen($c_data);            // compressed length of file
    $uc_len   = strlen($_data);             // uncompressed length of file
    $fn_len   = strlen($_name);             // length of filename

    // pack and add file data
    $this->filedata .= pack('VvvvVVVVvva' . $fn_len . 'a' . $c_len,
                             0x04034b50,    // local file header signature      (4 bytes)
                             $min_ver,      // version needed to extract        (2 bytes)
                             0x00,          // gen purpose bit flag             (2 bytes)
                             $zmethod,      // compression method               (2 bytes)
                             $mod_time,     // last modified time and date      (4 bytes)
                             $crc32,        // crc-32                           (4 bytes)
                             $c_len,        // compressed filesize              (4 bytes)
                             $uc_len,       // uncompressed filesize            (4 bytes)
                             $fn_len,       // length of filename               (2 bytes)
                             0,             // extra field length               (2 bytes)
                             $_name,        // filename                 (variable length)
                             $c_data);      // compressed data          (variable length)

    // pack file data and add to central directory
    $this->cntrldir .= pack('VvvvvVVVVvvvvvVVa' . $fn_len,
                             0x02014b50,     // central file header signature   (4 bytes)
                             0x14,           // version made by                 (2 bytes)
                             $min_ver,       // version needed to extract       (2 bytes)
                             0x00,           // gen purpose bit flag            (2 bytes)
                             $zmethod,       // compression method              (2 bytes)
                             $mod_time,      // last modified time and date     (4 bytes)
                             $crc32,         // crc32                           (4 bytes)
                             $c_len,         // compressed filesize             (4 bytes)
                             $uc_len,        // uncompressed filesize           (4 bytes)
                             $fn_len,        // length of filename              (2 bytes)
                             0,              // extra field length              (2 bytes)
                             0,              // file comment length             (2 bytes)
                             0,              // disk number start               (2 bytes)
                             0,              // internal file attributes        (2 bytes)
                             $ext_fa,        // external file attributes        (4 bytes)
                             $this->offset,  // relative offset of local header (4 bytes)
                             $_name);        // filename                (variable length)

    // update offset tracker   (30bytes + length of filename + length of compressed data)
    $this->offset += 0x1e + $fn_len + $c_len;

    // increment entry counter
    $this->entries++;

    // cleanup
    unset($c_data, $z_type, $min_ver, $zmethod, $mod_time, $c_len, $uc_len, $fn_len);
  }

  /**
  * @return
  * @param  string  _comment  zip file comment
  * @desc                     adds a comment to the archive
  */
  function add_comment($_comment)
  {
    $this->comment = $_comment;
  }

  /**
  * @return string       the zipped file
  * @desc                throws everything together and returns it
  */
  function build_zip()
  {
    $com_len = strlen($this->comment);     // length of zip file comment

    return $this->filedata                 // .zip file data                (variable length)
         . $this->cntrldir                 // .zip central directory record (variable length)
         . pack('VvvvvVVva' . $com_len,
                 0x06054b50,               // end of central dir signature          (4 bytes)
                 0,                        // number of this disk                   (2 bytes)
                 0,                        // number of the disk with start of
                                           // central directory record              (2 bytes)
                 $this->entries,           // total # of entries on this disk       (2 bytes)
                 $this->entries,           // total # of entries overall            (2 bytes)
                 strlen($this->cntrldir),  // size of central dir                   (4 bytes)
                 $this->offset,            // offset to start of central dir        (4 bytes)
                 $com_len,                 // .zip file comment length              (2 bytes)
                 $this->comment);          // .zip file comment             (variable length)
  }

  /**
  * @return string
  * @param  string  _path       filename/directory path
  * @param  bool    _isdir      is $_path a directory
  * @desc                       cleans filename/directory path of invalid paths
  */
  function clean_path($_path, $_isdir = false)
  {
    // remove leading and trailing spaces from filename
    // and correct any erros with directory seperators
    $_path   = trim(str_replace('\\', '/', $_path));

    // remove any invalid start path definitions (e.g C:/, /, ./ etc..)
    $_path   = preg_replace('/^([A-z]:\/+|\.?\/+)/', '', $_path);

    // remove consecutive path seperators
    $_path   = preg_replace('/\/{2,}/', '/', $_path);

    if ($_isdir)
    {
      // add trailing slash
      $_path = substr($_path, -1) != '/' ? $_path . '/' : $_path;
    }

    return $_path;
  }
}
?>
