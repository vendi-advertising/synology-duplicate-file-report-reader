# Synology Duplicate File Report Reader
The app parses the duplicate file log created by Synology.

The log file should be on the `Titan` server under the `IT` share in the `Storage Reports` folder. The file created is a zipped so you'll need to extract that first. If you want to add zip support to this, go ahead.

## Download
Download the latest release from the Releases section or us the below, setting the release version appropriately

```
VENDI_RELEASE_VERSION=0.0.2
VENDI_PATH=https://github.com/vendi-advertising/synology-duplicate-file-report-reader/releases/download/${VENDI_RELEASE_VERSION}
VENDI_FILE_ROOT=dupe-log-reader.${VENDI_RELEASE_VERSION}.phar
wget --quiet ${VENDI_PATH}/${VENDI_FILE_ROOT}            --output-document ${VENDI_FILE_ROOT}
wget --quiet ${VENDI_PATH}/${VENDI_FILE_ROOT}.sha256     --output-document ${VENDI_FILE_ROOT}.sha256
wget --quiet ${VENDI_PATH}/${VENDI_FILE_ROOT}.sha256.asc --output-document ${VENDI_FILE_ROOT}.sha256.asc
gpg --verify dupe-log-reader.${VENDI_RELEASE_VERSION}.phar.sha256.asc

rm ${VENDI_FILE_ROOT}.sha256
rm ${VENDI_FILE_ROOT}.sha256.asc
unset VENDI_RELEASE_VERSION
unset VENDI_PATH
unset VENDI_FILE_ROOT
```

It might tell you that the key is untrusted but don't worry about that for now. I'll put my public key out here in a bit. Maybe.

## Install
```
VENDI_RELEASE_VERSION=0.0.2
chmod +x dupe-log-reader.${VENDI_RELEASE_VERSION}.phar
sudo mv dupe-log-reader.${VENDI_RELEASE_VERSION}.phar /usr/local/bin/dupe-log-reader
unset VENDI_RELEASE_VERSION
```

## Usage
`dupe-log-reader duplicate_file.csv`
