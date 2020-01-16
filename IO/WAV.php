<?php

/*
  IO_WAV class -- v0.x
  (c) 2020/01/15 yoya@awm.jp
  ref) http://pwiki.awm.jp/~yoya/?WAV
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

class IO_WAV {
    var $_wavdata = null;
    var $_wavChunks = [];
    var $_RIFFLength = null;
    const WAVE_FORMAT_PCM        = 0x1;
    const WAVE_FORMAT_ADPCM      = 0x2;
    const WAVE_FORMAT_IEEE_FLOAT = 0x3;
    const WAVE_FORMAT_ALAW       = 0x6;
    const WAVE_FORMAT_MULAW      = 0x7;
    const WAVE_FORMAT_DRM        = 0x9;
    const WAVE_FORMAT_MPEGLAYER3 = 0x55;
    const WAVE_FORMAT_SWF_ADPCM  = 0x5346;
    const WAVE_FORMAT_EXTENSIBLE = 0xFFFE;
    function getFormatName($formatTag) {
        static $formatNameTable = [
            self::WAVE_FORMAT_PCM        => "PCM",
            self::WAVE_FORMAT_ADPCM      => "ADPCM",
            self::WAVE_FORMAT_IEEE_FLOAT => "IEEE_FLOAT",
            self::WAVE_FORMAT_ALAW       => "ALAW",
            self::WAVE_FORMAT_MULAW      => "MULAW",
            self::WAVE_FORMAT_DRM        => "DRM",
            self::WAVE_FORMAT_MPEGLAYER3 => "MPEGLAYER3",
            self::WAVE_FORMAT_SWF_ADPCM  => "SWF_ADPCM",
            self::WAVE_FORMAT_EXTENSIBLE => "EXTENSIBLE",
        ];
        if (isset($formatNameTable[$formatTag])) {
            return $formatNameTable[$formatTag];
        }
        return "Unknown WAVE_FORMAT";
    }
    function parse($wavdata) {
        $this->_wavdata = $wavdata;
        $this->_RIFFLength = false;
        $bit = new IO_Bit();
        $bit->input($wavdata);
        $magick = $bit->getData(4);
        if ($magick == "RIFF") {
            $this->_RIFFLength = $bit->getUI32LE();
            $magick = $bit->getData(4);
        }
        if ($magick !== "WAVE") {
            throw new Exception("must be start with WAVE");
        }
        while ($bit->hasNextData(8)) {
            list($startOffset, $dummy) = $bit->getOffset();
            $chunk = ["_chunkOffset" => $startOffset,
                      "_chunkSize" => null];
            try {
                $this->parseChunk($bit, $chunk);
            } catch (Exception $e) {
                fprintf(STDERR, "WARNING: ".$e->getMessage()."\n");
            }
            if (isset($chunk["ChunkSize"])) {
                $chunkSize = $chunk["ChunkSize"];
                $chunk["_chunkSize"] = $chunkSize;
                $bit->setOffset($startOffset + $chunkSize  + 8, 0);
            }
            $this->_wavChunks []= $chunk;
            if (! isset($chunk["ChunkSize"])) {
                break; // abnormal termination
            }
        }
    }
    function parseChunk($bit, &$chunk) {
        $chunkID = $bit->getData(4);
        $chunkSize = $bit->getUI32LE();
        // echo "chunkSize:$chunkSize\n";
        $chunk["ChunkID"] = $chunkID;
        $chunk["ChunkSize"] = $chunkSize;
        switch ($chunkID) {
        case "fmt ":
            // WAVEFORMAT structure
            // https://docs.microsoft.com/en-us/windows/win32/api/mmeapi/ns-mmeapi-waveformat
            if ($chunkSize < 14) {
                fprintf(STDERR, "Warning: too short fmt chunk (size:$chunkSize < 14)\n");
            }
            $formatTag = $bit->getUI16LE();
            $chunk["FormatTag"] = $formatTag;
            $chunk["Channels"] = $bit->getUI16LE();
            $chunk["SamplesPerSec"] = $bit->getUI32LE();
            $chunk["AvgBytesPerSec"]= $bit->getUI32LE();
            $chunk["BlockAlign"] = $bit->getUI16LE();
            if ($chunkSize <= 14) {
                break;
            }
            // WAVEFORMATEX structure
            // https://docs.microsoft.com/en-us/windows/win32/api/mmeapi/ns-mmeapi-waveformatex
            $chunk["BitsPerSample"] = $bit->getUI16LE(); // for PCM
            if ($chunkSize <= 16) {
                break;
            }
            if ($formatTag === 1) { // PCM
                fprintf(STDERR, "Warning: too long fmt chunk for audio format PCM\n");
            }
            $chunk["cbSize"] = $bit->getUI16LE();
            if ($chunkSize <= 18) {
                break;
            }
            // WAVEFORMATEXTENSIBLE structure
            // https://docs.microsoft.com/ja-jp/windows/win32/api/mmreg/ns-mmreg-waveformatextensible_1
            switch ($formatTag) {
            case self::WAVE_FORMAT_ADPCM:
                $chunk["SamplesPerBlock"] = $bit->getUI16LE();
                $chunk["NumCoef"] = $bit->getUI16LE();
                break;
            default:
                $chunk["ValidBitsPerSample"] = $bit->getUI16LE();
                $chunk["ChannelMask"] = $bit->getUI32LE();
                $chunk["SubFormat"] = $bit->getData(16);  // GUID
            }
            break;
        case "data":
            $chunk["Data"] = $bit->getData($chunkSize - 8);
            break;
        default:
            $chunk["Data"] = $bit->getData($chunkSize - 8);
        }
        return $chunk;
    }
    function dump($opts = []) {
        $opts['hexdump'] = !empty($opts['hexdump']);
        if ($opts['hexdump']) {
            $bit = new IO_Bit();
            $bit->input($this->_wavdata);
            $opts["bit"] = $bit;
        }
        if (! is_null($this->_RIFFLength)) {
            echo "RIFF conainter Length:".$this->_RIFFLength."\n";
        }
        echo "WAVE Chunks(count:".count($this->_wavChunks).")\n";
        foreach ($this->_wavChunks as $idx => $chunk) {
            echo "Chunk[$idx]";
            $this->dumpChunk($chunk, $opts);
        }
    }
    function dumpChunk($chunk, $opts = []) {
        if ($opts['hexdump']) {
            $bit = $opts["bit"];
        }
        $chunkID = $chunk["ChunkID"];
        $chunkSize = $chunk["ChunkSize"];
        echo " ID:$chunkID Size:$chunkSize\n";
        switch ($chunkID) {
        case "fmt ":
            $formatTag = $chunk["FormatTag"];
            $formatName = self::getFormatName($formatTag);
            echo "    FormatTag:$formatTag($formatName)";
            echo " Channels:".$chunk["Channels"];
            echo " SamplesPerSec:".$chunk["SamplesPerSec"];
            echo PHP_EOL;
            echo "    AvgBytesPerSec:".$chunk["AvgBytesPerSec"];
            echo " BlockAlign:".$chunk["BlockAlign"];
            if ($chunkSize <= 14) {
                echo PHP_EOL;
                break;
            }
            echo " BitsPerSample:".$chunk["BitsPerSample"];
            echo PHP_EOL;
            if ($chunkSize <= 16) {
                break;
            }
            echo "    cbSize:".$chunk["cbSize"];
            echo PHP_EOL;
            if ($chunkSize <= 18) {
                break;
            }
            switch ($formatTag) {
            case self::WAVE_FORMAT_ADPCM:
                echo "    SamplesPerBlock:".$chunk["SamplesPerBlock"];
                echo " NumCoef:".$chunk["NumCoef"];
                echo PHP_EOL;
                break;
            default:
                echo "    ValidBitsPerSample:".$chunk["ValidBitsPerSample"];
                echo " ChannelMask:".$chunk["ChannelMask"];
                echo PHP_EOL;
                echo "    SubFormat:".chunk_split(bin2hex($chunk["SubFormat"]), 4, " ");
                echo PHP_EOL;
                break;
            }
            break;
        case "data":
            echo "    Data(size:".strlen($chunk["Data"]).")\n";
            break;
        default:
            echo "    Data(size:".strlen($chunk["Data"]).")\n";
            break;
        }
    }
    function build($opts = []) {
        $bit = new IO_Bit();
        foreach ($this->_jpegChunk as $chunk) {
            $chunk->build($bit);
        }
        echo $bit->output();
    }
}
