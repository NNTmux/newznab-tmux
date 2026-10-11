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
     * Build a minimal RAR4 archive with one file, stored unless another method is given.
     */
    private function rar(string $name, string $content, bool $encryptedFile = false, int $method = 0x30): string
    {
        $file = pack('VVCVVCCvV', strlen($content), strlen($content), 0, crc32($content), 0, 20, $method, strlen($name), 0x20).$name;

        return "Rar!\x1A\x07\x00"
            ."\x00\x00\x73\x00\x00\x0D\x00\x00\x00\x00\x00\x00\x00"
            ."\x00\x00\x74".pack('vv', $encryptedFile ? 0x8004 : 0x8000, 7 + strlen($file)).$file
            .$content;
    }

    /**
     * Build one RAR4 volume holding a stored chunk of a file that spans several volumes.
     */
    private function rarVolume(string $name, string $chunk, int $totalSize, bool $splitBefore, bool $splitAfter): string
    {
        $file = pack('VVCVVCCvV', strlen($chunk), $totalSize, 0, crc32($chunk), 0, 20, 0x30, strlen($name), 0x20).$name;
        $flags = 0x8000 | ($splitBefore ? 0x01 : 0) | ($splitAfter ? 0x02 : 0);

        return "Rar!\x1A\x07\x00"
            ."\x00\x00\x73\x01\x00\x0D\x00\x00\x00\x00\x00\x00\x00"
            ."\x00\x00\x74".pack('vv', $flags, 7 + strlen($file)).$file
            .$chunk;
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

    /**
     * Build a PAR2 index: a Main packet and one File Description packet per file.
     *
     * @param  list<array{0: string, 1: int, 2: string}>  $files  [name, size, first bytes of the file]
     */
    private function par2Index(array $files): string
    {
        $setId = str_repeat("\x5A", 16);
        $packet = static function (string $type, string $body) use ($setId): string {
            $hashed = $setId.$type.$body;

            return "PAR2\0PKT".pack('P', 32 + strlen($hashed)).md5($hashed, true).$hashed;
        };

        $fileIds = [];
        $descriptions = '';
        foreach ($files as [$name, $size, $start]) {
            $fileId = md5($name, true);
            $fileIds[] = $fileId;
            $paddedName = $name.str_repeat("\0", (4 - strlen($name) % 4) % 4);
            $descriptions .= $packet(
                "PAR 2.0\0FileDesc",
                $fileId.md5($start, true).md5(substr($start, 0, min(16384, $size)), true).pack('P', $size).$paddedName,
            );
        }

        return $packet("PAR 2.0\0Main\0\0\0\0", pack('PV', 768000, count($fileIds)).implode('', $fileIds)).$descriptions;
    }
}
