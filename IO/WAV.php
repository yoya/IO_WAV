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
            throw new Exception("must be start with WAVEfmt ");
        }
        while ($bit->hasNextData(6)) {
            list($startOffset, $dummy) = $bit->getOffset();
            $chunkID = $bit->getData(4);
            $chunkSize = $bit->getUI32LE();
            $chunk = ["_offset" => $startOffset,
                      "ChunkID" => $chunkID, "ChunkSize"=> $chunkSize];
            try {
                $this->parseChunk($chunk, $bit);
            } catch (Exception $e) {
                fprintf(STDERR, "WARNING: ".$e->getMessage()."\n");
            }
            $this->_wavChunks []= $chunk;
            $bit->setOffset($startOffset + $chunkSize - 8, 0);
        }
    }
    function parseChunk(&$chunk, $bit) {
        $chunkID = $chunk["ChunkID"];
        $chunkSize = $chunk["ChunkSize"];
        switch ($chunkID) {
        case "fmt ":
            // https://docs.microsoft.com/en-us/windows/win32/api/mmeapi/ns-mmeapi-waveformat
            $chunk["FormatTag"] = $bit->getUI16LE();
            $chunk["Channels"] = $bit->getUI16LE();
            $chunk["SamplesPerSec"] = $bit->getUI32LE();
            $chunk["AvgBytesPerSec"]= $bit->getUI32LE();
            $chunk["BlockAlign"] = $bit->getUI16LE();
            $chunk["WaveData"] = $bit->getData($chunkSize - 14);
            break;
        default:
            $chunk["WaveData"] = $bit->getData($chunkSize);
        }
        return $chunk;
    }
    function dump($opts) {
        $opts['hexdump'] = !empty($opts['hexdump']);
        if ($opts['hexdump']) {
            $bit = new IO_Bit();
        }
        if (! is_null($this->_RIFFLength)) {
            echo "RIFF conainter Length:".$this->_RIFFLength."\n";
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
