<?php

namespace DisplaceTech\ReleaseHashes;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginHashesCommand extends Command
{
    /**
     * Configure the console command
     */
    protected function configure()
    {
        $this
            ->setName('hasher:plugin')
            ->addArgument('password', InputArgument::REQUIRED, 'The password to use while generating keys.')
            ->addArgument('plugin', InputArgument::REQUIRED, 'The plugin for which to generate hashes')
            ->setDescription('Generate hashes for WordPress plugins.');
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $password = $input->getArgument('password');
        $plugin = $input->getArgument('plugin');
        $salt = substr(hash_hmac('sha256', $password, $password), 0, \ParagonIE_Sodium_Compat::CRYPTO_PWHASH_SALTBYTES);
        $seed = \ParagonIE_Sodium_Compat::crypto_pwhash(
            \ParagonIE_Sodium_Compat::CRYPTO_SIGN_SEEDBYTES,
            $password,
            $salt,
            \ParagonIE_Sodium_Compat::CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \ParagonIE_Sodium_Compat::CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
        $keys = \ParagonIE_Sodium_Compat::crypto_sign_seed_keypair($seed);
        $private_key = \ParagonIE_Sodium_Compat::crypto_sign_secretkey($keys);
        $public_key = \ParagonIE_Sodium_Compat::crypto_sign_publickey($keys);

        $output->writeln(sprintf('Signing with public key: %s', \ParagonIE_Sodium_Compat::bin2hex($public_key)));
        $files = $this->getFileList($plugin);

        // Make the tmp directory
        mkdir(__DIR__ . '/../tmp');

        $basepath = __DIR__ . '/../hashes/wordpress/plugins/' . $plugin;
        if(!file_exists($basepath)) {
            mkdir($basepath, 0777, true);
        }

        foreach ($files as $file) {
            $basename = basename($file);

            $abspath = $basepath . '/' . $basename . '.sig';
            if (file_exists($abspath)) {
                $output->writeln(sprintf('Already signed file: %s. Skipping...', $basename));
                continue;
            }

            $output->writeln(sprintf('Downloading plugin package: %s', $basename));
            $tmppath = __DIR__ . '/../tmp/' . $basename;
            file_put_contents($tmppath, fopen($file, 'r'));

            $output->writeln('Signing ...');

            $signature = $this->sign_file_ed25519($tmppath, $private_key);
            $output->writeln(sprintf('Signature: %s', $signature));

            $output->writeln('Writing signature file');
            $signature_json = [
                'signature' => $signature,
                'created' => time()
            ];

            file_put_contents($abspath, json_encode($signature_json));

            $output->writeln('Clearing temporary file cache ...');
            unlink($tmppath);
        }

        // Remove the tmp directory
        rmdir(__DIR__ . '/../tmp');
    }

    /**
     * Sign a local file given a private signing key.
     *
     * @param string $filename
     * @param string $private_key
     *
     * @return string
     */
    private function sign_file_ed25519($filename, $private_key)
    {
        if (\ParagonIE_Sodium_Core_Util::strlen($private_key) === \ParagonIE_Sodium_Compat::CRYPTO_SIGN_SECRETKEYBYTES * 2) {
            $private_key = \ParagonIE_Sodium_Compat::hex2bin($private_key);
        }

        return \ParagonIE_Sodium_Compat::bin2hex(\ParagonIE_Sodium_File::sign($filename, $private_key));
    }

    /**
     * Get the list of files to download, hash-verify, and sign from WordPress' download system.
     *
     * @param string $slug Plugin slug
     *
     * @return array
     */
    private function getFileList($slug)
    {
        $files = [];

        // Request plugin info from the API
        $raw_info = file_get_contents(sprintf('https://api.wordpress.org/plugins/info/1.0/%s.json', $slug));
        $info = json_decode($raw_info, true);

        if ($info !== false) {
            $versions = array_keys($info['versions']);

            foreach ($versions as $version) {
                if ('trunk' === $version) continue;
                $files[] = sprintf('https://downloads.wordpress.org/plugin/%s.%s.zip', $slug, $version);
            }
        }

        return $files;
    }
}