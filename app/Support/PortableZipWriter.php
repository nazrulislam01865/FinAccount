<?php

namespace App\Support;

use RuntimeException;

/**
 * Minimal ZIP writer used for XLSX exports without requiring PHP's ext-zip.
 *
 * It writes standard DEFLATE-compressed ZIP files and intentionally supports
 * only the features needed by the application's generated workbooks.
 */
final class PortableZipWriter
{
    /** @var resource */
    private $handle;

    /** @var array<int, array{name:string,crc:int,compressed_size:int,size:int,offset:int,time:int,date:int,method:int}> */
    private array $entries = [];

    private bool $finished = false;

    public function __construct(private readonly string $path)
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the ZIP export directory.');
        }

        $this->handle = fopen($path, 'w+b');
        if ($this->handle === false) {
            throw new RuntimeException('Unable to create the ZIP export file.');
        }
    }

    public function add(string $name, string $contents): void
    {
        if ($this->finished) {
            throw new RuntimeException('Cannot add a ZIP entry after the archive has been finished.');
        }

        $name = ltrim(str_replace('\\', '/', $name), '/');
        if ($name === '') {
            throw new RuntimeException('ZIP entry names cannot be empty.');
        }

        $method = function_exists('gzdeflate') ? 8 : 0;
        $compressed = $method === 8 ? gzdeflate($contents, 6) : $contents;
        if ($compressed === false) {
            throw new RuntimeException('Unable to compress an XLSX export entry.');
        }

        [$dosTime, $dosDate] = $this->dosDateTime();
        // Keep crc32() in its native signed/unsigned representation. pack('V')
        // writes the correct low 32 bits on both 32-bit and 64-bit PHP.
        $crc = crc32($contents);
        $size = strlen($contents);
        $compressedSize = strlen($compressed);
        $offset = ftell($this->handle);
        if ($offset === false) {
            throw new RuntimeException('Unable to determine the ZIP write position.');
        }

        $nameLength = strlen($name);
        $localHeader = pack('V', 0x04034b50)
            .pack('v', 20)
            .pack('v', 0x0800)
            .pack('v', $method)
            .pack('v', $dosTime)
            .pack('v', $dosDate)
            .$this->packUnsignedLong($crc)
            .$this->packUnsignedLong($compressedSize)
            .$this->packUnsignedLong($size)
            .pack('v', $nameLength)
            .pack('v', 0)
            .$name;

        $this->write($localHeader.$compressed);

        $this->entries[] = [
            'name' => $name,
            'crc' => $crc,
            'compressed_size' => $compressedSize,
            'size' => $size,
            'offset' => $offset,
            'time' => $dosTime,
            'date' => $dosDate,
            'method' => $method,
        ];
    }

    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        $centralOffset = ftell($this->handle);
        if ($centralOffset === false) {
            throw new RuntimeException('Unable to determine the ZIP central directory position.');
        }

        foreach ($this->entries as $entry) {
            $nameLength = strlen($entry['name']);
            $centralHeader = pack('V', 0x02014b50)
                .pack('v', 20)
                .pack('v', 20)
                .pack('v', 0x0800)
                .pack('v', $entry['method'])
                .pack('v', $entry['time'])
                .pack('v', $entry['date'])
                .$this->packUnsignedLong($entry['crc'])
                .$this->packUnsignedLong($entry['compressed_size'])
                .$this->packUnsignedLong($entry['size'])
                .pack('v', $nameLength)
                .pack('v', 0)
                .pack('v', 0)
                .pack('v', 0)
                .pack('v', 0)
                .$this->packUnsignedLong(0)
                .$this->packUnsignedLong($entry['offset'])
                .$entry['name'];

            $this->write($centralHeader);
        }

        $centralEnd = ftell($this->handle);
        if ($centralEnd === false) {
            throw new RuntimeException('Unable to determine the ZIP central directory size.');
        }

        $entryCount = count($this->entries);
        if ($entryCount > 0xffff) {
            throw new RuntimeException('The XLSX export contains too many ZIP entries.');
        }

        $endRecord = pack('V', 0x06054b50)
            .pack('v', 0)
            .pack('v', 0)
            .pack('v', $entryCount)
            .pack('v', $entryCount)
            .$this->packUnsignedLong($centralEnd - $centralOffset)
            .$this->packUnsignedLong($centralOffset)
            .pack('v', 0);

        $this->write($endRecord);
        fflush($this->handle);
        fclose($this->handle);
        $this->finished = true;
    }

    public function __destruct()
    {
        if (! $this->finished && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    private function write(string $bytes): void
    {
        $length = strlen($bytes);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($this->handle, substr($bytes, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Unable to write the XLSX export file.');
            }
            $written += $result;
        }
    }

    private function packUnsignedLong(int $value): string
    {
        return pack('V', $value);
    }

    /** @return array{0:int,1:int} */
    private function dosDateTime(): array
    {
        $parts = getdate();
        $year = max(1980, min(2107, (int) $parts['year']));
        $dosTime = ((int) $parts['hours'] << 11)
            | ((int) $parts['minutes'] << 5)
            | ((int) floor((int) $parts['seconds'] / 2));
        $dosDate = (($year - 1980) << 9)
            | ((int) $parts['mon'] << 5)
            | (int) $parts['mday'];

        return [$dosTime, $dosDate];
    }
}
