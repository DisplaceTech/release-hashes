# Release Hashes

This repository is meant to aid in the creation of a secure application updater for PHP apps. Initially, this will serve primarily as a repository of Ed25519 signatures on [WordPress](https://wordpress.org) download archives. It will power a more secure system update plugin to be released independently.

File signatures are stored in the `/hashes` directory. All files in that directory are signed with the following Ed25519 public key (hex-encoded):

```

```

All signatures can be read directly from this repository, or from our CloudFront cached distribution: `https://releasesignatures.displace.tech`.

## Updates

All commits to this repository must be signed by a trusted and verified party.

New public keys might be added in the future to aid in release distribution.

Updates to the list of signed files might lag slightly behind official WordPress releases as this is a manual, offline process for security purposes.