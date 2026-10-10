<?php

namespace Tests\Unit\AdditionalProcessing;

trait BuildsArchiveFixtures
{
    /**
     * Build a minimal single-file 7z archive (stored, plain end header), optionally
     * with an AES coder on the file or on the whole end header.
     */
    private function sevenZip(
        string $name,
        string $content,
        bool $encryptedFile = false,
        bool $encryptedHeader = false,
    ): string {
        $aes = "\x24\x06\xF1\x07\x01\x02\x13\x00";
        $utf16Name = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8')."\x00\x00";

        $packed = $content;
        $header = "\x01\x04"
            ."\x06\x00\x01\x09".chr(strlen($content))."\x00"
            ."\x07\x0B\x01\x00\x01".($encryptedFile ? $aes : "\x01\x00")."\x0C".chr(strlen($content))."\x00"
            ."\x00"
            ."\x05\x01\x11".chr(strlen($utf16Name) + 1)."\x00".$utf16Name."\x00"
            ."\x00";

        if ($encryptedHeader) {
            $packed .= str_repeat("\xAA", 16);
            $header = "\x17\x06".chr(strlen($content))."\x01\x09\x10\x00"
                ."\x07\x0B\x01\x00\x01".$aes."\x0C\x10\x00"
                ."\x00";
        }

        $start = pack('PPV', strlen($packed), strlen($header), crc32($header));

        return "7z\xBC\xAF\x27\x1C\x00\x04".pack('V', crc32($start)).$start.$packed.$header;
    }

    /**
     * Build a minimal RAR4 archive with one stored file.
     */
    private function rar(string $name, string $content, bool $encryptedFile = false): string
    {
        $file = pack('VVCVVCCvV', strlen($content), strlen($content), 0, crc32($content), 0, 20, 0x30, strlen($name), 0x20).$name;

        return "Rar!\x1A\x07\x00"
            ."\x00\x00\x73\x00\x00\x0D\x00\x00\x00\x00\x00\x00\x00"
            ."\x00\x00\x74".pack('vv', $encryptedFile ? 0x8004 : 0x8000, 7 + strlen($file)).$file
            .$content;
    }

    /**
     * Build the local header and data of one stored ZIP entry.
     */
    private function zip(string $name, string $content, bool $encryptedFile = false): string
    {
        return "PK\x03\x04"
            .pack('vvvvvVVVvv', 20, $encryptedFile ? 1 : 0, 0, 0, 0, crc32($content), strlen($content), strlen($content), strlen($name), 0)
            .$name.$content;
    }
}
