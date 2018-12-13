<?php

namespace DisplaceTech\ReleaseHashes;

use ParagonIE_Sodium_Compat as Compat;
use ParagonIE_Sodium_Core_Util as Util;
use ParagonIE_Sodium_File as File;
use PHPUnit\Framework\TestCase;

class SignatureTest extends TestCase
{
    const PUBLIC_KEY = '5d4c696e571307b4a47626ae0bf9a7a229403c46657b4a9e832fee47e253bc5b';

    private function get_download_list()
    {
        $files = [];

        $versions = [
            '4.9.9',
            '4.8.8',
            '4.7.11',
            '4.6.13',
            '4.5.16',
            '4.4.17',
            '4.3.18',
            '4.2.22'
        ];

        foreach ($versions as $version) {
            $files[] = sprintf('https://downloads.wordpress.org/release/wordpress-%s.zip', $version);
        }

        return $files;
    }

    public static function setUpBeforeClass()
    {
        // Make the tmp directory
        mkdir(__DIR__ . '/../tmp');
    }

    public static function tearDownAfterClass()
    {
        // Remove the tmp directory
        rmdir(__DIR__ . '/../tmp');
    }

    public function test_head_core_signatures()
    {
        $files = $this->get_download_list();

        foreach ($files as $file) {
            $basename = basename($file);
            $abspath = __DIR__ . '/../hashes/wordpress/' . $basename . '.sig';
            $tmppath = __DIR__ . '/../tmp/' . $basename;

            try {
                file_put_contents($tmppath, fopen($file, 'r'));

                // Server hash
                $headers = get_headers($file, 1);
                $server_hash = $headers['Content-MD5'];

                // Local hash
                $hash = md5_file($file, false);

                $this->assertEquals($server_hash, $hash, 'Hashes are not equal!');

                // Get signature
                $signature_json = json_decode(file_get_contents($abspath), true);
                $this->assertEquals($hash, $signature_json['md5'], 'Stored hashes are not equal!');

                $signature = Compat::hex2bin($signature_json['signature']);
                $public_key = Compat::hex2bin(self::PUBLIC_KEY);
                $this->assertTrue(File::verify($signature, $tmppath, $public_key));
            } catch (\Exception $e) {
                $this->fail($e->getMessage());
            } finally {
                unlink($tmppath);
            }
        }
    }
}