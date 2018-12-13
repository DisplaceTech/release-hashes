<?php

namespace DisplaceTech\ReleaseHashes;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WordPressHashesCommand extends Command
{
    /**
     * Configure the console command
     */
    protected function configure()
    {
        $this
            ->setName('hasher:wordpress-core')
            ->addArgument('password', InputArgument::REQUIRED, 'The password to use while generating keys.')
            ->setDescription('Update any missing WordPress core hashes.');
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
        $files = $this->getFileList();

        // Make the tmp directory
        mkdir(__DIR__ . '/../tmp');

        foreach ($files as $file) {
            $basename = basename($file);
            $abspath = __DIR__ . '/../hashes/wordpress/' . $basename . '.sig';
            if (file_exists($abspath)) {
                $output->writeln(sprintf('Already signed file: %s. Skipping...', $basename));
                continue;
            }

            $output->writeln(sprintf('Downloading core package: %s', $basename));
            $tmppath = __DIR__ . '/../tmp/' . $basename;
            file_put_contents($tmppath, fopen($file, 'r'));

            $output->writeln('Confirming file MD5 hash');
            $headers = get_headers($file, 1);
            $server_hash = $headers['Content-MD5'];
            $output->writeln(sprintf('Server hash: %s', $server_hash));

            $hash = md5_file($file, false);
            $output->writeln(sprintf('Local hash: %s', $hash));

            if (!hash_equals($server_hash, $hash)) {
                $output->writeln('Hashes not equal, refusing to sign the package!');
                continue;
            }

            $output->writeln('Signing ...');

            $signature = $this->sign_file_ed25519($tmppath, $private_key);
            $output->writeln(sprintf('Signature: %s', $signature));

            $output->writeln('Writing signature file');
            $signature_json = [
                'md5' => $hash,
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
     * @return array
     */
    private function getFileList()
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
            $files[] = sprintf('https://downloads.wordpress.org/release/wordpress-%s-no-content.zip', $version);
            $files[] = sprintf('https://downloads.wordpress.org/release/wordpress-%s-new-bundled.zip', $version);

            // Get any partial versions
            $parts = explode('.', $version);
            $tag = intval($parts[2]);
            for ($i = 0; $i < $tag; $i++) {
                $files[] = sprintf('https://downloads.wordpress.org/release/wordpress-%s-partial-%d.zip', $version, $i);
            }
        }

        return $files;
    }
}
