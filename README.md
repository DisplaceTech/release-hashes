# Release Hashes [![Build Status][travis-image]][travis-url] [![Coverage Status][coveralls-image]][coveralls-url]

This repository is meant to aid in the creation of a secure application updater for PHP apps. Initially, this will serve primarily as a repository of Ed25519 signatures on [WordPress](https://wordpress.org) download archives. It will power a more secure system update plugin to be released independently.

File signatures are stored in the `/hashes` directory. All files in that directory are signed with the following Ed25519 public key (hex-encoded):

```
5d4c696e571307b4a47626ae0bf9a7a229403c46657b4a9e832fee47e253bc5b
```

All signatures can be read directly from this repository, or from our CloudFront cached distribution: `https://releasesignatures.displace.tech`.

## Updates

All commits to this repository must be signed by a trusted and verified party.

New public keys might be added in the future to aid in release distribution.

Updates to the list of signed files might lag slightly behind official WordPress releases as this is a manual, offline process for security purposes.

[travis-image]: https://travis-ci.org/DisplaceTech/release-hashes.svg?branch=master
[travis-url]: https://travis-ci.org/DisplaceTech/release-hashes
[coveralls-image]: https://coveralls.io/repos/github/DisplaceTech/release-hashes/badge.svg?branch=master
[coveralls-url]: https://coveralls.io/github/DisplaceTech/release-hashes?branch=master
