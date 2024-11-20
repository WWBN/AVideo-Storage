<?php
class ManifestGenerator
{
    static function generateManifest($baseFolder)
    {
        // List of expected resolution folder prefixes
        $resolutions = ['res240', 'res360', 'res480', 'res540', 'res720', 'res1080', 'res1440', 'res2160'];

        // Initialize manifest content
        $manifest = "#EXTM3U\n";
        $manifest .= "#EXT-X-VERSION:3\n";

        // Scan the base folder
        $folders = scandir($baseFolder);

        // Find the audio folder and audio manifest
        $audioManifest = self::findAudioManifest($baseFolder);
        if ($audioManifest) {
            $manifest .= "#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID=\"audio_group\",NAME=\"eng\",LANGUAGE=\"eng\",DEFAULT=YES,AUTOSELECT=YES,URI=\"$audioManifest\"\n";
        }

        // Add resolution streams
        foreach ($resolutions as $resolution) {
            if (in_array($resolution, $folders) && is_dir("$baseFolder/$resolution") && file_exists("$baseFolder/$resolution/index.m3u8")) {
                $bandwidth = self::getBandwidthForResolution($resolution); // Generate bandwidth
                $resolutionNumber = str_replace('res', '', $resolution); // Remove the 'res' prefix
                $manifest .= "#EXT-X-STREAM-INF:BANDWIDTH=$bandwidth,RESOLUTION=-2x{$resolutionNumber},AUDIO=\"audio_group\"\n";
                $manifest .= "$resolution/index.m3u8\n";
            }
        }

        return $manifest;
    }

    static function findAudioManifest($baseFolder)
    {
        // Look for the audio manifest in the audio_tracks subfolder
        $audioFolder = "$baseFolder/audio_tracks";
        if (is_dir($audioFolder)) {
            $subfolders = scandir($audioFolder);
            foreach ($subfolders as $subfolder) {
                if (is_dir("$audioFolder/$subfolder") && $subfolder !== '.' && $subfolder !== '..') {
                    $audioManifestPath = "$audioFolder/$subfolder/audio.m3u8";
                    if (file_exists($audioManifestPath)) {
                        return "audio_tracks/$subfolder/audio.m3u8";
                    }
                }
            }
        }
        return null; // Return null if no audio manifest is found
    }

    static function getBandwidthForResolution($resolution)
    {
        // Generate bandwidth based on resolution
        switch ($resolution) {
            case 'res240':
                return 500000; // Example values, adjust as needed
            case 'res360':
                return 800000;
            case 'res480':
                return 1000000;
            case 'res540':
                return 1500000;
            case 'res720':
                return 2000000;
            case 'res1080':
                return 4000000;
            case 'res1440':
                return 8000000;
            case 'res2160':
                return 12000000;
            default:
                return 1000000;
        }
    }
}

?>
